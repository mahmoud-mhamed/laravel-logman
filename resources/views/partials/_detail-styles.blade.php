{{-- Shared styles for entry detail panels --}}
<style>
    .log-row { cursor: pointer; transition: all 0.1s ease; }
    .log-row:hover td { background: var(--primary-light); }
    .expand-icon { display: inline-flex; align-items: center; justify-content: center; width: 18px; height: 18px; transition: transform 0.2s ease; color: var(--text-light); font-size: 8px; border-radius: 4px; }
    .log-row:hover .expand-icon { background: var(--primary-light); color: var(--primary); }
    .log-row.open .expand-icon { transform: rotate(90deg); background: var(--primary-light); color: var(--primary); }
    .detail-row { display: none; }
    .detail-row.visible { display: table-row; }
    .detail-row td { padding: 0 12px 12px 12px; background: var(--bg) !important; }
    .detail-content { background: var(--bg-card); border-radius: var(--radius); border: 1px solid var(--border); overflow: hidden; box-shadow: var(--shadow-sm); }
    .detail-tabs { display: flex; border-bottom: 1px solid var(--border); background: var(--bg-sidebar); }
    .detail-tab { padding: 9px 18px; font-size: 12px; font-weight: 600; cursor: pointer; color: var(--text-light); border-bottom: 2px solid transparent; transition: all 0.2s; background: none; border-top: none; border-left: none; border-right: none; }
    .detail-tab:hover { color: var(--text); }
    .detail-tab.active { color: var(--primary); border-bottom-color: var(--primary); background: var(--bg-card); }
    .detail-pane { display: none; padding: 16px 20px; }
    .detail-pane.active { display: block; }
    .stack-content { font-family: var(--font-mono); font-size: 12px; white-space: pre-wrap; word-break: break-all; max-height: 400px; overflow-y: auto; color: var(--text-muted); line-height: 1.8; }
    .context-content { font-family: var(--font-mono); font-size: 12px; white-space: pre-wrap; color: var(--info-text); line-height: 1.8; }
    .copy-btn { position: absolute; top: 10px; right: 12px; padding: 4px 12px; font-size: 11px; border: 1px solid var(--border); border-radius: var(--radius-sm); background: var(--bg-card); color: var(--text-light); cursor: pointer; font-weight: 500; transition: all 0.2s; }
    .copy-btn:hover { background: var(--primary); color: white; border-color: var(--primary); }
    .detail-pane-wrap { position: relative; }
    .detail-search { display: flex; gap: 6px; align-items: center; padding: 8px 20px; border-bottom: 1px solid var(--border-light); }
    .detail-search input { flex: 1; padding: 5px 10px; border: 1px solid var(--border); border-radius: var(--radius-sm); font-size: 12px; background: var(--bg); color: var(--text); outline: none; font-family: var(--font-mono); }
    .detail-search input:focus { border-color: var(--primary); box-shadow: 0 0 0 2px var(--primary-glow); }
    .detail-search-info { font-size: 11px; color: var(--text-light); white-space: nowrap; }
    .detail-search-nav { display: flex; gap: 2px; }
    .detail-search-nav button { padding: 3px 8px; border: 1px solid var(--border); border-radius: 4px; background: var(--bg-card); color: var(--text-muted); cursor: pointer; font-size: 11px; }
    .detail-search-nav button:hover { background: var(--hover); }
    .detail-highlight { background: #fbbf24; color: #1a1d26; border-radius: 2px; padding: 0 1px; }
    .detail-highlight.current { background: #f97316; color: white; }
</style>
