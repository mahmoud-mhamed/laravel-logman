@php
    $isChannel = str_starts_with($sectionName, 'Channel:');
    $channelEnabled = $isChannel && collect($items)->first(fn($i) => str_ends_with($i['key'], '.enabled'))['value'] ?? false;
@endphp
<div class="config-section {{ $isChannel && $channelEnabled ? 'channel-enabled' : '' }} {{ $isChannel && !$channelEnabled ? 'channel-disabled' : '' }}">
    <div class="config-section-title">
        {{ $sectionName }}
        @if($isChannel)
            @if($channelEnabled)
                <span class="channel-status-badge channel-on">Active</span>
            @else
                <span class="channel-status-badge channel-off">Disabled</span>
            @endif
        @endif
    </div>
    @foreach($items as $item)
        <div class="config-row">
            <div class="config-label">
                <div class="label-name">{{ $item['label'] }}</div>
                <div class="label-key">{{ $item['key'] }}</div>
            </div>
            <div class="config-value">
                @if($item['type'] === 'bool')
                    @if($item['value'])
                        <span class="val-true">true</span>
                    @else
                        <span class="val-false">false</span>
                    @endif
                @elseif($item['type'] === 'number')
                    <span class="val-number">{{ $item['value'] }}</span>
                @elseif($item['type'] === 'path')
                    <span class="val-path">{{ $item['value'] }}</span>
                @elseif($item['type'] === 'status')
                    @if(str_contains($item['value'], 'NOT SET') || str_contains($item['value'], 'None'))
                        <span class="val-status-bad">{{ $item['value'] }}</span>
                    @else
                        <span class="val-status-ok">{{ $item['value'] }}</span>
                    @endif
                @elseif($item['type'] === 'list')
                    @if(is_array($item['value']) && count($item['value']) > 0)
                        <div class="val-list">
                            @foreach($item['value'] as $listItem)
                                <span class="val-list-item">{{ $listItem }}</span>
                            @endforeach
                        </div>
                    @else
                        <span class="val-empty">None</span>
                    @endif
                @else
                    {{ $item['value'] }}
                @endif
            </div>
            <div class="config-desc">{{ $item['description'] }}</div>
        </div>
    @endforeach
</div>
