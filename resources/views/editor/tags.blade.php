@extends('shopify-app::layouts.default')

@section('content')

    <ui-title-bar title="ApexBulk > Tags Editor">
        <button onclick="location.href='{{ URL::tokenRoute('home', ['host' => request('host')]) }}'">← Dashboard</button>
        <button variant="primary" onclick="openConfirmModal('confirm-tag-modal', 'tag-form')">⚡ Execute</button>
    </ui-title-bar>

    @include('components.nav-menu')

    <s-page heading="Bulk Tags Editor">

        @include('components.usage-banner')

        @if($errors->any())
        <s-banner tone="critical" style="margin-bottom:16px;">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </s-banner>
        @endif

        @include('components.confirm-modal', [
            'modalId' => 'confirm-tag-modal',
            'formId' => 'tag-form',
            'title' => 'Confirm Bulk Tag Update',
            'message' => 'This action will modify tags on your live products. Make sure you\'ve reviewed your settings before proceeding.',
        ])

        @php $isFree = Auth::user()->isFree(); @endphp

        <form id="tag-form" method="POST" action="{{ route('editor.tags.submit') }}" style="display:flex;flex-direction:column;gap:24px;">
            @csrf
            @sessionToken
            <input type="hidden" name="product_ids" id="product-ids">

            <s-section heading="1. Select Products">
                <s-paragraph tone="subdued">Choose which products to update tags for.</s-paragraph>

                <s-select label="Selection Mode" name="selection_mode" required onchange="toggleBrowse()">
                    @if($isFree)
                    <s-option value="all" disabled>All Products 🔒 Pro</s-option>
                    @else
                    <s-option value="all">All Products</s-option>
                    @endif
                    <s-option value="manual">Manual Selection</s-option>
                </s-select>

                <div id="browse-section" style="display:none;">
                    <s-button type="button" onclick="openResourcePicker()">🔍 Browse Products</s-button>
                    <s-paragraph id="selected-count" tone="subdued">No products selected</s-paragraph>
                </div>
            </s-section>

            <s-section heading="2. Tag Action">
                <s-paragraph tone="subdued">Add, remove, or replace tags in bulk.</s-paragraph>

                <s-select label="Action *" name="action" required onchange="toggleTagField()">
                    <s-option value="add">Add tags to existing</s-option>
                    <s-option value="remove">Remove specific tags</s-option>
                    <s-option value="replace">Replace all tags</s-option>
                    <s-option value="clear">Clear all tags</s-option>
                </s-select>

                <div id="tags-field">
                    <s-text-field label="Tags (comma separated)" name="tags_input" placeholder="summer, sale, clearance"></s-text-field>
                </div>
                <div id="tags-hidden-inputs"></div>
            </s-section>

            <s-section heading="3. Schedule">
                <s-paragraph tone="subdued">Run now or schedule for a later time (your local time).</s-paragraph>

                <input type="hidden" name="is_scheduled" value="0">
                <s-checkbox label="Schedule for later" name="is_scheduled" value="1" onchange="var row=document.getElementById('schedule-row');row.style.display=this.checked?'flex':'none';if(this.checked){setTimeout(()=>row.scrollIntoView({behavior:'smooth',block:'nearest'}),100)}"></s-checkbox>

                <div id="schedule-row" style="display:none;gap:12px;align-items:flex-end;margin-top:12px;">
                    <input type="hidden" name="browser_tz" id="browser-tz">
                    <div style="flex:1;max-width:200px;">
                        <label style="display:block;font-size:12px;font-weight:500;margin-bottom:4px;color:var(--p-color-text-subdued);">Date</label>
                        <input type="date" name="schedule_date" style="width:100%;padding:8px 10px;border:1px solid var(--p-border);border-radius:6px;font-size:13px;background:var(--p-surface);color:var(--p-color-text-primary);box-sizing:border-box;">
                    </div>
                    <div style="width:120px;">
                        <label style="display:block;font-size:12px;font-weight:500;margin-bottom:4px;color:var(--p-color-text-subdued);">Time</label>
                        <input type="time" name="schedule_time" style="width:100%;padding:8px 10px;border:1px solid var(--p-border);border-radius:6px;font-size:13px;background:var(--p-surface);color:var(--p-color-text-primary);box-sizing:border-box;">
                    </div>
                </div>
            </s-section>
            <script>document.getElementById('browser-tz').value = Intl.DateTimeFormat().resolvedOptions().timeZone;</script>
        </form>

    </s-page>

@endsection

@section('scripts')
    @parent
    @php $previewUrl = route('editor.tags.preview') @endphp
<script>
    let selectedProductIds = [];

    function toggleBrowse() {
        const mode = document.querySelector('[name="selection_mode"]').value;
        document.getElementById('browse-section').style.display = mode === 'manual' ? 'block' : 'none';
    }
    document.addEventListener('DOMContentLoaded', toggleBrowse);

    function toggleTagField() {
        const action = document.querySelector('[name="action"]').value;
        document.getElementById('tags-field').style.display = action === 'clear' ? 'none' : 'block';
    }

    function buildTagsArray() {
        const action = document.querySelector('[name="action"]').value;
        const container = document.getElementById('tags-hidden-inputs');
        container.innerHTML = '';

        if (action === 'clear') {
            return [];
        }

        const input = document.querySelector('[name="tags_input"]');
        const tags = input.value.split(',').map(t => t.trim()).filter(Boolean);
        tags.forEach(tag => {
            const el = document.createElement('input');
            el.type = 'hidden';
            el.name = 'tags[]';
            el.value = tag;
            container.appendChild(el);
        });
        return tags;
    }

    function openConfirmModal(modalId, formId) {
        const mode = document.querySelector('[name="selection_mode"]');
        if (mode && mode.value === 'manual' && selectedProductIds.length === 0) {
            alert('Please select at least one product before executing. Use "Browse Products" to pick products.');
            return;
        }
        const action = document.querySelector('[name="action"]');
        if (action && action.value !== 'clear') {
            const tagsInput = document.querySelector('[name="tags_input"]');
            if (!tagsInput || tagsInput.value.trim() === '') {
                alert('Please enter tags or select "Clear all tags" before executing.');
                if (tagsInput && tagsInput.focus) tagsInput.focus();
                return;
            }
        }
        buildTagsArray();
        fetchTagsPreview(modalId, formId);
    }

    async function fetchTagsPreview(modalId, formId) {
        const form = document.getElementById(formId);
        const formData = new FormData(form);

        const payload = {};
        const tagArray = [];
        for (const [key, val] of formData.entries()) {
            if (key === '_token') continue;
            if (key === 'tags[]') {
                tagArray.push(val);
                continue;
            }
            payload[key] = val;
        }
        payload.tags = tagArray;

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
            const resp = await fetch('{{ $previewUrl }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + window.sessionToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify(payload),
            });

            const data = await resp.json();

            summaryEl.style.display = 'block';
            summaryEl.textContent = data.has_changes
                ? '🏷️ Action: ' + data.summary
                : '⚠️ ' + data.summary + ' (no changes detected)';

            previewEl.style.display = 'flex';
            previewEl.innerHTML = '';

            if (!data.preview_products || data.preview_products.length === 0) {
                previewEl.innerHTML = '<s-text tone="subdued" style="text-align:center;padding:12px;">No tag changes will be made with these settings.</s-text>';
            } else {
                data.preview_products.forEach(function(product) {
                    var rowEl = document.createElement('div');
                    rowEl.style.cssText = 'background:var(--p-surface);border-radius:6px;padding:8px 12px;';

                    // Old tags HTML
                    var oldTagsHtml = '';
                    if (product.old_tags.length === 0) {
                        oldTagsHtml = '<span style="font-size:11px;color:var(--p-color-text-subdued);font-style:italic;">(none)</span>';
                    } else {
                        product.old_tags.forEach(function(tag) {
                            var isRemoved = product.removed && product.removed.indexOf(tag) !== -1;
                            var style = isRemoved
                                ? 'text-decoration:line-through;color:#d82c0d;background:#fbeae5;'
                                : 'background:var(--p-surface-subdued);color:var(--p-color-text-subdued);';
                            oldTagsHtml += '<span style="display:inline-block;padding:1px 6px;border-radius:4px;font-size:11px;' + style + 'margin:1px 2px;">' + escapeHtml(tag) + '</span>';
                        });
                    }

                    // New tags HTML
                    var newTagsHtml = '';
                    if (product.new_tags.length === 0) {
                        newTagsHtml = '<span style="font-size:11px;color:var(--p-color-text-subdued);font-style:italic;">(none)</span>';
                    } else {
                        product.new_tags.forEach(function(tag) {
                            var added = product.added || [];
                            var isNew = added.indexOf(tag) !== -1;
                            var color = isNew ? 'color:#1a7f4b;background:#e6f4ec;' : 'background:var(--p-surface-subdued);color:var(--p-color-text-subdued);';
                            newTagsHtml += '<span style="display:inline-block;padding:1px 6px;border-radius:4px;font-size:11px;' + color + 'margin:1px 2px;">' + escapeHtml(tag) + '</span>';
                        });
                    }

                    rowEl.innerHTML =
                        '<div style="font-weight:500;font-size:13px;margin-bottom:6px;">🏷️ ' + escapeHtml(product.product_title) + '</div>' +
                        '<div style="line-height:1.8;">' + oldTagsHtml + ' <span style="color:var(--p-color-text-subdued);font-size:11px;">→</span> ' + newTagsHtml + '</div>';
                    previewEl.appendChild(rowEl);
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

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    document.getElementById('tag-form').addEventListener('submit', function(e) {
        buildTagsArray();
    });
    function openResourcePicker() {
        shopify.resourcePicker({ type: 'product', multiple: true }).then(result => {
            if (result) {
                selectedProductIds = result.map(p => p.id.replace('gid://shopify/Product/', ''));
                var titles = {};
                result.forEach(p => { titles[p.id.replace('gid://shopify/Product/', '')] = p.title; });
                document.getElementById('selected-count').textContent = selectedProductIds.length + ' product(s) selected';
                document.getElementById('product-ids').value = JSON.stringify(selectedProductIds);
                if (!document.getElementById('product-titles')) {
                    var pt = document.createElement('input');
                    pt.type = 'hidden';
                    pt.name = 'product_titles';
                    pt.id = 'product-titles';
                    document.getElementById('product-ids').parentNode.appendChild(pt);
                }
                document.getElementById('product-titles').value = JSON.stringify(titles);
            }
        });
    }
</script>
@endsection
