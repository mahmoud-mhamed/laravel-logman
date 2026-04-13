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
        .config-section-title { padding: 14px 20px; font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); background: var(--bg-sidebar); border-bottom: 1px solid var(--border); }
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

        @foreach($sections as $sectionName => $items)
            <div class="config-section">
                <div class="config-section-title">{{ $sectionName }}</div>
                @foreach($items as $item)
                    <div class="config-row">
                        <div class="config-label">
                            <div class="label-name">{{ $item['label'] }}</div>
                            <div class="label-key">{{ $item['key'] }}</div>
                        </div>
                        <div class="config-value">
                            @if($item['type'] === 'bool')
                                @if($item['value'])
                                    <span class="val-true">true</span>
                                @else
                                    <span class="val-false">false</span>
                                @endif
                            @elseif($item['type'] === 'number')
                                <span class="val-number">{{ $item['value'] }}</span>
                            @elseif($item['type'] === 'path')
                                <span class="val-path">{{ $item['value'] }}</span>
                            @elseif($item['type'] === 'status')
                                @if(str_contains($item['value'], 'NOT SET'))
                                    <span class="val-status-bad">{{ $item['value'] }}</span>
                                @else
                                    <span class="val-status-ok">{{ $item['value'] }}</span>
                                @endif
                            @elseif($item['type'] === 'list')
                                @if(is_array($item['value']) && count($item['value']) > 0)
                                    <div class="val-list">
                                        @foreach($item['value'] as $listItem)
                                            <span class="val-list-item">{{ $listItem }}</span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="val-empty">None</span>
                                @endif
                            @else
                                {{ $item['value'] }}
                            @endif
                        </div>
                        <div class="config-desc">{{ $item['description'] }}</div>
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>
</div>

<script>
@include('logman::partials.theme-js')
</script>
</body>
</html>
