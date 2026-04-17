<script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- All your variable declarations are here ---
        const messageForm = document.getElementById('message-form');
        const messageInput = document.getElementById('message-input');
        const chatMessagesArea = document.getElementById('chat-messages-area');
        const messageStatus = document.getElementById('message-status');
        const sendBtn = document.getElementById('send-btn');
        const selectedUserId = <?= json_encode($selectedUserId) ?>;
        const currentUserId = <?= json_encode($currentUserId) ?>;
        // ... and so on for all your other element variables.

        // --- All your other functions (user search, file handling, etc.) are here ---

        // ===================================================================
        // START: CORRECTED MESSAGE SENDING AND DISPLAY LOGIC
        // ===================================================================

        // This is the function that builds the HTML for a new message bubble
        function appendNewMessage(message, isSent) {
            if (!chatMessagesArea) return;

            // Remove the "no messages yet" placeholder if it exists
            const placeholder = chatMessagesArea.querySelector('.text-muted');
            if (placeholder) placeholder.remove();

            const wrapper = document.createElement('div');
            wrapper.className = 'message-wrapper ' + (isSent ? 'sent' : 'received');
            wrapper.dataset.messageId = message.id;

            let contentHTML = '';

            // Avatar for received messages
            if (!isSent) {
                const senderImg = message.sender_image ? '/uploads/' + message.sender_image.replace(/^\//, '') : '/assets/images/default-avatar.jpg';
                contentHTML += `<img src="${htmlspecialchars(senderImg)}" alt="" class="message-avatar">`;
            }

            // Main container for the bubble and metadata
            contentHTML += `<div style="width: 100%;">`;
            
            // The bubble itself
            let bubbleHTML = `<div class="message-bubble ${isSent ? 'sent' : 'received'}">`;
            if (message.message_content) {
                bubbleHTML += `<div class="message-content-display">${nl2br(htmlspecialchars(message.message_content))}</div>`;
            }
            // ... (Your existing logic for displaying attachments would go inside here) ...
            bubbleHTML += `</div>`; // Close message-bubble
            contentHTML += bubbleHTML;

            // The metadata (timestamp and read status)
            let metaHTML = `<div class="message-meta" dir="ltr" style="text-align: ${isSent ? 'right' : 'left'};">`;
            
            // **THE KEY FIX IS HERE:**
            // Use the persian_timestamp provided by the API instead of formatting it in the browser.
            metaHTML += `<span dir="rtl">${message.persian_timestamp || '...'}</span>`;

            if (isSent) {
                metaHTML += message.is_read ? ' <i class="fas fa-check-double" title="خوانده شده"></i>' : ' <i class="fas fa-check" title="ارسال شده"></i>';
            }
            if (message.edited_at) {
                metaHTML += `<span style="font-style: italic; font-size: 0.9em;">(ویرایش شده)</span>`;
            }
            metaHTML += `</div>`; // Close message-meta
            contentHTML += metaHTML;
            
            contentHTML += `</div>`; // Close main container
            wrapper.innerHTML = contentHTML;
            chatMessagesArea.appendChild(wrapper);
            chatMessagesArea.scrollTop = chatMessagesArea.scrollHeight;
        }

        // Handle form submission
        if (messageForm && selectedUserId) {
            messageForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                // ... (your logic to disable send button, show sending status) ...

                fetch('api/send_message.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.message) {
                        // **This now calls the corrected function above**
                        appendNewMessage(data.message, true); // true = isSent

                        // Clear the input form and reset UI elements
                        messageInput.value = '';
                        autoResizeTextarea();
                        // ... (your logic to clear file previews, reset buttons, etc.) ...
                    } else {
                        alert(data.message || 'خطا در ارسال پیام');
                    }
                })
                .catch(error => {
                    console.error('Send message error:', error);
                    alert('خطای شبکه.');
                });
            });
        }
        
        // ===================================================================
        // END: CORRECTED MESSAGE SENDING AND DISPLAY LOGIC
        // ===================================================================

        // --- Polling for new messages (This part also uses the corrected appendNewMessage) ---
        if (selectedUserId && chatMessagesArea) {
            let lastTimestamp = <?= json_encode($lastTimestampValue) ?>;
            setInterval(() => {
                fetch(`api/get_new_messages.php?with=${selectedUserId}&since=${encodeURIComponent(lastTimestamp)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.messages.length > 0) {
                        data.messages.forEach(msg => {
                            appendNewMessage(msg, msg.sender_id == currentUserId);
                            lastTimestamp = msg.timestamp;
                        });
                        // ... (your logic to mark messages as read) ...
                    }
                })
                .catch(error => console.error("Polling error:", error));
            }, 5000);
        }

        // --- All your other helper functions (nl2br, htmlspecialchars, etc.) ---
        function htmlspecialchars(str) { /* ... */ }
        function nl2br(str) { /* ... */ }
        // ... etc.
    });
</script>