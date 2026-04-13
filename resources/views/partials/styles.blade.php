<style>
    :root {
        --bg: #f4f6f9; --bg-card: #ffffff; --bg-sidebar: #fbfcfd;
        --text: #1a1d26; --text-muted: #6b7280; --text-light: #9ca3af;
        --border: #e5e7eb; --border-light: #f0f1f3;
        --primary: #4f6ef7; --primary-hover: #3b5de7; --primary-light: rgba(79,110,247,0.08); --primary-glow: rgba(79,110,247,0.15);
        --hover: rgba(0,0,0,0.03);
        --shadow-sm: 0 1px 2px rgba(0,0,0,0.04); --shadow: 0 2px 8px rgba(0,0,0,0.06); --shadow-lg: 0 8px 24px rgba(0,0,0,0.08);
        --danger-bg: #fef2f2; --danger-text: #dc2626; --danger-border: #fecaca;
        --error-bg: #fff5f5; --error-text: #e11d48; --error-border: #fecdd3;
        --warning-bg: #fffbeb; --warning-text: #d97706; --warning-border: #fde68a;
        --info-bg: #eff6ff; --info-text: #2563eb; --info-border: #bfdbfe;
        --debug-bg: #f0fdf4; --debug-text: #16a34a; --debug-border: #bbf7d0;
        --success-bg: #ecfdf5; --success-text: #059669;
        --radius: 10px; --radius-sm: 6px; --radius-lg: 14px;
        --font-mono: 'JetBrains Mono', 'SF Mono', 'Fira Code', 'Cascadia Code', monospace;
    }
    [data-theme="dark"] {
        --bg: #0c0f1a; --bg-card: #151929; --bg-sidebar: #111525;
        --text: #e4e7ef; --text-muted: #8b92a8; --text-light: #5a6178;
        --border: #232840; --border-light: #1d2237;
        --primary-light: rgba(79,110,247,0.12); --primary-glow: rgba(79,110,247,0.2);
        --hover: rgba(255,255,255,0.04);
        --shadow-sm: 0 1px 2px rgba(0,0,0,0.2); --shadow: 0 2px 8px rgba(0,0,0,0.3); --shadow-lg: 0 8px 24px rgba(0,0,0,0.4);
        --danger-bg: #1f0a0a; --danger-text: #f87171; --danger-border: #3b1111;
        --error-bg: #1f0a10; --error-text: #fb7185; --error-border: #3b1120;
        --warning-bg: #1f1506; --warning-text: #fbbf24; --warning-border: #3b2a0a;
        --info-bg: #0a1528; --info-text: #60a5fa; --info-border: #1e3a5f;
        --debug-bg: #051a0e; --debug-text: #4ade80; --debug-border: #14532d;
        --success-bg: #051a0e; --success-text: #34d399;
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: var(--bg); color: var(--text); font-size: 14px; line-height: 1.5; -webkit-font-smoothing: antialiased; }
    .layout { display: flex; flex-direction: column; height: 100vh; overflow: hidden; }

    /* Top Nav */
    .top-nav { display: flex; align-items: center; gap: 12px; padding: 0 20px; height: 52px; background: var(--bg-card); border-bottom: 1px solid var(--border); flex-shrink: 0; box-shadow: var(--shadow-sm); position: relative; z-index: 10; overflow-x: auto; }
    .top-nav .brand { font-weight: 700; font-size: 15px; color: var(--primary); text-decoration: none; display: flex; align-items: center; gap: 8px; letter-spacing: -0.01em; flex-shrink: 0; }
    .top-nav .brand svg { width: 20px; height: 20px; opacity: 0.9; }
    .nav-links { display: flex; gap: 2px; margin-left: 20px; background: var(--bg); border-radius: var(--radius-sm); padding: 3px; flex-shrink: 0; }
    .nav-links a { padding: 5px 16px; border-radius: 5px; font-size: 13px; font-weight: 500; color: var(--text-muted); text-decoration: none; transition: all 0.2s ease; white-space: nowrap; }
    .nav-links a:hover { color: var(--text); }
    .nav-links a.active { background: var(--bg-card); color: var(--primary); box-shadow: var(--shadow-sm); font-weight: 600; }
    .nav-right { margin-left: auto; display: flex; align-items: center; gap: 10px; }
    .nav-env { font-size: 11px; color: var(--text-light); background: var(--bg); padding: 3px 10px; border-radius: 20px; font-weight: 500; }
    .theme-toggle { background: var(--bg); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 6px 10px; cursor: pointer; color: var(--text-muted); font-size: 14px; line-height: 1; transition: all 0.2s; }
    .theme-toggle:hover { background: var(--hover); border-color: var(--primary); color: var(--primary); }

    /* Buttons */
    .btn { padding: 7px 14px; border: 1px solid var(--border); border-radius: var(--radius-sm); font-size: 13px; cursor: pointer; background: var(--bg-card); color: var(--text); text-decoration: none; display: inline-flex; align-items: center; gap: 5px; transition: all 0.2s ease; white-space: nowrap; font-weight: 500; }
    .btn:hover { background: var(--hover); box-shadow: var(--shadow-sm); }
    .btn-sm { padding: 5px 10px; font-size: 12px; }
    .btn-primary { background: var(--primary); color: white; border-color: var(--primary); }
    .btn-primary:hover { background: var(--primary-hover); box-shadow: 0 2px 8px var(--primary-glow); }
    .btn-danger { color: var(--danger-text); border-color: var(--danger-border); }
    .btn-danger:hover { background: var(--danger-bg); }

    /* Flash */
    .flash { padding: 10px 16px; font-size: 13px; border-radius: var(--radius-sm); margin: 10px 16px; animation: flashIn 0.3s ease; font-weight: 500; display: flex; align-items: center; justify-content: space-between; gap: 12px; }
    .flash-success { background: var(--success-bg); color: var(--success-text); border: 1px solid var(--debug-border); }
    .flash-dismiss { background: none; border: none; cursor: pointer; color: inherit; opacity: 0.5; padding: 2px; line-height: 1; font-size: 16px; transition: opacity 0.2s; flex-shrink: 0; }
    .flash-dismiss:hover { opacity: 1; }
    @keyframes flashIn { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: none; } }

    /* Badges */
    .badge { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; }
    .badge-danger { background: var(--danger-bg); color: var(--danger-text); }
    .badge-error { background: var(--error-bg); color: var(--error-text); }
    .badge-warning { background: var(--warning-bg); color: var(--warning-text); }
    .badge-info { background: var(--info-bg); color: var(--info-text); }
    .badge-debug { background: var(--debug-bg); color: var(--debug-text); }

    @media (max-width: 768px) {
        .top-nav { gap: 8px; padding: 0 12px; }
        .nav-links { margin-left: 8px; }
        .nav-links a { padding: 5px 10px; font-size: 12px; }
        .nav-env { display: none; }
    }
</style>
