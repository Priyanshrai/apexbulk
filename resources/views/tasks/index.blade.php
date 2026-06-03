@extends('shopify-app::layouts.default')

@section('content')

    <ui-title-bar title="ApexBulk > Task History"></ui-title-bar>

    @include('components.nav-menu')

    <s-page heading="Task History" style="display:flex;flex-direction:column;gap:24px;">

        @php
            $tasks = \App\Models\BulkEditTask::where('user_id', Auth::id())
                ->latest()
                ->paginate(25);
        @endphp

        @if($tasks->isEmpty())
            <s-banner tone="info">No tasks yet. Go to the dashboard and start editing!</s-banner>
            <s-button onclick="location.href='{{ url('/') }}?{{ http_build_query(request()->query()) }}'">← Back to Dashboard</s-button>
        @else
            <s-table>
                <s-table-header-row>
                    <s-table-header>Type</s-table-header>
                    <s-table-header>Products</s-table-header>
                    <s-table-header>Status</s-table-header>
                    <s-table-header>Created</s-table-header>
                </s-table-header-row>
                <s-table-body>
                @foreach($tasks as $task)
                <s-table-row>
                    <s-table-cell>@if($task->task_type === 'price') 💰 Price @elseif($task->task_type === 'inventory') 📦 Inventory @elseif($task->task_type === 'tags') 🏷️ Tags @else {{ ucfirst($task->task_type) }} @endif</s-table-cell>
                    <s-table-cell>{{ $task->productCount() ?: 'All' }}</s-table-cell>
                    <s-table-cell><s-badge tone="{{ $task->status === 'completed' ? 'success' : ($task->status === 'failed' ? 'critical' : ($task->status === 'running' ? 'caution' : 'info')) }}">{{ ucfirst($task->status) }}</s-badge></s-table-cell>
                    <s-table-cell>{{ $task->created_at->diffForHumans() }}</s-table-cell>
                </s-table-row>
                @endforeach
                </s-table-body>
            </s-table>

            @if($tasks->hasPages())
            <div style="display:flex;align-items:center;justify-content:center;gap:12px;padding-top:24px;">
                @php
                    $shopParams = http_build_query(request()->except('page'));
                @endphp
                @if($tasks->onFirstPage())
                    <s-button disabled>← Previous</s-button>
                @else
                    <s-button onclick="location.href='{{ $tasks->previousPageUrl() }}&{{ $shopParams }}'">← Previous</s-button>
                @endif
                <span style="color:var(--p-color-text-secondary);font-size:14px;">Page {{ $tasks->currentPage() }} of {{ $tasks->lastPage() }}</span>
                @if($tasks->hasMorePages())
                    <s-button onclick="location.href='{{ $tasks->nextPageUrl() }}&{{ $shopParams }}'">Next →</s-button>
                @else
                    <s-button disabled>Next →</s-button>
                @endif
            </div>
            @endif
        @endif

    </s-page>

@endsection
