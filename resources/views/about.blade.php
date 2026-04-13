<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About - {{ config('app.name') }}</title>
    @include('logman::partials.styles')
    <style>
        .about-page { padding: 24px; max-width: 1000px; margin: 0 auto; flex: 1; overflow-y: auto; scrollbar-width: none; width: 100%; }
        .about-page::-webkit-scrollbar { display: none; }
        .about-hero { text-align: center; padding: 40px 20px 32px; }
        .about-hero h1 { font-size: 32px; font-weight: 800; color: var(--primary); letter-spacing: -0.02em; }
        .about-hero p { font-size: 14px; color: var(--text-muted); margin-top: 6px; max-width: 500px; margin-left: auto; margin-right: auto; }
        .about-hero .version-badge { display: inline-block; margin-top: 10px; padding: 4px 14px; font-size: 12px; font-weight: 600; background: var(--primary-light); color: var(--primary); border-radius: 20px; font-family: var(--font-mono); }
        .about-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px; }
        @media (max-width: 640px) { .about-grid { grid-template-columns: 1fr; } }
        .about-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 20px; }
        .about-card h3 { font-size: 14px; font-weight: 700; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
        .about-card h3 .card-icon { width: 18px; height: 18px; color: var(--primary); }
        .about-section { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 20px; margin-bottom: 16px; }
        .about-section h3 { font-size: 14px; font-weight: 700; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
        .about-section h3 .card-icon { width: 18px; height: 18px; color: var(--primary); }
        .env-row { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid var(--border-light); font-size: 13px; }
        .env-row:last-child { border-bottom: none; }
        .env-row .env-label { color: var(--text-muted); font-weight: 500; }
        .env-row .env-value { font-family: var(--font-mono); font-weight: 600; }
        .channel-list { display: flex; flex-direction: column; gap: 8px; }
        .channel-item { display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; border-radius: var(--radius-sm); border: 1px solid var(--border-light); }
        .channel-item.is-active { background: var(--debug-bg); border-color: var(--debug-border); }
        .channel-item.is-inactive { opacity: 0.5; }
        .channel-name { font-size: 13px; font-weight: 600; }
        .channel-badge { font-size: 10px; padding: 2px 8px; border-radius: 10px; font-weight: 700; text-transform: uppercase; }
        .channel-badge.on { background: var(--debug-text); color: #fff; }
        .channel-badge.off { background: var(--border); color: var(--text-light); }
        .cmd-list { display: flex; flex-direction: column; gap: 6px; }
        .cmd-item { display: flex; align-items: baseline; gap: 10px; padding: 8px 12px; background: var(--bg); border-radius: var(--radius-sm); }
        .cmd-item code { font-family: var(--font-mono); font-size: 12px; font-weight: 600; color: var(--primary); white-space: nowrap; }
        .cmd-item .cmd-desc { font-size: 12px; color: var(--text-muted); }
        .feature-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        @media (max-width: 640px) { .feature-grid { grid-template-columns: 1fr; } }
        .feature-item { padding: 10px 14px; background: var(--bg); border-radius: var(--radius-sm); font-size: 12px; font-weight: 500; color: var(--text); display: flex; align-items: center; gap: 8px; }
        .feature-item .fi-icon { color: var(--debug-text); font-size: 14px; flex-shrink: 0; }
        .quick-links { display: flex; gap: 8px; flex-wrap: wrap; }
        .quick-link { padding: 8px 16px; border: 1px solid var(--border); border-radius: var(--radius-sm); font-size: 13px; font-weight: 500; color: var(--text); text-decoration: none; transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px; }
        .quick-link:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-light); }
    </style>
</head>
<body>
<div class="layout">
    @include('logman::partials.nav')

    <div class="about-page">
        {{-- Hero --}}
        <div class="about-hero">
            <h1>Logman</h1>
            <p>Error reporting & log management for Laravel — automatic exception notifications with a powerful built-in log viewer.</p>
            <span class="version-badge">v{{ $version }}</span>
        </div>

        {{-- Quick Links --}}
        <div class="about-section">
            <h3>
                <svg class="card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                Quick Links
            </h3>
            <div class="quick-links">
                <a href="{{ route('logman.index') }}" class="quick-link">Logs</a>
                <a href="{{ route('logman.dashboard') }}" class="quick-link">Analysis</a>
                <a href="{{ route('logman.mutes') }}" class="quick-link">Mutes</a>
                <a href="{{ route('logman.throttles') }}" class="quick-link">Throttles</a>
                <a href="{{ route('logman.config') }}" class="quick-link">Config</a>
            </div>
        </div>

        {{-- Environment + Channels --}}
        <div class="about-grid">
            <div class="about-card">
                <h3>
                    <svg class="card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                    Environment
                </h3>
                <div class="env-row">
                    <span class="env-label">Logman</span>
                    <span class="env-value">v{{ $version }}</span>
                </div>
                <div class="env-row">
                    <span class="env-label">Laravel</span>
                    <span class="env-value">{{ $laravelVersion }}</span>
                </div>
                <div class="env-row">
                    <span class="env-label">PHP</span>
                    <span class="env-value">{{ $phpVersion }}</span>
                </div>
                <div class="env-row">
                    <span class="env-label">Environment</span>
                    <span class="env-value">{{ $environment }}</span>
                </div>
                <div class="env-row">
                    <span class="env-label">Auto-Report</span>
                    <span class="env-value" style="color:{{ $config['auto_report_exceptions'] ? 'var(--debug-text)' : 'var(--danger-text)' }}">{{ $config['auto_report_exceptions'] ? 'Enabled' : 'Disabled' }}</span>
                </div>
                <div class="env-row">
                    <span class="env-label">Rate Limiting</span>
                    <span class="env-value" style="color:{{ $config['rate_limit']['enabled'] ?? true ? 'var(--debug-text)' : 'var(--danger-text)' }}">{{ ($config['rate_limit']['enabled'] ?? true) ? ($config['rate_limit']['cooldown_seconds'] ?? 10) . 's cooldown' : 'Disabled' }}</span>
                </div>
            </div>

            <div class="about-card">
                <h3>
                    <svg class="card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2L11 13"/><path d="M22 2L15 22L11 13L2 9L22 2Z"/></svg>
                    Notification Channels
                </h3>
                <div class="channel-list">
                    @foreach($allChannels as $name => $ch)
                        @php $isOn = !empty($ch['enabled']); @endphp
                        <div class="channel-item {{ $isOn ? 'is-active' : 'is-inactive' }}">
                            <span class="channel-name">{{ ucfirst($name) }}</span>
                            <div style="display:flex;align-items:center;gap:6px;">
                                @if($isOn && !empty($ch['auto_report_exceptions']))
                                    <span style="font-size:10px;color:var(--text-muted);">auto</span>
                                @endif
                                <span class="channel-badge {{ $isOn ? 'on' : 'off' }}">{{ $isOn ? 'Active' : 'Off' }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Artisan Commands --}}
        <div class="about-section">
            <h3>
                <svg class="card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 17 10 11 4 5"/><line x1="12" y1="19" x2="20" y2="19"/></svg>
                Artisan Commands
            </h3>
            <div class="cmd-list">
                <div class="cmd-item">
                    <code>logman:test</code>
                    <span class="cmd-desc">Send a test notification to all enabled channels</span>
                </div>
                <div class="cmd-item">
                    <code>logman:digest</code>
                    <span class="cmd-desc">Send a daily digest summary (use --date, --channel)</span>
                </div>
                <div class="cmd-item">
                    <code>logman:mute "Class"</code>
                    <span class="cmd-desc">Mute an exception (use --duration, --pattern, --reason)</span>
                </div>
                <div class="cmd-item">
                    <code>logman:list-mutes</code>
                    <span class="cmd-desc">List all active mutes in a table</span>
                </div>
                <div class="cmd-item">
                    <code>logman:clear-mutes</code>
                    <span class="cmd-desc">Remove all active mutes</span>
                </div>
            </div>
        </div>

        {{-- Features --}}
        <div class="about-section">
            <h3>
                <svg class="card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                Features
            </h3>
            <div class="feature-grid">
                <div class="feature-item"><span class="fi-icon">&#10003;</span> Multi-channel notifications (Slack, Telegram, Discord, Mail)</div>
                <div class="feature-item"><span class="fi-icon">&#10003;</span> Custom channel drivers</div>
                <div class="feature-item"><span class="fi-icon">&#10003;</span> Automatic exception reporting</div>
                <div class="feature-item"><span class="fi-icon">&#10003;</span> Rich error details (trace, request, auth, queries)</div>
                <div class="feature-item"><span class="fi-icon">&#10003;</span> Log viewer with search, regex, filters</div>
                <div class="feature-item"><span class="fi-icon">&#10003;</span> Analysis dashboard with charts</div>
                <div class="feature-item"><span class="fi-icon">&#10003;</span> Mute & throttle system</div>
                <div class="feature-item"><span class="fi-icon">&#10003;</span> Rate limiting per exception</div>
                <div class="feature-item"><span class="fi-icon">&#10003;</span> Review system with notes</div>
                <div class="feature-item"><span class="fi-icon">&#10003;</span> Send logs to channels from UI</div>
                <div class="feature-item"><span class="fi-icon">&#10003;</span> Daily digest command</div>
                <div class="feature-item"><span class="fi-icon">&#10003;</span> Comparison with yesterday</div>
                <div class="feature-item"><span class="fi-icon">&#10003;</span> Search history</div>
                <div class="feature-item"><span class="fi-icon">&#10003;</span> Dark mode</div>
                <div class="feature-item"><span class="fi-icon">&#10003;</span> Authorization callback</div>
                <div class="feature-item"><span class="fi-icon">&#10003;</span> Zero external dependencies</div>
            </div>
        </div>

        {{-- Usage --}}
        <div class="about-section">
            <h3>
                <svg class="card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                Quick Usage
            </h3>
            <div class="cmd-list">
                <div class="cmd-item">
                    <code>Logman::logException($e)</code>
                    <span class="cmd-desc">Manually report an exception to all enabled channels</span>
                </div>
                <div class="cmd-item">
                    <code>Logman::sendInfo('message')</code>
                    <span class="cmd-desc">Send an info message to all enabled channels</span>
                </div>
            </div>
            <div style="margin-top:12px;padding:12px 14px;background:var(--bg);border-radius:var(--radius-sm);font-size:12px;color:var(--text-muted);font-family:var(--font-mono);line-height:1.8;">
                <span style="color:var(--text-light);">// Automatic — no code needed</span><br>
                <span style="color:var(--text-light);">// Every unhandled exception is reported automatically</span><br>
                <span style="color:var(--text-light);">// when auto_report_exceptions is enabled.</span><br><br>
                <span style="color:var(--text-light);">// Manual reporting:</span><br>
                <span style="color:var(--info-text);">use</span> Mhamed\Logman\Facades\Logman;<br><br>
                <span style="color:var(--info-text);">try</span> {<br>
                &nbsp;&nbsp;&nbsp;&nbsp;<span style="color:var(--text-light);">// risky operation...</span><br>
                } <span style="color:var(--info-text);">catch</span> (\Throwable <span style="color:var(--warning-text);">$e</span>) {<br>
                &nbsp;&nbsp;&nbsp;&nbsp;Logman::<span style="color:var(--primary);">logException</span>(<span style="color:var(--warning-text);">$e</span>);<br>
                }
            </div>
        </div>

        {{-- Footer --}}
        <div style="text-align:center;padding:24px 0;font-size:12px;color:var(--text-light);">
            Logman v{{ $version }} &middot; Laravel {{ $laravelVersion }} &middot; PHP {{ $phpVersion }}
        </div>
    </div>
</div>

<script>
@include('logman::partials.theme-js')
</script>
</body>
</html>
