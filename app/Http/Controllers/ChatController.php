<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Chat;
use App\Models\Conversation;
use App\Events\MessageSent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $conversations = Conversation::where('user_one_id', $user->id)
            ->orWhere('user_two_id', $user->id)
            ->with(['userOne', 'userTwo', 'chats' => function ($query) {
                $query->latest();
            }])
            ->get();

        return view('chat.index', compact('conversations'));
    }

    public function searchUsers(Request $request)
    {
        $search = $request->get('q', '');
        $userId = Auth::id();

        if (!$search) {
            return response()->json([]);
        }

        $users = User::where('id', '!=', $userId)
            ->where(function ($query) use ($search) {
                $query->where('name', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%");
            })
            ->limit(10)
            ->get(['id', 'name', 'email']);  // Select only needed fields

        return response()->json($users);
    }


    public function getConversation($userId)
    {
        $authUserId = auth()->id();

        $conversation = Conversation::where(function ($q) use ($authUserId, $userId) {
            $q->where('user_one_id', $authUserId)
                ->where('user_two_id', $userId);
        })->orWhere(function ($q) use ($authUserId, $userId) {
            $q->where('user_two_id', $authUserId)
                ->where('user_one_id', $userId);
        })->with(['userOne', 'userTwo', 'chats.sender']) // make sure to eager load these
            ->first();

        if (!$conversation) {
            return response()->json(['conversation' => null, 'messages' => []]);
        }

        $chats = $conversation->chats()->with('sender')->get();

        return response()->json([
            'conversation' => $conversation,
            'messages' => $chats,
        ]);
    }

    public function sendMessage(Request $request)
    {
        $request->validate([
            'conversation_id' => 'required|exists:conversations,id',
            'message' => 'required|string|max:2000',
        ]);

        $user = Auth::user();
        $conversation = Conversation::findOrFail($request->conversation_id);

        if (!in_array($user->id, [$conversation->user_one_id, $conversation->user_two_id])) {
            abort(403, 'Unauthorized');
        }

        $receiverId = $conversation->user_one_id == $user->id ? $conversation->user_two_id : $conversation->user_one_id;

        $chat = Chat::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'receiver_id' => $receiverId,
            'message' => $request->message,
        ]);

        // Load sender relationship so it's available in the event
        $chat->load('sender');

        // Broadcast the event to others listening on the private conversation channel
        broadcast(new MessageSent($chat))->toOthers();

        return response()->json($chat);
    }

    public function getActiveConversations()
    {
        $userId = auth()->id();

        // Get conversations where user is either user_one or user_two
        // Join with latest chat date to order by recent activity
        $conversations = Conversation::where('user_one_id', $userId)
            ->orWhere('user_two_id', $userId)
            ->with(['userOne', 'userTwo', 'chats' => function ($query) {
                $query->latest()->limit(1); // eager load latest chat per conversation
            }])
            ->get()
            ->sortByDesc(function ($conversation) {
                // Use latest chat created_at or conversation created_at as fallback
                return $conversation->chats->first()->created_at ?? $conversation->created_at;
            });

        return view('chat.index', compact('conversations'));
    }
}
