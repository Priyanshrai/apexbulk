@extends('shopify-app::layouts.default')

@section('content')

    <ui-title-bar title="ApexBulk > Price Editor">
        <button variant="primary" onclick="document.getElementById('price-form').submit()">Execute</button>
    </ui-title-bar>

    @include('components.nav-menu')

    <s-page heading="Bulk Price Editor">

        <s-section heading="Step 1: Select Products">
            <s-select label="Selection Mode" name="selection_mode">
                <s-option value="all">All Products</s-option>
                <s-option value="manual">Manual Selection</s-option>
            </s-select>
            <s-button onclick="openResourcePicker()">🔍 Browse Products</s-button>
            <s-paragraph id="selected-count">0 products selected</s-paragraph>
        </s-section>

        <s-section heading="Step 2: Set Price Rule">
            <form id="price-form" method="POST" action="{{ url('/editor/price') }}?{{ http_build_query(request()->query()) }}">
                @csrf
                <input type="hidden" name="product_ids" id="product-ids">

                <s-select label="Action" name="action">
                    <s-option value="set_specific">Set Specific Price</s-option>
                    <s-option value="increase_amount">Increase by Amount ($)</s-option>
                    <s-option value="decrease_amount">Decrease by Amount ($)</s-option>
                    <s-option value="increase_percent">Increase by Percentage (%)</s-option>
                    <s-option value="decrease_percent">Decrease by Percentage (%)</s-option>
                </s-select>

                <s-number-field label="Value" name="value" step="0.01" placeholder="10.00"></s-number-field>

                <s-select label="Rounding" name="rounding">
                    <s-option value="none">No Rounding</s-option>
                    <s-option value="nearest_01">Nearest $0.01</s-option>
                    <s-option value="nearest_whole">Nearest Whole Number</s-option>
                    <s-option value="end_99">End in .99</s-option>
                    <s-option value="end_custom">Custom Ending</s-option>
                </s-select>

                <s-number-field label="Custom Rounding Value" name="rounding_value" step="0.01" placeholder="0.99"></s-number-field>
            </form>
        </s-section>

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
