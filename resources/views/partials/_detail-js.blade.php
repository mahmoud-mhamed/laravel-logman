{{-- Shared JS for entry detail panels --}}
function toggleDetail(index, row) {
    const detailRow = document.getElementById('detail-' + index);
    if (!detailRow) return;
    detailRow.classList.toggle('visible');
    row.classList.toggle('open');
}

function showPane(index, pane, tab) {
    const parent = tab.closest('.detail-content');
    parent.querySelectorAll('.detail-tab').forEach(t => t.classList.remove('active'));
    parent.querySelectorAll('.detail-pane').forEach(p => p.classList.remove('active'));
    tab.classList.add('active');
    document.getElementById('pane-' + index + '-' + pane)?.classList.add('active');
}

function copyText(btn, index, pane) {
    const el = document.querySelector('#pane-' + index + '-' + pane + ' .stack-content, #pane-' + index + '-' + pane + ' .context-content');
    if (!el) return;
    const text = el.textContent;
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(() => showCopied(btn));
    } else {
        const ta = document.createElement('textarea');
        ta.value = text; ta.style.cssText = 'position:fixed;left:-9999px;';
        document.body.appendChild(ta); ta.select(); document.execCommand('copy');
        document.body.removeChild(ta); showCopied(btn);
    }
}

function showCopied(btn) {
    const orig = btn.textContent;
    btn.textContent = 'Copied!'; btn.style.background = 'var(--success-bg)'; btn.style.color = 'var(--success-text)'; btn.style.borderColor = 'var(--debug-border)';
    setTimeout(() => { btn.textContent = orig; btn.style.background = ''; btn.style.color = ''; btn.style.borderColor = ''; }, 1500);
}

const detailSearchState = {};

function searchInDetail(input, index) {
    const query = input.value.trim().toLowerCase();
    const panel = document.getElementById('detail-' + index);
    if (!panel) return;
    const activePane = panel.querySelector('.detail-pane.active');
    if (!activePane) return;
    const contentEl = activePane.querySelector('.stack-content, .context-content');
    if (!contentEl) return;
    if (!detailSearchState[index]) detailSearchState[index] = { originals: {} };
    const state = detailSearchState[index];
    const paneId = activePane.id;
    if (!state.originals[paneId]) state.originals[paneId] = contentEl.textContent;
    if (!query) { contentEl.innerHTML = escapeHtml(state.originals[paneId]); document.getElementById('detail-search-info-' + index).textContent = ''; state.matches = []; state.current = -1; return; }
    const text = state.originals[paneId];
    const escaped = escapeRegex(query);
    let matchCount = 0;
    const highlighted = escapeHtml(text).replace(new RegExp('(' + escapeRegex(escapeHtml(query)) + ')', 'gi'), function(m) { matchCount++; return '<span class="detail-highlight" data-match="' + matchCount + '">' + m + '</span>'; });
    contentEl.innerHTML = highlighted;
    state.matches = contentEl.querySelectorAll('.detail-highlight');
    state.current = state.matches.length > 0 ? 0 : -1;
    const info = document.getElementById('detail-search-info-' + index);
    if (state.matches.length > 0) { info.textContent = '1 / ' + state.matches.length; state.matches[0].classList.add('current'); state.matches[0].scrollIntoView({ block: 'nearest' }); } else { info.textContent = 'No results'; }
}

function detailSearchNav(index, direction) {
    const state = detailSearchState[index];
    if (!state || !state.matches || state.matches.length === 0) return;
    state.matches[state.current]?.classList.remove('current');
    state.current = (state.current + direction + state.matches.length) % state.matches.length;
    state.matches[state.current].classList.add('current');
    state.matches[state.current].scrollIntoView({ block: 'nearest' });
    document.getElementById('detail-search-info-' + index).textContent = (state.current + 1) + ' / ' + state.matches.length;
}

function escapeHtml(text) { const div = document.createElement('div'); div.textContent = text; return div.innerHTML; }
function escapeRegex(str) { return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }
