@extends('shopify-app::layouts.default')

@section('content')

    <ui-title-bar title="ApexBulk > Inventory Editor">
        <button variant="primary" onclick="document.getElementById('inv-form').submit()">Execute</button>
    </ui-title-bar>

    @include('components.nav-menu')

    <s-page heading="Bulk Inventory Editor">

        <s-section heading="Step 1: Select Products">
            <s-button onclick="openResourcePicker()">🔍 Browse Products</s-button>
            <s-paragraph id="selected-count">0 products selected</s-paragraph>
        </s-section>

        <s-section heading="Step 2: Set Inventory Rule">
            <form id="inv-form" method="POST" action="{{ url('/editor/inventory') }}?{{ http_build_query(request()->query()) }}">
                @csrf
                <input type="hidden" name="product_ids" id="product-ids">

                <s-select label="Action" name="action">
                    <s-option value="set_quantity">Set Quantity</s-option>
                    <s-option value="add_quantity">Add to Quantity</s-option>
                    <s-option value="remove_quantity">Remove from Quantity</s-option>
                </s-select>

                <s-number-field label="Quantity" name="quantity" placeholder="100"></s-number-field>

                <s-checkbox label="Track inventory" name="track_inventory" value="1"></s-checkbox>
                <s-checkbox label="Continue selling when out of stock" name="continue_selling" value="1"></s-checkbox>
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
