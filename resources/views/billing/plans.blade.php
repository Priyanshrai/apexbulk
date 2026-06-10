@extends('shopify-app::layouts.default')

@section('content')

    @php
        $host = request('host');
        $shopDomain = Auth::user()->getDomain()->toNative();
        $homeUrl = URL::tokenRoute('home', compact('host'));
        $upgradeUrl = '/billing/1?host=' . $host . '&shop=' . $shopDomain;
        $isFree = Auth::user()->isFree();
    @endphp

    <ui-title-bar title="ApexBulk > Plans">
        <button onclick="location.href='{{ $homeUrl }}'">← Dashboard</button>
    </ui-title-bar>

    @include('components.nav-menu')

    <s-page heading="Choose Your Plan">

        <s-stack gap="large-200">

            @include('components.usage-banner')

            {{-- Flash Messages --}}
            @if(session('success'))
                <s-banner tone="success">{{ session('success') }}</s-banner>
            @endif
            @if(session('error'))
                <s-banner tone="critical">{{ session('error') }}</s-banner>
            @endif

            <s-stack direction="inline" gap="large-200">

                {{-- Free Plan --}}
                <s-section heading="🆓 Free">
                    <s-stack gap="base">
                        <s-paragraph tone="subdued">For small shops getting started.</s-paragraph>

                        <s-text as="p" variant="heading2xl" fontWeight="bold">$0<s-text as="span" variant="bodySm" tone="subdued" fontWeight="regular">/month</s-text></s-text>

                        <s-stack gap="small-200">
                            <s-text as="p">· 100 unique product edits per month</s-text>
                            <s-text as="p">· Price, Inventory & Tags editors</s-text>
                            <s-text as="p">· Schedule tasks</s-text>
                            <s-text as="p">· Task history & revert</s-text>
                        </s-stack>

                        @if($isFree)
                            <s-badge tone="success">Current plan</s-badge>
                        @endif
                    </s-stack>
                </s-section>

                {{-- Pro Plan --}}
                <s-section heading="🚀 Pro">
                    <s-stack gap="base">
                        <s-paragraph tone="subdued">For growing businesses that need unlimited power.</s-paragraph>

                        <s-text as="p" variant="heading2xl" fontWeight="bold">$9.99<s-text as="span" variant="bodySm" tone="subdued" fontWeight="regular">/month</s-text></s-text>

                        <s-paragraph tone="subdued">Cancel anytime</s-paragraph>

                        <s-stack gap="small-200">
                            <s-text as="p">· Unlimited product edits</s-text>
                            <s-text as="p">· All Products mode</s-text>
                            <s-text as="p">· Price, Inventory & Tags editors</s-text>
                            <s-text as="p">· Schedule tasks</s-text>
                            <s-text as="p">· Task history & revert</s-text>
                            <s-text as="p">· Priority support</s-text>
                        </s-stack>

                        @if(!$isFree)
                            <s-badge tone="success">Current plan</s-badge>
                            <s-stack gap="small-200">
                                <form method="POST" action="{{ URL::tokenRoute('plans.cancel', ['host' => $host]) }}">
                                    <s-button variant="danger" full-width submit>Cancel Subscription</s-button>
                                </form>
                            </s-stack>
                        @else
                            <s-button variant="primary" full-width onclick="location.href='{{ $upgradeUrl }}'">
                                Subscribe Now →
                            </s-button>
                        @endif
                    </s-stack>
                </s-section>

            </s-stack>

            <s-banner tone="info">
                💡 You&apos;ll be redirected to Shopify to approve the subscription.
            </s-banner>

        </s-stack>

    </s-page>
@endsection
