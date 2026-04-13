<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Muted Errors - {{ config('app.name') }}</title>
    @include('logman::partials.styles')
    <style>
        .mutes-page { padding: 24px; max-width: 1600px; margin: 0 auto; flex: 1; overflow-y: auto; scrollbar-width: none; width: 100%; }
        .mutes-page::-webkit-scrollbar { display: none; }
        .mutes-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
        .mutes-header h2 { font-size: 18px; font-weight: 700; }
        .mutes-header-right { display: flex; gap: 8px; align-items: center; }
        .mutes-count { font-size: 13px; color: var(--text-muted); background: var(--bg); padding: 4px 12px; border-radius: 20px; }
        .mutes-table { width: 100%; border-collapse: collapse; background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); overflow: visible; }
        .mutes-table th { padding: 12px 16px; text-align: left; font-size: 11px; text-transform: uppercase; color: var(--text-light); font-weight: 700; letter-spacing: 0.05em; background: var(--bg-sidebar); border-bottom: 2px solid var(--border); }
        .mutes-table td { padding: 10px 16px; border-bottom: 1px solid var(--border-light); font-size: 13px; vertical-align: middle; }
        .mutes-table tr:hover td { background: var(--hover); }
        .mutes-table .mono { font-family: var(--font-mono); font-size: 12px; word-break: break-all; }
        .mutes-table .meta-text { font-size: 12px; color: var(--text-muted); }
        .mute-badge-active { display: inline-flex; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; background: var(--warning-bg); color: var(--warning-text); border: 1px solid var(--warning-border); }
        .extend-wrap { position: relative; display: inline-block; }
        .extend-dropdown { position: absolute; top: 100%; right: 0; background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-sm); box-shadow: var(--shadow-lg); z-index: 100; min-width: 120px; padding: 8px 0; display: none; }
        .extend-dropdown.open { display: block; }
        .extend-dropdown-item { display: block; width: 100%; padding: 6px 14px; font-size: 12px; text-align: left; cursor: pointer; background: none; border: none; color: var(--text); transition: background 0.15s; }
        .extend-dropdown-item:hover { background: var(--hover); }
        .mute-check { width: 14px; height: 14px; accent-color: var(--primary); cursor: pointer; }
        .empty-mutes { text-align: center; padding: 60px 20px; color: var(--text-light); }
        .empty-mutes p { font-size: 15px; font-weight: 500; margin-top: 12px; }
        .empty-mutes .sub { font-size: 13px; margin-top: 4px; color: var(--text-light); }
        .json-section { margin-top: 24px; }
        .json-section h3 { font-size: 14px; font-weight: 600; margin-bottom: 8px; }
        .json-content { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 16px; font-family: var(--font-mono); font-size: 12px; white-space: pre-wrap; word-break: break-all; max-height: 300px; overflow-y: auto; color: var(--text-muted); line-height: 1.8; }

        /* Custom Mute Form */
        .custom-mute-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 20px; margin-bottom: 24px; }
        .custom-mute-card h3 { font-size: 15px; font-weight: 700; margin-bottom: 14px; }
        .custom-mute-form { display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end; }
        .custom-mute-field { display: flex; flex-direction: column; gap: 4px; flex: 1; min-width: 160px; }
        .custom-mute-field label { font-size: 11px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em; }
        .custom-mute-field input, .custom-mute-field select, .custom-mute-field textarea { padding: 8px 12px; border: 1px solid var(--border); border-radius: var(--radius-sm); font-size: 13px; background: var(--bg); color: var(--text); font-family: inherit; }
        .custom-mute-field textarea { resize: vertical; min-height: 38px; }
        .custom-mute-field input:focus, .custom-mute-field select:focus, .custom-mute-field textarea:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px var(--primary-glow); }
    </style>
</head>
<body>
<div class="layout">
    @include('logman::partials.nav')

    <div class="mutes-page">
        <div class="mutes-header">
            <h2>Muted Errors</h2>
            <div class="mutes-header-right">
                <span class="mutes-count">{{ count($mutes) }} active</span>
                @if(count($mutes) > 0)
                    <button class="btn btn-sm btn-danger" id="deleteSelectedBtn" style="display:none;" onclick="deleteSelectedMutes()">Delete Selected</button>
                    <form method="POST" action="{{ route('logman.unmute-all') }}" style="display:inline;" onsubmit="return confirm('Remove all mutes?')">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-danger">Unmute All</button>
                    </form>
                @endif
            </div>
        </div>

        @if(session('success'))
            <div class="flash flash-success">{{ session('success') }}</div>
        @endif

        {{-- Custom Mute Form --}}
        <div class="custom-mute-card">
            <h3>Add Custom Mute</h3>
            <form method="POST" action="{{ route('logman.mute') }}" class="custom-mute-form">
                @csrf
                <div class="custom-mute-field" style="flex:2;">
                    <label>Exception Class *</label>
                    <input type="text" name="exception_class" placeholder="App\Exceptions\MyException" required>
                </div>
                <div class="custom-mute-field" style="flex:2;">
                    <label>Message Pattern (optional)</label>
                    <input type="text" name="message_pattern" placeholder="Partial message match...">
                </div>
                <div class="custom-mute-field" style="flex:0.7;">
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
                <div class="custom-mute-field" style="flex:2;">
                    <label>Reason (optional)</label>
                    <input type="text" name="reason" placeholder="Why are you muting this?">
                </div>
                <button type="submit" class="btn btn-primary" style="align-self:flex-end;">Mute</button>
            </form>
        </div>

        @if(count($mutes) > 0)
            <table class="mutes-table">
                <thead>
                    <tr>
                        <th style="width:30px"><input type="checkbox" class="mute-check" onchange="toggleAllMutes(this.checked)"></th>
                        <th>Exception / Pattern</th>
                        <th style="width:100px">Hits</th>
                        <th style="width:160px">Expires</th>
                        <th style="width:140px">Created</th>
                        <th style="width:200px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($mutes as $mute)
                        <tr>
                            <td><input type="checkbox" class="mute-check mute-item-check" value="{{ $mute['id'] }}" onchange="updateMuteDeleteBtn()"></td>
                            <td>
                                <div class="mono">{{ $mute['exception_class'] }}</div>
                                @if(!empty($mute['message_pattern']))
                                    <div class="meta-text" style="margin-top:2px;">Pattern: "{{ $mute['message_pattern'] }}"</div>
                                @endif
                                @if(!empty($mute['reason']))
                                    <div class="meta-text" style="font-style:italic;margin-top:2px;">{{ $mute['reason'] }}</div>
                                @endif
                            </td>
                            <td>
                                <span style="font-weight:600;{{ ($mute['hit_count'] ?? 0) > 0 ? 'color:var(--warning-text)' : '' }}">{{ $mute['hit_count'] ?? 0 }}</span>
                            </td>
                            <td>
                                <span class="mute-badge-active">{{ \Carbon\Carbon::parse($mute['muted_until'])->diffForHumans() }}</span>
                            </td>
                            <td class="meta-text">{{ \Carbon\Carbon::parse($mute['created_at'])->format('M j, H:i') }}</td>
                            <td>
                                <div style="display:flex;gap:4px;align-items:center;">
                                    <div class="extend-wrap">
                                        <button class="btn btn-sm" onclick="toggleExtendDropdown(this)">Extend</button>
                                        <div class="extend-dropdown">
                                            @foreach(['1h' => '+1 Hour', '6h' => '+6 Hours', '1d' => '+1 Day', '3d' => '+3 Days', '1w' => '+1 Week'] as $dur => $durLabel)
                                                <form method="POST" action="{{ route('logman.extend-mute') }}" style="display:contents;">
                                                    @csrf
                                                    <input type="hidden" name="id" value="{{ $mute['id'] }}">
                                                    <input type="hidden" name="duration" value="{{ $dur }}">
                                                    <button type="submit" class="extend-dropdown-item">{{ $durLabel }}</button>
                                                </form>
                                            @endforeach
                                        </div>
                                    </div>
                                    <form method="POST" action="{{ route('logman.unmute') }}" style="display:inline;">
                                        @csrf
                                        <input type="hidden" name="id" value="{{ $mute['id'] }}">
                                        <button type="submit" class="btn btn-sm btn-danger">Unmute</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{-- Raw JSON --}}
            <div class="json-section">
                <h3>Raw JSON (mutes.json)</h3>
                <div class="json-content">{{ json_encode($mutes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</div>
            </div>
        @else
            <div class="empty-mutes">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:48px;height:48px;opacity:0.2;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M15.536 8.464a5 5 0 010 7.072M12 12v.01M8.464 8.464a5 5 0 000 7.072M5.636 5.636a9 9 0 000 12.728M18.364 5.636a9 9 0 010 12.728"/></svg>
                <p>No muted errors</p>
                <div class="sub">Mute errors from the log viewer to temporarily stop them from being reported</div>
            </div>
        @endif
    </div>
</div>

<script>
@include('logman::partials.theme-js')

function toggleAllMutes(checked) {
    document.querySelectorAll('.mute-item-check').forEach(cb => cb.checked = checked);
    updateMuteDeleteBtn();
}

function updateMuteDeleteBtn() {
    const checked = document.querySelectorAll('.mute-item-check:checked');
    document.getElementById('deleteSelectedBtn').style.display = checked.length > 0 ? 'inline-flex' : 'none';
}

function toggleExtendDropdown(btn) {
    const dropdown = btn.nextElementSibling;
    document.querySelectorAll('.extend-dropdown.open').forEach(d => {
        if (d !== dropdown) d.classList.remove('open');
    });
    dropdown.classList.toggle('open');
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.extend-wrap')) {
        document.querySelectorAll('.extend-dropdown.open').forEach(d => d.classList.remove('open'));
    }
});

function deleteSelectedMutes() {
    const checked = document.querySelectorAll('.mute-item-check:checked');
    if (checked.length === 0) return;
    if (!confirm('Remove ' + checked.length + ' mute(s)?')) return;

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '{{ route("logman.unmute-multiple") }}';

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
