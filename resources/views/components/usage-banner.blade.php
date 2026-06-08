@php
    $shop = Auth::user();
    if ($shop->plan || $shop->isFreemium() || $shop->isGrandfathered()) return;
    $tracker = app(\App\Services\UsageTracker::class);
    $uid = $shop->getId()->toNative();
    $used = $tracker->countThisMonth($uid);
    $limit = \App\Services\UsageTracker::FREE_LIMIT;
    $remaining = $tracker->remaining($uid);
@endphp

@if(!$shop->plan && !$shop->isFreemium() && !$shop->isGrandfathered())
<s-banner tone="{{ $remaining <= 10 ? 'warning' : 'info' }}" style="margin-bottom:16px;">
    📊 <strong>{{ $used }}/{{ $limit }}</strong> free edits used this month
    · <strong>{{ $remaining }}</strong> remaining
    &nbsp;
    <a href="{{ URL::tokenRoute('billing.plans', ['host' => request('host')]) }}" style="color:var(--p-color-text-primary);font-weight:600;">
        Upgrade to Pro ($9.99/mo) →
    </a>
</s-banner>
@endif
