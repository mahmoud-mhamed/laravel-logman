<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Dashboard - {{ config('app.name') }}</title>
    @include('logman::partials.styles')
    <style>
        .dashboard { padding: 24px; max-width: 1600px; margin: 0 auto; flex: 1; overflow-y: auto; scrollbar-width: none; width: 100%; }
        .dashboard::-webkit-scrollbar { display: none; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 12px; margin-bottom: 24px; }
        .stat-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 10px; padding: 16px; text-align: center; transition: transform 0.15s, box-shadow 0.15s; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow); }
        .stat-card .stat-value { font-size: 28px; font-weight: 700; line-height: 1.2; }
        .stat-card .stat-label { font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); margin-top: 4px; font-weight: 600; }
        .stat-card .stat-pct { font-size: 11px; color: var(--text-muted); margin-top: 2px; }
        .stat-card.danger .stat-value { color: var(--danger-text); }
        .stat-card.error .stat-value { color: var(--error-text); }
        .stat-card.warning .stat-value { color: var(--warning-text); }
        .stat-card.info .stat-value { color: var(--info-text); }
        .stat-card.debug .stat-value { color: var(--debug-text); }
        .chart-section { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px; }
        .chart-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 10px; padding: 20px; }
        .chart-card h3 { font-size: 15px; font-weight: 600; margin-bottom: 16px; }
        .chart-container { position: relative; height: 280px; display: flex; align-items: center; justify-content: center; }
        .files-table { width: 100%; border-collapse: collapse; background: var(--bg-card); border: 1px solid var(--border); border-radius: 10px; overflow: hidden; }
        .files-table th { padding: 12px 16px; text-align: left; font-size: 12px; text-transform: uppercase; color: var(--text-muted); font-weight: 600; letter-spacing: 0.05em; background: var(--bg-sidebar); border-bottom: 2px solid var(--border); }
        .files-table td { padding: 10px 16px; border-bottom: 1px solid var(--border); font-size: 13px; }
        .files-table tr:hover td { background: var(--hover); }
        .files-table a { color: var(--primary); text-decoration: none; font-weight: 500; }
        .files-table a:hover { text-decoration: underline; }
        .mini-bar { display: flex; height: 6px; border-radius: 3px; overflow: hidden; gap: 1px; min-width: 120px; }
        .mini-bar span { height: 100%; }
        .summary-row { display: flex; gap: 24px; margin-bottom: 24px; }
        .summary-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 10px; padding: 20px; flex: 1; text-align: center; }
        .summary-card .big-num { font-size: 36px; font-weight: 700; color: var(--primary); }
        .summary-card .label { font-size: 13px; color: var(--text-muted); margin-top: 4px; }
        @media (max-width: 768px) {
            .chart-section { grid-template-columns: 1fr; }
            .summary-row { flex-direction: column; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
<div class="layout">
    @include('logman::partials.nav')

    <div class="dashboard">
        {{-- Summary --}}
        <div class="summary-row">
            <div class="summary-card">
                <div class="big-num">{{ $file_count }}</div>
                <div class="label">Log Files</div>
            </div>
            <div class="summary-card">
                <div class="big-num">{{ number_format($total_entries) }}</div>
                <div class="label">Total Entries</div>
            </div>
            <div class="summary-card">
                <div class="big-num">{{ ($global_counts['error'] ?? 0) + ($global_counts['critical'] ?? 0) + ($global_counts['alert'] ?? 0) + ($global_counts['emergency'] ?? 0) }}</div>
                <div class="label">Errors & Above</div>
            </div>
        </div>

        {{-- Today Stats --}}
        @php
            $levelClasses = [
                'emergency' => 'danger', 'alert' => 'danger', 'critical' => 'danger',
                'error' => 'error', 'warning' => 'warning',
                'notice' => 'info', 'info' => 'info', 'debug' => 'debug',
            ];
            $allLevels = ['emergency','alert','critical','error','warning','notice','info','debug'];
            $todayErrors = ($today_counts['error'] ?? 0) + ($today_counts['critical'] ?? 0) + ($today_counts['alert'] ?? 0) + ($today_counts['emergency'] ?? 0);
        @endphp

        <h3 style="font-size: 15px; font-weight: 600; margin-bottom: 12px;">Today ({{ date('M j, Y') }})</h3>
        <div class="summary-row" style="margin-bottom: 24px;">
            <div class="summary-card">
                <div class="big-num">{{ number_format($today_total) }}</div>
                <div class="label">Today Entries</div>
                @if(!empty($comparison['total']))
                    <div style="font-size:12px;margin-top:4px;font-weight:600;color:{{ $comparison['total']['direction'] === 'up' ? 'var(--danger-text)' : ($comparison['total']['direction'] === 'down' ? 'var(--debug-text)' : 'var(--text-muted)') }};">
                        {!! $comparison['total']['direction'] === 'up' ? '&#9650;' : ($comparison['total']['direction'] === 'down' ? '&#9660;' : '&#8212;') !!}
                        {{ $comparison['total']['pct'] }}% vs yesterday ({{ $yesterday_total }})
                    </div>
                @endif
            </div>
            <div class="summary-card">
                <div class="big-num" style="color: var(--danger-text);">{{ $todayErrors }}</div>
                <div class="label">Today Errors & Above</div>
                @if(!empty($comparison['errors']))
                    <div style="font-size:12px;margin-top:4px;font-weight:600;color:{{ $comparison['errors']['direction'] === 'up' ? 'var(--danger-text)' : ($comparison['errors']['direction'] === 'down' ? 'var(--debug-text)' : 'var(--text-muted)') }};">
                        {!! $comparison['errors']['direction'] === 'up' ? '&#9650;' : ($comparison['errors']['direction'] === 'down' ? '&#9660;' : '&#8212;') !!}
                        {{ $comparison['errors']['pct'] }}% vs yesterday
                    </div>
                @endif
            </div>
            @foreach(['warning', 'info', 'debug'] as $tl)
                @if(($today_counts[$tl] ?? 0) > 0)
                    <div class="summary-card">
                        <div class="big-num" style="color: var(--{{ $levelClasses[$tl] }}-text);">{{ $today_counts[$tl] }}</div>
                        <div class="label">Today {{ ucfirst($tl) }}</div>
                    </div>
                @endif
            @endforeach
        </div>

        {{-- All Time Level Stats Cards --}}
        <h3 style="font-size: 15px; font-weight: 600; margin-bottom: 12px;">All Time</h3>
        <div class="stats-grid">
            @foreach($allLevels as $lvl)
                <div class="stat-card {{ $levelClasses[$lvl] }}">
                    <div class="stat-value">{{ number_format($global_counts[$lvl] ?? 0) }}</div>
                    <div class="stat-label">{{ ucfirst($lvl) }}</div>
                    @if(isset($percentages[$lvl]))
                        <div class="stat-pct">{{ $percentages[$lvl] }}%</div>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Charts --}}
        <div class="chart-section">
            <div class="chart-card">
                <h3>Distribution by Level</h3>
                <div class="chart-container">
                    <canvas id="levelChart"></canvas>
                </div>
            </div>
            <div class="chart-card">
                <h3>Entries per File</h3>
                <div class="chart-container">
                    <canvas id="fileChart"></canvas>
                </div>
            </div>
        </div>

        {{-- Files Table --}}
        <h3 style="font-size: 15px; font-weight: 600; margin-bottom: 12px;">Log Files Overview</h3>
        <table class="files-table">
            <thead>
                <tr>
                    <th>File</th>
                    <th>Entries</th>
                    <th>Size</th>
                    <th>Last Modified</th>
                    <th>Distribution</th>
                </tr>
            </thead>
            <tbody>
                @forelse($per_file_counts as $filename => $info)
                    <tr>
                        <td><a href="{{ route('logman.index', ['file' => $filename]) }}">{{ $filename }}</a></td>
                        <td>{{ number_format($info['total']) }}</td>
                        <td>{{ $info['size'] }}</td>
                        <td style="color: var(--text-muted);">{{ $info['modified'] }}</td>
                        <td>
                            @if($info['total'] > 0)
                                <div class="mini-bar">
                                    @php $colors = ['emergency'=>'#7f1d1d','alert'=>'#991b1b','critical'=>'#dc2626','error'=>'#e11d48','warning'=>'#f59e0b','notice'=>'#06b6d4','info'=>'#3b82f6','debug'=>'#22c55e']; @endphp
                                    @foreach($colors as $lvl => $color)
                                        @if(($info['counts'][$lvl] ?? 0) > 0)
                                            <span style="background:{{ $color }};flex:{{ $info['counts'][$lvl] }}" title="{{ ucfirst($lvl) }}: {{ $info['counts'][$lvl] }}"></span>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:24px;">No log files found</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<script>
@include('logman::partials.theme-js')

// Charts
(function() {
    const chartData = @json($chart_data);
    const fileData = @json($per_file_counts);

    function drawDoughnut(canvasId, labels, data, colors) {
        const canvas = document.getElementById(canvasId);
        if (!canvas || data.length === 0) return;
        const ctx = canvas.getContext('2d');
        const size = Math.min(canvas.parentElement.offsetWidth, canvas.parentElement.offsetHeight) - 20;
        canvas.width = size; canvas.height = size;
        const cx = size / 2, cy = size / 2, r = size * 0.38, inner = size * 0.22;
        const total = data.reduce((a, b) => a + b, 0);
        let angle = -Math.PI / 2;

        data.forEach((val, i) => {
            const slice = (val / total) * Math.PI * 2;
            ctx.beginPath();
            ctx.arc(cx, cy, r, angle, angle + slice);
            ctx.arc(cx, cy, inner, angle + slice, angle, true);
            ctx.closePath();
            ctx.fillStyle = colors[i];
            ctx.fill();
            // Label
            if (val / total > 0.05) {
                const mid = angle + slice / 2;
                const lx = cx + Math.cos(mid) * (r + inner) / 2;
                const ly = cy + Math.sin(mid) * (r + inner) / 2;
                ctx.fillStyle = '#fff';
                ctx.font = 'bold 11px sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(Math.round(val / total * 100) + '%', lx, ly);
            }
            angle += slice;
        });

        // Center text
        ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--text').trim();
        ctx.font = 'bold 20px sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(total.toLocaleString(), cx, cy - 8);
        ctx.font = '11px sans-serif';
        ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--text-muted').trim();
        ctx.fillText('total', cx, cy + 10);

        // Legend
        const legend = document.createElement('div');
        legend.style.cssText = 'display:flex;flex-wrap:wrap;gap:8px;justify-content:center;margin-top:8px;font-size:11px;';
        labels.forEach((l, i) => {
            legend.innerHTML += `<span style="display:inline-flex;align-items:center;gap:4px;"><span style="width:10px;height:10px;border-radius:50%;background:${colors[i]};display:inline-block;"></span>${l}</span>`;
        });
        canvas.parentElement.appendChild(legend);
    }

    function drawBarChart(canvasId, fileData) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return;
        const entries = Object.entries(fileData);
        if (entries.length === 0) return;
        const ctx = canvas.getContext('2d');
        const w = canvas.parentElement.offsetWidth - 20;
        const h = canvas.parentElement.offsetHeight - 20;
        canvas.width = w; canvas.height = h;

        const max = Math.max(...entries.map(([,v]) => v.total), 1);
        const barWidth = Math.min(40, (w - 60) / entries.length - 4);
        const startX = 50;
        const bottomY = h - 40;
        const chartH = bottomY - 20;

        // Grid
        ctx.strokeStyle = getComputedStyle(document.documentElement).getPropertyValue('--border').trim();
        ctx.lineWidth = 0.5;
        for (let i = 0; i <= 4; i++) {
            const y = bottomY - (chartH * i / 4);
            ctx.beginPath(); ctx.moveTo(startX, y); ctx.lineTo(w, y); ctx.stroke();
            ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--text-muted').trim();
            ctx.font = '10px sans-serif';
            ctx.textAlign = 'right';
            ctx.fillText(Math.round(max * i / 4).toLocaleString(), startX - 6, y + 4);
        }

        entries.forEach(([name, info], i) => {
            const x = startX + i * (barWidth + 4) + 2;
            const barH = (info.total / max) * chartH;
            ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--primary').trim();
            ctx.beginPath();
            ctx.roundRect(x, bottomY - barH, barWidth, barH, [3, 3, 0, 0]);
            ctx.fill();

            // Label
            ctx.save();
            ctx.translate(x + barWidth / 2, bottomY + 6);
            ctx.rotate(Math.PI / 4);
            ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--text-muted').trim();
            ctx.font = '9px sans-serif';
            ctx.textAlign = 'left';
            const short = name.length > 16 ? name.substr(0, 14) + '..' : name;
            ctx.fillText(short, 0, 0);
            ctx.restore();
        });
    }

    drawDoughnut('levelChart', chartData.labels, chartData.data, chartData.colors);
    drawBarChart('fileChart', fileData);
})();
</script>
</body>
</html>
