<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Config - {{ config('app.name') }}</title>
    @include('logman::partials.styles')
    <style>
        .config-page { padding: 24px; max-width: 1600px; margin: 0 auto; flex: 1; overflow-y: auto; scrollbar-width: none; width: 100%; }
        .config-page::-webkit-scrollbar { display: none; }
        .config-header { margin-bottom: 24px; }
        .config-header h2 { font-size: 18px; font-weight: 700; }
        .config-header p { font-size: 13px; color: var(--text-muted); margin-top: 4px; }
        .config-section { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; margin-bottom: 20px; }
        .config-channels-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        @media (max-width: 768px) { .config-channels-grid { grid-template-columns: 1fr; } }
        .config-section-title { padding: 14px 20px; font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); background: var(--bg-sidebar); border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
        .config-section.channel-enabled { border-color: var(--debug-border); }
        .config-section.channel-enabled .config-section-title { background: var(--debug-bg); color: var(--debug-text); }
        .config-section.channel-disabled { opacity: 0.6; }
        .channel-status-badge { font-size: 10px; padding: 2px 8px; border-radius: 10px; text-transform: uppercase; font-weight: 700; letter-spacing: 0.04em; }
        .channel-on { background: var(--debug-text); color: #fff; }
        .channel-off { background: var(--border); color: var(--text-light); }
        .config-row { display: flex; align-items: center; padding: 12px 20px; border-bottom: 1px solid var(--border-light); gap: 16px; }
        .config-row:last-child { border-bottom: none; }
        .config-row:hover { background: var(--hover); }
        .config-label { flex: 0 0 200px; }
        .config-label .label-name { font-size: 13px; font-weight: 600; }
        .config-label .label-key { font-size: 10px; font-family: var(--font-mono); color: var(--text-light); margin-top: 1px; }
        .config-value { flex: 1; font-size: 13px; font-family: var(--font-mono); word-break: break-all; }
        .config-desc { flex: 0 0 300px; font-size: 12px; color: var(--text-light); }
        .val-true { color: var(--debug-text); font-weight: 600; }
        .val-false { color: var(--danger-text); font-weight: 600; }
        .val-path { color: var(--info-text); font-size: 12px; }
        .val-status-ok { color: var(--debug-text); font-weight: 600; }
        .val-status-bad { color: var(--danger-text); font-weight: 700; background: var(--danger-bg); padding: 2px 10px; border-radius: 12px; font-size: 11px; }
        .val-number { color: var(--primary); font-weight: 600; }
        .val-list { display: flex; flex-direction: column; gap: 2px; }
        .val-list-item { font-size: 12px; font-family: var(--font-mono); color: var(--text-muted); padding: 2px 8px; background: var(--bg); border-radius: 4px; display: inline-block; }
        .val-empty { color: var(--text-light); font-style: italic; }
        @media (max-width: 768px) {
            .config-row { flex-direction: column; align-items: flex-start; gap: 4px; }
            .config-label, .config-desc { flex: none; }
        }
    </style>
</head>
<body>
<div class="layout">
    @include('logman::partials.nav')

    <div class="config-page">
        <div class="config-header">
            <h2>Configuration</h2>
            <p>Current settings for Logman. Edit <code>config/logman.php</code> to change these values.</p>
        </div>

        @php
            $channelSections = [];
            $otherSections = [];
            foreach ($sections as $name => $items) {
                if (str_starts_with($name, 'Channel:')) {
                    $channelSections[$name] = $items;
                } else {
                    $otherSections[$name] = $items;
                }
            }
            // Sort channels: enabled first
            uksort($channelSections, function($a, $b) use ($channelSections) {
                $aEnabled = collect($channelSections[$a])->first(fn($i) => str_ends_with($i['key'], '.enabled'))['value'] ?? false;
                $bEnabled = collect($channelSections[$b])->first(fn($i) => str_ends_with($i['key'], '.enabled'))['value'] ?? false;
                return $bEnabled <=> $aEnabled;
            });
        @endphp

        {{-- Non-channel sections before channels --}}
        @foreach($otherSections as $sectionName => $items)
            @if($sectionName === 'Rate Limiting') @break @endif
            @include('logman::partials._config-section', ['sectionName' => $sectionName, 'items' => $items])
        @endforeach

        {{-- Channel sections in grid --}}
        @if(!empty($channelSections))
            <div class="config-channels-grid">
                @foreach($channelSections as $sectionName => $items)
                    @include('logman::partials._config-section', ['sectionName' => $sectionName, 'items' => $items])
                @endforeach
            </div>
        @endif

        {{-- Remaining sections --}}
        @php $pastChannels = false; @endphp
        @foreach($otherSections as $sectionName => $items)
            @if($sectionName === 'Rate Limiting') @php $pastChannels = true; @endphp @endif
            @if($pastChannels)
                @include('logman::partials._config-section', ['sectionName' => $sectionName, 'items' => $items])
            @endif
        @endforeach
    </div>
</div>

<script>
@include('logman::partials.theme-js')
</script>
</body>
</html>
