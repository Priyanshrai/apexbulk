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
            <s-banner status="info">No tasks yet. Go to the dashboard and start editing!</s-banner>
            <s-button onclick="location.href='{{ url('/') }}?{{ http_build_query(request()->query()) }}'">← Back to Dashboard</s-button>
        @else
            <s-section heading="All Tasks">
                <s-table>
                    <table>
                        <thead>
                            <tr><th>Type</th><th>Products</th><th>Status</th><th>Created</th></tr>
                        </thead>
                        <tbody>
                            @foreach($tasks as $task)
                            <tr>
                                <td>@if($task->task_type === 'price') 💰 Price @elseif($task->task_type === 'inventory') 📦 Inventory @elseif($task->task_type === 'tags') 🏷️ Tags @else {{ ucfirst($task->task_type) }} @endif</td>
                                <td>{{ $task->productCount() ?: 'All' }}</td>
                                <td><s-badge status="{{ $task->status === 'completed' ? 'success' : ($task->status === 'failed' ? 'critical' : ($task->status === 'running' ? 'warning' : 'info')) }}">{{ ucfirst($task->status) }}</s-badge></td>
                                <td>{{ $task->created_at->diffForHumans() }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </s-table>
            </s-section>
            {{ $tasks->links() }}
        @endif

    </s-page>

@endsection
