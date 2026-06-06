@php
    $host = request('host');
    $homeUrl = URL::tokenRoute('home', compact('host'));
    $priceUrl = URL::tokenRoute('editor.price', compact('host'));
    $inventoryUrl = URL::tokenRoute('editor.inventory', compact('host'));
    $tagsUrl = URL::tokenRoute('editor.tags', compact('host'));
    $tasksUrl = URL::tokenRoute('tasks.index', compact('host'));
@endphp
<ui-nav-menu>
    <a href="{{ $homeUrl }}" rel="home">🏠 Dashboard</a>
    <a href="{{ $priceUrl }}">💰 Price</a>
    <a href="{{ $inventoryUrl }}">📦 Inventory</a>
    <a href="{{ $tagsUrl }}">🏷️ Tags</a>
    <a href="{{ $tasksUrl }}">📋 Tasks</a>
</ui-nav-menu>
