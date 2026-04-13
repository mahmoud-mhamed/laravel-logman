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
        .about-hero p { font-size: 14px; color: var(--text-muted); margin-top: 6px; max-width: 500px; margin-left: auto; margin-right: auto; line-height: 1.6; }
        .about-hero .version-badge { display: inline-block; margin-top: 10px; padding: 4px 14px; font-size: 12px; font-weight: 600; background: var(--primary-light); color: var(--primary); border-radius: 20px; font-family: var(--font-mono); }
        .about-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
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
        .channel-item { display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; border-radius: var(--radius-sm); border: 1px solid var(--border-light); transition: all 0.15s; }
        .channel-item.is-active { background: var(--debug-bg); border-color: var(--debug-border); }
        .channel-item.is-inactive { opacity: 0.5; }
        .channel-name { font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 8px; }
        .channel-name svg { width: 14px; height: 14px; opacity: 0.6; }
        .channel-tags { display: flex; align-items: center; gap: 6px; }
        .channel-tag { font-size: 10px; padding: 1px 6px; border-radius: 8px; font-weight: 600; }
        .channel-tag.auto { background: var(--debug-bg); color: var(--debug-text); }
        .channel-tag.digest { background: var(--info-bg); color: var(--info-text); }
        .channel-tag.throttle { background: var(--warning-bg); color: var(--warning-text); }
        .channel-tag.level { background: var(--bg); color: var(--text-muted); }
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
        .quick-link { padding: 8px 16px; border: 1px solid var(--border); border-radius: var(--radius-sm); font-size: 13px; font-weight: 500; color: var(--text); text-decoration: none; transition: all 0.2s; display: inline-flex; align-items: center; gap: 7px; }
        .quick-link:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-light); }
        .quick-link svg { width: 14px; height: 14px; opacity: 0.6; }
        .quick-link:hover svg { opacity: 1; }
        .rl-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        @media (max-width: 640px) { .rl-grid { grid-template-columns: 1fr; } }
        .rl-card { padding: 14px; background: var(--bg); border-radius: var(--radius-sm); }
        .rl-card-title { font-size: 12px; font-weight: 700; margin-bottom: 6px; }
        .rl-card-desc { font-size: 11px; color: var(--text-muted); line-height: 1.6; }
        .rl-card-status { margin-top: 8px; font-family: var(--font-mono); font-size: 11px; }
        .flow-bar { margin-top: 12px; padding: 10px 14px; background: var(--bg); border-radius: var(--radius-sm); font-size: 11px; color: var(--text-muted); font-family: var(--font-mono); line-height: 1.8; text-align: center; }
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
                <a href="{{ route('logman.index') }}" class="quick-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    Logs
                </a>
                <a href="{{ route('logman.dashboard') }}" class="quick-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                    Analysis
                </a>
                <a href="{{ route('logman.mutes') }}" class="quick-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="1" y1="1" x2="23" y2="23"/><path d="M9 9v3a3 3 0 0 0 5.12 2.12M15 9.34V4a3 3 0 0 0-5.94-.6"/><path d="M17 16.95A7 7 0 0 1 5 12v-2m14 0v2c0 .76-.13 1.49-.35 2.17"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>
                    Mutes
                </a>
                <a href="{{ route('logman.throttles') }}" class="quick-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    Throttles
                </a>
                <a href="{{ route('logman.grouped') }}" class="quick-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                    Grouped
                </a>
                <a href="{{ route('logman.bookmarks') }}" class="quick-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
                    Bookmarks
                </a>
                <a href="{{ route('logman.config') }}" class="quick-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                    Config
                </a>
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
                <div class="env-row">
                    <span class="env-label">Daily Digest</span>
                    <span class="env-value" style="color:{{ !empty($config['daily_digest']['enabled']) ? 'var(--debug-text)' : 'var(--danger-text)' }}">{{ !empty($config['daily_digest']['enabled']) ? 'Enabled at ' . ($config['daily_digest']['time'] ?? '09:00') : 'Disabled' }}</span>
                </div>
                <div class="env-row">
                    <span class="env-label">Log Viewer</span>
                    <span class="env-value" style="color:{{ $config['log_viewer']['enabled'] ?? true ? 'var(--debug-text)' : 'var(--danger-text)' }}">{{ ($config['log_viewer']['enabled'] ?? true) ? '/' . ($config['log_viewer']['route_prefix'] ?? 'logman') : 'Disabled' }}</span>
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
                            <span class="channel-name">
                                @switch($name)
                                    @case('slack')
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 10c-.83 0-1.5-.67-1.5-1.5v-5c0-.83.67-1.5 1.5-1.5s1.5.67 1.5 1.5v5c0 .83-.67 1.5-1.5 1.5z"/><path d="M20.5 10H19V8.5c0-.83.67-1.5 1.5-1.5s1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/><path d="M9.5 14c.83 0 1.5.67 1.5 1.5v5c0 .83-.67 1.5-1.5 1.5S8 21.33 8 20.5v-5c0-.83.67-1.5 1.5-1.5z"/><path d="M3.5 14H5v1.5c0 .83-.67 1.5-1.5 1.5S2 16.33 2 15.5 2.67 14 3.5 14z"/><path d="M14 14.5c0-.83.67-1.5 1.5-1.5h5c.83 0 1.5.67 1.5 1.5s-.67 1.5-1.5 1.5h-5c-.83 0-1.5-.67-1.5-1.5z"/><path d="M14 20.5c0-.83.67-1.5 1.5-1.5H17v1.5c0 .83-.67 1.5-1.5 1.5s-1.5-.67-1.5-1.5z"/><path d="M10 9.5C10 10.33 9.33 11 8.5 11h-5C2.67 11 2 10.33 2 9.5S2.67 8 3.5 8h5c.83 0 1.5.67 1.5 1.5z"/><path d="M8 3.5C8 2.67 8.67 2 9.5 2S11 2.67 11 3.5V5H9.5C8.67 5 8 4.33 8 3.5z"/></svg>
                                        @break
                                    @case('telegram')
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2L11 13"/><path d="M22 2L15 22L11 13L2 9L22 2Z"/></svg>
                                        @break
                                    @case('discord')
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 9a5 5 0 0 0-4.28-2A12.08 12.08 0 0 0 8 9"/><path d="M6 15a5 5 0 0 0 4.28 2 12.08 12.08 0 0 0 5.72-2"/><circle cx="9" cy="12" r="1"/><circle cx="15" cy="12" r="1"/><path d="M3 19c0 1.1.9 2 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14z"/></svg>
                                        @break
                                    @case('mail')
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                                        @break
                                    @default
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/></svg>
                                @endswitch
                                {{ ucfirst($name) }}
                            </span>
                            <div class="channel-tags">
                                @if($isOn)
                                    <span class="channel-tag level">{{ $ch['min_level'] ?? 'debug' }}</span>
                                    @if(!empty($ch['auto_report_exceptions']))
                                        <span class="channel-tag auto">auto</span>
                                    @endif
                                    @if(!empty($ch['daily_digest']))
                                        <span class="channel-tag digest">digest</span>
                                    @endif
                                    @if(($ch['throttle'] ?? 0) > 0)
                                        <span class="channel-tag throttle">{{ $ch['throttle'] }}s</span>
                                    @endif
                                @endif
                                <span class="channel-badge {{ $isOn ? 'on' : 'off' }}">{{ $isOn ? 'Active' : 'Off' }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Storage Health --}}
        <div class="about-section">
            <h3>
                <svg class="card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12H2"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/><line x1="6" y1="16" x2="6.01" y2="16"/><line x1="10" y1="16" x2="10.01" y2="16"/></svg>
                Storage Health
            </h3>
            <p style="font-size:12px;color:var(--text-muted);margin-bottom:12px;">Data files used at runtime. Files over 5 MB are automatically reset to prevent performance impact.</p>
            <div style="display:flex;flex-direction:column;gap:6px;">
                @foreach($storageSizes as $filename => $size)
                    @php
                        $sizeKb = round($size / 1024, 1);
                        $sizeMb = round($size / 1024 / 1024, 2);
                        $display = $size < 1024 ? $size . ' B' : ($size < 1048576 ? $sizeKb . ' KB' : $sizeMb . ' MB');
                        $percent = min(100, ($size / (5 * 1024 * 1024)) * 100);
                        $barColor = $percent < 50 ? 'var(--debug-text)' : ($percent < 80 ? 'var(--warning-text)' : 'var(--danger-text)');
                    @endphp
                    <div style="padding:8px 12px;background:var(--bg);border-radius:var(--radius-sm);display:flex;align-items:center;gap:12px;">
                        <code style="font-size:11px;color:var(--primary);min-width:120px;">{{ $filename }}</code>
                        <div style="flex:1;height:6px;background:var(--border);border-radius:3px;overflow:hidden;">
                            <div style="height:100%;width:{{ max(1, $percent) }}%;background:{{ $barColor }};border-radius:3px;transition:width 0.3s;"></div>
                        </div>
                        <span style="font-family:var(--font-mono);font-size:11px;color:{{ $barColor }};min-width:60px;text-align:right;">{{ $display }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Rate Limiting & Throttling --}}
        <div class="about-section">
            <h3>
                <svg class="card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                Rate Limiting & Throttling
            </h3>
            <p style="font-size:12px;color:var(--text-muted);margin-bottom:14px;">Two levels of protection to prevent notification flooding:</p>
            <div class="rl-grid">
                <div class="rl-card" style="border-left:3px solid var(--primary);">
                    <div class="rl-card-title">Level 1: Global Rate Limit</div>
                    <div class="rl-card-desc">
                        Runs <strong>first</strong>, before any channel is contacted. Blocks the same exception (class + file + line) for <strong>all channels</strong> within the cooldown window.
                    </div>
                    <div class="rl-card-status">
                        <span style="color:var(--text-muted);">Status:</span>
                        <span style="color:{{ $config['rate_limit']['enabled'] ?? true ? 'var(--debug-text)' : 'var(--danger-text)' }}">
                            {{ ($config['rate_limit']['enabled'] ?? true) ? 'Enabled — ' . ($config['rate_limit']['cooldown_seconds'] ?? 10) . 's cooldown' : 'Disabled' }}
                        </span>
                    </div>
                </div>
                <div class="rl-card" style="border-left:3px solid var(--info-text);">
                    <div class="rl-card-title">Level 2: Per-Channel Throttle</div>
                    <div class="rl-card-desc">
                        Runs <strong>second</strong>, independently per channel. Allows different cooldowns — e.g. Slack every 1s, Mail every 60s.
                    </div>
                    <div class="rl-card-status" style="display:flex;flex-wrap:wrap;gap:6px 14px;">
                        @foreach($allChannels as $name => $ch)
                            <span>
                                <span style="color:var(--text-muted);">{{ ucfirst($name) }}:</span>
                                <span style="color:{{ !empty($ch['enabled']) ? 'var(--debug-text)' : 'var(--text-light)' }}">{{ $ch['throttle'] ?? 0 }}s</span>
                            </span>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="flow-bar">
                Exception &rarr; <span style="color:var(--primary);">Global Rate Limit</span> (blocked? &rarr; stop all)
                &rarr; <span style="color:var(--info-text);">Per-Channel Throttle</span> (blocked? &rarr; skip this channel)
                &rarr; Send
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
                    <code>logman:install</code>
                    <span class="cmd-desc">Publish config, create storage, add env variables</span>
                </div>
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
                <div class="feature-item"><span class="fi-icon">&#10003;</span> Two-level rate limiting (global + per-channel)</div>
                <div class="feature-item"><span class="fi-icon">&#10003;</span> Review system with notes</div>
                <div class="feature-item"><span class="fi-icon">&#10003;</span> Send logs to channels from UI</div>
                <div class="feature-item"><span class="fi-icon">&#10003;</span> Auto daily digest (per-channel control)</div>
                <div class="feature-item"><span class="fi-icon">&#10003;</span> Grouped exceptions view</div>
                <div class="feature-item"><span class="fi-icon">&#10003;</span> Bookmark & export logs</div>
                <div class="feature-item"><span class="fi-icon">&#10003;</span> Comparison with yesterday</div>
                <div class="feature-item"><span class="fi-icon">&#10003;</span> Search history</div>
                <div class="feature-item"><span class="fi-icon">&#10003;</span> Dark mode</div>
                <div class="feature-item"><span class="fi-icon">&#10003;</span> Authorization callback</div>
                <div class="feature-item"><span class="fi-icon">&#10003;</span> Zero external dependencies</div>
            </div>
        </div>

        {{-- Quick Usage --}}
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
