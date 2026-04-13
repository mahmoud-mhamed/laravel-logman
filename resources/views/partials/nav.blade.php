{{-- Top Navigation --}}
<nav class="top-nav">
    <a href="{{ route('logman.index') }}" class="brand">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        Logman
    </a>

    <div class="nav-links">
        <a href="{{ route('logman.index') }}"
           class="{{ request()->routeIs('logman.index') ? 'active' : '' }}">
            <span class="nav-text">Logs</span>
        </a>
        <a href="{{ route('logman.dashboard') }}"
           class="{{ request()->routeIs('logman.dashboard') ? 'active' : '' }}">
            <span class="nav-text">Analysis</span>
        </a>
        <a href="{{ route('logman.mutes') }}"
           class="{{ request()->routeIs('logman.mutes') ? 'active' : '' }}">
            <span class="nav-text">Mutes</span>
        </a>
        <a href="{{ route('logman.throttles') }}"
           class="{{ request()->routeIs('logman.throttles') ? 'active' : '' }}">
            <span class="nav-text">Throttles</span>
        </a>
        <a href="{{ route('logman.config') }}"
           class="{{ request()->routeIs('logman.config') ? 'active' : '' }}">
            <span class="nav-text">Config</span>
        </a>
    </div>

    <div class="nav-right">
        <span class="nav-env">{{ config('app.name') }} &middot; {{ app()->environment() }}</span>
        <button class="theme-toggle" onclick="toggleTheme()" title="Toggle dark mode">
            <span id="theme-icon"></span>
        </button>
    </div>
</nav>
