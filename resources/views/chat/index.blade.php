@extends('layouts.app')

@section('content')
<div class="container-fluid vh-100 d-flex p-3 bg-light">
    <!-- Sidebar: Users & Search -->
    <div class="border-end bg-white" style="width: 320px; height: 100vh; overflow-y: auto; padding: 1rem;">
        <div class="mb-3">
            <input
                type="text"
                id="user-search"
                placeholder="Search users..."
                class="form-control"
                autocomplete="off" />
            <ul id="search-results" class="list-group mt-2 shadow-sm"></ul>
        </div>

        <h5 class="fw-bold text-secondary mb-3">Conversations</h5>
        <ul id="conversation-list" class="list-group">
            @foreach($conversations as $conversation)
            @php
            $otherUser = $conversation->user_one_id == auth()->id() ? $conversation->userTwo : $conversation->userOne;
            @endphp
            <li
                id="conversation-{{ $conversation->id }}"
                data-id="{{ $otherUser->id }}"
                data-conversation-id="{{ $conversation->id }}"
                class="list-group-item d-flex align-items-center justify-content-between"
                style="cursor:pointer;">

                <div class="d-flex align-items-center">
                    <div class="position-relative me-3">
                        <div class="rounded-circle bg-primary text-white d-flex justify-content-center align-items-center" style="width:40px; height:40px; font-weight:600;">
                            {{ strtoupper(substr($otherUser->name, 0, 1)) }}
                        </div>
                        <span class="position-absolute bottom-0 end-0 rounded-circle online-indicator bg-secondary" style="width:12px; height:12px; border: 2px solid white;"></span>
                    </div>
                    <div>
                        <div class="fw-semibold">{{ $otherUser->name }}</div>
                        <small class="text-muted last-message" style="max-width: 200px; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                            {{ $conversation->chats->last()?->message ?? 'No messages yet' }}
                        </small>
                    </div>
                </div>

                <small class="text-muted ms-2 last-message-time" style="white-space: nowrap;">
                    {{ $conversation->chats->last()?->created_at?->diffForHumans() ?? '' }}
                </small>
            </li>
            @endforeach
        </ul>


    </div>

    <!-- Chat Area -->
    <div class="flex-grow-1 d-flex flex-column ms-4 bg-white rounded shadow" style="height: 100vh;">
        <div
            id="chat-header"
            class="p-3 border-bottom fw-bold fs-5 text-primary"
            style="min-height: 60px;">
            Select a conversation
        </div>

        <div
            id="chat-messages"
            class="flex-grow-1 overflow-auto p-4"
            style="min-height: 0; background: #f8f9fa;">
            <!-- Messages will appear here -->
        </div>

        <form
            id="chat-form"
            class="d-flex border-top p-3 gap-3 align-items-center"
            autocomplete="off"
            style="display: none;">
            <input type="hidden" id="conversation_id" />

            <input
                type="text"
                id="chat-input"
                placeholder="Type your message..."
                class="form-control rounded-pill"
                style="padding-left: 20px; padding-right: 20px;" />

            <button type="submit" class="btn btn-primary rounded-pill px-4">
                Send
            </button>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/dayjs@1/dayjs.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/dayjs@1/plugin/relativeTime.js"></script>
<script>
    dayjs.extend(dayjs_plugin_relativeTime);

    const authUserId = @json(auth()->id());

    function formatRelativeTime(timestamp) {
        return dayjs(timestamp).fromNow();
    }

    function createMessageBubble(message, senderName, isOwn, timestamp) {
        return `
      <div class="d-flex ${isOwn ? 'justify-content-end' : 'justify-content-start'} mb-3">
        <div class="p-3 rounded" style="
          max-width: 65%;
          background-color: ${isOwn ? '#0d6efd' : '#e9ecef'};
          color: ${isOwn ? 'white' : 'black'};
          box-shadow: 0 1px 3px rgba(0,0,0,0.1);
          border-radius: 15px;">
          <div class="small fw-semibold mb-1">${senderName}</div>
          <div>${message}</div>
          <div class="text-end text-xs text-white-100 mt-1" style="font-size: 0.75rem; opacity: 0.7;">
            ${formatRelativeTime(timestamp)}
          </div>
        </div>
      </div>
    `;
    }


    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('user-search');
        const searchResults = document.getElementById('search-results');
        const conversationList = document.getElementById('conversation-list');
        const chatHeader = document.getElementById('chat-header');
        const chatMessages = document.getElementById('chat-messages');
        const chatForm = document.getElementById('chat-form');
        const chatInput = document.getElementById('chat-input');
        const conversationIdInput = document.getElementById('conversation_id');

        let currentChannel = null;

        // Search users dynamically
        searchInput.addEventListener('input', function() {
            let query = this.value.trim();
            if (query.length < 2) {
                searchResults.innerHTML = '';
                return;
            }
            fetch(`/chat/search?q=${encodeURIComponent(query)}`)
                .then(res => res.json())
                .then(users => {
                    searchResults.innerHTML = users.map(user => `
          <li data-id="${user.id}" class="list-group-item list-group-item-action d-flex align-items-center" style="cursor:pointer;">
            <div class="rounded-circle bg-primary text-white d-flex justify-content-center align-items-center me-3" style="width:35px; height:35px; font-weight:600;">
              ${user.name.charAt(0).toUpperCase()}
            </div>
            <div>
              <div class="fw-semibold">${user.name}</div>
            </div>
          </li>
        `).join('');
                });
        });

        // Clicking on a user from search to start chat
        searchResults.addEventListener('click', function(e) {
            const li = e.target.closest('li');
            if (!li) return;
            startConversation(li.dataset.id);
            searchResults.innerHTML = '';
            searchInput.value = '';
        });

        // Clicking on a conversation in the list
        conversationList.addEventListener('click', function(e) {
            const li = e.target.closest('li');
            if (!li) return;
            startConversation(li.dataset.id);
        });

        function subscribeToConversation(conversationId) {
            if (currentChannel) {
                currentChannel.unsubscribe();
            }

            currentChannel = window.Echo.private(`conversation.${conversationId}`);

            currentChannel.listen('MessageSent', (e) => {
                if (e.sender_id !== authUserId) {
                    chatMessages.innerHTML += createMessageBubble(e.message, e.sender.name, false, e.created_at);
                    chatMessages.scrollTop = chatMessages.scrollHeight;
                }

                const li = document.getElementById(`conversation-${e.conversation_id}`);
                if (li) {
                    const lastMsgElem = li.querySelector('.last-message');
                    const lastMsgTimeElem = li.querySelector('.last-message-time');

                    if (lastMsgElem) {
                        lastMsgElem.textContent = e.message.length > 40 ? e.message.substring(0, 40) + '...' : e.message;
                    }
                    if (lastMsgTimeElem) {
                        lastMsgTimeElem.textContent = formatRelativeTime(e.created_at || new Date().toISOString());
                    }

                    const parent = li.parentNode;
                    if (parent.firstChild !== li) {
                        parent.insertBefore(li, parent.firstChild);
                    }
                }
            });
        }

        function startConversation(userId) {
            fetch(`/chat/conversation/${userId}`)
                .then(res => res.json())
                .then(data => {
                    if (!data.conversation) {
                        chatHeader.textContent = "No conversation found.";
                        chatMessages.innerHTML = "";
                        chatForm.style.display = 'none';
                        return;
                    }

                    conversationIdInput.value = data.conversation.id;

                    const otherName = data.conversation.user_one_id === authUserId ?
                        data.conversation.user_two?.name || 'Unknown User' :
                        data.conversation.user_one?.name || 'Unknown User';

                    chatHeader.textContent = ` ${otherName}`;

                    chatMessages.innerHTML = data.messages.map(m =>
                        createMessageBubble(m.message, m.sender.name, m.sender_id === authUserId, m.created_at)
                    ).join('');

                    chatForm.style.display = 'flex';
                    chatMessages.scrollTop = chatMessages.scrollHeight;

                    // Subscribe to real-time updates for this conversation
                    subscribeToConversation(data.conversation.id);
                });
        }

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
                    chatMessages.innerHTML += createMessageBubble(chat.message, 'You', true, chat.created_at || new Date().toISOString());
                    chatInput.value = '';
                    chatMessages.scrollTop = chatMessages.scrollHeight;

                    // Update sidebar last message & timestamp for your own message
                    const li = document.getElementById(`conversation-${chat.conversation_id}`);
                    if (li) {
                        const lastMsgElem = li.querySelector('.last-message');
                        const lastMsgTimeElem = li.querySelector('.last-message-time');

                        if (lastMsgElem) {
                            lastMsgElem.textContent = chat.message.length > 40 ? chat.message.substring(0, 40) + '...' : chat.message;
                        }
                        if (lastMsgTimeElem) {
                            lastMsgTimeElem.textContent = 'Just now';
                        }

                        // Move to top
                        const parent = li.parentNode;
                        if (parent.firstChild !== li) {
                            parent.insertBefore(li, parent.firstChild);
                        }
                    }
                });
        });
    });
</script>
@endsection
