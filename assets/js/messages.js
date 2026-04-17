/* Extracted from messages.php during Phase 2C.
 * Concatenates 1 inline <script> block(s).
 */

document.addEventListener('DOMContentLoaded', function() {
        const scrollToBottomButton = document.getElementById('scrollToBottomBtn');

        if (scrollToBottomButton) { // Check if the button exists on the page
            // Show/Hide button based on scroll position
            window.onscroll = function() {
                if (document.body.scrollTop > 100 || document.documentElement.scrollTop > 100) { // Show after 100px scroll
                    scrollToBottomButton.classList.remove('hidden');
                } else {
                    scrollToBottomButton.classList.add('hidden');
                }
            };

            // Smooth scroll to bottom on click
            scrollToBottomButton.addEventListener('click', function() {
                window.scrollTo({
                    top: document.documentElement.scrollHeight, // Scroll to the very bottom
                    behavior: 'smooth'
                });
            });
        }
    });
