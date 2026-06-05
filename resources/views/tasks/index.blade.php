@extends('shopify-app::layouts.default')

@section('content')

    <ui-title-bar title="ApexBulk > Task History"></ui-title-bar>

    @include('components.nav-menu')

    <s-page heading="Task History" style="display:flex;flex-direction:column;gap:24px;">

        @if($tasks->isEmpty())
            <s-banner tone="info">No tasks yet. Go to the dashboard and start editing!</s-banner>
            <s-button onclick="location.href='{{ url('/') }}?{{ http_build_query(request()->query()) }}'">← Back to Dashboard</s-button>
        @else
            <s-table>
                <s-table-header-row>
                    <s-table-header>Task</s-table-header>
                    <s-table-header>Action</s-table-header>
                    <s-table-header>Products</s-table-header>
                    <s-table-header>Status</s-table-header>
                    <s-table-header>When</s-table-header>
                    <s-table-header></s-table-header>
                </s-table-header-row>
                <s-table-body>
                @foreach($tasks as $task)
                <s-table-row>
                    <s-table-cell>
                        <span style="font-weight:600;">#{{ $task->id }}</span>
                        <span style="font-size:12px;color:var(--p-color-text-subdued);">
                        @if($task->task_type === 'price') 💰 Price
                        @elseif($task->task_type === 'inventory') 📦 Inventory
                        @elseif($task->task_type === 'tags') 🏷️ Tags
                        @else {{ ucfirst($task->task_type) }}
                        @endif
                        </span>
                    </s-table-cell>

                    <s-table-cell>
                        <span style="font-weight:500;">{{ $task->actionSummary() }}</span>
                        @if($task->failure_reason)
                            <s-badge tone="critical" style="margin-left:6px;">error</s-badge>
                        @endif
                    </s-table-cell>

                    <s-table-cell>
                        @if($task->isAllProducts())
                            <span style="color:var(--p-color-text-subdued);">All products</span>
                        @else
                            @php $links = $task->productLinks(); @endphp
                            @if(count($links) <= 3)
                                @foreach($links as $link)
                                    <a href="{{ $link['url'] }}" target="_blank" rel="noopener" style="display:block;color:var(--p-color-text-primary);text-decoration:none;font-size:13px;">
                                        🛍️ Product #{{ $link['id'] }}
                                    </a>
                                @endforeach
                            @else
                                <details>
                                    <summary style="cursor:pointer;color:var(--p-color-text-primary);">
                                        {{ $task->productCountLabel() }}
                                    </summary>
                                    @foreach($links as $link)
                                        <a href="{{ $link['url'] }}" target="_blank" rel="noopener" style="display:block;color:var(--p-color-text-primary);text-decoration:none;font-size:13px;padding-left:8px;">
                                            🛍️ #{{ $link['id'] }}
                                        </a>
                                    @endforeach
                                    @if($task->productCount() > 5)
                                        <span style="font-size:12px;color:var(--p-color-text-subdued);padding-left:8px;">
                                            +{{ $task->productCount() - 5 }} more
                                        </span>
                                    @endif
                                </details>
                            @endif
                        @endif
                    </s-table-cell>

                    <s-table-cell>
                        <s-badge tone="{{ $task->status === 'completed' ? 'success' : ($task->status === 'failed' ? 'critical' : ($task->status === 'running' ? 'caution' : 'info')) }}">
                            {{ ucfirst($task->status) }}
                        </s-badge>
                    </s-table-cell>

                    <s-table-cell style="white-space:nowrap;">{{ $task->created_at->diffForHumans() }}</s-table-cell>
                    <s-table-cell>
                        @if($task->status === 'completed')
                            <s-button size="small" onclick="document.getElementById('revert-task').value='{{ $task->id }}';shopify.modal.show('revert-modal')">↩ Revert</s-button>
                        @elseif($task->status === 'reverting')
                            <s-spinner size="small"></s-spinner>
                        @elseif($task->status === 'reverted')
                            <s-badge tone="success">Reverted</s-badge>
                        @endif
                    </s-table-cell>
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

    <ui-modal id="revert-modal">
        <div style="padding:20px 24px;display:flex;flex-direction:column;gap:14px;">
            <s-text tone="subdued">This will restore the original values for this task. Revert logs will be used to restore data.</s-text>
            <s-banner tone="warning">This action cannot be undone. The original values will be set back via Shopify API.</s-banner>
        </div>
        <ui-title-bar title="Confirm Revert">
            <button onclick="shopify.modal.hide('revert-modal')">Cancel</button>
            <button variant="primary" onclick="shopify.modal.hide('revert-modal').then(()=>{var tid=document.getElementById('revert-task').value;var f=document.createElement('form');f.method='POST';f.action='/tasks/'+tid+'/revert'+location.search;var c=document.createElement('input');c.type='hidden';c.name='_token';c.value='{{ csrf_token() }}';f.appendChild(c);document.body.appendChild(f);f.submit();})">Confirm Revert</button>
        </ui-title-bar>
    </ui-modal>
    <input type="hidden" id="revert-task">

@endsection
