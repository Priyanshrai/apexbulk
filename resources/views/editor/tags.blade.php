@extends('shopify-app::layouts.default')

@section('content')

    <ui-title-bar title="ApexBulk > Tags Editor">
        <button variant="primary" onclick="openConfirmModal('confirm-tag-modal', 'tag-form')">⚡ Execute</button>
    </ui-title-bar>

    @include('components.nav-menu')

    @include('components.confirm-modal', [
        'modalId' => 'confirm-tag-modal',
        'formId' => 'tag-form',
        'title' => 'Confirm Bulk Tag Update',
        'message' => 'This action will modify tags on your live products. Make sure you\'ve reviewed your settings before proceeding.',
    ])

    <s-page heading="Bulk Tags Editor">

        <form id="tag-form" method="POST" action="{{ url('/editor/tags') }}?{{ http_build_query(request()->query()) }}" style="display:flex;flex-direction:column;gap:24px;">
            @csrf
            <input type="hidden" name="product_ids" id="product-ids">

            <s-section heading="1. Select Products">
                <s-paragraph tone="subdued">Choose which products to update tags for.</s-paragraph>
                <s-button type="button" onclick="openResourcePicker()">🔍 Browse Products</s-button>
                <s-paragraph id="selected-count" tone="subdued">No products selected</s-paragraph>
            </s-section>

            <s-section heading="2. Tag Action">
                <s-paragraph tone="subdued">Add, remove, or replace tags in bulk.</s-paragraph>

                <s-select label="Action *" name="action" required>
                    <s-option value="add">Add tags to existing</s-option>
                    <s-option value="remove">Remove specific tags</s-option>
                    <s-option value="replace">Replace all tags</s-option>
                    <s-option value="clear">Clear all tags</s-option>
                </s-select>

                <s-text-field label="Tags (comma separated)" name="tags_input" placeholder="summer, sale, clearance"></s-text-field>
                <input type="hidden" name="tags" id="tags-array">
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
        if (action && action.value === 'clear') {
            shopify.modal.show(modalId);
            return;
        }
        const tagsInput = document.querySelector('[name="tags_input"]');
        if (!tagsInput || tagsInput.value.trim() === '') {
            alert('Please enter tags or select "Clear all tags" before executing.');
            if (tagsInput && tagsInput.focus) tagsInput.focus();
            return;
        }
        shopify.modal.show(modalId);
    }

    document.getElementById('tag-form').addEventListener('submit', function() {
        const input = document.querySelector('[name="tags_input"]');
        const tags = input.value.split(',').map(t => t.trim()).filter(Boolean);
        document.getElementById('tags-array').value = JSON.stringify(tags);
    });
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
