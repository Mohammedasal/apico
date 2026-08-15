<div class="period-toolbar">
    <div class="segmented" aria-label="{{ __('Period') }}">
        <a @class(['active' => $period['mode'] === 'total']) href="{{ route($routeName, ['period' => 'total']) }}">{{ __('Total View') }}</a>
        <a @class(['active' => $period['mode'] === 'monthly']) href="{{ route($routeName, ['period' => 'monthly', 'month' => $period['month']]) }}">{{ __('Monthly View') }}</a>
        <a @class(['active' => $period['mode'] === 'weekly']) href="{{ route($routeName, ['period' => 'weekly', 'week' => $period['week']]) }}">{{ __('Weekly View') }}</a>
        <a @class(['active' => $period['mode'] === 'custom']) href="{{ route($routeName, ['period' => 'custom', 'from' => $period['customFrom'], 'to' => $period['customTo']]) }}">{{ __('Custom Range') }}</a>
    </div>

    @if ($period['mode'] !== 'total')
        <form class="filters compact-filter" method="get">
            <input type="hidden" name="period" value="{{ $period['mode'] }}">
            @if ($period['mode'] === 'monthly')
                <div><label>{{ __('Month') }}</label><input type="month" name="month" value="{{ $period['month'] }}"></div>
            @elseif ($period['mode'] === 'weekly')
                <div><label>{{ __('Week') }}</label><input type="week" name="week" value="{{ $period['week'] }}"></div>
            @else
                <div><label>{{ __('From') }}</label><input type="date" name="from" value="{{ $period['customFrom'] }}" required></div>
                <div><label>{{ __('To') }}</label><input type="date" name="to" value="{{ $period['customTo'] }}" required></div>
            @endif
            <div><button>{{ __('Apply') }}</button></div>
        </form>
    @endif
</div>
