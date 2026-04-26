<style>
    /* 
     * FLOATING SCROLLBAR SYSTEM 
     * Designed for heavy data tables in A-Six Project
     */
    .floating-scrollbar-wrapper {
        position: fixed;
        bottom: 0;
        z-index: 9999;
        height: 12px;
        background: rgba(255, 255, 255, 0.4);
        backdrop-filter: blur(8px);
        border-top: 1px solid rgba(0, 0, 0, 0.05);
        display: none;
        overflow-x: auto;
        overflow-y: hidden;
        transition: opacity 0.3s, height 0.2s, background 0.2s;
        opacity: 0.6;
        box-shadow: 0 -4px 12px rgba(0, 0, 0, 0.05);
        border-radius: 10px 10px 0 0;
    }

    .floating-scrollbar-wrapper:hover {
        opacity: 1;
        height: 16px;
        background: rgba(255, 255, 255, 0.9);
        box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.1);
    }

    .floating-scrollbar-content {
        height: 1px;
        pointer-events: none;
    }

    /* Custom Scrollbar Styling */
    .floating-scrollbar-wrapper::-webkit-scrollbar {
        height: 8px;
    }
    
    .floating-scrollbar-wrapper:hover::-webkit-scrollbar {
        height: 12px;
    }

    .floating-scrollbar-wrapper::-webkit-scrollbar-track {
        background: transparent;
    }

    .floating-scrollbar-wrapper::-webkit-scrollbar-thumb {
        background: #0857c3; /* BRI Blue */
        border-radius: 20px;
        border: 2px solid transparent;
        background-clip: content-box;
        transition: background 0.2s;
    }

    .floating-scrollbar-wrapper:hover::-webkit-scrollbar-thumb {
        background: #053b82;
    }

    /* Hide native scrollbar if preferred, but usually better to keep for accessibility */
    .kinerja-table-container::-webkit-scrollbar,
    .table-container::-webkit-scrollbar {
        height: 6px;
    }
</style>

<div id="global-floating-scrollbar" class="floating-scrollbar-wrapper">
    <div class="floating-scrollbar-content"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const floatScroll = document.getElementById('global-floating-scrollbar');
    const floatContent = floatScroll.querySelector('.floating-scrollbar-content');
    let activeContainer = null;
    let isSyncing = false;

    function syncPositions(source, target) {
        if (isSyncing) return;
        isSyncing = true;
        target.scrollLeft = source.scrollLeft;
        setTimeout(() => { isSyncing = false; }, 10);
    }

    function updateFloatingScroll() {
        const containers = document.querySelectorAll('.kinerja-table-container, .table-container');
        let currentBest = null;

        containers.forEach(container => {
            if (container.offsetParent === null) return; // Hidden by tabs etc.

            const rect = container.getBoundingClientRect();
            const windowHeight = window.innerHeight;
            
            const isVisible = rect.top < windowHeight && rect.bottom > 120;
            const needsScroll = container.scrollWidth > container.clientWidth + 5;
            const realScrollbarHidden = rect.bottom > windowHeight - 10;

            if (isVisible && needsScroll && realScrollbarHidden) {
                if (!currentBest) currentBest = container;
            }
        });

        if (currentBest) {
            activeContainer = currentBest;
            const rect = activeContainer.getBoundingClientRect();
            
            // Sync dimensions
            floatScroll.style.left = rect.left + 'px';
            floatScroll.style.width = rect.width + 'px';
            floatContent.style.width = activeContainer.scrollWidth + 'px';
            
            // Initial sync if just showing
            if (floatScroll.style.display !== 'block') {
                floatScroll.style.display = 'block';
                floatScroll.scrollLeft = activeContainer.scrollLeft;
            } else {
                // Regular sync
                if (Math.abs(floatScroll.scrollLeft - activeContainer.scrollLeft) > 1) {
                    syncPositions(activeContainer, floatScroll);
                }
            }
        } else {
            floatScroll.style.display = 'none';
            activeContainer = null;
        }
    }

    // Event Listeners
    floatScroll.addEventListener('scroll', function() {
        if (activeContainer) syncPositions(floatScroll, activeContainer);
    });

    // Listen for scroll events on containers (event delegation)
    document.addEventListener('scroll', function(e) {
        if (!e.target || !e.target.classList) return;
        
        if (e.target.classList.contains('kinerja-table-container') || e.target.classList.contains('table-container')) {
            if (activeContainer === e.target) {
                syncPositions(e.target, floatScroll);
            }
        }
    }, true);

    // Global scroll/resize
    window.addEventListener('scroll', updateFloatingScroll, { passive: true });
    window.addEventListener('resize', updateFloatingScroll);
    
    // Bootstrap Events
    $(document).on('shown.bs.tab shown.bs.collapse', function() {
        setTimeout(updateFloatingScroll, 150);
    });

    // Observer for layout changes (sidebar toggle, etc)
    const observer = new MutationObserver(() => {
        requestAnimationFrame(updateFloatingScroll);
    });
    
    observer.observe(document.body, { 
        attributes: true, 
        attributeFilter: ['class', 'style'] 
    });

    // Initial checks
    setTimeout(updateFloatingScroll, 600);
    setTimeout(updateFloatingScroll, 2000);
});
</script>
