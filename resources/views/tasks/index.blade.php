@extends('shopify-app::layouts.default')

@section('styles')
    <script src="https://cdn.shopify.com/shopifycloud/polaris.js"></script>
@endsection

@section('content')

    <ui-title-bar title="ApexBulk > Task History"></ui-title-bar>

    <ui-nav-menu>
        <a href="/">🏠 Dashboard</a>
        <a href="/editor/price">💰 Price</a>
        <a href="/editor/inventory">📦 Inventory</a>
        <a href="/editor/tags">🏷️ Tags</a>
        <a href="/tasks">📋 Tasks</a>
    </ui-nav-menu>

    <s-page heading="Task History">

        @php
            $tasks = \App\Models\BulkEditTask::where('user_id', Auth::id())
                ->latest()
                ->paginate(25);
        @endphp

        @if($tasks->isEmpty())
            <s-banner status="info">
                No tasks yet. Go to the dashboard and start editing!
            </s-banner>
            <s-button onclick="location.href='{{ url('/') }}?{{ http_build_query(request()->query()) }}'">← Back to Dashboard</s-button>
        @else
            <s-card>
                <s-data-table>
                    <table>
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Products</th>
                                <th>Status</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($tasks as $task)
                            <tr>
                                <td>
                                    @if($task->task_type === 'price') 💰 Price
                                    @elseif($task->task_type === 'inventory') 📦 Inventory
                                    @elseif($task->task_type === 'tags') 🏷️ Tags
                                    @else {{ ucfirst($task->task_type) }}
                                    @endif
                                </td>
                                <td>{{ $task->productCount() ?: 'All' }}</td>
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
            </s-card>

            {{ $tasks->links() }}
        @endif

    </s-page>

@endsection
