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
function dsIsCollapsed(){ return document.body.classList.contains('sidebar-collapsed'); }
function dsCloseFlyouts(except){
    document.querySelectorAll('#sidebar-menu .side-menu>li>.child_menu').forEach(function(m){
        if (m !== except) { m.style.display = 'none'; m.style.top = ''; }
    });
}
function dsPositionFlyout(li, menu){
    menu.style.display = 'block';
    var rect = li.getBoundingClientRect();
    var vh = window.innerHeight || document.documentElement.clientHeight;
    var mh = menu.offsetHeight;
    var top = rect.top;
    if (top + mh > vh - 10) { top = vh - mh - 10; }
    if (top < 10) { top = 10; }
    menu.style.top = top + 'px';
    var leftCol = document.getElementById('dsLeftCol');
    menu.style.left = (leftCol ? leftCol.offsetWidth : 90) + 'px';
    menu.style.maxHeight = (vh - 20) + 'px';
}
var dsFlyTimer = null;
function dsCancelFlyClose(){ if (dsFlyTimer) { clearTimeout(dsFlyTimer); dsFlyTimer = null; } }
function dsScheduleFlyClose(){ dsCancelFlyClose(); dsFlyTimer = setTimeout(function(){ dsCloseFlyouts(null); }, 260); }
function dsBindFlyouts(){
    document.querySelectorAll('#sidebar-menu .side-menu>li.has-children').forEach(function(li){
        var menu = li.querySelector(':scope > .child_menu');
        if (!menu) return;
        li.addEventListener('mouseenter', function(){
            if (!dsIsCollapsed()) return;
            dsCancelFlyClose();
            dsCloseFlyouts(menu);
            dsPositionFlyout(li, menu);
        });
        li.addEventListener('mouseleave', function(){
            if (!dsIsCollapsed()) return;
            dsScheduleFlyClose();
        });
        menu.addEventListener('mouseenter', function(){ if (dsIsCollapsed()) dsCancelFlyClose(); });
        menu.addEventListener('mouseleave', function(){ if (dsIsCollapsed()) dsScheduleFlyClose(); });
    });
}
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
    if (collapsed) { dsCloseFlyouts(null); }
    try { localStorage.setItem('sb_collapsed', collapsed ? '1' : '0'); } catch (e) {}
}
var dsSidebarInit = false;
function dsInitSidebarToggle(){
    if (dsSidebarInit) return;
    dsSidebarInit = true;
    var inBtn = document.getElementById('dsToggleIn');
    if (inBtn) {
        inBtn.addEventListener('click', function(e){
            e.preventDefault();
            e.stopPropagation();
            dsSetSidebarCollapsed(!dsIsCollapsed());
        });
    }
    var out = document.getElementById('dsSidebarToggleOut');
    if (out) {
        out.addEventListener('click', function(e){
            e.preventDefault();
            e.stopPropagation();
            dsSetSidebarCollapsed(false);
        });
    }
    dsBindFlyouts();
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
                if (dsIsCollapsed()) {
                    var wasOpen = menu.style.display === 'block';
                    dsCloseFlyouts(menu);
                    if (wasOpen) { menu.style.display = 'none'; menu.style.top = ''; }
                    else { dsPositionFlyout(li, menu); }
                    return;
                }
                dsCloseFlyouts(menu);
                var isOpen = menu.style.display === 'block';
                document.querySelectorAll('.side-menu>li').forEach(function(l){ l.classList.remove('active'); });
                li.classList.add('active');
                menu.style.display = isOpen ? 'none' : 'block';
            }
        }
    }
});
window.addEventListener('resize', function(){ if (dsIsCollapsed()) dsCloseFlyouts(null); });
var dsLeftColEl = document.getElementById('dsLeftCol');
if (dsLeftColEl) { dsLeftColEl.addEventListener('scroll', function(){ if (dsIsCollapsed()) dsCloseFlyouts(null); }); }
function slideout(){ setTimeout(function(){
    $(".alert-success").fadeOut("slow", function () { });
    $(".alert-danger").fadeOut("slow", function () { });
}, 4000);}
</script>
</body></html>