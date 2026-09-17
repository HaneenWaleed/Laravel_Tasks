<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Chatbot</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Store Assistant</h2>
            <a href="{{ url('/') }}" class="btn btn-outline-secondary btn-sm">Back</a>
        </div>

        <div id="conversation" class="border rounded p-3 mb-3" style="height: 420px; overflow-y: auto; background: #f8f9fa;">
            <div class="bg-white border rounded p-2 mb-2" style="max-width: 75%;">
                Hi! Ask me about categories, products, orders or order items.
            </div>
        </div>

        <form id="chat-form" class="d-flex gap-2">
            <input type="text" id="message" name="message" class="form-control" maxlength="4000" required
                placeholder="e.g. how many products are in the Electronics category?">
            <button type="submit" class="btn btn-primary">Send</button>
        </form>
    </div>

    <script>
        const form = document.getElementById('chat-form');
        const conversation = document.getElementById('conversation');
        const input = document.getElementById('message');

        function addBubble(text, isUser) {
            const bubble = document.createElement('div');
            bubble.className = 'border rounded p-2 mb-2 ' + (isUser ? 'bg-primary text-white ms-auto' : 'bg-white');
            bubble.style.maxWidth = '75%';
            bubble.textContent = text;
            conversation.appendChild(bubble);
            conversation.scrollTop = conversation.scrollHeight;
            return bubble;
        }

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const message = input.value.trim();
            if (!message) return;

            addBubble(message, true);
            const pending = addBubble('Thinking...', false);
            input.value = '';
            form.querySelector('button').disabled = true;

            try {
                const response = await fetch('{{ route('chatbot.respond') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ message }),
                });
                const result = await response.json();
                if (!response.ok) throw new Error(result.message ?? 'The request failed.');
                pending.textContent = result.content;
            } catch (error) {
                pending.textContent = error.message;
                pending.classList.add('text-danger');
            } finally {
                form.querySelector('button').disabled = false;
                input.focus();
            }
        });
    </script>
</body>
</html>
