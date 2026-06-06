@extends('shopify-app::layouts.default')

@section('content')

    <ui-title-bar title="ApexBulk > Inventory Editor">
        <button variant="primary" onclick="openConfirmModal('confirm-inv-modal', 'inv-form')">⚡ Execute</button>
    </ui-title-bar>

    @include('components.nav-menu')

    @if($errors->any())
    <s-banner tone="critical" style="margin-bottom:16px;">
        @foreach($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </s-banner>
    @endif

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

                <s-select label="Selection Mode" name="selection_mode" required onchange="toggleBrowse()">
                    <s-option value="all">All Products</s-option>
                    <s-option value="manual">Manual Selection</s-option>
                </s-select>

                <div id="browse-section" style="display:none;">
                    <s-button type="button" onclick="openResourcePicker()">🔍 Browse Products</s-button>
                    <s-paragraph id="selected-count" tone="subdued">No products selected</s-paragraph>
                </div>
            </s-section>

            <s-section heading="2. Location">
                <s-paragraph tone="subdued">Choose which warehouse/location to update inventory for.</s-paragraph>

                <s-select label="Location *" name="location_id">
                    <s-option value="all">All Locations</s-option>
                    @foreach($locations as $loc)
                        <s-option value="{{ $loc['id'] }}">{{ $loc['name'] }}</s-option>
                    @endforeach
                </s-select>
                <s-paragraph tone="subdued" style="margin-top:4px;">💡 Only locations where the product is currently stocked will be updated.</s-paragraph>
            </s-section>

            <s-section heading="3. Inventory Rule">
                <s-paragraph tone="subdued">Define how inventory quantities should change.</s-paragraph>

                <s-select label="Action *" name="action" required>
                    <s-option value="set_quantity">Set quantity to</s-option>
                    <s-option value="add_quantity">Add to existing quantity</s-option>
                    <s-option value="remove_quantity">Remove from existing quantity</s-option>
                </s-select>

                <s-number-field label="Quantity *" name="quantity" placeholder="100" required></s-number-field>
            </s-section>

            <s-section heading="4. Options">
                <s-paragraph tone="subdued">Additional inventory settings.</s-paragraph>

                <input type="hidden" name="track_inventory" value="0">
                <s-checkbox label="Track inventory" name="track_inventory" value="1"></s-checkbox>
                <input type="hidden" name="continue_selling" value="0">
                <s-checkbox label="Continue selling when out of stock" name="continue_selling" value="1"></s-checkbox>
                <input type="hidden" name="apply_variants" value="0">
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
        const mode = document.querySelector('[name="selection_mode"]');
        if (mode && mode.value === 'manual' && selectedProductIds.length === 0) {
            alert('Please select at least one product before executing. Use "Browse Products" to pick products.');
            return;
        }
        const quantity = document.querySelector('[name="quantity"]');
        if (!quantity || quantity.value === '') {
            alert('Please enter a quantity before executing.');
            if (quantity && quantity.focus) quantity.focus();
            return;
        }
        fetchInventoryPreview(modalId, formId);
    }

    async function fetchInventoryPreview(modalId, formId) {
        const form = document.getElementById(formId);
        const formData = new FormData(form);

        const payload = {};
        for (const [key, val] of formData.entries()) {
            if (key === '_token') continue;
            if (payload[key] !== undefined && val === '0') continue;
            payload[key] = val;
        }
        if (payload.apply_variants === '1') payload.apply_variants = true;
        else payload.apply_variants = false;
        if (payload.track_inventory === '1') payload.track_inventory = true;
        else payload.track_inventory = false;
        if (payload.continue_selling === '1') payload.continue_selling = true;
        else payload.continue_selling = false;

        const summaryEl = document.getElementById(modalId + '-summary');
        const previewEl = document.getElementById(modalId + '-preview');
        const moreEl = document.getElementById(modalId + '-more');

        // Disable Execute button while loading
        var execBtn = document.getElementById(modalId + '-execute-btn');
        if (execBtn) { execBtn.disabled = true; }

        summaryEl.style.display = 'block';
        summaryEl.innerHTML = '<div style="display:flex;align-items:center;gap:8px;"><s-spinner size="small"></s-spinner> Fetching preview...</div>';
        previewEl.style.display = 'none';
        moreEl.style.display = 'none';

        shopify.modal.show(modalId);

        try {
            const resp = await fetch('/editor/inventory/preview?' + new URLSearchParams(window.location.search).toString(), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                    'Accept': 'application/json',
                },
                body: JSON.stringify(payload),
            });

            const data = await resp.json();

            summaryEl.style.display = 'block';
            summaryEl.textContent = data.has_changes
                ? '📦 Action: ' + data.summary
                : '⚠️ ' + data.summary + ' (no changes detected)';

            previewEl.style.display = 'flex';
            previewEl.innerHTML = '';

            if (!data.preview_products || data.preview_products.length === 0) {
                previewEl.innerHTML = '<s-text tone="subdued" style="text-align:center;padding:12px;">No inventory changes will be made with these settings. Products may not be stocked at the selected location.</s-text>';
            } else {
                data.preview_products.forEach(function(product) {
                    previewEl.appendChild(buildInventoryProductBlock(product));
                });
            }

            if (data.more_products > 0) {
                moreEl.style.display = 'block';
                moreEl.textContent = '📊 ' + data.shown_products + ' of ' + (data.shown_products + data.more_products) + ' products shown. ' + data.more_products + ' more will also be updated.';
            } else {
                moreEl.style.display = 'none';
            }

            // Enable Execute button now that preview is ready
            apexEnableExecute(modalId);
        } catch (err) {
            summaryEl.innerHTML = '⚠️ Could not load preview.';
            previewEl.innerHTML = '';
            moreEl.style.display = 'none';
        }
    }

    function buildInventoryProductBlock(product) {
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

        if (!product.is_expandable && product.variants && product.variants.length === 1) {
            var v = product.variants[0];
            header.innerHTML =
                '<div style="flex:1;min-width:0;">' +
                    '<span style="font-weight:500;font-size:13px;">🛍️ ' + escapeHtml(product.product_title) + '</span>' +
                    '<span style="font-size:12px;color:var(--p-color-text-subdued);margin-left:6px;">— ' + escapeHtml(v.variant_title) + ' · ' + escapeHtml(v.location) + '</span>' +
                '</div>' +
                '<div style="display:flex;align-items:center;gap:8px;flex-shrink:0;">' +
                    '<span style="color:var(--p-color-text-subdued);font-size:13px;">' + escapeHtml(v.old_value) + '</span>' +
                    '<span style="color:var(--p-color-text-subdued);">→</span>' +
                    '<span style="font-weight:600;font-size:13px;color:var(--p-color-text-primary);">' + escapeHtml(v.new_value) + '</span>' +
                '</div>';
        } else {
            header.innerHTML =
                '<div style="font-weight:500;font-size:13px;">🛍️ ' + escapeHtml(product.product_title) + '</div>' +
                '<span style="font-size:12px;color:var(--p-color-text-subdued);">' + arrow + '</span>';
        }

        block.appendChild(header);

        if (product.is_expandable && product.variants && product.variants.length > 0) {
            var variantList = document.createElement('div');
            variantList.style.cssText = 'display:none;border-top:1px solid var(--p-divider);';

            product.variants.forEach(function(v) {
                var vRow = document.createElement('div');
                vRow.style.cssText = 'display:flex;align-items:center;justify-content:space-between;padding:6px 12px 6px 28px;font-size:12px;';
                vRow.innerHTML =
                    '<span style="color:var(--p-color-text-subdued);flex:1;">' + escapeHtml(v.variant_title) + ' · ' + escapeHtml(v.location) + '</span>' +
                    '<span style="display:flex;align-items:center;gap:8px;flex-shrink:0;">' +
                        '<span style="color:var(--p-color-text-subdued);">' + escapeHtml(v.old_value) + '</span>' +
                        '<span style="color:var(--p-color-text-subdued);">→</span>' +
                        '<span style="font-weight:600;color:var(--p-color-text-primary);">' + escapeHtml(v.new_value) + '</span>' +
                    '</span>';
                variantList.appendChild(vRow);
            });

            if (product.variant_more > 0) {
                var vMore = document.createElement('div');
                vMore.style.cssText = 'padding:4px 12px 8px 28px;font-size:11px;color:var(--p-color-text-subdued);';
                vMore.textContent = '📊 ' + product.variant_shown + ' of ' + product.total_variants + ' variants shown (' + product.variant_more + ' more)';
                variantList.appendChild(vMore);
            }

            block.appendChild(variantList);

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

    function toggleBrowse() {
        const mode = document.querySelector('[name="selection_mode"]').value;
        document.getElementById('browse-section').style.display = mode === 'manual' ? 'block' : 'none';
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
