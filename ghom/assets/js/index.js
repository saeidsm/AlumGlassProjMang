/* Extracted from ghom/index.php during Phase 2C.
 * Concatenates 2 inline <script> block(s).
 */

function openWorkflowModal() {
            document.getElementById('workflowModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeWorkflowModal() {
            document.getElementById('workflowModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Close modal when clicking outside of it
        window.onclick = function(event) {
            const modal = document.getElementById('workflowModal');
            if (event.target == modal) {
                closeWorkflowModal();
            }
        }

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeWorkflowModal();
            }
        });

/* ---- next block ---- */

// Clear all drawings function
        function clearAllDrawings() {
            if (confirm('آیا مطمئن هستید که می‌خواهید همه ترسیم‌ها را پاک کنید؟')) {
                if (fabricCanvas) {
                    // Keep only the panel background (polygon)
                    const objects = fabricCanvas.getObjects();
                    const toRemove = objects.filter(obj => obj.shapeType);
                    toRemove.forEach(obj => fabricCanvas.remove(obj));
                    fabricCanvas.renderAll();
                }
            }
        }

        // Enhanced tool switching with visual feedback
        function switchTool(toolName) {
            currentTool = toolName;
            
            // Update active states
            document.querySelectorAll('.tool-item').forEach(item => {
                item.classList.remove('active');
            });
            
            const activeToolItem = document.querySelector(`[data-tool="${toolName}"]`).closest('.tool-item');
            if (activeToolItem) {
                activeToolItem.classList.add('active');
            }
            
            // Update cursor based on tool
            if (fabricCanvas) {
                switch(toolName) {
                    case 'line':
                        fabricCanvas.defaultCursor = 'crosshair';
                        break;
                    case 'rectangle':
                        fabricCanvas.defaultCursor = 'crosshair';
                        break;
                    case 'circle':
                        fabricCanvas.defaultCursor = 'crosshair';
                        break;
                    case 'freedraw':
                        fabricCanvas.defaultCursor = 'pencil';
                        break;
                    default:
                        fabricCanvas.defaultCursor = 'default';
                }
            }
        }

        // Add keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (document.getElementById('crack-drawer-modal').style.display !== 'flex') return;
            
            switch(e.key) {
                case '1':
                    switchTool('line');
                    break;
                case '2':
                    switchTool('rectangle');
                    break;
                case '3':
                    switchTool('circle');
                    break;
                case '4':
                    switchTool('freedraw');
                    break;
                case 'Escape':
                    document.getElementById('crack-drawer-modal').style.display = 'none';
                    break;
                case 'Delete':
                case 'Backspace':
                    if (e.ctrlKey) {
                        clearAllDrawings();
                        e.preventDefault();
                    }
                    break;
            }
        });
