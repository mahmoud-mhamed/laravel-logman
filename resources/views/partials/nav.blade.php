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
        <a href="{{ route('logman.grouped') }}"
           class="{{ request()->routeIs('logman.grouped') ? 'active' : '' }}">
            <span class="nav-text">Grouped</span>
        </a>
        <a href="{{ route('logman.bookmarks') }}"
           class="{{ request()->routeIs('logman.bookmarks') ? 'active' : '' }}">
            <span class="nav-text">Bookmarks</span>
        </a>
        <a href="{{ route('logman.config') }}"
           class="{{ request()->routeIs('logman.config') ? 'active' : '' }}">
            <span class="nav-text">Config</span>
        </a>
        <a href="{{ route('logman.about') }}"
           class="{{ request()->routeIs('logman.about') ? 'active' : '' }}">
            <span class="nav-text">About</span>
        </a>
    </div>

    <div class="nav-right">
        <span class="nav-env">{{ config('app.name') }} &middot; {{ app()->environment() }}</span>
        <button class="nav-menu-btn" onclick="toggleNavMenu()" title="Menu">
            <svg id="nav-menu-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>
        <button class="theme-toggle" onclick="toggleTheme()" title="Toggle dark mode">
            <span id="theme-icon"></span>
        </button>
        @if(config('logman.viewer.password'))
            <form method="POST" action="{{ route('logman.logout') }}" style="margin:0;">
                @csrf
                <button type="submit" class="theme-toggle" title="Logout">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                </button>
            </form>
        @endif
    </div>
</nav>

{{-- Mobile dropdown --}}
<div class="nav-mobile-dropdown" id="navMobileDropdown">
    <a href="{{ route('logman.index') }}" class="{{ request()->routeIs('logman.index') ? 'active' : '' }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        Logs
    </a>
    <a href="{{ route('logman.dashboard') }}" class="{{ request()->routeIs('logman.dashboard') ? 'active' : '' }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
        Analysis
    </a>
    <a href="{{ route('logman.mutes') }}" class="{{ request()->routeIs('logman.mutes') ? 'active' : '' }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="1" y1="1" x2="23" y2="23"/><path d="M9 9v3a3 3 0 0 0 5.12 2.12M15 9.34V4a3 3 0 0 0-5.94-.6"/><path d="M17 16.95A7 7 0 0 1 5 12v-2m14 0v2c0 .76-.13 1.49-.35 2.17"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>
        Mutes
    </a>
    <a href="{{ route('logman.throttles') }}" class="{{ request()->routeIs('logman.throttles') ? 'active' : '' }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        Throttles
    </a>
    <a href="{{ route('logman.grouped') }}" class="{{ request()->routeIs('logman.grouped') ? 'active' : '' }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
        Grouped
    </a>
    <a href="{{ route('logman.bookmarks') }}" class="{{ request()->routeIs('logman.bookmarks') ? 'active' : '' }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
        Bookmarks
    </a>
    <a href="{{ route('logman.config') }}" class="{{ request()->routeIs('logman.config') ? 'active' : '' }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
        Config
    </a>
    <a href="{{ route('logman.about') }}" class="{{ request()->routeIs('logman.about') ? 'active' : '' }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
        About
    </a>
    @if(config('logman.viewer.password'))
        <form method="POST" action="{{ route('logman.logout') }}" style="margin:0;">
            @csrf
            <button type="submit" style="width:100;background:none;border:none;cursor:pointer;display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:var(--radius-sm);font-size:13px;font-weight:500;color:var(--danger-text);text-decoration:none;transition:all 0.15s;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                Logout
            </button>
        </form>
    @endif
</div>

<script>
function toggleNavMenu() {
    var dropdown = document.getElementById('navMobileDropdown');
    var icon = document.getElementById('nav-menu-icon');
    var isOpen = dropdown.classList.toggle('open');
    if (isOpen) {
        icon.innerHTML = '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>';
    } else {
        icon.innerHTML = '<line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>';
    }
}

document.addEventListener('click', function(e) {
    var dropdown = document.getElementById('navMobileDropdown');
    var menuBtn = document.querySelector('.nav-menu-btn');
    if (dropdown.classList.contains('open') && !dropdown.contains(e.target) && !menuBtn.contains(e.target)) {
        dropdown.classList.remove('open');
        document.getElementById('nav-menu-icon').innerHTML = '<line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>';
    }
});
</script>
