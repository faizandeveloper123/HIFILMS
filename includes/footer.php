<?php if (!defined('HIIFI')) exit('Direct access not allowed.'); ?>
        </div>
<script src="<?php echo BASE_URL; ?>assets/js/jquery.sparkline.min.js"></script>
<script>
(function(){
    // The shell now uses the expanded Gentelella-blue sidebar by default.
    // Always show expanded on load so any previously stored collapsed
    // preference cannot override the new theme.
    document.body.className = 'sidebar-expanded';
    var savedCollapsed = '0';
    try { savedCollapsed = localStorage.getItem('sb_collapsed') || '0'; } catch (e) {}
    if (savedCollapsed === '1') {
        document.body.classList.add('sidebar-collapsed');
        document.body.classList.remove('sidebar-expanded');
    }
})();
document.addEventListener('dblclick', function(e){
    if (e.target.closest('#sidebar-menu, .sidebar-logo, .left_col')) {
        var exp = document.body.classList.contains('sidebar-expanded');
        document.body.classList.toggle('sidebar-collapsed', exp);
        document.body.classList.toggle('sidebar-expanded', !exp);
        localStorage.setItem('sb_collapsed', exp ? '1' : '0');
    }
});
function dsSetSidebarCollapsed(collapsed){
    document.body.classList.toggle('sidebar-collapsed', collapsed);
    document.body.classList.toggle('sidebar-expanded', !collapsed);
    document.body.classList.remove('sidebar-hidden');
    try { localStorage.setItem('sb_collapsed', collapsed ? '1' : '0'); } catch (e) {}
}
function dsInitSidebarToggle(){
    document.getElementById('dsToggleIn').addEventListener('click', function(e){
        e.preventDefault();
        e.stopPropagation();
        dsSetSidebarCollapsed(!document.body.classList.contains('sidebar-collapsed'));
    });
    var out = document.getElementById('dsSidebarToggleOut');
    if (out) {
        out.addEventListener('click', function(e){
            e.preventDefault();
            e.stopPropagation();
            dsSetSidebarCollapsed(false);
        });
    }
}
document.addEventListener('DOMContentLoaded', dsInitSidebarToggle);
if (document.readyState === 'interactive' || document.readyState === 'complete') dsInitSidebarToggle();
document.addEventListener('click', function(e){
    var item = e.target.closest('.side-menu>li>a, .side-menu>li.has-children>a');
    if (item) {
        var li = item.parentElement;
        if (li.classList.contains('has-children')) {
            var menu = li.querySelector(':scope > .child_menu');
            if (menu) {
                e.preventDefault();
                document.querySelectorAll('.side-menu>li>.child_menu').forEach(function(m){ if (m !== menu) m.style.display='none'; });
                var isOpen = menu.style.display === 'block';
                document.querySelectorAll('.side-menu>li').forEach(function(l){ l.classList.remove('active'); });
                li.classList.add('active');
                menu.style.display = isOpen ? 'none' : 'block';
            }
        }
    }
});
function slideout(){ setTimeout(function(){
    $(".alert-success").fadeOut("slow", function () { });
    $(".alert-danger").fadeOut("slow", function () { });
}, 4000);}
</script>
</body></html>