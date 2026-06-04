@extends('shopify-app::layouts.default')

@section('content')

    <ui-title-bar title="ApexBulk > Inventory Editor">
        <button variant="primary" onclick="openConfirmModal('confirm-inv-modal', 'inv-form')">⚡ Execute</button>
    </ui-title-bar>

    @include('components.nav-menu')

    @include('components.confirm-modal', [
        'modalId' => 'confirm-inv-modal',
        'formId' => 'inv-form',
        'title' => 'Confirm Bulk Inventory Update',
        'message' => 'This action will modify inventory levels on your live products. Make sure you\'ve reviewed your settings before proceeding.',
    ])

    <s-page heading="Bulk Inventory Editor">

        <form id="inv-form" method="POST" action="{{ url('/editor/inventory') }}?{{ http_build_query(request()->query()) }}" style="display:flex;flex-direction:column;gap:24px;">
            @csrf
            <input type="hidden" name="product_ids" id="product-ids">

            <s-section heading="1. Select Products">
                <s-paragraph tone="subdued">Choose which products to update inventory for.</s-paragraph>
                <s-button type="button" onclick="openResourcePicker()">🔍 Browse Products</s-button>
                <s-paragraph id="selected-count" tone="subdued">No products selected</s-paragraph>
            </s-section>

            <s-section heading="2. Inventory Rule">
                <s-paragraph tone="subdued">Define how inventory quantities should change.</s-paragraph>

                <s-select label="Action *" name="action" required>
                    <s-option value="set_quantity">Set quantity to</s-option>
                    <s-option value="add_quantity">Add to existing quantity</s-option>
                    <s-option value="remove_quantity">Remove from existing quantity</s-option>
                </s-select>

                <s-number-field label="Quantity *" name="quantity" placeholder="100" required></s-number-field>
            </s-section>

            <s-section heading="3. Options">
                <s-paragraph tone="subdued">Additional inventory settings.</s-paragraph>

                <s-checkbox label="Track inventory" name="track_inventory" value="1"></s-checkbox>
                <s-checkbox label="Continue selling when out of stock" name="continue_selling" value="1"></s-checkbox>
                <s-checkbox label="Apply to variants" name="apply_variants" value="1" checked></s-checkbox>
            </s-section>
        </form>

    </s-page>

@endsection

@section('scripts')
    @parent
<script>
    let selectedProductIds = [];

    function openConfirmModal(modalId, formId) {
        const quantity = document.querySelector('[name="quantity"]');
        if (!quantity || quantity.value === '') {
            alert('Please enter a quantity before executing.');
            if (quantity && quantity.focus) quantity.focus();
            return;
        }
        shopify.modal.show(modalId);
    }

    function openResourcePicker() {
        shopify.resourcePicker({ type: 'product', multiple: true }).then(result => {
            if (result) {
                selectedProductIds = result.map(p => p.id.replace('gid://shopify/Product/', ''));
                document.getElementById('selected-count').textContent = selectedProductIds.length + ' product(s) selected';
                document.getElementById('product-ids').value = JSON.stringify(selectedProductIds);
            }
        });
    }
</script>
@endsection
