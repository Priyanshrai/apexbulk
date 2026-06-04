<ui-modal id="{{ $modalId }}">
    <div style="padding:20px 24px;display:flex;flex-direction:column;gap:14px;">
        <s-text tone="subdued">{{ $message }}</s-text>
        <s-banner tone="warning">This action cannot be undone automatically. A revert log will be saved.</s-banner>
        <s-text tone="subdued">Please confirm to continue.</s-text>
    </div>

    <ui-title-bar title="{{ $title }}">
        <button onclick="shopify.modal.hide('{{ $modalId }}')">Cancel</button>
        <button variant="primary" onclick="shopify.modal.hide('{{ $modalId }}').then(() => document.getElementById('{{ $formId }}').submit())">Execute update</button>
    </ui-title-bar>
</ui-modal>
