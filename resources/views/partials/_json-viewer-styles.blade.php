{{-- Shared CSS for the interactive JSON tree viewer. Rendered inside a <style> block. --}}
.json-content { font-family: var(--font-mono); font-size: 12px; white-space: pre-wrap; word-break: break-word; max-height: 460px; overflow: auto; line-height: 1.7; color: var(--text-muted); margin: 0 0 12px 0; background: var(--bg); padding: 12px 14px; border-radius: var(--radius-sm); border: 1px solid var(--border-light); }
.json-content:last-child { margin-bottom: 0; }
.json-content.jt-ready { white-space: normal; }
.jt-toolbar { display: flex; gap: 6px; margin-bottom: 8px; position: sticky; top: -12px; }
.jt-btn { padding: 3px 10px; font-size: 11px; border: 1px solid var(--border); border-radius: var(--radius-sm); background: var(--bg-card); color: var(--text-muted); cursor: pointer; font-weight: 600; }
.jt-btn:hover { background: var(--primary); color: #fff; border-color: var(--primary); }
.jt-tree { font-family: var(--font-mono); font-size: 12px; line-height: 1.7; }
.jt-children { margin-left: 0.6em; padding-left: 0.8em; border-left: 1px solid var(--border-light); }
.jt-line { padding: 0 4px; border-radius: 3px; }
.jt-line.jt-clickable { cursor: pointer; }
.jt-line.jt-clickable:hover { background: var(--primary-light); }
.jt-close-line { padding-left: 16px; }
.jt-toggle { display: inline-block; width: 12px; text-align: center; color: var(--text-light); user-select: none; }
.jt-toggle.jt-has-children::before { content: '\25BE'; font-size: 9px; }
.jt-node.collapsed > .jt-line > .jt-toggle.jt-has-children::before { content: '\25B8'; }
.jt-node.collapsed > .jt-children,
.jt-node.collapsed > .jt-close-line { display: none; }
.jt-preview, .jt-close-inline { display: none; color: var(--text-light); font-style: italic; }
.jt-node.collapsed > .jt-line > .jt-preview,
.jt-node.collapsed > .jt-line > .jt-close-inline { display: inline; }
.jt-key { color: #0891b2; }
.jt-index { color: var(--text-light); }
.jt-str { color: #16a34a; white-space: pre-wrap; word-break: break-word; }
.jt-str.jt-date { color: #0d9488; border-bottom: 1px dotted currentColor; cursor: help; }
.jt-link { color: #2563eb; text-decoration: underline; text-underline-offset: 2px; word-break: break-all; }
.jt-link:hover { color: var(--primary); }
.jt-num { color: #d97706; }
.jt-bool { color: #7c3aed; }
.jt-null { color: #dc2626; }
.jt-brace { color: var(--text-muted); }
.jt-copy { margin-left: 8px; font-size: 10px; color: var(--text-light); cursor: pointer; opacity: 0; transition: opacity 0.15s; padding: 0 4px; border: 1px solid var(--border); border-radius: 3px; user-select: none; }
.jt-line:hover .jt-copy { opacity: 0.7; }
.jt-copy:hover { opacity: 1; color: var(--primary); border-color: var(--primary); }
.jt-copy.jt-copied { opacity: 1; color: var(--success-text); border-color: var(--debug-border); }
[data-theme="dark"] .jt-key { color: #22d3ee; }
[data-theme="dark"] .jt-str { color: #4ade80; }
[data-theme="dark"] .jt-str.jt-date { color: #2dd4bf; }
[data-theme="dark"] .jt-link { color: #60a5fa; }
[data-theme="dark"] .jt-num { color: #fbbf24; }
[data-theme="dark"] .jt-bool { color: #c4b5fd; }
[data-theme="dark"] .jt-null { color: #f87171; }
