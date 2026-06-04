@extends('shopify-app::layouts.default')

@section('content')

    <ui-title-bar title="ApexBulk > Price Editor">
        <button variant="primary" onclick="openConfirmModal('confirm-price-modal', 'price-form')">⚡ Execute</button>
    </ui-title-bar>

    @include('components.nav-menu')

    @include('components.confirm-modal', [
        'modalId' => 'confirm-price-modal',
        'formId' => 'price-form',
        'title' => 'Confirm Bulk Price Update',
        'message' => 'This action will modify prices on your live products. Make sure you\'ve reviewed your settings before proceeding.',
    ])

    <s-page heading="Bulk Price Editor">

        <form id="price-form" method="POST" action="{{ url('/editor/price') }}?{{ http_build_query(request()->query()) }}" style="display:flex;flex-direction:column;gap:24px;">
            @csrf
            <input type="hidden" name="product_ids" id="product-ids">

            <s-section heading="1. Select Products">
                <s-paragraph tone="subdued">Choose which products to update prices for.</s-paragraph>

                <s-select label="Selection Mode" name="selection_mode" required onchange="toggleBrowse()">
                    <s-option value="all">All Products</s-option>
                    <s-option value="manual">Manual Selection</s-option>
                </s-select>

                <div id="browse-section" style="display:none;">
                    <s-button type="button" onclick="openResourcePicker()">🔍 Browse Products</s-button>
                    <s-paragraph id="selected-count" tone="subdued">No products selected</s-paragraph>
                </div>
            </s-section>

            <s-section heading="2. Price Action">
                <s-paragraph tone="subdued">Define how prices should be modified.</s-paragraph>

                <s-select label="Action *" name="action" required>
                    <s-option value="set_specific">Set to a specific price</s-option>
                    <s-option value="increase_amount">Increase by amount</s-option>
                    <s-option value="decrease_amount">Decrease by amount</s-option>
                    <s-option value="increase_percent">Increase by percentage (%)</s-option>
                    <s-option value="decrease_percent">Decrease by percentage (%)</s-option>
                </s-select>

                <s-number-field label="Value *" name="value" step="0.01" placeholder="10.00" required></s-number-field>
            </s-section>

            <s-section heading="3. Options">
                <s-paragraph tone="subdued">Apply rounding and choose scope.</s-paragraph>

                <s-select label="Rounding" name="rounding" onchange="toggleRounding()">
                    <s-option value="none">No rounding</s-option>
                    <s-option value="nearest_01">Nearest cent</s-option>
                    <s-option value="nearest_whole">Nearest whole number</s-option>
                    <s-option value="end_99">End in .99</s-option>
                    <s-option value="end_custom">Custom ending value</s-option>
                </s-select>

                <div id="custom-rounding" style="display:none;">
                    <s-number-field label="Custom ending value (0.00 - 0.99)" name="rounding_value" min="0" max="0.99" step="0.01" placeholder="0.99"></s-number-field>
                </div>

                <input type="hidden" name="apply_variants" value="0">
                <s-checkbox label="Apply to product variants (recommended)" name="apply_variants" value="1" checked></s-checkbox>
            </s-section>
        </form>

    </s-page>

@endsection

@section('scripts')
    @parent
<script>
    let selectedProductIds = [];

    function openConfirmModal(modalId, formId) {
        const action = document.querySelector('[name="action"]');
        const value = document.querySelector('[name="value"]');
        if (!value || value.value === '') {
            alert('Please enter a value before executing.');
            if (value && value.focus) value.focus();
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

    // Show/hide Browse button based on selection mode
    function toggleBrowse() {
        const mode = document.querySelector('[name="selection_mode"]').value;
        document.getElementById('browse-section').style.display = mode === 'manual' ? 'block' : 'none';
    }

    // Show/hide custom rounding field
    function toggleRounding() {
        const rounding = document.querySelector('[name="rounding"]').value;
        document.getElementById('custom-rounding').style.display = rounding === 'end_custom' ? 'block' : 'none';
    }
</script>
@endsection
