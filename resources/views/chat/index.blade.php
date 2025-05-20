@extends('layouts.app')

@section('content')
<div class="container flex">
    <div class="w-1/4 border-r h-screen overflow-auto p-4">
        <input type="text" id="user-search" placeholder="Search users..." class="w-full p-2 border rounded" />
        <ul id="search-results" class="mt-2"></ul>

        <h3 class="mt-6 font-bold">Conversations</h3>
        <ul id="conversation-list" class="mt-2">
            @foreach($conversations as $conversation)
            @php
            $otherUser = $conversation->user_one_id == auth()->id() ? $conversation->userTwo : $conversation->userOne;
            @endphp
            <li data-id="{{ $otherUser->id }}" class="cursor-pointer p-2 border-b hover:bg-gray-100">
                {{ $otherUser->name }} ({{ $otherUser->email }})
            </li>
            @endforeach
        </ul>
    </div>

    <div class="w-3/4 h-screen flex flex-col p-4">
        <div id="chat-header" class="p-4 border-b font-bold">Select a conversation</div>
        <div id="chat-messages" class="flex-1 overflow-auto p-4 border rounded bg-white"></div>

        <form id="chat-form" class="p-4 border-t hidden flex space-x-2" autocomplete="off">
            <input type="hidden" id="conversation_id" />
            <input type="text" id="chat-input" placeholder="Type your message..." class="flex-grow p-2 border rounded" />
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded">Send</button>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
    const authUserId = @json(auth()->id());

    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('user-search');
        const searchResults = document.getElementById('search-results');
        const conversationList = document.getElementById('conversation-list');
        const chatHeader = document.getElementById('chat-header');
        const chatMessages = document.getElementById('chat-messages');
        const chatForm = document.getElementById('chat-form');
        const chatInput = document.getElementById('chat-input');
        const conversationIdInput = document.getElementById('conversation_id');

        let activeUserId = null;

        // Search users dynamically
        searchInput.addEventListener('input', function() {
            let query = this.value.trim();
            if (query.length < 2) {
                searchResults.innerHTML = '';
                return;
            }
            fetch(`/chat/search?q=${encodeURIComponent(query)}`)
                .then(res => {
                    if (!res.ok) throw new Error('Network response was not ok');
                    return res.json();
                })
                .then(users => {
                    searchResults.innerHTML = users.map(user => `
                        <li data-id="${user.id}" class="cursor-pointer p-2 border-b hover:bg-gray-100">
                            ${user.name} (${user.email})
                        </li>
                    `).join('');
                })
                .catch(err => {
                    console.error('Search fetch error:', err);
                });
        });

        // Click on user search result to start/continue chat
        searchResults.addEventListener('click', function(e) {
            if (e.target.tagName === 'LI') {
                let userId = e.target.getAttribute('data-id');
                startConversation(userId);
                searchResults.innerHTML = '';
                searchInput.value = '';
            }
        });


        // Click on conversation in list
        conversationList.addEventListener('click', function(e) {
            const li = e.target.closest('li');
            if (li && conversationList.contains(li)) {
                let userId = li.getAttribute('data-id');
                startConversation(userId);
            }
        });


        // Load conversation messages
        function startConversation(userId) {
            console.log("Start conversation with userId:", userId);

            fetch(`/chat/conversation/${userId}`)
                .then(res => {
                    console.log("Response status:", res.status);
                    return res.json();
                })
                .then(data => {
                    console.log("Conversation data:", data);

                    if (!data.conversation) {
                        chatHeader.textContent = "No conversation found.";
                        chatMessages.innerHTML = "";
                        chatForm.classList.add('hidden');
                        return;
                    }

                    conversationIdInput.value = data.conversation.id;

                    const otherName = data.conversation.user_one_id == authUserId ?
                        data.conversation.userTwo?.name :
                        data.conversation.userOne?.name;

                    chatHeader.textContent = `Chat with ${otherName || 'Unknown User'}`;

                    chatMessages.innerHTML = data.messages.map(m => `
                <div class="${m.sender_id == authUserId ? 'text-right' : 'text-left'} mb-2">
                    <strong>${m.sender.name}</strong>: ${m.message}
                </div>
            `).join('');

                    chatForm.classList.remove('hidden');
                    chatMessages.scrollTop = chatMessages.scrollHeight;
                })
                .catch(err => {
                    console.error("Error fetching conversation:", err);
                    chatHeader.textContent = "Error loading conversation.";
                    chatMessages.innerHTML = "";
                    chatForm.classList.add('hidden');
                });
        }


        // Send message
        chatForm.addEventListener('submit', function(e) {
            e.preventDefault();
            let message = chatInput.value.trim();
            if (!message) return;

            fetch('/chat/send', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        conversation_id: conversationIdInput.value,
                        message: message
                    })
                }).then(res => res.json())
                .then(chat => {
                    chatMessages.innerHTML += `
                    <div class="text-right mb-2">
                        <strong>You</strong>: ${chat.message}
                    </div>
                `;
                    chatInput.value = '';
                    chatMessages.scrollTop = chatMessages.scrollHeight;
                });
        });
    });
</script>
@endsection
