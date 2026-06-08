@extends('shopify-app::layouts.default')

@section('content')

    @php
        $host = request('host');
        $priceUrl = URL::tokenRoute('editor.price', compact('host'));
        $inventoryUrl = URL::tokenRoute('editor.inventory', compact('host'));
        $tagsUrl = URL::tokenRoute('editor.tags', compact('host'));
        $tasksUrl = URL::tokenRoute('tasks.index', compact('host'));
    @endphp

    {{-- Title Bar --}}
    <ui-title-bar title="ApexBulk"></ui-title-bar>

    @include('components.nav-menu')

    @include('components.usage-banner')

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
                    <s-button variant="primary" full-width onclick="location.href='{{ $priceUrl }}'">Open Price Editor</s-button>
                </s-section>

                <s-section heading="📦 Inventory Editor">
                    <s-paragraph>Set quantities, enable/disable tracking, manage stock across all your products and variants.</s-paragraph>
                    <s-button variant="primary" full-width onclick="location.href='{{ $inventoryUrl }}'">Open Inventory Editor</s-button>
                </s-section>

                <s-section heading="🏷️ Tags Editor">
                    <s-paragraph>Add, remove, or replace tags across hundreds of products in a single operation.</s-paragraph>
                    <s-button variant="primary" full-width onclick="location.href='{{ $tagsUrl }}'">Open Tags Editor</s-button>
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
                    <s-table-header-row>
                        <s-table-header>Type</s-table-header>
                        <s-table-header>Products</s-table-header>
                        <s-table-header>Status</s-table-header>
                        <s-table-header>Date</s-table-header>
                    </s-table-header-row>
                    <s-table-body>
                    @foreach($recentTasks as $task)
                    <s-table-row>
                        <s-table-cell>@if($task->task_type === 'price') 💰 Price @elseif($task->task_type === 'inventory') 📦 Inventory @elseif($task->task_type === 'tags') 🏷️ Tags @else {{ ucfirst($task->task_type) }} @endif</s-table-cell>
                        <s-table-cell>{{ is_array($task->product_ids) ? count($task->product_ids) : 'All' }}</s-table-cell>
                        <s-table-cell><s-badge tone="{{ $task->status === 'completed' ? 'success' : ($task->status === 'failed' ? 'critical' : ($task->status === 'running' ? 'caution' : 'info')) }}">{{ $task->isScheduled() ? 'Scheduled' : ucfirst($task->status) }}</s-badge></s-table-cell>
                        <s-table-cell>@if($task->isScheduled()) ⏰ <time class="local-time" datetime="{{ $task->scheduledAtLabel() }}">{{ $task->scheduled_at->format('M d, h:i A') }} UTC</time> @else {{ $task->created_at->diffForHumans() }} @endif</s-table-cell>
                    </s-table-row>
                    @endforeach
                    </s-table-body>
                </s-table>
            @endif

            <s-stack distribution="trailing">
                <s-button onclick="location.href='{{ $tasksUrl }}'">View All Tasks →</s-button>
            </s-stack>
        </s-section>

    </s-page>

<script>
document.querySelectorAll('time.local-time').forEach(function(el) {
    var d = new Date(el.getAttribute('datetime'));
    el.textContent = d.toLocaleString(undefined, {month:'short',day:'numeric',hour:'numeric',minute:'2-digit',hour12:true});
});
</script>

@endsection
