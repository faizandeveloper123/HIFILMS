/* ============ LAPS School & College LMS shared helpers ============ */
var HIIFI_BASE = null;

(function () {
    var s = document.currentScript;
    if (s && s.src) {
        var m = s.src.match(/^(https?:\/\/[^/]+)?(\/HIIFI%20LMS\/|\/api\/|\/)(?:assets\/js\/app_shared\.js)?/i);
    }
    var links = document.querySelectorAll('link[href*="bootstrap.min.css"]');
    var base = '';
    links.forEach(function (l) { base = l.href.split('/assets/').shift(); });
    if (!base) base = '/';
    HIIFI_BASE = base.replace(/\/$/, '') + '/';
})();

function eduGet(url, cb) {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', url, true);
    xhr.onreadystatechange = function () {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                try { cb(JSON.parse(xhr.responseText)); } catch (e) { cb(null); }
            } else { cb(null); }
        }
    };
    xhr.send();
}

/* Auto-init Select2 on any element with class .edu-select2 (safe if plugin loaded) */
function eduInitSelect2(scope) {
    if (!window.jQuery || !jQuery.fn.select2) return;
    var root = scope || document;
    jQuery(root).find('select.edu-select2').each(function () {
        var $el = jQuery(this);
        if ($el.data('select2')) return;
        $el.select2({ placeholder: $el.attr('placeholder') || 'Select...', width: '100%', allowClear: true });
    });
}

/* Load sections for a class into a section <select> (fallback plain HTML if ajax fails) */
function eduSections(classSel, sectionSel) {
    var cls = jQuery(classSel).val();
    var $sec = jQuery(sectionSel);
    if (!cls) { $sec.empty(); return; }
    eduGet(HIIFI_BASE + 'get_sections.php?class_id=' + encodeURIComponent(cls), function (data) {
        $sec.empty();
        if (Array.isArray(data)) {
            data.forEach(function (r) {
                $sec.append(new Option(r.section_name, r.section_id));
            });
        } else if (data && data.results) {
            data.results.forEach(function (r) {
                $sec.append(new Option(r.name || r.section_name, r.id || r.section_id));
            });
        }
    });
}

/* Load terms (exam names) for a session into a select */
function eduTerms(sessionSel, termSel) {
    var s = jQuery(sessionSel).val();
    var $t = jQuery(termSel);
    if (!s) { $t.empty(); return; }
    eduGet(HIIFI_BASE + 'get_terms.php?session=' + encodeURIComponent(s), function (data) {
        $t.empty();
        var list = (data && data.results) ? data.results : [];
        list.forEach(function (name) { $t.append(new Option(name, name)); });
    });
}

/* Load classes for a class-head into a select */
function eduClassesByHead(headSel, classSel) {
    var h = jQuery(headSel).val();
    var $c = jQuery(classSel);
    $c.empty();
    if (!h) return;
    eduGet(HIIFI_BASE + 'get_classes_by_head.php?head=' + encodeURIComponent(h), function (data) {
        (data || []).forEach(function (r) { $c.append(new Option(r.name, r.id)); });
        try { if (jQuery.fn.select2) $c.trigger('change'); } catch (e) {}
    });
}

/* Small toast used by many pages */
function eduToast(msg, type) {
    var t = document.createElement('div');
    t.style.cssText = 'position:fixed;top:18px;right:18px;z-index:99999;background:' + (type === 'error' ? '#f43f5e' : '#10b981') + ';color:#fff;padding:12px 18px;border-radius:10px;font:600 13px Segoe UI,sans-serif;box-shadow:0 8px 24px rgba(0,0,0,.25);opacity:0;transition:opacity .25s;';
    t.textContent = msg;
    document.body.appendChild(t);
    requestAnimationFrame(function () { t.style.opacity = '1'; });
    setTimeout(function () { t.style.opacity = '0'; setTimeout(function () { t.remove(); }, 300); }, 2800);
}

/* Auto-hide .slideout alert after 4s */
document.addEventListener('DOMContentLoaded', function () {
    var so = document.querySelectorAll('.slideout, .alert-dismissible:not(.keep)');
    so.forEach(function (el) {
        if (el.classList.contains('keep')) return;
        setTimeout(function () {
            el.style.transition = 'opacity .4s'; el.style.opacity = '0';
            setTimeout(function () { el.style.display = 'none'; }, 420);
        }, 4200);
    });
});

/* Re-init select2 after every navigation/mutation so dynamically injected selects get styled */
if (window.MutationObserver) {
    var ob = new MutationObserver(function (mut) {
        clearTimeout(ob.__t);
        ob.__t = setTimeout(function () { eduInitSelect2(document); }, 150);
    });
    document.addEventListener('DOMContentLoaded', function () { ob.observe(document.body, { childList: true, subtree: true }); });
}