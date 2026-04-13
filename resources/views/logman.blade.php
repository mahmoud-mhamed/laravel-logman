<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs - {{ config('app.name') }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('logman::partials.styles')
    <style>
        .main-layout { display: flex; flex: 1; overflow: hidden; }

        /* Sidebar */
        .sidebar { width: 264px; background: var(--bg-card); border-right: 1px solid var(--border); display: flex; flex-direction: column; flex-shrink: 0; }
        .sidebar-header { padding: 14px 16px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
        .sidebar-header h3 { font-size: 12px; font-weight: 700; text-transform: uppercase; color: var(--text-light); letter-spacing: 0.08em; }
        .sidebar-tools { padding: 8px; border-bottom: 1px solid var(--border-light); display: flex; gap: 6px; align-items: center; }
        .sidebar-search { flex: 1; padding: 6px 10px; border: 1px solid var(--border); border-radius: var(--radius-sm); font-size: 12px; background: var(--bg); color: var(--text); outline: none; }
        .sidebar-search:focus { border-color: var(--primary); }
        .select-all-wrap { display: flex; align-items: center; gap: 4px; font-size: 11px; color: var(--text-light); cursor: pointer; white-space: nowrap; }
        .select-all-wrap input { accent-color: var(--primary); }
        .file-list { flex: 1; overflow-y: auto; padding: 6px 8px; }
        .file-list::-webkit-scrollbar { width: 4px; }
        .file-list::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }
        .file-entry { display: flex; align-items: stretch; margin-bottom: 2px; border-radius: var(--radius-sm); overflow: hidden; transition: all 0.15s; }
        .file-entry:hover { background: var(--hover); }
        .file-entry.is-active { background: var(--primary); }
        .file-check-wrap { display: flex; align-items: center; padding: 0 4px 0 8px; }
        .file-check { width: 13px; height: 13px; accent-color: var(--primary); cursor: pointer; }
        .file-item { display: flex; align-items: center; flex: 1; padding: 10px 10px; text-decoration: none; color: var(--text); min-width: 0; }
        .file-entry.is-active .file-item { color: #fff; font-weight: 600; }
        .file-entry.is-active .file-meta { color: rgba(255,255,255,0.65); }
        .file-entry.is-active .file-check { accent-color: #fff; }
        .file-info { flex: 1; min-width: 0; }
        .file-name { font-size: 13px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .file-meta { font-size: 10px; color: var(--text-light); display: flex; gap: 8px; margin-top: 2px; }
        .file-entry.is-active:hover { background: var(--primary-hover); }

        /* Main */
        .main { flex: 1; display: flex; flex-direction: column; overflow: hidden; background: var(--bg); }

        /* Toolbar */
        .toolbar { padding: 10px 16px; border-bottom: 1px solid var(--border); background: var(--bg-card); display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .search-group { display: flex; flex: 1; min-width: 200px; }
        .search-box { flex: 1; padding: 8px 14px; border: 1px solid var(--border); border-radius: var(--radius-sm) 0 0 var(--radius-sm); background: var(--bg); color: var(--text); font-size: 13px; outline: none; transition: all 0.2s; }
        .search-box:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-glow); z-index: 1; }
        .search-box::placeholder { color: var(--text-light); }
        .regex-toggle { padding: 8px 12px; border: 1px solid var(--border); border-left: none; border-radius: 0 var(--radius-sm) var(--radius-sm) 0; background: var(--bg); color: var(--text-light); font-size: 12px; cursor: pointer; font-family: var(--font-mono); font-weight: 700; transition: all 0.2s; }
        .regex-toggle.active { background: var(--primary); color: white; border-color: var(--primary); }
        .regex-toggle:hover:not(.active) { color: var(--primary); border-color: var(--primary); }
        .search-history { position: absolute; top: 100%; left: 0; right: 0; background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-sm); box-shadow: var(--shadow-lg); z-index: 50; display: none; max-height: 240px; overflow-y: auto; }
        .search-history.open { display: block; }
        .search-history-item { display: flex; align-items: center; justify-content: space-between; padding: 7px 12px; font-size: 12px; cursor: pointer; color: var(--text); transition: background 0.15s; }
        .search-history-item:hover { background: var(--hover); }
        .search-history-item .sh-text { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .search-history-item .sh-remove { color: var(--text-light); font-size: 14px; padding: 0 4px; opacity: 0; transition: opacity 0.15s; line-height: 1; }
        .search-history-item:hover .sh-remove { opacity: 1; }
        .search-history-item .sh-remove:hover { color: var(--danger-text); }
        .search-history-empty { padding: 12px; text-align: center; font-size: 12px; color: var(--text-light); }
        .actions-bar { display: flex; gap: 4px; margin-left: auto; }

        /* Filters bar */
        .filters-bar { padding: 10px 16px; border-bottom: 1px solid var(--border-light); background: var(--bg-card); display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .level-filters { display: flex; gap: 3px; flex-wrap: wrap; }
        .level-btn { padding: 4px 11px; border: 1px solid var(--border); border-radius: 20px; font-size: 11px; cursor: pointer; background: transparent; color: var(--text-muted); transition: all 0.2s ease; text-decoration: none; white-space: nowrap; font-weight: 600; }
        .level-btn:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-light); }
        .level-btn.active { background: var(--primary); color: white; border-color: var(--primary); box-shadow: 0 1px 4px var(--primary-glow); }
        .level-btn .count { opacity: 0.7; font-size: 10px; font-weight: 500; }
        .filters-spacer { flex: 1; }
        .date-filter { display: flex; gap: 5px; align-items: center; }
        .date-filter input[type="date"],
        .date-filter input[type="time"] { padding: 5px 8px; border: 1px solid var(--border); border-radius: var(--radius-sm); font-size: 12px; background: var(--bg); color: var(--text); transition: border-color 0.2s; font-family: var(--font-mono); }
        .date-filter input:focus { border-color: var(--primary); outline: none; }
        .date-filter label { font-size: 11px; color: var(--text-light); font-weight: 500; }
        .clear-filter-btn { background: none; border: 1px solid var(--border); border-radius: 50%; width: 20px; height: 20px; font-size: 13px; line-height: 1; cursor: pointer; color: var(--text-light); display: inline-flex; align-items: center; justify-content: center; padding: 0; transition: all 0.2s; }
        .clear-filter-btn:hover { background: var(--danger-bg); color: var(--danger-text); border-color: var(--danger-border); }
        .sort-toggle { padding: 5px 12px; border: 1px solid var(--border); border-radius: 20px; font-size: 11px; cursor: pointer; background: transparent; color: var(--text-muted); text-decoration: none; font-weight: 600; transition: all 0.2s; }
        .sort-toggle:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-light); }

        /* Content */
        .content { flex: 1; overflow-y: auto; }
        .content::-webkit-scrollbar { width: 6px; }
        .content::-webkit-scrollbar-thumb { background: var(--border); border-radius: 6px; }
        .log-table { width: 100%; border-collapse: collapse; }
        .log-table th { position: sticky; top: 0; background: var(--bg-card); border-bottom: 1px solid var(--border); padding: 10px 12px; text-align: left; font-size: 10px; text-transform: uppercase; color: var(--text-light); font-weight: 700; letter-spacing: 0.08em; z-index: 1; }
        .log-table td { padding: 8px 12px; border-bottom: 1px solid var(--border-light); vertical-align: top; }
        .log-row { cursor: pointer; transition: all 0.1s ease; user-select: none; -webkit-user-select: none; }
        .log-row.no-details { cursor: default; }
        .log-row:hover td { background: var(--primary-light); font-weight: 600; text-decoration: underline; }
        .log-row.level-danger td { background: var(--danger-bg); }
        .log-row.level-danger td:first-child { box-shadow: inset 3px 0 0 var(--danger-text); }
        .log-row.level-error td { background: var(--error-bg); }
        .log-row.level-error td:first-child { box-shadow: inset 3px 0 0 var(--error-text); }
        .log-row.level-warning td { background: var(--warning-bg); }
        .log-row.level-warning td:first-child { box-shadow: inset 3px 0 0 var(--warning-text); }
        .log-row.level-info td:first-child { box-shadow: inset 3px 0 0 var(--info-text); }
        .log-row.level-debug td:first-child { box-shadow: inset 3px 0 0 var(--debug-text); }
        .date-col { white-space: nowrap; font-size: 12px; color: var(--text-muted); font-family: var(--font-mono); }
        .env-col { font-size: 12px; color: var(--text-muted); font-weight: 500; }
        .message-col { font-size: 13px; max-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .expand-icon { display: inline-flex; align-items: center; justify-content: center; width: 18px; height: 18px; transition: transform 0.2s ease; color: var(--text-light); font-size: 8px; border-radius: 4px; }
        .log-row:hover .expand-icon { background: var(--primary-light); color: var(--primary); }
        .log-row.open .expand-icon { transform: rotate(90deg); background: var(--primary-light); color: var(--primary); }

        /* Detail rows */
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

        /* Pagination */
        .pagination-bar { display: flex; align-items: center; justify-content: space-between; padding: 10px 16px; border-top: 1px solid var(--border); background: var(--bg-card); font-size: 12px; color: var(--text-muted); flex-shrink: 0; }
        .page-links { display: flex; gap: 3px; }
        .page-links a, .page-links span { padding: 5px 11px; border: 1px solid var(--border); border-radius: var(--radius-sm); text-decoration: none; color: var(--text); font-size: 12px; font-weight: 500; transition: all 0.2s; }
        .page-links a:hover { background: var(--primary-light); border-color: var(--primary); color: var(--primary); }
        .page-links span.current { background: var(--primary); color: white; border-color: var(--primary); }
        .page-links span.disabled { opacity: 0.4; cursor: default; }
        .per-page-select { padding: 5px 10px; border: 1px solid var(--border); border-radius: var(--radius-sm); font-size: 12px; background: var(--bg); color: var(--text); cursor: pointer; }

        /* Highlight */
        mark { background: #fef08a; color: #854d0e; border-radius: 2px; padding: 0 2px; }
        [data-theme="dark"] mark { background: #854d0e; color: #fef08a; }

        /* Empty / Too large */
        .empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: var(--text-light); gap: 12px; padding: 40px; }
        .empty-state svg { width: 56px; height: 56px; opacity: 0.2; stroke-width: 1; }
        .empty-state p { font-size: 14px; font-weight: 500; }
        .too-large { text-align: center; padding: 40px; color: var(--warning-text); background: var(--warning-bg); margin: 16px; border-radius: var(--radius); border: 1px solid var(--warning-border); font-weight: 500; }

        /* Review */
        .review-btn { background: none; border: 1px solid var(--border); border-radius: 4px; padding: 4px 6px; cursor: pointer; font-size: 11px; color: var(--text-light); transition: all 0.2s; line-height: 1; display: inline-flex; align-items: center; justify-content: center; min-width: 28px; min-height: 28px; }
        .review-btn:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-light); }
        .review-btn.reviewed { background: var(--success-bg); color: var(--success-text); border-color: var(--debug-border); }
        .review-btn.in-progress { background: var(--warning-bg); color: var(--warning-text); border-color: var(--warning-border); }
        .review-btn.wont-fix { background: var(--danger-bg); color: var(--danger-text); border-color: var(--danger-border); }
        .review-indicator { display: inline-flex; align-items: center; gap: 4px; font-size: 10px; color: var(--success-text); font-weight: 600; }
        .muted-indicator { display: inline-flex; align-items: center; gap: 4px; font-size: 10px; color: var(--warning-text); font-weight: 600; }
        .log-row.is-muted td { opacity: 0.6; }
        .review-note-text { font-size: 11px; color: var(--text-muted); font-style: italic; margin-top: 2px; }
        .review-actions { display: flex; gap: 4px; align-items: center; white-space: nowrap; }
        .review-filter { display: flex; gap: 3px; }
        .review-filter-btn { padding: 4px 10px; border: 1px solid var(--border); border-radius: 20px; font-size: 11px; cursor: pointer; background: transparent; color: var(--text-muted); text-decoration: none; font-weight: 600; transition: all 0.2s; }
        .review-filter-btn:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-light); }
        .review-filter-btn.active { background: var(--primary); color: white; border-color: var(--primary); }

        /* Mute */
        .mute-btn { background: none; border: 1px solid var(--border); border-radius: 4px; padding: 4px 6px; cursor: pointer; font-size: 11px; color: var(--text-light); transition: all 0.2s; line-height: 1; display: inline-flex; align-items: center; justify-content: center; min-width: 28px; min-height: 28px; }
        .mute-btn:hover { border-color: var(--warning-text); color: var(--warning-text); background: var(--warning-bg); }
        .mute-dropdown { position: fixed; background-color: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-sm); box-shadow: 0 4px 24px rgba(0,0,0,0.25); z-index: 9999; min-width: 220px; padding: 8px 0; display: none; isolation: isolate; }
        [data-theme="dark"] .mute-dropdown { box-shadow: 0 4px 24px rgba(0,0,0,0.6); }
        .mute-dropdown.open { display: block; }
        .mute-dropdown-item { display: block; width: 100%; padding: 6px 14px; font-size: 12px; text-align: left; cursor: pointer; background: none; border: none; color: var(--text); transition: background 0.15s; }
        .mute-dropdown-item:hover { background: var(--hover); }
        .mute-dropdown-separator { height: 1px; background: var(--border); margin: 4px 0; }
        .mute-dropdown-header { padding: 4px 14px; font-size: 10px; text-transform: uppercase; color: var(--text-light); font-weight: 700; letter-spacing: 0.05em; }

        /* Modal */
        .modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; display: none; align-items: center; justify-content: center; }
        .modal-overlay.open { display: flex; }
        .modal { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-lg); box-shadow: var(--shadow-lg); width: 420px; max-width: 90vw; padding: 24px; }
        .modal h3 { font-size: 16px; font-weight: 700; margin-bottom: 16px; }
        .modal-field { margin-bottom: 12px; }
        .modal-field label { display: block; font-size: 12px; font-weight: 600; color: var(--text-muted); margin-bottom: 4px; }
        .modal-field input, .modal-field textarea, .modal-field select { width: 100%; padding: 8px 12px; border: 1px solid var(--border); border-radius: var(--radius-sm); font-size: 13px; background: var(--bg); color: var(--text); font-family: inherit; }
        .modal-field textarea { resize: vertical; min-height: 60px; }
        .modal-field input:focus, .modal-field textarea:focus, .modal-field select:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px var(--primary-glow); }
        .modal-actions { display: flex; gap: 8px; justify-content: flex-end; margin-top: 16px; }

        @media (max-width: 768px) {
            .main-layout { flex-direction: column; }
            .sidebar { width: 100%; max-height: 160px; border-right: none; border-bottom: 1px solid var(--border); }
            .filters-bar { flex-direction: column; align-items: stretch; }
            .actions-bar { margin-left: 0; }
        }
    </style>
</head>
<body>
<div class="layout" id="app">
    @include('logman::partials.nav')

    <div class="main-layout">
        {{-- Sidebar --}}
        <aside class="sidebar">
            <div class="sidebar-header">
                <h3>Files ({{ $files->count() }})</h3>
                <button class="btn btn-sm btn-danger" onclick="openClearAllModal()" title="Clear all: files, mutes, throttles & bookmarks">
                    Clear All
                </button>
            </div>
            <div class="sidebar-tools">
                <input type="text" class="sidebar-search" placeholder="Filter files..." oninput="filterFiles(this.value)">
                <div style="display:flex;gap:6px;align-items:center;">
                    <label class="select-all-wrap">
                        <input type="checkbox" onchange="toggleSelectAll(this.checked)">
                        All
                    </label>
                    <button class="btn btn-sm btn-danger" onclick="deleteSelected()" title="Delete selected" id="deleteSelectedBtn" style="display:none;padding:2px 6px;font-size:11px;">
                        Delete
                    </button>
                </div>
            </div>
            <div class="file-list">
                @forelse($files as $file)
                    <div class="file-entry {{ $selectedFile === $file['name'] ? 'is-active' : '' }}">
                        <div class="file-check-wrap">
                            <input type="checkbox" class="file-check" value="{{ $file['name'] }}" onchange="updateDeleteBtn()">
                        </div>
                        <a href="{{ route('logman.index', ['file' => $file['name']]) }}" class="file-item">
                            <div class="file-info">
                                <div class="file-name" title="{{ $file['name'] }}">{{ $file['name'] }}</div>
                                <div class="file-meta">
                                    <span>{{ $file['size_formatted'] }}</span>
                                    <span>{{ $file['modified_human'] }}</span>
                                </div>
                            </div>
                        </a>
                    </div>
                @empty
                    <div style="padding: 24px; text-align: center; color: var(--text-light); font-size: 13px;">
                        No log files found
                    </div>
                @endforelse
            </div>
        </aside>

        {{-- Main Content --}}
        <main class="main">
            @if($selectedFile)
                {{-- Toolbar --}}
                <div class="toolbar">
                    <form method="GET" action="{{ route('logman.index') }}" id="filterForm" style="display:contents;">
                        <input type="hidden" name="file" value="{{ $selectedFile }}">
                        <input type="hidden" name="level" value="{{ $currentLevel }}" id="levelInput">
                        <input type="hidden" name="sort" value="{{ $sortDirection }}">
                        <input type="hidden" name="per_page" value="{{ $perPage }}">
                        <input type="hidden" name="regex" value="{{ $isRegex ? '1' : '0' }}" id="regexInput">
                        @if($reviewFilter)<input type="hidden" name="review" value="{{ $reviewFilter }}">@endif
                        @if($dateFrom)<input type="hidden" name="date_from" value="{{ $dateFrom }}">@endif
                        @if($dateTo)<input type="hidden" name="date_to" value="{{ $dateTo }}">@endif
                        @if($timeFrom)<input type="hidden" name="time_from" value="{{ $timeFrom }}">@endif
                        @if($timeTo)<input type="hidden" name="time_to" value="{{ $timeTo }}">@endif

                        <div class="search-group" style="position:relative;">
                            <input type="text" name="search" class="search-box" id="searchBox"
                                   placeholder="{{ $isRegex ? 'Regex pattern...' : 'Search logs...' }}"
                                   value="{{ $search }}" autocomplete="off"
                                   onfocus="showSearchHistory()" oninput="filterSearchHistory(this.value)">
                            <button type="button" class="regex-toggle {{ $isRegex ? 'active' : '' }}" onclick="toggleRegex()" title="Toggle regex search">.*</button>
                            <div class="search-history" id="searchHistory"></div>
                        </div>
                    </form>

                    <div class="actions-bar">
                        <a href="{{ route('logman.download', ['file' => $selectedFile]) }}" class="btn btn-sm" title="Download">
                            {!! '&#8615;' !!} Download
                        </a>
                        <form method="POST" action="{{ route('logman.clear-cache') }}" style="display:inline;">
                            @csrf
                            <input type="hidden" name="file" value="{{ $selectedFile }}">
                            <button type="submit" class="btn btn-sm" title="Clear cache">{!! '&#8635;' !!} Cache</button>
                        </form>
                        <form method="POST" action="{{ route('logman.clear') }}" style="display:inline;" onsubmit="return confirm('Clear this log file?')">
                            @csrf
                            <input type="hidden" name="file" value="{{ $selectedFile }}">
                            <button type="submit" class="btn btn-sm btn-danger">Clear</button>
                        </form>
                        <form method="POST" action="{{ route('logman.delete') }}" style="display:inline;" onsubmit="return confirm('Delete this log file permanently?')">
                            @csrf
                            <input type="hidden" name="file" value="{{ $selectedFile }}">
                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </div>
                </div>

                {{-- Filters --}}
                <div class="filters-bar">
                    <div class="level-filters">
                        @php $levels = ['all','emergency','alert','critical','error','warning','notice','info','debug']; @endphp
                        @foreach($levels as $lvl)
                            <button type="button" onclick="setLevel('{{ $lvl }}')"
                                    class="level-btn {{ $currentLevel === $lvl ? 'active' : '' }}">
                                {{ ucfirst($lvl) }}
                                @if($lvl !== 'all' && isset($levelCounts[$lvl]))
                                    <span class="count">({{ $levelCounts[$lvl] }})</span>
                                @elseif($lvl === 'all')
                                    <span class="count">({{ array_sum($levelCounts) }})</span>
                                @endif
                            </button>
                        @endforeach
                    </div>

                    <div class="filters-spacer"></div>

                    @if($hasMultipleDates)
                        <div class="date-filter">
                            <label>Date</label>
                            <input type="date" value="{{ $dateFrom }}" onchange="setDateFilter(this, 'date_from')">
                            <label>-</label>
                            <input type="date" value="{{ $dateTo }}" onchange="setDateFilter(this, 'date_to')">
                        </div>
                    @endif

                    <div class="date-filter">
                        <label>Time</label>
                        <input type="time" step="1" value="{{ $timeFrom }}" onchange="setDateFilter(this, 'time_from')">
                        <label>-</label>
                        <input type="time" step="1" value="{{ $timeTo }}" onchange="setDateFilter(this, 'time_to')">
                        @if($timeFrom || $timeTo)
                            <button type="button" class="clear-filter-btn" onclick="clearFilters(['time_from','time_to'])" title="Clear time filter">&times;</button>
                        @endif
                    </div>

                    <a href="{{ route('logman.index', array_merge(request()->query(), ['sort' => $sortDirection === 'desc' ? 'asc' : 'desc'])) }}"
                       class="sort-toggle" title="Toggle sort direction">
                        {{ $sortDirection === 'desc' ? 'Newest' : 'Oldest' }}
                        {!! $sortDirection === 'desc' ? '&#8595;' : '&#8593;' !!}
                    </a>
                </div>

                {{-- Review & Mute Filters --}}
                <div class="filters-bar" style="padding:6px 16px;">
                    <div class="review-filter">
                        @php
                            $reviewOptions = [null => 'All', 'reviewed' => 'Reviewed', 'unreviewed' => 'Unreviewed'];
                            $reviewStatuses = ['reviewed' => 'Reviewed', 'in_progress' => 'In Progress', 'wont_fix' => "Won't Fix"];
                        @endphp
                        @foreach($reviewOptions as $val => $label)
                            @php
                                $isActive = $val === ''
                                    ? empty($reviewFilter)
                                    : ($reviewFilter === $val && empty(request('review_status')));
                            @endphp
                            <a href="{{ route('logman.index', array_merge(request()->query(), ['review' => $val, 'review_status' => null, 'page' => 1])) }}"
                               class="review-filter-btn {{ $isActive ? 'active' : '' }}">
                                {{ $label }}
                            </a>
                        @endforeach
                        @if(($reviewFilter ?? null) === 'reviewed')
                            <span style="color:var(--border);margin:0 2px;">|</span>
                            @foreach($reviewStatuses as $sVal => $sLabel)
                                <a href="{{ route('logman.index', array_merge(request()->query(), ['review' => 'reviewed', 'review_status' => $sVal, 'page' => 1])) }}"
                                   class="review-filter-btn {{ request('review_status') === $sVal ? 'active' : '' }}">
                                    {{ $sLabel }}
                                </a>
                            @endforeach
                        @endif
                    </div>

                    <div class="filters-spacer"></div>

                    <div class="review-filter">
                        @php
                            $muteOptions = [null => 'All', 'muted' => 'Muted', 'unmuted' => 'Unmuted'];
                        @endphp
                        @foreach($muteOptions as $mVal => $mLabel)
                            @php
                                $isMuteActive = $mVal === '' ? empty($muteFilter) : ($muteFilter === $mVal);
                            @endphp
                            <a href="{{ route('logman.index', array_merge(request()->query(), ['mute_filter' => $mVal, 'page' => 1])) }}"
                               class="review-filter-btn {{ $isMuteActive ? 'active' : '' }}">
                                {{ $mLabel }}
                            </a>
                        @endforeach
                    </div>

                    <span style="color:var(--border);margin:0 4px;">|</span>

                    <div class="review-filter">
                        @php
                            $bookmarkFilter = request('bookmark_filter');
                            $bmOptions = [null => 'All', 'bookmarked' => 'Bookmarked', 'not_bookmarked' => 'Not Bookmarked'];
                        @endphp
                        @foreach($bmOptions as $bmVal => $bmLabel)
                            @php $isBmActive = $bmVal === '' ? empty($bookmarkFilter) : ($bookmarkFilter === $bmVal); @endphp
                            <a href="{{ route('logman.index', array_merge(request()->query(), ['bookmark_filter' => $bmVal, 'page' => 1])) }}"
                               class="review-filter-btn {{ $isBmActive ? 'active' : '' }}">
                                {{ $bmLabel }}
                            </a>
                        @endforeach
                    </div>
                </div>

                @if(session('success'))
                    <div class="flash flash-success"><span>{{ session('success') }}</span><button class="flash-dismiss" onclick="this.parentElement.remove()" title="Dismiss">&times;</button></div>
                @endif

                @if($tooLarge)
                    <div class="too-large">
                        This file is too large to display. You can download it instead.
                    </div>
                @else
                    {{-- Log Table --}}
                    <div class="content" id="logContent">
                        @if($entries && count($entries) > 0)
                            <table class="log-table">
                                <thead>
                                    <tr>
                                        <th style="width:30px"></th>
                                        <th style="width:85px">Level</th>
                                        <th style="width:60px">Env</th>
                                        <th style="width:165px">Date</th>
                                        <th>Message</th>
                                        <th style="width:120px">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($entries as $i => $entry)
                                        @php $hasDetails = $entry['stack'] || $entry['context']; @endphp
                                        <tr class="log-row level-{{ $entry['level_class'] }} {{ !$hasDetails ? 'no-details' : '' }} {{ !empty($entry['is_muted']) ? 'is-muted' : '' }}" @if($hasDetails) onclick="toggleDetail({{ $i }}, this)" @endif data-index="{{ $i }}">
                                            <td>@if($hasDetails)<span class="expand-icon">&#9654;</span>@endif</td>
                                            <td><span class="badge badge-{{ $entry['level_class'] }}">{{ $entry['level'] }}</span></td>
                                            <td class="env-col">{{ $entry['env'] }}</td>
                                            <td class="date-col">{{ $entry['date'] }}</td>
                                            <td class="message-col">
                                                {!! $search ? highlightSearch(e($entry['message']), $search, $isRegex) : e($entry['message']) !!}
                                                @if(!empty($entry['reviewed']))
                                                    <span class="review-indicator">
                                                        &#10003; {{ ucfirst($entry['review_status'] ?? 'reviewed') }}
                                                    </span>
                                                @endif
                                                @if(!empty($entry['review_note']))
                                                    <div class="review-note-text">{{ $entry['review_note'] }}</div>
                                                @endif
                                                @if(!empty($entry['is_muted']))
                                                    <span class="muted-indicator"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><line x1="23" y1="9" x2="17" y2="15"/><line x1="17" y1="9" x2="23" y2="15"/></svg> Muted</span>
                                                @endif
                                                @if(!empty($entry['is_throttled']))
                                                    <span class="review-indicator" style="color:var(--info-text);"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> {{ $entry['throttle_info']['current_hits'] }}/{{ $entry['throttle_info']['max_hits'] }}</span>
                                                @endif
                                            </td>
                                            <td onclick="event.stopPropagation()">
                                                <div class="review-actions">
                                                    {{-- Send to Channel --}}
                                                    @if(!empty($enabledChannels))
                                                        <div style="position:relative;display:inline-block;">
                                                            <button class="mute-btn" onclick="toggleMuteDropdown(this)" title="Send to channel"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2L11 13"/><path d="M22 2L15 22L11 13L2 9L22 2Z"/></svg></button>
                                                            <div class="mute-dropdown">
                                                                <div class="mute-dropdown-header">Send to Channel</div>
                                                                @foreach($enabledChannels as $ch)
                                                                    <form method="POST" action="{{ route('logman.send-to-channel') }}" style="display:contents;">
                                                                        @csrf
                                                                        <input type="hidden" name="file" value="{{ $selectedFile }}">
                                                                        <input type="hidden" name="hash" value="{{ $entry['hash'] }}">
                                                                        <input type="hidden" name="channel" value="{{ $ch }}">
                                                                        <button type="submit" class="mute-dropdown-item">{{ ucfirst($ch) }}</button>
                                                                    </form>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    @endif
                                                    {{-- Bookmark --}}
                                                    @if(isset($bookmarkedHashes[$entry['hash']]))
                                                        <form method="POST" action="{{ route('logman.unbookmark') }}" style="display:inline;">
                                                            @csrf
                                                            <input type="hidden" name="id" value="{{ $bookmarkedHashes[$entry['hash']] }}">
                                                            <button type="submit" class="mute-btn" style="color:var(--warning-text);border-color:var(--warning-border);background:var(--warning-bg);" title="Remove bookmark"><svg width="14" height="14" viewBox="0 0 24 24" fill="var(--warning-text)" stroke="var(--warning-text)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg></button>
                                                        </form>
                                                    @else
                                                        <form method="POST" action="{{ route('logman.bookmark') }}" style="display:inline;">
                                                            @csrf
                                                            <input type="hidden" name="file" value="{{ $selectedFile }}">
                                                            <input type="hidden" name="hash" value="{{ $entry['hash'] }}">
                                                            <button type="submit" class="mute-btn" title="Bookmark"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg></button>
                                                        </form>
                                                    @endif
                                                    @if(!empty($entry['reviewed']))
                                                        @php
                                                            $statusLabels = ['reviewed' => 'Reviewed', 'in_progress' => 'In Progress', 'wont_fix' => "Won't Fix"];
                                                            $currentStatus = $entry['review_status'] ?? 'reviewed';
                                                            $statusClass = str_replace('_', '-', $currentStatus);
                                                        @endphp
                                                        <button class="review-btn {{ $statusClass }}" onclick="openReviewModal('{{ $selectedFile }}', '{{ $entry['hash'] }}', '{{ $currentStatus }}', '{{ addslashes($entry['review_note'] ?? '') }}', true)" title="{{ $statusLabels[$currentStatus] ?? 'Reviewed' }}{{ $entry['review_note'] ? ': ' . $entry['review_note'] : '' }}"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></button>
                                                    @else
                                                        <button class="review-btn" onclick="openReviewModal('{{ $selectedFile }}', '{{ $entry['hash'] }}')" title="Mark as Reviewed"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></button>
                                                    @endif
                                                    @if(in_array($entry['level'], ['emergency','alert','critical','error','warning']))
                                                        @php $muteClass = $entry['exception_class'] ?: ($entry['exception_message'] ?? $entry['message']); @endphp
                                                        <div style="position:relative;display:inline-block;">
                                                            @if(!empty($entry['is_muted']))
                                                                <button class="mute-btn" style="color:var(--danger-text);border-color:var(--danger-border);background:var(--danger-bg);" onclick="toggleMuteDropdown(this)" title="Muted"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><line x1="23" y1="9" x2="17" y2="15"/><line x1="17" y1="9" x2="23" y2="15"/></svg></button>
                                                                <div class="mute-dropdown" style="min-width:260px;">
                                                                    <div class="mute-dropdown-header">Muted</div>
                                                                    <div style="padding:6px 14px;font-size:12px;color:var(--text-muted);">
                                                                        <div><strong>Exception:</strong> <span style="font-family:var(--font-mono);font-size:11px;">{{ $entry['mute_info']['exception_class'] }}</span></div>
                                                                        @if(!empty($entry['mute_info']['message_pattern']))
                                                                            <div style="margin-top:2px;"><strong>Pattern:</strong> {{ $entry['mute_info']['message_pattern'] }}</div>
                                                                        @endif
                                                                        <div style="margin-top:2px;"><strong>Expires:</strong> {{ \Carbon\Carbon::parse($entry['mute_info']['muted_until'])->diffForHumans() }}</div>
                                                                        <div style="margin-top:2px;"><strong>Hits blocked:</strong> {{ $entry['mute_info']['hit_count'] ?? 0 }}</div>
                                                                        @if(!empty($entry['mute_info']['reason']))
                                                                            <div style="margin-top:2px;"><strong>Reason:</strong> {{ $entry['mute_info']['reason'] }}</div>
                                                                        @endif
                                                                    </div>
                                                                    <div class="mute-dropdown-separator"></div>
                                                                    <form method="POST" action="{{ route('logman.unmute') }}" style="display:contents;">
                                                                        @csrf
                                                                        <input type="hidden" name="id" value="{{ $entry['mute_info']['id'] }}">
                                                                        <button type="submit" class="mute-dropdown-item" style="color:var(--danger-text);font-weight:600;">Unmute</button>
                                                                    </form>
                                                                </div>
                                                            @else
                                                                <button class="mute-btn" onclick="toggleMuteDropdown(this)" title="Mute this error"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"/></svg></button>
                                                                <div class="mute-dropdown">
                                                                    <div class="mute-dropdown-header">Mute Notifications</div>
                                                                    @foreach(['1h' => '1 Hour', '6h' => '6 Hours', '1d' => '1 Day', '3d' => '3 Days', '1w' => '1 Week'] as $dur => $durLabel)
                                                                        <form method="POST" action="{{ route('logman.mute') }}" style="display:contents;">
                                                                            @csrf
                                                                            <input type="hidden" name="exception_class" value="{{ $muteClass }}">
                                                                            <input type="hidden" name="message_pattern" value="">
                                                                            <input type="hidden" name="duration" value="{{ $dur }}">
                                                                            <button type="submit" class="mute-dropdown-item">{{ $durLabel }}</button>
                                                                        </form>
                                                                    @endforeach
                                                                    <div class="mute-dropdown-separator"></div>
                                                                    <button class="mute-dropdown-item" onclick="openMuteModal('{{ addslashes($muteClass) }}')">Custom...</button>
                                                                </div>
                                                            @endif
                                                        </div>
                                                        {{-- Throttle --}}
                                                        <div style="position:relative;display:inline-block;">
                                                            @if(!empty($entry['is_throttled']))
                                                                <button class="mute-btn" style="color:var(--info-text);border-color:var(--info-border);background:var(--info-bg);" onclick="toggleMuteDropdown(this)" title="Throttled: {{ $entry['throttle_info']['current_hits'] }}/{{ $entry['throttle_info']['max_hits'] }}"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></button>
                                                                <div class="mute-dropdown" style="min-width:260px;">
                                                                    <div class="mute-dropdown-header">Throttled</div>
                                                                    <div style="padding:6px 14px;font-size:12px;color:var(--text-muted);">
                                                                        <div><strong>Exception:</strong> <span style="font-family:var(--font-mono);font-size:11px;">{{ $entry['throttle_info']['exception_class'] }}</span></div>
                                                                        <div style="margin-top:2px;"><strong>Limit:</strong> {{ $entry['throttle_info']['max_hits'] }} per {{ $entry['throttle_info']['period'] }}</div>
                                                                        <div style="margin-top:2px;"><strong>Used:</strong> {{ $entry['throttle_info']['current_hits'] }} / {{ $entry['throttle_info']['max_hits'] }}</div>
                                                                        @if(!empty($entry['throttle_info']['reason']))
                                                                            <div style="margin-top:2px;"><strong>Reason:</strong> {{ $entry['throttle_info']['reason'] }}</div>
                                                                        @endif
                                                                    </div>
                                                                    <div class="mute-dropdown-separator"></div>
                                                                    <form method="POST" action="{{ route('logman.unthrottle') }}" style="display:contents;">
                                                                        @csrf
                                                                        <input type="hidden" name="id" value="{{ $entry['throttle_info']['id'] }}">
                                                                        <button type="submit" class="mute-dropdown-item" style="color:var(--danger-text);font-weight:600;">Remove Throttle</button>
                                                                    </form>
                                                                </div>
                                                            @else
                                                                <button class="mute-btn" onclick="openThrottleModal('{{ addslashes($muteClass) }}')" title="Set throttle limit"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></button>
                                                            @endif
                                                        </div>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                        @if($hasDetails)
                                            <tr class="detail-row" id="detail-{{ $i }}">
                                                <td colspan="6">
                                                    @include('logman::partials._entry-detail', ['entry' => $entry, 'i' => $i, 'search' => $search, 'isRegex' => $isRegex])
                                                </td>
                                            </tr>
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            <div class="empty-state">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                <p>No log entries found</p>
                                @if($search || ($currentLevel && $currentLevel !== 'all'))
                                    <a href="{{ route('logman.index', ['file' => $selectedFile]) }}" class="btn btn-sm">Clear Filters</a>
                                @endif
                            </div>
                        @endif
                    </div>

                    {{-- Pagination --}}
                    @if($entries && $entries->hasPages())
                        <div class="pagination-bar">
                            <div>
                                Showing {{ $entries->firstItem() }}-{{ $entries->lastItem() }} of {{ number_format($entries->total()) }}
                            </div>
                            <div class="page-links">
                                @if($entries->onFirstPage())
                                    <span class="disabled">{!! '&#8592;' !!}</span>
                                @else
                                    <a href="{{ $entries->previousPageUrl() }}">{!! '&#8592;' !!}</a>
                                @endif

                                @foreach($entries->getUrlRange(max(1, $entries->currentPage()-2), min($entries->lastPage(), $entries->currentPage()+2)) as $p => $url)
                                    @if($p == $entries->currentPage())
                                        <span class="current">{{ $p }}</span>
                                    @else
                                        <a href="{{ $url }}">{{ $p }}</a>
                                    @endif
                                @endforeach

                                @if($entries->hasMorePages())
                                    <a href="{{ $entries->nextPageUrl() }}">{!! '&#8594;' !!}</a>
                                @else
                                    <span class="disabled">{!! '&#8594;' !!}</span>
                                @endif
                            </div>
                            <select class="per-page-select" onchange="setPerPage(this.value)">
                                @foreach(config('logman.viewer.per_page_options', [15,25,50,100]) as $opt)
                                    <option value="{{ $opt }}" {{ $perPage == $opt ? 'selected' : '' }}>{{ $opt }} / page</option>
                                @endforeach
                            </select>
                        </div>
                    @elseif($entries)
                        <div class="pagination-bar">
                            <span>{{ number_format($entries->total()) }} entries</span>
                            <span>{{ $selectedFile }}</span>
                            <select class="per-page-select" onchange="setPerPage(this.value)">
                                @foreach(config('logman.viewer.per_page_options', [15,25,50,100]) as $opt)
                                    <option value="{{ $opt }}" {{ $perPage == $opt ? 'selected' : '' }}>{{ $opt }} / page</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                @endif
            @else
                <div class="empty-state">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                    <p>Select a log file to view</p>
                </div>
            @endif
        </main>
    </div>
</div>

<script>
@include('logman::partials.theme-js')

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
        ta.value = text;
        ta.style.cssText = 'position:fixed;left:-9999px;';
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        showCopied(btn);
    }
}

function showCopied(btn) {
    const orig = btn.textContent;
    btn.textContent = 'Copied!';
    btn.style.background = 'var(--success-bg)';
    btn.style.color = 'var(--success-text)';
    btn.style.borderColor = 'var(--debug-border)';
    setTimeout(() => { btn.textContent = orig; btn.style.background = ''; btn.style.color = ''; btn.style.borderColor = ''; }, 1500);
}

function setLevel(level) {
    document.getElementById('levelInput').value = level;
    document.getElementById('filterForm').submit();
}

function toggleRegex() {
    const input = document.getElementById('regexInput');
    input.value = input.value === '1' ? '0' : '1';
    document.querySelector('.regex-toggle').classList.toggle('active');
}

function setDateFilter(el, name) {
    const form = document.getElementById('filterForm');
    let hidden = form.querySelector('input[name="' + name + '"]');
    if (!hidden) {
        hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = name;
        form.appendChild(hidden);
    }
    hidden.value = el.value;
    form.submit();
}

function clearFilters(names) {
    const url = new URL(window.location);
    names.forEach(n => url.searchParams.delete(n));
    url.searchParams.set('page', '1');
    window.location = url;
}

function setPerPage(val) {
    const url = new URL(window.location);
    url.searchParams.set('per_page', val);
    url.searchParams.set('page', '1');
    window.location = url;
}

document.querySelector('.search-box')?.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        saveSearchHistory(this.value);
        document.getElementById('filterForm').submit();
    }
});

// ─── Search History ───────────────────────────────────────
const SEARCH_HISTORY_KEY = 'logman-search-history';
const SEARCH_HISTORY_MAX = 10;

function getSearchHistory() {
    try { return JSON.parse(localStorage.getItem(SEARCH_HISTORY_KEY)) || []; }
    catch { return []; }
}

function saveSearchHistory(query) {
    if (!query || !query.trim()) return;
    query = query.trim();
    let history = getSearchHistory().filter(h => h !== query);
    history.unshift(query);
    if (history.length > SEARCH_HISTORY_MAX) history = history.slice(0, SEARCH_HISTORY_MAX);
    localStorage.setItem(SEARCH_HISTORY_KEY, JSON.stringify(history));
}

function removeSearchHistoryItem(query, e) {
    e.stopPropagation();
    let history = getSearchHistory().filter(h => h !== query);
    localStorage.setItem(SEARCH_HISTORY_KEY, JSON.stringify(history));
    renderSearchHistory(history);
}

function showSearchHistory() {
    const history = getSearchHistory();
    renderSearchHistory(history);
}

function filterSearchHistory(value) {
    const history = getSearchHistory();
    if (!value.trim()) { renderSearchHistory(history); return; }
    const q = value.toLowerCase();
    renderSearchHistory(history.filter(h => h.toLowerCase().includes(q)));
}

function renderSearchHistory(items) {
    const el = document.getElementById('searchHistory');
    if (!items.length) { el.classList.remove('open'); return; }
    el.innerHTML = items.map(item =>
        `<div class="search-history-item" onclick="selectSearchHistory('${item.replace(/'/g, "\\'")}')">
            <span class="sh-text">${item.replace(/</g,'&lt;')}</span>
            <span class="sh-remove" onclick="removeSearchHistoryItem('${item.replace(/'/g, "\\'")}', event)" title="Remove">&times;</span>
        </div>`
    ).join('');
    el.classList.add('open');
}

function selectSearchHistory(query) {
    const box = document.getElementById('searchBox');
    box.value = query;
    document.getElementById('searchHistory').classList.remove('open');
    saveSearchHistory(query);
    document.getElementById('filterForm').submit();
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.search-group')) {
        document.getElementById('searchHistory')?.classList.remove('open');
    }
});

function updateDeleteBtn() {
    const checked = document.querySelectorAll('.file-check:checked');
    document.getElementById('deleteSelectedBtn').style.display = checked.length > 0 ? 'inline-flex' : 'none';
}

function deleteSelected() {
    const checked = document.querySelectorAll('.file-check:checked');
    if (checked.length === 0) return;
    if (!confirm('Delete ' + checked.length + ' file(s)?')) return;
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '{{ route("logman.delete-multiple") }}';
    const csrf = document.createElement('input');
    csrf.type = 'hidden'; csrf.name = '_token'; csrf.value = '{{ csrf_token() }}';
    form.appendChild(csrf);
    checked.forEach(cb => {
        const input = document.createElement('input');
        input.type = 'hidden'; input.name = 'files[]'; input.value = cb.value;
        form.appendChild(input);
    });
    document.body.appendChild(form);
    form.submit();
}

// Sidebar: filter files by name
function filterFiles(query) {
    const q = query.toLowerCase();
    document.querySelectorAll('.file-entry').forEach(entry => {
        const name = entry.querySelector('.file-name')?.textContent.toLowerCase() || '';
        entry.style.display = name.includes(q) ? '' : 'none';
    });
}

// Sidebar: select/deselect all visible checkboxes
function toggleSelectAll(checked) {
    document.querySelectorAll('.file-entry').forEach(entry => {
        if (entry.style.display !== 'none') {
            const cb = entry.querySelector('.file-check');
            if (cb) cb.checked = checked;
        }
    });
    updateDeleteBtn();
}

// Detail panel: search within content
const detailSearchState = {};

function searchInDetail(input, index) {
    const query = input.value.trim().toLowerCase();
    const panel = document.getElementById('detail-' + index);
    if (!panel) return;

    // Get the active pane's content element
    const activePane = panel.querySelector('.detail-pane.active');
    if (!activePane) return;
    const contentEl = activePane.querySelector('.stack-content, .context-content');
    if (!contentEl) return;

    // Clear previous highlights
    if (!detailSearchState[index]) detailSearchState[index] = { originals: {} };
    const state = detailSearchState[index];

    // Store original text if not yet stored for this pane
    const paneId = activePane.id;
    if (!state.originals[paneId]) {
        state.originals[paneId] = contentEl.textContent;
    }

    if (!query) {
        contentEl.innerHTML = escapeHtml(state.originals[paneId]);
        document.getElementById('detail-search-info-' + index).textContent = '';
        state.matches = [];
        state.current = -1;
        return;
    }

    const text = state.originals[paneId];
    const escaped = escapeRegex(query);
    const regex = new RegExp('(' + escaped + ')', 'gi');
    let matchCount = 0;
    const highlighted = escapeHtml(text).replace(new RegExp('(' + escapeRegex(escapeHtml(query)) + ')', 'gi'), function(m) {
        matchCount++;
        return '<span class="detail-highlight" data-match="' + matchCount + '">' + m + '</span>';
    });

    contentEl.innerHTML = highlighted;
    state.matches = contentEl.querySelectorAll('.detail-highlight');
    state.current = state.matches.length > 0 ? 0 : -1;

    const info = document.getElementById('detail-search-info-' + index);
    if (state.matches.length > 0) {
        info.textContent = '1 / ' + state.matches.length;
        state.matches[0].classList.add('current');
        state.matches[0].scrollIntoView({ block: 'nearest' });
    } else {
        info.textContent = 'No results';
    }
}

function detailSearchNav(index, direction) {
    const state = detailSearchState[index];
    if (!state || !state.matches || state.matches.length === 0) return;

    state.matches[state.current]?.classList.remove('current');
    state.current = (state.current + direction + state.matches.length) % state.matches.length;
    state.matches[state.current].classList.add('current');
    state.matches[state.current].scrollIntoView({ block: 'nearest' });

    document.getElementById('detail-search-info-' + index).textContent =
        (state.current + 1) + ' / ' + state.matches.length;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function escapeRegex(str) {
    return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

// ─── Review Modal ──────────────────────────────────────────
function openReviewModal(file, hash, status, note, isReviewed) {
    const modal = document.getElementById('reviewModal');
    modal.querySelector('#reviewForm [name="file"]').value = file;
    modal.querySelector('#reviewForm [name="hash"]').value = hash;
    modal.querySelector('[name="status"]').value = status || 'reviewed';
    modal.querySelector('[name="note"]').value = note || '';

    const unreviewBtn = document.getElementById('unreviewBtn');
    const unreviewForm = document.getElementById('unreviewForm');
    if (isReviewed) {
        unreviewBtn.style.display = '';
        unreviewForm.querySelector('[name="file"]').value = file;
        unreviewForm.querySelector('[name="hash"]').value = hash;
    } else {
        unreviewBtn.style.display = 'none';
    }

    modal.classList.add('open');
}

function submitUnreview() {
    document.getElementById('unreviewForm').submit();
}

function closeReviewModal() {
    document.getElementById('reviewModal').classList.remove('open');
}

// ─── Mute Dropdown ─────────────────────────────────────────
function toggleMuteDropdown(btn) {
    // Check if this button already has an open dropdown (moved to body)
    let dropdown = btn._openDropdown || btn.parentElement.querySelector('.mute-dropdown');
    if (!dropdown) return;

    // Close all other open dropdowns first
    document.querySelectorAll('.mute-dropdown.open').forEach(d => {
        if (d !== dropdown) {
            d.classList.remove('open');
            if (d._muteBtn) {
                d._muteBtn.parentElement.appendChild(d);
                d._muteBtn._openDropdown = null;
            }
        }
    });

    if (!dropdown.classList.contains('open')) {
        // Move dropdown to body so it's not clipped by overflow
        document.body.appendChild(dropdown);
        dropdown._muteBtn = btn;
        btn._openDropdown = dropdown;
        dropdown.classList.add('open');

        const rect = btn.getBoundingClientRect();
        const dw = dropdown.offsetWidth;
        let left = rect.left;
        if (left + dw > window.innerWidth - 8) {
            left = window.innerWidth - dw - 8;
        }
        if (left < 8) left = 8;
        dropdown.style.top = (rect.bottom + 4) + 'px';
        dropdown.style.left = left + 'px';
        dropdown.style.right = 'auto';
    } else {
        dropdown.classList.remove('open');
        // Move back to original parent
        if (dropdown._muteBtn) {
            dropdown._muteBtn.parentElement.appendChild(dropdown);
            dropdown._muteBtn._openDropdown = null;
        }
    }
}

function openMuteModal(exceptionClass) {
    document.querySelectorAll('.mute-dropdown.open').forEach(d => d.classList.remove('open'));
    const modal = document.getElementById('muteModal');
    modal.querySelector('[name="exception_class"]').value = exceptionClass;
    modal.classList.add('open');
}

function closeMuteModal() {
    document.getElementById('muteModal').classList.remove('open');
}

// ─── Throttle Modal ────────────────────────────────────────
function openThrottleModal(exceptionClass) {
    document.querySelectorAll('.mute-dropdown.open').forEach(d => d.classList.remove('open'));
    const modal = document.getElementById('throttleModal');
    modal.querySelector('[name="exception_class"]').value = exceptionClass;
    modal.classList.add('open');
}

function closeThrottleModal() {
    document.getElementById('throttleModal').classList.remove('open');
}

// Close dropdowns on outside click
document.addEventListener('click', function(e) {
    if (!e.target.closest('.mute-btn') && !e.target.closest('.mute-dropdown')) {
        document.querySelectorAll('.mute-dropdown.open').forEach(d => {
            d.classList.remove('open');
            if (d._muteBtn) {
                d._muteBtn.parentElement.appendChild(d);
                d._muteBtn._openDropdown = null;
            }
        });
    }
});
</script>

{{-- Review Modal --}}
<div class="modal-overlay" id="reviewModal" onclick="if(event.target===this)closeReviewModal()">
    <div class="modal">
        <h3>Review Entry</h3>
        <form method="POST" action="{{ route('logman.review') }}" id="reviewForm">
            @csrf
            <input type="hidden" name="file" value="">
            <input type="hidden" name="hash" value="">
            <div class="modal-field">
                <label>Status</label>
                <select name="status">
                    <option value="reviewed">Reviewed</option>
                    <option value="in_progress">In Progress</option>
                    <option value="wont_fix">Won't Fix</option>
                </select>
            </div>
            <div class="modal-field">
                <label>Note (optional)</label>
                <textarea name="note" placeholder="Add a note about this error..."></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-sm btn-danger" id="unreviewBtn" style="display:none;margin-right:auto;" onclick="submitUnreview()">Remove Review</button>
                <button type="button" class="btn btn-sm" onclick="closeReviewModal()">Cancel</button>
                <button type="submit" class="btn btn-sm btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>
<form method="POST" action="{{ route('logman.unreview') }}" id="unreviewForm" style="display:none;">
    @csrf
    <input type="hidden" name="file" value="">
    <input type="hidden" name="hash" value="">
</form>

{{-- Mute Modal --}}
<div class="modal-overlay" id="muteModal" onclick="if(event.target===this)closeMuteModal()">
    <div class="modal">
        <h3>Mute Error Notifications</h3>
        <form method="POST" action="{{ route('logman.mute') }}">
            @csrf
            <div class="modal-field">
                <label>Exception Class</label>
                <input type="text" name="exception_class" readonly>
            </div>
            <div class="modal-field">
                <label>Message Pattern (optional)</label>
                <input type="text" name="message_pattern" placeholder="Partial message match...">
            </div>
            <div class="modal-field">
                <label>Duration</label>
                <select name="duration">
                    <option value="1h">1 Hour</option>
                    <option value="6h">6 Hours</option>
                    <option value="12h">12 Hours</option>
                    <option value="1d" selected>1 Day</option>
                    <option value="3d">3 Days</option>
                    <option value="1w">1 Week</option>
                    <option value="1m">1 Month</option>
                </select>
            </div>
            <div class="modal-field">
                <label>Reason (optional)</label>
                <textarea name="reason" placeholder="Why are you muting this?"></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-sm" onclick="closeMuteModal()">Cancel</button>
                <button type="submit" class="btn btn-sm btn-primary">Mute</button>
            </div>
        </form>
    </div>
</div>

{{-- Throttle Modal --}}
<div class="modal-overlay" id="throttleModal" onclick="if(event.target===this)closeThrottleModal()">
    <div class="modal">
        <h3>Set Throttle Limit</h3>
        <form method="POST" action="{{ route('logman.throttle') }}">
            @csrf
            <div class="modal-field">
                <label>Exception Class</label>
                <input type="text" name="exception_class" readonly>
            </div>
            <div class="modal-field">
                <label>Message Pattern (optional)</label>
                <input type="text" name="message_pattern" placeholder="Partial message match...">
            </div>
            <div style="display:flex;gap:12px;">
                <div class="modal-field" style="flex:1;">
                    <label>Max Hits</label>
                    <input type="number" name="max_hits" value="1" min="1" max="1000">
                </div>
                <div class="modal-field" style="flex:1;">
                    <label>Per Period</label>
                    <select name="period">
                        <option value="1h">1 Hour</option>
                        <option value="6h">6 Hours</option>
                        <option value="12h">12 Hours</option>
                        <option value="1d" selected>1 Day</option>
                        <option value="3d">3 Days</option>
                        <option value="1w">1 Week</option>
                        <option value="10d">10 Days</option>
                        <option value="1m">1 Month</option>
                    </select>
                </div>
            </div>
            <div class="modal-field">
                <label>Reason (optional)</label>
                <textarea name="reason" placeholder="Why are you throttling this?"></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-sm" onclick="closeThrottleModal()">Cancel</button>
                <button type="submit" class="btn btn-sm btn-primary">Set Throttle</button>
            </div>
        </form>
    </div>
</div>

{{-- Clear All Modal --}}
<div class="modal-overlay" id="clearAllModal" onclick="if(event.target===this)closeClearAllModal()">
    <div class="modal">
        <h3 style="color:var(--danger);">Clear All Data</h3>
        <p style="font-size:13px;color:var(--text-muted);margin-bottom:12px;">This action will permanently delete:</p>
        <ul style="font-size:13px;color:var(--text);margin:0 0 16px 18px;line-height:1.8;">
            <li><strong>{{ $files->count() }}</strong> log file(s)</li>
            <li>All active <strong>mutes</strong></li>
            <li>All active <strong>throttles</strong></li>
            <li>All saved <strong>bookmarks</strong></li>
        </ul>
        <p style="font-size:12px;color:var(--danger);margin-bottom:16px;">This cannot be undone.</p>
        <form method="POST" action="{{ route('logman.clear-all') }}">
            @csrf
            <div class="modal-actions">
                <button type="button" class="btn btn-sm" onclick="closeClearAllModal()">Cancel</button>
                <button type="submit" class="btn btn-sm btn-danger">Yes, Clear All</button>
            </div>
        </form>
    </div>
</div>

<script>
function openClearAllModal() {
    document.getElementById('clearAllModal').classList.add('open');
}
function closeClearAllModal() {
    document.getElementById('clearAllModal').classList.remove('open');
}
</script>

</body>
</html>
