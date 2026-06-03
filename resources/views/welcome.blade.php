@extends('shopify-app::layouts.default')

@section('content')

    {{-- Title Bar --}}
    <ui-title-bar title="ApexBulk"></ui-title-bar>

    @include('components.nav-menu')

    {{-- Main Dashboard --}}
    <s-page heading="Bulk Product Manager" style="display:flex;flex-direction:column;gap:24px;">

        {{-- Store Info --}}
        <s-banner status="info">
            🏪 <strong>{{ $shopDomain ?? Auth::user()->name }}</strong> — Bulk edit your products in seconds
        </s-banner>

        {{-- Core Modules --}}
        <s-section heading="Core Editors">
            <s-paragraph tone="subdued">Select an editor below to start making bulk changes to your products.</s-paragraph>

            <s-grid>
                <s-section heading="💰 Price Editor">
                    <s-paragraph>Set specific prices, increase/decrease by amount or percentage. Apply rounding rules like .99 endings.</s-paragraph>
                    <s-button variant="primary" full-width onclick="location.href='{{ url('/editor/price') }}?{{ http_build_query(request()->query()) }}'">Open Price Editor</s-button>
                </s-section>

                <s-section heading="📦 Inventory Editor">
                    <s-paragraph>Set quantities, enable/disable tracking, manage stock across all your products and variants.</s-paragraph>
                    <s-button variant="primary" full-width onclick="location.href='{{ url('/editor/inventory') }}?{{ http_build_query(request()->query()) }}'">Open Inventory Editor</s-button>
                </s-section>

                <s-section heading="🏷️ Tags Editor">
                    <s-paragraph>Add, remove, or replace tags across hundreds of products in a single operation.</s-paragraph>
                    <s-button variant="primary" full-width onclick="location.href='{{ url('/editor/tags') }}?{{ http_build_query(request()->query()) }}'">Open Tags Editor</s-button>
                </s-section>
            </s-grid>
        </s-section>

        {{-- Recent Activity --}}
        <s-section heading="📋 Recent Activity">

            @php
                $recentTasks = \App\Models\BulkEditTask::where('user_id', Auth::id())
                    ->latest()
                    ->take(5)
                    ->get();
            @endphp

            @if($recentTasks->isEmpty())
                <s-banner status="info">No tasks yet. Open an editor above to get started!</s-banner>
            @else
                <s-table>
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
                                <td>{{ is_array($task->product_ids) ? count($task->product_ids) : 'All' }}</td>
                                <td><s-badge status="{{ $task->status === 'completed' ? 'success' : ($task->status === 'failed' ? 'critical' : ($task->status === 'running' ? 'warning' : 'info')) }}">{{ ucfirst($task->status) }}</s-badge></td>
                                <td>{{ $task->created_at->diffForHumans() }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </s-table>
            @endif

            <s-stack distribution="trailing">
                <s-button onclick="location.href='{{ url('/tasks') }}?{{ http_build_query(request()->query()) }}'">View All Tasks →</s-button>
            </s-stack>
        </s-section>

    </s-page>

@endsection
