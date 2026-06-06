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
        fetchPricePreview(modalId, formId);
    }

    async function fetchPricePreview(modalId, formId) {
        const form = document.getElementById(formId);
        const formData = new FormData(form);

        // Build payload
        const payload = {};
        for (const [key, val] of formData.entries()) {
            if (key === '_token') continue;
            if (payload[key] !== undefined && val === '0') continue;
            payload[key] = val;
        }
        if (payload.apply_variants === '1') payload.apply_variants = true;
        else payload.apply_variants = false;

        const summaryEl = document.getElementById(modalId + '-summary');
        const previewEl = document.getElementById(modalId + '-preview');
        const moreEl = document.getElementById(modalId + '-more');

        // Show loading
        summaryEl.style.display = 'block';
        summaryEl.textContent = '⏳ Fetching preview...';
        previewEl.style.display = 'none';
        moreEl.style.display = 'none';

        try {
            const resp = await fetch('/editor/price/preview?' + new URLSearchParams(window.location.search).toString(), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                    'Accept': 'application/json',
                },
                body: JSON.stringify(payload),
            });

            const data = await resp.json();

            // Update summary
            summaryEl.style.display = 'block';
            summaryEl.textContent = data.has_changes
                ? '💰 Action: ' + data.summary
                : '⚠️ ' + data.summary + ' (no changes detected)';

            // Build preview — grouped by product
            previewEl.style.display = 'flex';
            previewEl.innerHTML = '';

            if (!data.preview_products || data.preview_products.length === 0) {
                previewEl.innerHTML = '<s-text tone="subdued" style="text-align:center;padding:12px;">No products will be changed with these settings.</s-text>';
            } else {
                data.preview_products.forEach(function(product) {
                    previewEl.appendChild(buildProductBlock(product, 'price'));
                });
            }

            // Show more indicator
            if (data.more_products > 0) {
                moreEl.style.display = 'block';
                moreEl.textContent = '📊 ' + data.shown_products + ' of ' + (data.shown_products + data.more_products) + ' products shown. ' + data.more_products + ' more will also be updated.';
            } else {
                moreEl.style.display = 'none';
            }
        } catch (err) {
            summaryEl.textContent = '⚠️ Could not load preview.';
            previewEl.innerHTML = '';
            moreEl.style.display = 'none';
        }

        shopify.modal.show(modalId);
    }

    function buildProductBlock(product, type) {
        var block = document.createElement('div');
        block.style.cssText = 'background:var(--p-surface);border-radius:6px;overflow:hidden;';

        var arrow = product.is_expandable ? '▼' : '';

        // Product header
        var header = document.createElement('div');
        if (product.is_expandable) {
            header.style.cssText = 'display:flex;align-items:center;justify-content:space-between;padding:8px 12px;cursor:pointer;user-select:none;';
        } else {
            header.style.cssText = 'display:flex;align-items:center;justify-content:space-between;padding:8px 12px;';
        }

        // When not expandable (1 variant or apply_variants off), show the single variant price inline
        if (!product.is_expandable && product.variants && product.variants.length === 1) {
            var v = product.variants[0];
            var prefix = type === 'price' ? '$' : '';
            header.innerHTML =
                '<div style="flex:1;min-width:0;">' +
                    '<span style="font-weight:500;font-size:13px;">🛍️ ' + escapeHtml(product.product_title) + '</span>' +
                    '<span style="font-size:12px;color:var(--p-color-text-subdued);margin-left:6px;">— ' + escapeHtml(v.variant_title) + '</span>' +
                '</div>' +
                '<div style="display:flex;align-items:center;gap:8px;flex-shrink:0;">' +
                    '<span style="color:var(--p-color-text-subdued);font-size:13px;">' + prefix + escapeHtml(v.old_value) + '</span>' +
                    '<span style="color:var(--p-color-text-subdued);">→</span>' +
                    '<span style="font-weight:600;font-size:13px;color:var(--p-color-text-primary);">' + prefix + escapeHtml(v.new_value) + '</span>' +
                '</div>';
        } else {
            header.innerHTML =
                '<div style="font-weight:500;font-size:13px;">🛍️ ' + escapeHtml(product.product_title) + '</div>' +
                '<span style="font-size:12px;color:var(--p-color-text-subdued);">' + arrow + '</span>';
        }

        block.appendChild(header);

        // Variant list (hidden by default if expandable)
        if (product.is_expandable && product.variants && product.variants.length > 0) {
            var variantList = document.createElement('div');
            variantList.style.cssText = 'display:none;border-top:1px solid var(--p-divider);';

            product.variants.forEach(function(v) {
                var vRow = document.createElement('div');
                vRow.style.cssText = 'display:flex;align-items:center;justify-content:space-between;padding:6px 12px 6px 28px;font-size:12px;';
                vRow.innerHTML =
                    '<span style="color:var(--p-color-text-subdued);flex:1;">' + escapeHtml(v.variant_title) + '</span>' +
                    '<span style="display:flex;align-items:center;gap:8px;flex-shrink:0;">' +
                        '<span style="color:var(--p-color-text-subdued);">$' + escapeHtml(v.old_value) + '</span>' +
                        '<span style="color:var(--p-color-text-subdued);">→</span>' +
                        '<span style="font-weight:600;color:var(--p-color-text-primary);">$' + escapeHtml(v.new_value) + '</span>' +
                    '</span>';
                variantList.appendChild(vRow);
            });

            // Variant more indicator
            if (product.variant_more > 0) {
                var vMore = document.createElement('div');
                vMore.style.cssText = 'padding:4px 12px 8px 28px;font-size:11px;color:var(--p-color-text-subdued);';
                vMore.textContent = '📊 ' + product.variant_shown + ' of ' + product.total_variants + ' variants shown (' + product.variant_more + ' more)';
                variantList.appendChild(vMore);
            }

            block.appendChild(variantList);

            // Toggle expand/collapse
            header.addEventListener('click', function() {
                if (variantList.style.display === 'none') {
                    variantList.style.display = 'block';
                    header.querySelector('span:last-child').textContent = '▲';
                } else {
                    variantList.style.display = 'none';
                    header.querySelector('span:last-child').textContent = '▼';
                }
            });
        }

        return block;
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
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
