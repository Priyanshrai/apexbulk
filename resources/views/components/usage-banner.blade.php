@php
    $shop = Auth::user();
    if (!$shop->isFree()) return;
    $tracker = app(\App\Services\UsageTracker::class);
    $uid = $shop->getId()->toNative();
    $used = $tracker->countThisMonth($uid);
    $limit = \App\Services\UsageTracker::FREE_LIMIT;
    $remaining = $tracker->remaining($uid);
    $upgradeUrl = URL::tokenRoute('billing.plans', ['host' => request('host')]);
@endphp

<s-banner tone="{{ $remaining <= 10 ? 'warning' : 'info' }}" style="margin-bottom:16px;max-width:100%;">
    📊 <strong>{{ $used }}/{{ $limit }}</strong> free edits used this month &nbsp;·&nbsp; <strong>{{ $remaining }}</strong> remaining &nbsp;&nbsp;
    <a href="{{ $upgradeUrl }}" style="font-weight:600;white-space:nowrap;">Upgrade to Pro ($9.99/mo) →</a>
</s-banner>
