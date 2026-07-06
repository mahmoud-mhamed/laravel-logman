{{-- Shared JS for the interactive collapsible JSON tree viewer.
     Included inside an existing <script> block. --}}
function jtCopyToClipboard(text) {
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).catch(function () {});
    } else {
        const ta = document.createElement('textarea');
        ta.value = text; ta.style.cssText = 'position:fixed;left:-9999px;';
        document.body.appendChild(ta); ta.select();
        try { document.execCommand('copy'); } catch (e) {}
        document.body.removeChild(ta);
    }
}

function jtFlash(el) {
    el.classList.add('jt-copied');
    setTimeout(function () { el.classList.remove('jt-copied'); }, 900);
}

function jtIsUrl(s) {
    return /^https?:\/\/[^\s"]+$/i.test(s);
}

function jtIsDate(s) {
    return /^\d{4}-\d{2}-\d{2}(?:[T ]\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?(?:Z|[+-]\d{2}:?\d{2})?)?$/.test(s)
        || /^\d{2}\/\d{2}\/\d{4}(?: \d{2}:\d{2}(?::\d{2})?)?$/.test(s);
}

function jtFormatDate(s) {
    const d = new Date(s);
    if (isNaN(d.getTime())) return s;
    try { return d.toLocaleString(); } catch (e) { return d.toString(); }
}

function jtStringNode(value) {
    const wrap = document.createElement('span');
    wrap.className = 'jt-str';

    if (jtIsUrl(value)) {
        wrap.appendChild(document.createTextNode('"'));
        const a = document.createElement('a');
        a.className = 'jt-link';
        a.href = value;
        a.target = '_blank';
        a.rel = 'noopener noreferrer nofollow';
        a.textContent = value;
        a.addEventListener('click', function (e) { e.stopPropagation(); });
        wrap.appendChild(a);
        wrap.appendChild(document.createTextNode('"'));
    } else if (jtIsDate(value)) {
        wrap.classList.add('jt-date');
        wrap.textContent = JSON.stringify(value);
        wrap.title = jtFormatDate(value);
    } else {
        wrap.textContent = JSON.stringify(value);
    }

    return wrap;
}

function jtBuildNode(key, value, isArrayItem) {
    const node = document.createElement('div');
    node.className = 'jt-node';

    const line = document.createElement('div');
    line.className = 'jt-line';

    const isArray = Array.isArray(value);
    const isObject = value !== null && typeof value === 'object' && !isArray;
    const isContainer = isArray || isObject;
    const entries = isArray ? value.map(function (v, i) { return [i, v]; })
        : (isObject ? Object.entries(value) : []);
    const hasChildren = isContainer && entries.length > 0;

    const toggle = document.createElement('span');
    toggle.className = 'jt-toggle' + (hasChildren ? ' jt-has-children' : '');
    line.appendChild(toggle);

    if (key !== null) {
        const k = document.createElement('span');
        k.className = isArrayItem ? 'jt-index' : 'jt-key';
        k.textContent = isArrayItem ? key : JSON.stringify(key);
        line.appendChild(k);
        line.appendChild(document.createTextNode(': '));
    }

    if (isContainer) {
        const open = isArray ? '[' : '{';
        const close = isArray ? ']' : '}';

        const openSpan = document.createElement('span');
        openSpan.className = 'jt-brace';
        openSpan.textContent = open;
        line.appendChild(openSpan);

        const preview = document.createElement('span');
        preview.className = 'jt-preview';
        preview.textContent = hasChildren ? (' ' + entries.length + (isArray ? ' items ' : ' keys ')) : '';
        line.appendChild(preview);

        const closeInline = document.createElement('span');
        closeInline.className = 'jt-brace jt-close-inline';
        closeInline.textContent = close;
        line.appendChild(closeInline);

        const copyBtn = document.createElement('span');
        copyBtn.className = 'jt-copy';
        copyBtn.title = 'Copy this node';
        copyBtn.textContent = 'copy';
        copyBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            jtCopyToClipboard(JSON.stringify(value, null, 2));
            jtFlash(copyBtn);
        });
        line.appendChild(copyBtn);

        node.appendChild(line);

        const children = document.createElement('div');
        children.className = 'jt-children';
        entries.forEach(function (pair) {
            children.appendChild(jtBuildNode(pair[0], pair[1], isArray));
        });
        node.appendChild(children);

        const closeLine = document.createElement('div');
        closeLine.className = 'jt-line jt-close-line';
        const cs = document.createElement('span');
        cs.className = 'jt-brace';
        cs.textContent = close;
        closeLine.appendChild(cs);
        node.appendChild(closeLine);

        if (hasChildren) {
            line.classList.add('jt-clickable');
            line.addEventListener('click', function (e) {
                if (e.target.closest('.jt-copy')) return;
                node.classList.toggle('collapsed');
            });
        }
    } else {
        let val;
        if (typeof value === 'string') {
            val = jtStringNode(value);
        } else {
            val = document.createElement('span');
            if (typeof value === 'number') { val.className = 'jt-num'; val.textContent = String(value); }
            else if (typeof value === 'boolean') { val.className = 'jt-bool'; val.textContent = String(value); }
            else { val.className = 'jt-null'; val.textContent = 'null'; }
        }
        line.appendChild(val);

        const copyBtn = document.createElement('span');
        copyBtn.className = 'jt-copy';
        copyBtn.title = 'Copy value';
        copyBtn.textContent = 'copy';
        copyBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            jtCopyToClipboard(typeof value === 'string' ? value : String(value));
            jtFlash(copyBtn);
        });
        line.appendChild(copyBtn);

        node.appendChild(line);
    }

    return node;
}

function initJsonViewers(root) {
    (root || document).querySelectorAll('.json-content').forEach(function (el) {
        if (el.dataset.jsonInit) return;
        const raw = el.textContent;
        let data;
        try { data = JSON.parse(raw); } catch (e) { return; }
        el.dataset.jsonInit = '1';
        el.dataset.raw = raw;
        el.classList.add('jt-ready');
        el.textContent = '';

        const bar = document.createElement('div');
        bar.className = 'jt-toolbar';
        const expandBtn = document.createElement('button');
        expandBtn.type = 'button'; expandBtn.className = 'jt-btn'; expandBtn.textContent = 'Expand all';
        const collapseBtn = document.createElement('button');
        collapseBtn.type = 'button'; collapseBtn.className = 'jt-btn'; collapseBtn.textContent = 'Collapse all';
        bar.appendChild(expandBtn);
        bar.appendChild(collapseBtn);

        const tree = document.createElement('div');
        tree.className = 'jt-tree';
        tree.appendChild(jtBuildNode(null, data, false));

        expandBtn.addEventListener('click', function () {
            tree.querySelectorAll('.jt-node.collapsed').forEach(function (n) { n.classList.remove('collapsed'); });
        });
        collapseBtn.addEventListener('click', function () {
            tree.querySelectorAll('.jt-node').forEach(function (n) {
                if (n.querySelector(':scope > .jt-children')) n.classList.add('collapsed');
            });
        });

        el.appendChild(bar);
        el.appendChild(tree);
    });
}

document.addEventListener('DOMContentLoaded', function () { initJsonViewers(); });
