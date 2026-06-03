@extends('shopify-app::layouts.default')

@section('styles')
    <script src="https://cdn.shopify.com/shopifycloud/polaris.js"></script>
@endsection

@section('content')

    <ui-title-bar title="ApexBulk > Price Editor">
        <button variant="primary" onclick="document.getElementById('price-form').submit()">Execute</button>
    </ui-title-bar>

    <s-page heading="Bulk Price Editor">

        {{-- Step 1: Select Products --}}
        <s-card>
            <s-text as="h2" variant="headingLg">Step 1: Select Products</s-text>

            <s-select label="Selection Mode" name="selection_mode">
                <option value="all">All Products</option>
                <option value="manual">Manual Selection</option>
            </s-select>

            <s-button onclick="openResourcePicker()">🔍 Browse Products</s-button>
            <s-paragraph id="selected-count">0 products selected</s-paragraph>
        </s-card>

        {{-- Step 2: Set Price Rule --}}
        <s-card>
            <s-text as="h2" variant="headingLg">Step 2: Set Price Rule</s-text>

            <form id="price-form" method="POST" action="{{ url('/editor/price') }}?{{ http_build_query(request()->query()) }}">
                @csrf
                <input type="hidden" name="product_ids" id="product-ids">

                <s-select label="Action" name="action">
                    <option value="set_specific">Set Specific Price</option>
                    <option value="increase_amount">Increase by Amount ($)</option>
                    <option value="decrease_amount">Decrease by Amount ($)</option>
                    <option value="increase_percent">Increase by Percentage (%)</option>
                    <option value="decrease_percent">Decrease by Percentage (%)</option>
                </s-select>

                <s-text-field label="Value" name="value" type="number" step="0.01" placeholder="10.00"></s-text-field>

                <s-select label="Rounding" name="rounding">
                    <option value="none">No Rounding</option>
                    <option value="nearest_01">Nearest $0.01</option>
                    <option value="nearest_whole">Nearest Whole Number</option>
                    <option value="end_99">End in .99</option>
                    <option value="end_custom">Custom Ending</option>
                </s-select>

                <s-text-field label="Custom Rounding Value" name="rounding_value" type="number" step="0.01" placeholder="0.99"></s-text-field>
            </form>
        </s-card>

    </s-page>

@endsection

@section('scripts')
    @parent
<script>
    let selectedProductIds = [];

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
