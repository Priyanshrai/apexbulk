<s-modal id="{{ $modalId }}">
    <s-box padding="400">
        <s-stack style="flex-direction:column;gap:16px;">
            <s-text variant="headingMd" as="h2">{{ $title }}</s-text>
            <s-text tone="subdued">{{ $message }}</s-text>
            <s-banner tone="warning">⚠️ This cannot be undone automatically. A revert log will be saved.</s-banner>
            <s-stack distribution="trailing" style="gap:12px;">
                <s-button onclick="shopify.modal.hide('{{ $modalId }}')">Cancel</s-button>
                <s-button variant="primary" onclick="shopify.modal.hide('{{ $modalId }}').then(() => document.getElementById('{{ $formId }}').submit())">Yes, Execute</s-button>
            </s-stack>
        </s-stack>
    </s-box>
</s-modal>
