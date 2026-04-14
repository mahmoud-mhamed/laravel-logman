{{-- Entry detail expandable row. Expects: $entry, $i, $search (optional), $isRegex (optional), $hasLongMessage (optional), $hasStackOrContext (optional) --}}
@php
    $search = $search ?? '';
    $isRegex = $isRegex ?? false;
    $hasLongMessage = $hasLongMessage ?? false;
    $hasStackOrContext = $hasStackOrContext ?? ($entry['stack'] || $entry['context']);
    $messageIsFirstTab = $hasLongMessage && !$hasStackOrContext;
@endphp
<div class="detail-content">
    <div class="detail-tabs">
        <button class="detail-tab {{ $messageIsFirstTab ? 'active' : '' }}" onclick="showPane({{ $i }}, 'message', this)">Message</button>
        @if($entry['stack'])
            <button class="detail-tab {{ !$messageIsFirstTab ? 'active' : '' }}" onclick="showPane({{ $i }}, 'stack', this)">Stack Trace</button>
        @endif
        @if($entry['context'])
            <button class="detail-tab {{ !$entry['stack'] && !$messageIsFirstTab ? 'active' : '' }}" onclick="showPane({{ $i }}, 'context', this)">Context</button>
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
    <div class="detail-pane {{ $messageIsFirstTab ? 'active' : '' }} detail-pane-wrap" id="pane-{{ $i }}-message">
        <button class="copy-btn" onclick="copyText(this, {{ $i }}, 'message')">Copy</button>
        <div class="context-content">{!! $search ? highlightSearch(e($entry['message']), $search, $isRegex) : e($entry['message']) !!}</div>
    </div>
    @if($entry['stack'])
        <div class="detail-pane {{ !$messageIsFirstTab ? 'active' : '' }} detail-pane-wrap" id="pane-{{ $i }}-stack">
            <button class="copy-btn" onclick="copyText(this, {{ $i }}, 'stack')">Copy</button>
            <div class="stack-content">{!! $search ? highlightSearch(e($entry['stack']), $search, $isRegex) : e($entry['stack']) !!}</div>
        </div>
    @endif
    @if($entry['context'])
        <div class="detail-pane {{ !$entry['stack'] && !$messageIsFirstTab ? 'active' : '' }} detail-pane-wrap" id="pane-{{ $i }}-context">
            <button class="copy-btn" onclick="copyText(this, {{ $i }}, 'context')">Copy</button>
            <div class="context-content">{!! $search ? highlightSearch(e($entry['context']), $search, $isRegex) : e($entry['context']) !!}</div>
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
