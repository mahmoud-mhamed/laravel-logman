{{-- Entry detail expandable row. Expects: $entry, $i, $search (optional), $isRegex (optional), $hasLongMessage (optional), $hasStackOrContext (optional) --}}
@php
    $search = $search ?? '';
    $isRegex = $isRegex ?? false;
    $hasLongMessage = $hasLongMessage ?? false;
    $hasStackOrContext = $hasStackOrContext ?? ($entry['stack'] || $entry['context']);
    $hasJson = !empty($entry['json_blocks']);

    // Decide which tab opens by default.
    if ($entry['stack']) {
        $activeTab = 'stack';
    } elseif ($entry['context']) {
        $activeTab = 'context';
    } elseif ($hasLongMessage) {
        $activeTab = 'message';
    } elseif ($hasJson) {
        $activeTab = 'json';
    } else {
        $activeTab = 'message';
    }
@endphp
<div class="detail-content">
    @if(!empty($entry['called_from'] ?? ''))
        <div class="called-from-bar">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            <span class="cf-label">Logged from:</span>
            <span class="cf-loc">{{ $entry['called_from'] }}</span>
        </div>
    @endif
    <div class="detail-tabs">
        <button class="detail-tab {{ $activeTab === 'message' ? 'active' : '' }}" onclick="showPane({{ $i }}, 'message', this)">Message</button>
        @if($entry['stack'])
            <button class="detail-tab {{ $activeTab === 'stack' ? 'active' : '' }}" onclick="showPane({{ $i }}, 'stack', this)">Stack Trace</button>
        @endif
        @if($entry['context'])
            <button class="detail-tab {{ $activeTab === 'context' ? 'active' : '' }}" onclick="showPane({{ $i }}, 'context', this)">Context</button>
        @endif
        @if($hasJson)
            <button class="detail-tab {{ $activeTab === 'json' ? 'active' : '' }}" onclick="showPane({{ $i }}, 'json', this)">JSON</button>
        @endif
        <button class="detail-tab" onclick="showPane({{ $i }}, 'raw', this)">Raw</button>
    </div>
    <div class="detail-search">
        <input type="text" placeholder="Search in content..." oninput="searchInDetail(this, {{ $i }})" onkeydown="if(event.key==='Enter'){event.preventDefault();detailSearchNav({{ $i }}, event.shiftKey?-1:1)}">
        <span class="detail-search-info" id="detail-search-info-{{ $i }}"></span>
        <div class="detail-search-nav">
            <button onclick="detailSearchNav({{ $i }}, -1)" title="Previous">{!! '&#8593;' !!}</button>
            <button onclick="detailSearchNav({{ $i }}, 1)" title="Next">{!! '&#8595;' !!}</button>
        </div>
    </div>
    <div class="detail-pane {{ $activeTab === 'message' ? 'active' : '' }} detail-pane-wrap" id="pane-{{ $i }}-message">
        <button class="copy-btn" onclick="copyText(this, {{ $i }}, 'message')">Copy</button>
        <div class="context-content">{!! $search ? highlightSearch(e($entry['message']), $search, $isRegex) : e($entry['message']) !!}</div>
    </div>
    @if($entry['stack'])
        <div class="detail-pane {{ $activeTab === 'stack' ? 'active' : '' }} detail-pane-wrap" id="pane-{{ $i }}-stack">
            <button class="copy-btn" onclick="copyText(this, {{ $i }}, 'stack')">Copy</button>
            <div class="stack-content">{!! $search ? highlightSearch(e($entry['stack']), $search, $isRegex) : e($entry['stack']) !!}</div>
        </div>
    @endif
    @if($entry['context'])
        <div class="detail-pane {{ $activeTab === 'context' ? 'active' : '' }} detail-pane-wrap" id="pane-{{ $i }}-context">
            <button class="copy-btn" onclick="copyText(this, {{ $i }}, 'context')">Copy</button>
            <div class="context-content">{!! $search ? highlightSearch(e($entry['context']), $search, $isRegex) : e($entry['context']) !!}</div>
        </div>
    @endif
    @if($hasJson)
        <div class="detail-pane {{ $activeTab === 'json' ? 'active' : '' }} detail-pane-wrap" id="pane-{{ $i }}-json">
            <button class="copy-btn" onclick="copyText(this, {{ $i }}, 'json')">Copy</button>
            @foreach($entry['json_blocks'] as $block)
                <div class="json-content">{{ $block }}</div>
            @endforeach
        </div>
    @endif
    @php
        $rawText = '[' . $entry['date'] . '] ' . $entry['env'] . '.' . strtoupper($entry['level']) . ': ' . $entry['message'] . "\n" . $entry['stack'];
    @endphp
    <div class="detail-pane detail-pane-wrap" id="pane-{{ $i }}-raw">
        <button class="copy-btn" onclick="copyText(this, {{ $i }}, 'raw')">Copy</button>
        <div class="stack-content">{!! $search ? highlightSearch(e($rawText), $search, $isRegex) : e($rawText) !!}</div>
    </div>
</div>
