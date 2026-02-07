<?php
/*
File: footer.php
Professional Blue Navy Layout - Sticky Footer
*/
?>
        </main>
        <footer class="footer py-3 border-top" style="margin-top: auto; background-color: #fff;">
            <div class="container-fluid text-center">
                <span class="text-muted" style="font-size: 13px;">Sistem Jurnal - Manajemen Pembelajaran &copy; <?php echo date('Y'); ?></span>
            </div>
        </footer>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
<script type="text/javascript">
document.addEventListener("DOMContentLoaded", function() {
    const sidebarCollapse = document.getElementById('sidebarCollapse');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const content = document.getElementById('content');
    const htmlEl = document.documentElement;

    // Apply saved state classes (sync with instant CSS applied in head)
    const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    if (isCollapsed && window.innerWidth > 768) {
        sidebar.classList.add('collapsed');
        content.classList.add('expanded');
        if (sidebarCollapse) sidebarCollapse.classList.add('toggled');
    } else {
        // Remove the instant-load class if not collapsed
        htmlEl.classList.remove('sidebar-collapsed-mode');
    }

    if (sidebarCollapse && sidebar) {
        sidebarCollapse.addEventListener('click', function() {
            if (window.innerWidth > 768) {
                // Desktop: Toggle collapsed mode (icon only)
                sidebar.classList.toggle('collapsed');
                content.classList.toggle('expanded');
                htmlEl.classList.toggle('sidebar-collapsed-mode');
                sidebarCollapse.classList.toggle('toggled');
                
                // Save state
                localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
            } else {
                // Mobile: Toggle show/hide
                sidebar.classList.toggle('active');
                if (overlay) overlay.classList.toggle('active');
            }
        });
    }

    // Close sidebar when clicking overlay (mobile)
    if (overlay) {
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        });
    }

    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            if (overlay) overlay.classList.remove('active');
            sidebar.classList.remove('active');
            
            // Restore desktop state
            const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (isCollapsed) {
                sidebar.classList.add('collapsed');
                content.classList.add('expanded');
                htmlEl.classList.add('sidebar-collapsed-mode');
                if (sidebarCollapse) sidebarCollapse.classList.add('toggled');
            } else {
                sidebar.classList.remove('collapsed');
                content.classList.remove('expanded');
                htmlEl.classList.remove('sidebar-collapsed-mode');
                if (sidebarCollapse) sidebarCollapse.classList.remove('toggled');
            }
        } else {
            // Reset for mobile
            sidebar.classList.remove('collapsed');
            content.classList.remove('expanded');
            if (sidebarCollapse) sidebarCollapse.classList.remove('toggled');
        }
    });
});
</script>
</body>
</html>