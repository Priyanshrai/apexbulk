@extends('shopify-app::layouts.default')

@section('content')

    @php $homeUrl = URL::tokenRoute('home', ['host' => request('host')]) @endphp
    <ui-title-bar title="ApexBulk > Task History">
        <button onclick="location.href='{{ $homeUrl }}'">← Dashboard</button>
    </ui-title-bar>

    @include('components.nav-menu')

    <s-page heading="Task History">

        <s-stack gap="large-200">

        @include('components.usage-banner')

        @if($tasks->isEmpty())
            <s-banner tone="info">No tasks yet. Go to the dashboard and start editing!</s-banner>
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
                        <s-text as="span" fontWeight="bold">#{{ $task->id }}</s-text>
                        <s-text as="span" variant="bodySm" tone="subdued">
                        @if($task->task_type === 'price') 💰 Price
                        @elseif($task->task_type === 'inventory') 📦 Inventory
                        @elseif($task->task_type === 'tags') 🏷️ Tags
                        @else {{ ucfirst($task->task_type) }}
                        @endif
                        </s-text>
                    </s-table-cell>

                    <s-table-cell>
                        <span style="font-weight:500;">{{ $task->actionSummary() }}</span>
                        @if($task->failure_reason)
                            <s-badge tone="critical" style="margin-left:6px;">error</s-badge>
                        @endif
                    </s-table-cell>

                    <s-table-cell>
                        @if($task->isAllProducts())
                            <s-text tone="subdued">All products</s-text>
                        @else
                            @php $links = $task->productLinks(); @endphp
                            @if(count($links) <= 3)
                                @foreach($links as $link)
                                    <a href="{{ $link['url'] }}" target="_blank" rel="noopener" style="display:block;color:var(--p-color-text-primary);text-decoration:none;font-size:13px;">
                                        🛍️ {{ $link['title'] ?? 'Product #'.$link['id'] }}
                                    </a>
                                @endforeach
                            @else
                                <details>
                                    <summary style="cursor:pointer;color:var(--p-color-text-primary);">
                                        {{ $task->productCountLabel() }}
                                    </summary>
                                    @foreach($links as $link)
                                        <a href="{{ $link['url'] }}" target="_blank" rel="noopener" style="display:block;color:var(--p-color-text-primary);text-decoration:none;font-size:13px;padding-left:8px;">
                                            🛍️ {{ $link['title'] ?? '#'.$link['id'] }}
                                        </a>
                                    @endforeach
                                    @if($task->productCount() > 5)
                                        <s-text tone="subdued" variant="bodySm" style="padding-left:8px;">
                                            +{{ $task->productCount() - 5 }} more
                                        </s-text>
                                    @endif
                                </details>
                            @endif
                        @endif
                    </s-table-cell>

                    <s-table-cell>
                        <s-badge tone="{{ $task->status === 'completed' ? 'success' : ($task->status === 'failed' ? 'critical' : ($task->status === 'running' ? 'caution' : 'info')) }}">
                            {{ $task->isScheduled() ? 'Scheduled' : ucfirst($task->status) }}
                        </s-badge>
                    </s-table-cell>

                    <s-table-cell style="white-space:nowrap;">
                        @if($task->isScheduled())
                            ⏰ <time class="local-time" datetime="{{ $task->scheduledAtLabel() }}">{{ $task->scheduled_at->format('M d, h:i A') }} UTC</time>
                        @else
                            {{ $task->created_at->diffForHumans() }}
                        @endif
                    </s-table-cell>
                    <s-table-cell>
                        @if($task->status === 'completed')
                            <s-button size="small" onclick="document.getElementById('revert-task').value='{{ $task->id }}';shopify.modal.show('revert-modal')">↩ Revert</s-button>
                            <form id="revert-form-{{ $task->id }}" method="POST" action="{{ route('tasks.revert', ['task' => $task->id]) }}" style="display:none;">
                                @csrf
                                @sessionToken
                            </form>
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
            <s-stack direction="inline" gap="base" justifyContent="center">
                @if($tasks->onFirstPage())
                    <s-button disabled>← Previous</s-button>
                @else
                    <s-button onclick="location.href='{{ $tasks->previousPageUrl() }}&host={{ request('host') }}&shop={{ request('shop') }}'">← Previous</s-button>
                @endif
                <s-text tone="subdued">Page {{ $tasks->currentPage() }} of {{ $tasks->lastPage() }}</s-text>
                @if($tasks->hasMorePages())
                    <s-button onclick="location.href='{{ $tasks->nextPageUrl() }}&host={{ request('host') }}&shop={{ request('shop') }}'">Next →</s-button>
                @else
                    <s-button disabled>Next →</s-button>
                @endif
            </s-stack>
            @endif
        @endif

        </s-stack>

    </s-page>

    <ui-modal id="revert-modal">
        <s-box padding="large-200">
            <s-stack gap="large-200">
                <s-text tone="subdued">This will restore the original values for this task. Revert logs will be used to restore data.</s-text>
                <s-banner tone="warning">This action cannot be undone. The original values will be set back via Shopify API.</s-banner>
            </s-stack>
        </s-box>
        <ui-title-bar title="Confirm Revert">
            <button onclick="shopify.modal.hide('revert-modal')">Cancel</button>
            <button variant="primary" onclick="shopify.modal.hide('revert-modal').then(()=>document.getElementById('revert-form-'+document.getElementById('revert-task').value).submit())">Confirm Revert</button>
        </ui-title-bar>
    </ui-modal>
    <input type="hidden" id="revert-task">

<script>
document.querySelectorAll('time.local-time').forEach(function(el) {
    var d = new Date(el.getAttribute('datetime'));
    el.textContent = d.toLocaleString(undefined, {month:'short',day:'numeric',hour:'numeric',minute:'2-digit',hour12:true});
});
</script>

@endsection
