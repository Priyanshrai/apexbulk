@extends('shopify-app::layouts.default')

@section('styles')
    <script src="https://cdn.shopify.com/shopifycloud/polaris.js"></script>
@endsection

@section('content')

    <ui-title-bar title="ApexBulk > Tags Editor">
        <button variant="primary" onclick="document.getElementById('tag-form').submit()">Execute</button>
    </ui-title-bar>

    <s-page heading="Bulk Tags Editor">

        <s-card>
            <s-text as="h2" variant="headingLg">Step 1: Select Products</s-text>
            <s-button onclick="openResourcePicker()">🔍 Browse Products</s-button>
            <s-paragraph id="selected-count">0 products selected</s-paragraph>
        </s-card>

        <s-card>
            <s-text as="h2" variant="headingLg">Step 2: Tag Action</s-text>

            <form id="tag-form" method="POST" action="{{ url('/editor/tags') }}?{{ http_build_query(request()->query()) }}">
                @csrf
                <input type="hidden" name="product_ids" id="product-ids">

                <s-select label="Action" name="action">
                    <option value="add">Add Tags</option>
                    <option value="remove">Remove Tags</option>
                    <option value="replace">Replace All Tags</option>
                    <option value="clear">Clear All Tags</option>
                </s-select>

                <s-text-field label="Tags (comma separated)" name="tags_input" placeholder="summer, sale, 2026"></s-text-field>
                <input type="hidden" name="tags" id="tags-array">
            </form>
        </s-card>

    </s-page>

@endsection

@section('scripts')
    @parent
<script>
    let selectedProductIds = [];

    // Convert comma text to array before submit
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
