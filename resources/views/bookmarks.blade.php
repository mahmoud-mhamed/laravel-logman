<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bookmarks - {{ config('app.name') }}</title>
    @include('logman::partials.styles')
    @include('logman::partials._detail-styles')
    <style>
        .bookmarks-page { padding: 24px; max-width: 1600px; margin: 0 auto; flex: 1; overflow-y: auto; scrollbar-width: none; width: 100%; }
        .bookmarks-page::-webkit-scrollbar { display: none; }
        .bookmarks-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
        .bookmarks-header h2 { font-size: 18px; font-weight: 700; }
        .bookmarks-count { font-size: 13px; color: var(--text-muted); background: var(--bg); padding: 4px 12px; border-radius: 20px; }
        .bookmarks-table { width: 100%; border-collapse: collapse; background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; }
        .bookmarks-table th { padding: 12px 16px; text-align: left; font-size: 11px; text-transform: uppercase; color: var(--text-light); font-weight: 700; letter-spacing: 0.05em; background: var(--bg-sidebar); border-bottom: 2px solid var(--border); }
        .bookmarks-table td { padding: 10px 16px; border-bottom: 1px solid var(--border-light); font-size: 13px; vertical-align: middle; }
        .bookmarks-table tr:hover td { background: var(--hover); }
        .bookmarks-table .mono { font-family: var(--font-mono); font-size: 12px; word-break: break-all; }
        .bookmarks-table .meta-text { font-size: 12px; color: var(--text-muted); }
        .empty-bookmarks { text-align: center; padding: 60px 20px; color: var(--text-light); }
        .empty-bookmarks p { font-size: 15px; font-weight: 500; margin-top: 12px; }
        .empty-bookmarks .sub { font-size: 13px; margin-top: 4px; }
    </style>
</head>
<body>
<div class="layout">
    @include('logman::partials.nav')

    <div class="bookmarks-page">
        <div class="bookmarks-header">
            <h2>Bookmarks</h2>
            <span class="bookmarks-count">{{ count($bookmarks) }} saved</span>
        </div>

        @if(session('success'))
            <div class="flash flash-success"><span>{{ session('success') }}</span><button class="flash-dismiss" onclick="this.parentElement.remove()" title="Dismiss">&times;</button></div>
        @endif

        @if(!empty($bookmarks))
            <table class="bookmarks-table">
                <thead>
                    <tr>
                        <th style="width:30px"></th>
                        <th style="width:80px">Level</th>
                        <th>Message</th>
                        <th style="width:120px">File</th>
                        <th style="width:140px">Bookmarked</th>
                        <th style="width:80px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($bookmarks as $i => $bm)
                        @php $hasDetails = !empty($bm['full_entry']) && ($bm['full_entry']['stack'] || $bm['full_entry']['context']); @endphp
                        <tr class="log-row" @if($hasDetails) onclick="toggleDetail({{ $i }}, this)" @endif>
                            <td>@if($hasDetails)<span class="expand-icon">&#9654;</span>@endif</td>
                            <td><span class="badge badge-{{ match($bm['level']) { 'emergency','alert','critical' => 'danger', 'error' => 'error', 'warning' => 'warning', 'notice','info' => 'info', default => 'debug' } }}">{{ $bm['level'] }}</span></td>
                            <td>
                                <div style="max-width:500px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $bm['message'] }}">{{ $bm['message'] }}</div>
                                @if(!empty($bm['exception_class']))
                                    <div class="mono meta-text" style="margin-top:2px;">{{ $bm['exception_class'] }}</div>
                                @endif
                                @if(!empty($bm['note']))
                                    <div class="meta-text" style="font-style:italic;margin-top:2px;">{{ $bm['note'] }}</div>
                                @endif
                            </td>
                            <td class="mono meta-text">{{ $bm['file'] }}</td>
                            <td class="meta-text" style="font-family:var(--font-mono);font-size:11px;">{{ $bm['bookmarked_at'] }}</td>
                            <td onclick="event.stopPropagation()">
                                <form method="POST" action="{{ route('logman.unbookmark') }}" style="display:inline;">
                                    @csrf
                                    <input type="hidden" name="id" value="{{ $bm['id'] }}">
                                    <button type="submit" class="btn btn-sm btn-danger">Remove</button>
                                </form>
                            </td>
                        </tr>
                        @if($hasDetails)
                            <tr class="detail-row" id="detail-{{ $i }}">
                                <td colspan="6">
                                    @include('logman::partials._entry-detail', ['entry' => $bm['full_entry'], 'i' => $i])
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="empty-bookmarks">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:48px;height:48px;opacity:0.2;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>
                <p>No bookmarks yet</p>
                <div class="sub">Bookmark log entries from the log viewer to save them for later</div>
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
