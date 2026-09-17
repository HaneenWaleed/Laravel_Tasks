<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Store Assistant — Chatbot</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --chat-bg: #0f172a;
            --chat-panel: #111827;
            --chat-surface: #1f2937;
            --chat-accent: #38bdf8;
            --chat-accent-soft: rgba(56, 189, 248, 0.15);
            --chat-user: #2563eb;
            --chat-bot: #1e293b;
            --chat-text: #e5e7eb;
            --chat-muted: #94a3b8;
            --chat-border: rgba(148, 163, 184, 0.18);
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            color: var(--chat-text);
            background:
                radial-gradient(circle at top left, rgba(56, 189, 248, 0.18), transparent 32%),
                radial-gradient(circle at bottom right, rgba(37, 99, 235, 0.22), transparent 28%),
                linear-gradient(160deg, #020617 0%, #0f172a 45%, #111827 100%);
            font-family: "Segoe UI", system-ui, -apple-system, sans-serif;
        }

        .chat-shell {
            max-width: 980px;
            margin: 0 auto;
            padding: 1.5rem 1rem 2rem;
        }

        .chat-card {
            background: rgba(17, 24, 39, 0.92);
            border: 1px solid var(--chat-border);
            border-radius: 1.25rem;
            box-shadow: 0 24px 60px rgba(2, 6, 23, 0.45);
            overflow: hidden;
            backdrop-filter: blur(10px);
        }

        .chat-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--chat-border);
            background: linear-gradient(90deg, rgba(37, 99, 235, 0.18), rgba(56, 189, 248, 0.08));
        }

        .chat-avatar {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: grid;
            place-items: center;
            background: var(--chat-accent-soft);
            color: var(--chat-accent);
            font-size: 1.35rem;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #22c55e;
            box-shadow: 0 0 0 4px rgba(34, 197, 94, 0.18);
            display: inline-block;
        }

        .chat-messages {
            height: min(62vh, 560px);
            overflow-y: auto;
            padding: 1.25rem 1.5rem;
            background:
                linear-gradient(180deg, rgba(15, 23, 42, 0.35), rgba(15, 23, 42, 0.7));
            scroll-behavior: smooth;
        }

        .chat-messages::-webkit-scrollbar {
            width: 8px;
        }

        .chat-messages::-webkit-scrollbar-thumb {
            background: rgba(148, 163, 184, 0.35);
            border-radius: 999px;
        }

        .message-row {
            display: flex;
            margin-bottom: 1rem;
            animation: fadeUp 0.25s ease;
        }

        .message-row.user {
            justify-content: flex-end;
        }

        .message-bubble {
            max-width: min(78%, 640px);
            padding: 0.85rem 1rem;
            border-radius: 1rem;
            white-space: pre-wrap;
            line-height: 1.55;
            word-break: break-word;
            border: 1px solid transparent;
        }

        .message-row.bot .message-bubble {
            background: var(--chat-bot);
            border-color: var(--chat-border);
            border-bottom-left-radius: 0.35rem;
        }

        .message-row.user .message-bubble {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            border-bottom-right-radius: 0.35rem;
        }

        .message-meta {
            font-size: 0.75rem;
            color: var(--chat-muted);
            margin-bottom: 0.35rem;
        }

        .quick-prompts {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            padding: 0 1.5rem 1rem;
        }

        .quick-prompts .btn {
            border-radius: 999px;
            border-color: var(--chat-border);
            color: var(--chat-text);
            background: rgba(30, 41, 59, 0.8);
            font-size: 0.85rem;
        }

        .quick-prompts .btn:hover {
            background: var(--chat-accent-soft);
            border-color: rgba(56, 189, 248, 0.45);
            color: #fff;
        }

        .chat-composer {
            padding: 1rem 1.5rem 1.35rem;
            border-top: 1px solid var(--chat-border);
            background: rgba(15, 23, 42, 0.85);
        }

        .composer-input {
            background: var(--chat-surface);
            border: 1px solid var(--chat-border);
            color: var(--chat-text);
            border-radius: 0.9rem;
            padding: 0.85rem 1rem;
            resize: none;
            min-height: 52px;
        }

        .composer-input:focus {
            background: #243044;
            color: #fff;
            border-color: rgba(56, 189, 248, 0.55);
            box-shadow: 0 0 0 0.2rem rgba(56, 189, 248, 0.15);
        }

        .composer-input::placeholder {
            color: #7b8798;
        }

        .btn-send {
            min-width: 120px;
            border: none;
            border-radius: 0.9rem;
            background: linear-gradient(135deg, #38bdf8, #2563eb);
            color: #fff;
            font-weight: 600;
            padding: 0.85rem 1.1rem;
        }

        .btn-send:disabled {
            opacity: 0.65;
        }

        .typing-indicator span {
            display: inline-block;
            width: 7px;
            height: 7px;
            margin-right: 3px;
            border-radius: 50%;
            background: var(--chat-muted);
            animation: bounce 1.2s infinite ease-in-out;
        }

        .typing-indicator span:nth-child(2) { animation-delay: 0.15s; }
        .typing-indicator span:nth-child(3) { animation-delay: 0.3s; }

        .nav-links a {
            color: var(--chat-muted);
            text-decoration: none;
            margin-right: 1rem;
            font-size: 0.9rem;
        }

        .nav-links a:hover {
            color: var(--chat-accent);
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes bounce {
            0%, 80%, 100% { transform: translateY(0); opacity: 0.45; }
            40% { transform: translateY(-5px); opacity: 1; }
        }

        @media (max-width: 576px) {
            .message-bubble {
                max-width: 90%;
            }

            .btn-send {
                min-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="chat-shell">
        <div class="d-flex justify-content-between align-items-center mb-3 nav-links">
            <div>
                <a href="{{ route('products.index') }}"><i class="bi bi-box-seam me-1"></i>Products</a>
                <a href="{{ route('categories.index') }}"><i class="bi bi-tags me-1"></i>Categories</a>
                <a href="{{ route('orders.index') }}"><i class="bi bi-receipt me-1"></i>Orders</a>
            </div>
            <span class="text-secondary small">Live store data</span>
        </div>

        <div class="chat-card">
            <div class="chat-header d-flex align-items-center gap-3">
                <div class="chat-avatar">
                    <i class="bi bi-robot"></i>
                </div>
                <div class="flex-grow-1">
                    <h1 class="h5 mb-1">Store Assistant</h1>
                    <div class="small text-secondary d-flex align-items-center gap-2">
                        <span class="status-dot"></span>
                        @if ($aiEnabled)
                            Online — AI + live store database
                        @else
                            Online — local mode (add OPENAI_API_KEY or GEMINI_API_KEY to enable AI)
                        @endif
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-outline-light" id="clearChat" title="Clear conversation">
                    <i class="bi bi-trash3"></i>
                </button>
            </div>

            <div id="chatMessages" class="chat-messages" aria-live="polite"></div>

            <div class="quick-prompts" id="quickPrompts">
                <button type="button" class="btn btn-sm" data-prompt="Give me an overview">Overview</button>
                <button type="button" class="btn btn-sm" data-prompt="List all products">Products</button>
                <button type="button" class="btn btn-sm" data-prompt="List categories">Categories</button>
                <button type="button" class="btn btn-sm" data-prompt="List recent orders">Orders</button>
                <button type="button" class="btn btn-sm" data-prompt="Which products are out of stock?">Out of stock</button>
                <button type="button" class="btn btn-sm" data-prompt="Show the cheapest products">Cheapest</button>
                <button type="button" class="btn btn-sm" data-prompt="Help">Help</button>
            </div>

            <form id="chatForm" class="chat-composer">
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-md">
                        <label for="messageInput" class="form-label small text-secondary mb-1">Ask about your store data</label>
                        <textarea
                            id="messageInput"
                            class="form-control composer-input"
                            rows="1"
                            maxlength="1000"
                            placeholder="Example: What is the price of ...? / Show products in Electronics"
                            required
                        ></textarea>
                    </div>
                    <div class="col-12 col-md-auto">
                        <button type="submit" class="btn btn-send w-100" id="sendBtn">
                            <i class="bi bi-send-fill me-1"></i> Send
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        const messagesEl = document.getElementById('chatMessages');
        const form = document.getElementById('chatForm');
        const input = document.getElementById('messageInput');
        const sendBtn = document.getElementById('sendBtn');
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const askUrl = @json(route('chatbot.ask'));

        const welcome = "Hello! I'm your Store Assistant.\nAsk me about products, categories, orders, stock, prices, or store statistics.\nTip: try the quick prompts below, or type \"help\".";

        appendMessage(welcome, 'bot');

        document.querySelectorAll('[data-prompt]').forEach((button) => {
            button.addEventListener('click', () => {
                input.value = button.getAttribute('data-prompt');
                form.requestSubmit();
            });
        });

        document.getElementById('clearChat').addEventListener('click', () => {
            messagesEl.innerHTML = '';
            appendMessage(welcome, 'bot');
            input.focus();
        });

        input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                form.requestSubmit();
            }
        });

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const message = input.value.trim();
            if (!message) {
                return;
            }

            appendMessage(message, 'user');
            input.value = '';
            autoResize();
            setLoading(true);

            const typingId = appendTyping();

            try {
                const response = await fetch(askUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ message }),
                });

                const data = await response.json();
                removeTyping(typingId);

                if (!response.ok) {
                    const errorMessage = data.message
                        || Object.values(data.errors || {}).flat()[0]
                        || 'Something went wrong. Please try again.';
                    appendMessage(errorMessage, 'bot');
                    return;
                }

                appendMessage(data.reply || 'No response received.', 'bot');
            } catch (error) {
                removeTyping(typingId);
                appendMessage('Unable to reach the assistant. Please check your connection and try again.', 'bot');
            } finally {
                setLoading(false);
                input.focus();
            }
        });

        function appendMessage(text, role) {
            const row = document.createElement('div');
            row.className = `message-row ${role}`;

            const wrap = document.createElement('div');
            const meta = document.createElement('div');
            meta.className = 'message-meta';
            meta.textContent = role === 'user' ? 'You' : 'Store Assistant';

            const bubble = document.createElement('div');
            bubble.className = 'message-bubble';
            bubble.textContent = text;

            wrap.appendChild(meta);
            wrap.appendChild(bubble);
            row.appendChild(wrap);
            messagesEl.appendChild(row);
            messagesEl.scrollTop = messagesEl.scrollHeight;
        }

        function appendTyping() {
            const id = `typing-${Date.now()}`;
            const row = document.createElement('div');
            row.className = 'message-row bot';
            row.id = id;
            row.innerHTML = `
                <div>
                    <div class="message-meta">Store Assistant</div>
                    <div class="message-bubble">
                        <div class="typing-indicator">
                            <span></span><span></span><span></span>
                        </div>
                    </div>
                </div>
            `;
            messagesEl.appendChild(row);
            messagesEl.scrollTop = messagesEl.scrollHeight;
            return id;
        }

        function removeTyping(id) {
            const el = document.getElementById(id);
            if (el) {
                el.remove();
            }
        }

        function setLoading(isLoading) {
            sendBtn.disabled = isLoading;
            input.disabled = isLoading;
        }

        function autoResize() {
            input.style.height = 'auto';
            input.style.height = `${Math.min(input.scrollHeight, 140)}px`;
        }

        input.addEventListener('input', autoResize);
        input.focus();
    </script>
</body>
</html>
