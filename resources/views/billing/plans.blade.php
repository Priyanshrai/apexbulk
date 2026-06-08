@extends('shopify-app::layouts.default')

@section('content')
    <ui-title-bar title="ApexBulk > Plans"></ui-title-bar>

    @include('components.nav-menu')

    <s-page heading="Choose Your Plan" style="display:flex;flex-direction:column;gap:24px;">

        <s-grid>
            <s-section heading="🆓 Free">
                <s-paragraph tone="subdued">For small shops getting started.</s-paragraph>
                <s-list>
                    <s-list-item>✅ 100 unique product edits per month</s-list-item>
                    <s-list-item>✅ Price, Inventory & Tags editors</s-list-item>
                    <s-list-item>✅ Schedule tasks</s-list-item>
                    <s-list-item>✅ Task history & revert</s-list-item>
                </s-list>
                <s-badge tone="success">Current plan</s-badge>
            </s-section>

            <s-section heading="🚀 Pro">
                <s-paragraph tone="subdued">For growing businesses.</s-paragraph>
                <s-list>
                    <s-list-item>✅ Unlimited product edits</s-list-item>
                    <s-list-item>✅ Price, Inventory & Tags editors</s-list-item>
                    <s-list-item>✅ Schedule tasks</s-list-item>
                    <s-list-item>✅ Task history & revert</s-list-item>
                    <s-list-item>✅ Priority support</s-list-item>
                </s-list>
                <s-text style="font-size:24px;font-weight:700;margin:8px 0;">$9.99<span style="font-size:14px;font-weight:400;">/month</span></s-text>
                <s-paragraph tone="subdued">7-day free trial included</s-paragraph>
                <s-button variant="primary" full-width onclick="location.href='/billing/1?host={{ request('host') }}&shop={{ request('shop') }}'">
                    Start 7-Day Free Trial →
                </s-button>
            </s-section>
        </s-grid>

        <s-banner tone="info">
            💡 You'll be redirected to Shopify to approve the subscription. You won't be charged until the trial ends.
        </s-banner>

    </s-page>
@endsection
