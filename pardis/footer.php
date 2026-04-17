<?php
// footer.php
?>
</div> <!-- Close content-wrapper from header -->
</main> <!-- Close main tag from header -->
<footer class="bg-gray-800 text-white py-6 mt-auto">
    <div class="max-w-7xl mx-auto px-4">
        <div class="text-center">
            <p class="text-gray-300">تمامی حقوق محفوظ است © <?php echo date('Y'); ?> شرکت آلومینیوم شیشه تهران</p>
        </div>
        
        <!-- RESPONSIVE Scroll to Top Button -->
        <!-- Base classes are for MOBILE: Centered, larger padding for easy tapping -->
        <!-- 'md:' prefixed classes are for DESKTOP: Moves to the right corner, smaller padding -->
        <button id="scrollToTopBtn" title="برو بالا" 
                class="hidden fixed bottom-4 left-1/2 -translate-x-1/2 md:left-auto md:right-4 md:translate-x-0 
                       bg-blue-600 hover:bg-blue-700 text-white p-3 md:p-2 
                       rounded-full shadow-lg transition-all duration-300">
            
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
            </svg>
        </button>

    </div>
</footer>
</div> <!-- Close main-container from header-->

<script>
    // Scroll to Top Button Functionality (This JavaScript remains unchanged)
    document.addEventListener('DOMContentLoaded', function() {
        const scrollToTopButton = document.getElementById('scrollToTopBtn');

        window.onscroll = function() {
            if (document.body.scrollTop > 20 || document.documentElement.scrollTop > 20) {
                scrollToTopButton.classList.remove('hidden');
            } else {
                scrollToTopButton.classList.add('hidden');
            }
        };

        scrollToTopButton.addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    });
</script>
</body>

</html>