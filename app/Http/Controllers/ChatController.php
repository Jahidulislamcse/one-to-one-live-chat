<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Chat;
use App\Models\Conversation;
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


    public function getConversation($id)
    {
        $user = Auth::user();
        $otherUserId = (int) $id;

        if ($user->id === $otherUserId) {
            abort(403, 'Cannot chat with yourself.');
        }

        $conversation = Conversation::with(['userOne', 'userTwo'])->where(function ($q) use ($user, $otherUserId) {
            $q->where('user_one_id', $user->id)->where('user_two_id', $otherUserId);
        })->orWhere(function ($q) use ($user, $otherUserId) {
            $q->where('user_one_id', $otherUserId)->where('user_two_id', $user->id);
        })->first();

        if (!$conversation) {
            $conversation = Conversation::create([
                'user_one_id' => $user->id,
                'user_two_id' => $otherUserId,
            ]);
            // Eager load after creation as well
            $conversation->load(['userOne', 'userTwo']);
        }

        $messages = $conversation->chats()->with('sender')->orderBy('created_at')->get();

        return response()->json([
            'conversation' => $conversation,
            'messages' => $messages,
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


        return response()->json($chat);
    }
}
