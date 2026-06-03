@extends('shopify-app::layouts.default')

@section('styles')
    <script src="https://cdn.shopify.com/shopifycloud/polaris.js"></script>
@endsection

@section('content')

    {{-- Title Bar --}}
    <ui-title-bar title="ApexBulk"></ui-title-bar>

    {{-- Navigation Menu (oldapp pattern) --}}
    <ui-nav-menu>
        <a href="/" rel="home">🏠 Dashboard</a>
        <a href="/editor/price">💰 Price</a>
        <a href="/editor/inventory">📦 Inventory</a>
        <a href="/editor/tags">🏷️ Tags</a>
        <a href="/tasks">📋 Tasks</a>
    </ui-nav-menu>

    {{-- Main Dashboard --}}
    <s-page heading="Bulk Product Manager">

        {{-- Store Info --}}
        <s-banner status="info">
            🏪 <strong>{{ $shopDomain ?? Auth::user()->name }}</strong> — Bulk edit your products in seconds
        </s-banner>

        {{-- Core Modules --}}
        <s-card>
            <s-text as="h2" variant="headingLg">Core Editors</s-text>
            <s-text as="p" variant="bodyMd" tone="subdued">
                Select an editor below to start making bulk changes to your products.
            </s-text>

            <s-layout>
                <s-layout-section>
                    <s-card>
                        <s-text as="h3" variant="headingMd">💰 Price Editor</s-text>
                        <s-paragraph>
                            Set specific prices, increase/decrease by amount or percentage.
                            Apply rounding rules like .99 endings.
                        </s-paragraph>
                        <s-button variant="primary" full-width onclick="location.href='{{ url('/editor/price') }}?{{ http_build_query(request()->query()) }}'">
                            Open Price Editor
                        </s-button>
                    </s-card>
                </s-layout-section>

                <s-layout-section>
                    <s-card>
                        <s-text as="h3" variant="headingMd">📦 Inventory Editor</s-text>
                        <s-paragraph>
                            Set quantities, enable/disable tracking, manage stock
                            across all your products and variants.
                        </s-paragraph>
                        <s-button variant="primary" full-width onclick="location.href='{{ url('/editor/inventory') }}?{{ http_build_query(request()->query()) }}'">
                            Open Inventory Editor
                        </s-button>
                    </s-card>
                </s-layout-section>

                <s-layout-section>
                    <s-card>
                        <s-text as="h3" variant="headingMd">🏷️ Tags Editor</s-text>
                        <s-paragraph>
                            Add, remove, or replace tags across hundreds of products
                            in a single operation.
                        </s-paragraph>
                        <s-button variant="primary" full-width onclick="location.href='{{ url('/editor/tags') }}?{{ http_build_query(request()->query()) }}'">
                            Open Tags Editor
                        </s-button>
                    </s-card>
                </s-layout-section>
            </s-layout>
        </s-card>

        {{-- Recent Activity --}}
        <s-card>
            <s-text as="h2" variant="headingLg">📋 Recent Activity</s-text>

            @php
                $recentTasks = \App\Models\BulkEditTask::where('user_id', Auth::id())
                    ->latest()
                    ->take(5)
                    ->get();
            @endphp

            @if($recentTasks->isEmpty())
                <s-banner status="info">
                    No tasks yet. Open an editor above to get started!
                </s-banner>
            @else
                <s-data-table>
                    <table>
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Products</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentTasks as $task)
                            <tr>
                                <td>
                                    @if($task->task_type === 'price') 💰 Price
                                    @elseif($task->task_type === 'inventory') 📦 Inventory
                                    @elseif($task->task_type === 'tags') 🏷️ Tags
                                    @else {{ ucfirst($task->task_type) }}
                                    @endif
                                </td>
                                <td>
                                    {{ is_array($task->product_ids) ? count($task->product_ids) : 'All' }}
                                </td>
                                <td>
                                    <s-badge status="{{ $task->status === 'completed' ? 'success' : ($task->status === 'failed' ? 'critical' : ($task->status === 'running' ? 'warning' : 'info')) }}">
                                        {{ ucfirst($task->status) }}
                                    </s-badge>
                                </td>
                                <td>{{ $task->created_at->diffForHumans() }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </s-data-table>
            @endif

            <s-inline-stack distribution="trailing">
                <s-button onclick="location.href='{{ url('/tasks') }}?{{ http_build_query(request()->query()) }}'">View All Tasks →</s-button>
            </s-inline-stack>
        </s-card>

    </s-page>

@endsection
