<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Throttled Errors - {{ config('app.name') }}</title>
    @include('logman::partials.styles')
    <style>
        .throttles-page { padding: 24px; max-width: 1600px; margin: 0 auto; flex: 1; overflow-y: auto; scrollbar-width: none; width: 100%; }
        .throttles-page::-webkit-scrollbar { display: none; }
        .throttles-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
        .throttles-header h2 { font-size: 18px; font-weight: 700; }
        .throttles-header-right { display: flex; gap: 8px; align-items: center; }
        .throttles-count { font-size: 13px; color: var(--text-muted); background: var(--bg); padding: 4px 12px; border-radius: 20px; }
        .throttles-table { width: 100%; border-collapse: collapse; background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); overflow: visible; }
        .throttles-table th { padding: 12px 16px; text-align: left; font-size: 11px; text-transform: uppercase; color: var(--text-light); font-weight: 700; letter-spacing: 0.05em; background: var(--bg-sidebar); border-bottom: 2px solid var(--border); }
        .throttles-table td { padding: 10px 16px; border-bottom: 1px solid var(--border-light); font-size: 13px; vertical-align: middle; }
        .throttles-table tr:hover td { background: var(--hover); }
        .throttles-table .mono { font-family: var(--font-mono); font-size: 12px; word-break: break-all; }
        .throttles-table .meta-text { font-size: 12px; color: var(--text-muted); }
        .throttle-badge { display: inline-flex; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
        .throttle-badge-ok { background: var(--debug-bg); color: var(--debug-text); border: 1px solid var(--debug-border); }
        .throttle-badge-limit { background: var(--danger-bg); color: var(--danger-text); border: 1px solid var(--danger-border); }
        .throttle-check { width: 14px; height: 14px; accent-color: var(--primary); cursor: pointer; }
        .throttle-progress { display: flex; align-items: center; gap: 8px; }
        .throttle-bar { height: 6px; border-radius: 3px; background: var(--border); flex: 1; min-width: 60px; overflow: hidden; }
        .throttle-bar-fill { height: 100%; border-radius: 3px; transition: width 0.3s; }
        .throttle-bar-fill.ok { background: var(--debug-text); }
        .throttle-bar-fill.warn { background: var(--warning-text); }
        .throttle-bar-fill.full { background: var(--danger-text); }
        .empty-throttles { text-align: center; padding: 60px 20px; color: var(--text-light); }
        .empty-throttles p { font-size: 15px; font-weight: 500; margin-top: 12px; }
        .empty-throttles .sub { font-size: 13px; margin-top: 4px; }
        .json-section { margin-top: 24px; }
        .json-section h3 { font-size: 14px; font-weight: 600; margin-bottom: 8px; }
        .json-content { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 16px; font-family: var(--font-mono); font-size: 12px; white-space: pre-wrap; word-break: break-all; max-height: 300px; overflow-y: auto; color: var(--text-muted); line-height: 1.8; }
    </style>
</head>
<body>
<div class="layout">
    @include('logman::partials.nav')

    <div class="throttles-page">
        <div class="throttles-header">
            <h2>Throttled Errors</h2>
            <div class="throttles-header-right">
                <span class="throttles-count">{{ count($throttles) }} active</span>
                @if(count($throttles) > 0)
                    <button class="btn btn-sm btn-danger" id="deleteSelectedBtn" style="display:none;" onclick="deleteSelectedThrottles()">Delete Selected</button>
                    <form method="POST" action="{{ route('logman.unthrottle-all') }}" style="display:inline;" onsubmit="return confirm('Remove all throttles?')">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-danger">Remove All</button>
                    </form>
                @endif
            </div>
        </div>

        @if(session('success'))
            <div class="flash flash-success">{{ session('success') }}</div>
        @endif

        @if(count($throttles) > 0)
            <table class="throttles-table">
                <thead>
                    <tr>
                        <th style="width:30px"><input type="checkbox" class="throttle-check" onchange="toggleAllThrottles(this.checked)"></th>
                        <th>Exception / Pattern</th>
                        <th style="width:130px">Limit</th>
                        <th style="width:160px">Usage</th>
                        <th style="width:140px">Period Start</th>
                        <th style="width:100px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($throttles as $throttle)
                        @php
                            $pct = $throttle['max_hits'] > 0 ? min(100, round(($throttle['current_hits'] / $throttle['max_hits']) * 100)) : 0;
                            $barClass = $pct >= 100 ? 'full' : ($pct >= 70 ? 'warn' : 'ok');
                            $periodLabels = ['1h'=>'1 Hour','6h'=>'6 Hours','12h'=>'12 Hours','1d'=>'1 Day','3d'=>'3 Days','1w'=>'1 Week','10d'=>'10 Days','1m'=>'1 Month'];
                        @endphp
                        <tr>
                            <td><input type="checkbox" class="throttle-check throttle-item-check" value="{{ $throttle['id'] }}" onchange="updateThrottleDeleteBtn()"></td>
                            <td>
                                <div class="mono">{{ $throttle['exception_class'] }}</div>
                                @if(!empty($throttle['message_pattern']))
                                    <div class="meta-text" style="margin-top:2px;">Pattern: "{{ $throttle['message_pattern'] }}"</div>
                                @endif
                                @if(!empty($throttle['reason']))
                                    <div class="meta-text" style="font-style:italic;margin-top:2px;">{{ $throttle['reason'] }}</div>
                                @endif
                            </td>
                            <td>
                                <strong>{{ $throttle['max_hits'] }}</strong> / {{ $periodLabels[$throttle['period']] ?? $throttle['period'] }}
                            </td>
                            <td>
                                <div class="throttle-progress">
                                    <div class="throttle-bar">
                                        <div class="throttle-bar-fill {{ $barClass }}" style="width:{{ $pct }}%"></div>
                                    </div>
                                    <span style="font-size:12px;font-weight:600;{{ $pct >= 100 ? 'color:var(--danger-text)' : '' }}">{{ $throttle['current_hits'] }}/{{ $throttle['max_hits'] }}</span>
                                </div>
                                @if($pct >= 100)
                                    <span class="throttle-badge throttle-badge-limit" style="margin-top:4px;">Limit reached</span>
                                @else
                                    <span class="throttle-badge throttle-badge-ok" style="margin-top:4px;">Active</span>
                                @endif
                            </td>
                            <td class="meta-text">{{ \Carbon\Carbon::parse($throttle['period_start'])->format('M j, H:i') }}</td>
                            <td>
                                <form method="POST" action="{{ route('logman.unthrottle') }}" style="display:inline;">
                                    @csrf
                                    <input type="hidden" name="id" value="{{ $throttle['id'] }}">
                                    <button type="submit" class="btn btn-sm btn-danger">Remove</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{-- Raw JSON --}}
            <div class="json-section">
                <h3>Raw JSON (throttles.json)</h3>
                <div class="json-content">{{ json_encode($throttles, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</div>
            </div>
        @else
            <div class="empty-throttles">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:48px;height:48px;opacity:0.2;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <p>No throttled errors</p>
                <div class="sub">Set throttle limits from the log viewer to control how often errors are reported</div>
            </div>
        @endif
    </div>
</div>

<script>
@include('logman::partials.theme-js')

function toggleAllThrottles(checked) {
    document.querySelectorAll('.throttle-item-check').forEach(cb => cb.checked = checked);
    updateThrottleDeleteBtn();
}

function updateThrottleDeleteBtn() {
    const checked = document.querySelectorAll('.throttle-item-check:checked');
    document.getElementById('deleteSelectedBtn').style.display = checked.length > 0 ? 'inline-flex' : 'none';
}

function deleteSelectedThrottles() {
    const checked = document.querySelectorAll('.throttle-item-check:checked');
    if (checked.length === 0) return;
    if (!confirm('Remove ' + checked.length + ' throttle(s)?')) return;

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '{{ route("logman.unthrottle-multiple") }}';

    const csrf = document.createElement('input');
    csrf.type = 'hidden'; csrf.name = '_token'; csrf.value = '{{ csrf_token() }}';
    form.appendChild(csrf);

    checked.forEach(cb => {
        const input = document.createElement('input');
        input.type = 'hidden'; input.name = 'ids[]'; input.value = cb.value;
        form.appendChild(input);
    });

    document.body.appendChild(form);
    form.submit();
}
</script>
</body>
</html>
