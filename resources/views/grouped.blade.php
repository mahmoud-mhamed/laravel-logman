<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grouped Errors - {{ config('app.name') }}</title>
    @include('logman::partials.styles')
    @include('logman::partials._detail-styles')
    <style>
        .grouped-page { padding: 24px; max-width: 1600px; margin: 0 auto; flex: 1; overflow-y: auto; scrollbar-width: none; width: 100%; }
        .grouped-page::-webkit-scrollbar { display: none; }
        .grouped-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; gap: 12px; flex-wrap: wrap; }
        .grouped-header h2 { font-size: 18px; font-weight: 700; }
        .file-select { padding: 7px 12px; border: 1px solid var(--border); border-radius: var(--radius-sm); font-size: 13px; background: var(--bg); color: var(--text); cursor: pointer; }
        .grouped-table { width: 100%; border-collapse: collapse; background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; }
        .grouped-table th { padding: 12px 16px; text-align: left; font-size: 11px; text-transform: uppercase; color: var(--text-light); font-weight: 700; letter-spacing: 0.05em; background: var(--bg-sidebar); border-bottom: 2px solid var(--border); }
        .grouped-table td { padding: 10px 16px; border-bottom: 1px solid var(--border-light); font-size: 13px; vertical-align: middle; }
        .grouped-table .mono { font-family: var(--font-mono); font-size: 12px; word-break: break-all; }
        .grouped-table .meta-text { font-size: 12px; color: var(--text-muted); }
        .count-badge { display: inline-flex; align-items: center; justify-content: center; min-width: 32px; padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; }
        .count-badge.high { background: var(--danger-bg); color: var(--danger-text); }
        .count-badge.medium { background: var(--warning-bg); color: var(--warning-text); }
        .count-badge.low { background: var(--debug-bg); color: var(--debug-text); }
        .empty-grouped { text-align: center; padding: 60px 20px; color: var(--text-light); }
        .empty-grouped p { font-size: 15px; font-weight: 500; margin-top: 12px; }
    </style>
</head>
<body>
<div class="layout">
    @include('logman::partials.nav')

    <div class="grouped-page">
        <div class="grouped-header">
            <h2>Grouped Errors</h2>
            <div style="display:flex;gap:8px;align-items:center;">
                <select class="file-select" onchange="window.location='{{ route('logman.grouped') }}?file='+this.value">
                    @foreach($files as $file)
                        <option value="{{ $file['name'] }}" {{ $selectedFile === $file['name'] ? 'selected' : '' }}>{{ $file['name'] }}</option>
                    @endforeach
                </select>
                @if($selectedFile)
                    <a href="{{ route('logman.export', ['file' => $selectedFile, 'format' => 'json']) }}" class="btn btn-sm">Export JSON</a>
                    <a href="{{ route('logman.export', ['file' => $selectedFile, 'format' => 'csv']) }}" class="btn btn-sm">Export CSV</a>
                @endif
            </div>
        </div>

        @if(!empty($groups))
            <table class="grouped-table">
                <thead>
                    <tr>
                        <th style="width:30px"></th>
                        <th style="width:70px">Count</th>
                        <th style="width:80px">Level</th>
                        <th>Message</th>
                        <th style="width:180px">File</th>
                        <th style="width:130px">Last Seen</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($groups as $i => $group)
                        @php
                            $countClass = $group['count'] >= 10 ? 'high' : ($group['count'] >= 3 ? 'medium' : 'low');
                            $hasDetails = !empty($group['full_entry']) && ($group['full_entry']['stack'] || $group['full_entry']['context']);
                        @endphp
                        <tr class="log-row" @if($hasDetails) onclick="toggleDetail({{ $i }}, this)" @endif>
                            <td>@if($hasDetails)<span class="expand-icon">&#9654;</span>@endif</td>
                            <td><span class="count-badge {{ $countClass }}">{{ $group['count'] }}</span></td>
                            <td><span class="badge badge-{{ $group['level_class'] }}">{{ $group['level'] }}</span></td>
                            <td>
                                <div style="max-width:500px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $group['message'] }}">{{ $group['message'] }}</div>
                            </td>
                            <td class="mono meta-text">
                                @if($group['file'])
                                    {{ basename($group['file']) }}:{{ $group['line'] }}
                                @else
                                    -
                                @endif
                            </td>
                            <td class="meta-text" style="font-family:var(--font-mono);font-size:11px;">{{ $group['last_seen'] }}</td>
                        </tr>
                        @if($hasDetails)
                            <tr class="detail-row" id="detail-{{ $i }}">
                                <td colspan="6">
                                    @include('logman::partials._entry-detail', ['entry' => $group['full_entry'], 'i' => $i])
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="empty-grouped">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:48px;height:48px;opacity:0.2;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                <p>No grouped errors found</p>
            </div>
        @endif
    </div>
</div>

<script>
@include('logman::partials.theme-js')
@include('logman::partials._detail-js')
</script>
</body>
</html>
