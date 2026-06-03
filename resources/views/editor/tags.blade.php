@extends('shopify-app::layouts.default')

@section('content')

    <ui-title-bar title="ApexBulk > Tags Editor">
        <button variant="primary" onclick="document.getElementById('tag-form').submit()">Execute</button>
    </ui-title-bar>

    @include('components.nav-menu')

    <s-page heading="Bulk Tags Editor">

        <s-section heading="Step 1: Select Products">
            <s-button onclick="openResourcePicker()">🔍 Browse Products</s-button>
            <s-paragraph id="selected-count">0 products selected</s-paragraph>
        </s-section>

        <s-section heading="Step 2: Tag Action">
            <form id="tag-form" method="POST" action="{{ url('/editor/tags') }}?{{ http_build_query(request()->query()) }}">
                @csrf
                <input type="hidden" name="product_ids" id="product-ids">

                <s-select label="Action" name="action">
                    <s-option value="add">Add Tags</s-option>
                    <s-option value="remove">Remove Tags</s-option>
                    <s-option value="replace">Replace All Tags</s-option>
                    <s-option value="clear">Clear All Tags</s-option>
                </s-select>

                <s-text-field label="Tags (comma separated)" name="tags_input" placeholder="summer, sale, 2026"></s-text-field>
                <input type="hidden" name="tags" id="tags-array">
            </form>
        </s-section>

    </s-page>

@endsection

@section('scripts')
    @parent
<script>
    let selectedProductIds = [];
    document.getElementById('tag-form').addEventListener('submit', function() {
        const input = document.querySelector('[name="tags_input"]');
        const tags = input.value.split(',').map(t => t.trim()).filter(Boolean);
        document.getElementById('tags-array').value = JSON.stringify(tags);
    });
    function openResourcePicker() {
        shopify.resourcePicker({ type: 'product', multiple: true }).then(result => {
            if (result) {
                selectedProductIds = result.map(p => p.id.replace('gid://shopify/Product/', ''));
                document.getElementById('selected-count').textContent = selectedProductIds.length + ' products selected';
                document.getElementById('product-ids').value = JSON.stringify(selectedProductIds);
            }
        });
    }
</script>
@endsection
