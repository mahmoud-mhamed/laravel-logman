<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Logman</title>
    @include('logman::partials.styles')
    <style>
        .login-wrapper { display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px; }
        .login-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-lg); box-shadow: var(--shadow-lg); padding: 40px; width: 100%; max-width: 400px; }
        .login-brand { display: flex; align-items: center; justify-content: center; gap: 10px; margin-bottom: 32px; }
        .login-brand svg { width: 28px; height: 28px; color: var(--primary); }
        .login-brand span { font-size: 22px; font-weight: 700; color: var(--primary); letter-spacing: -0.02em; }
        .login-label { display: block; font-size: 13px; font-weight: 600; color: var(--text-muted); margin-bottom: 8px; }
        .login-input { width: 100%; padding: 10px 14px; border: 1px solid var(--border); border-radius: var(--radius-sm); background: var(--bg); color: var(--text); font-size: 14px; outline: none; transition: border-color 0.2s; }
        .login-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-glow); }
        .login-submit { width: 100%; padding: 10px 14px; margin-top: 20px; background: var(--primary); color: white; border: none; border-radius: var(--radius-sm); font-size: 14px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        .login-submit:hover { background: var(--primary-hover); }
        .login-error { color: var(--danger-text); background: var(--danger-bg); border: 1px solid var(--danger-border); padding: 8px 12px; border-radius: var(--radius-sm); font-size: 13px; margin-bottom: 16px; }
        .login-footer { text-align: center; margin-top: 24px; font-size: 12px; color: var(--text-light); }
    </style>
    <script>@include('logman::partials.theme-js')</script>
</head>
<body>
<div class="login-wrapper">
    <div class="login-card">
        <div class="login-brand">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>
            </svg>
            <span>Logman</span>
        </div>

        @if ($errors->any())
            <div class="login-error">{{ $errors->first('password') }}</div>
        @endif

        <form method="POST" action="{{ route('logman.login.submit') }}">
            @csrf
            <label class="login-label" for="password">Password</label>
            <input class="login-input" type="password" name="password" id="password" placeholder="Enter password" autofocus required>
            <button class="login-submit" type="submit">Login</button>
        </form>

        <div class="login-footer">
            <button onclick="toggleTheme()" style="background:none;border:none;cursor:pointer;color:var(--text-light);font-size:12px;">
                <span id="theme-icon"></span> Toggle theme
            </button>
        </div>
    </div>
</div>
</body>
</html>
