<ui-modal id="{{ $modalId }}">
    <div style="padding:20px 24px;display:flex;flex-direction:column;gap:14px;">
        <s-text tone="subdued">{{ $message }}</s-text>

        {{-- Preview summary --}}
        <div id="{{ $modalId }}-summary" style="display:none;padding:10px 14px;background:var(--p-surface-subdued);border-radius:8px;font-size:13px;font-weight:500;">
        </div>

        {{-- Preview rows (before/after) --}}
        <div id="{{ $modalId }}-preview" style="display:none;flex-direction:column;gap:8px;">
        </div>

        {{-- More products indicator --}}
        <div id="{{ $modalId }}-more" style="display:none;font-size:12px;color:var(--p-color-text-subdued);text-align:center;">
        </div>

        <s-banner tone="warning">This action cannot be undone automatically. A revert log will be saved.</s-banner>
        <s-text tone="subdued">Please confirm to continue.</s-text>
    </div>

    <ui-title-bar title="{{ $title }}">
        <button onclick="shopify.modal.hide('{{ $modalId }}')">Cancel</button>
        <button variant="primary" id="{{ $modalId }}-execute-btn" disabled onclick="apexConfirmSubmit('{{ $modalId }}', '{{ $formId }}')">Execute update</button>
    </ui-title-bar>
</ui-modal>

<script>
// Enable Execute button after preview loads
window.apexEnableExecute = function(modalId) {
    var btn = document.getElementById(modalId + '-execute-btn');
    if (btn) btn.disabled = false;
};

// Submit Polaris form — reads web component values and posts via fetch
window.apexConfirmSubmit = function(modalId, formId) {
    var f = document.getElementById(formId);
    if (!f) return;

    // Collect values from Polaris web components (they don't participate in normal form submit)
    var body = new URLSearchParams();
    f.querySelectorAll('input[type=hidden]').forEach(function(el) {
        if (el.name) body.append(el.name, el.value);
    });
    f.querySelectorAll('s-select, s-number-field, s-checkbox, s-text-field').forEach(function(el) {
        var name = el.getAttribute('name');
        if (!name) return;
        var val = el.tagName.toLowerCase() === 's-checkbox' ? (el.checked ? (el.getAttribute('value') || '1') : '0') : (el.value || '');
        body.set(name, val);
    });

    fetch(f.getAttribute('action'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString(),
        redirect: 'follow',
    }).then(function(resp) {
        resp.ok ? window.location.href = resp.url : window.location.reload();
    }).catch(function() {
        window.location.reload();
    });
};
</script>
