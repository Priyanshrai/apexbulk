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
// Enable the Execute button (called by editor pages after preview loads)
window.apexEnableExecute = function(modalId) {
    var btn = document.getElementById(modalId + '-execute-btn');
    if (btn) {
        btn.disabled = false;
    }
};

// Robust form submission for Shopify Polaris web components
// Called when user clicks "Execute" in any confirm-modal
window.apexConfirmSubmit = function(modalId, formId) {
    var f = document.getElementById(formId);
    if (!f) { console.error('Form not found:', formId); return; }

    // 1. Remove stale data-pc hidden inputs from any previous attempts
    f.querySelectorAll('input[type=hidden][data-pc]').forEach(function(el) { el.remove(); });

    // 2. Read every Polaris web component and create fresh hidden inputs
    f.querySelectorAll('s-select, s-number-field, s-checkbox, s-text-field').forEach(function(el) {
        var name = el.getAttribute('name');
        if (!name) return;

        var value;
        var tag = el.tagName.toLowerCase();

        if (tag === 's-checkbox') {
            // s-checkbox: checked state + fallback to getAttribute('value')
            value = el.checked ? (el.getAttribute('value') || '1') : '0';
        } else if (tag === 's-number-field') {
            // s-number-field: .value may be '' even when user typed — try getAttribute fallback
            var raw = el.value;
            value = (raw !== undefined && raw !== null && raw !== '') ? raw : (el.getAttribute('value') || '');
        } else {
            // s-select, s-text-field
            value = (el.value !== undefined && el.value !== null) ? el.value : (el.getAttribute('value') || '');
        }

        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.setAttribute('data-pc', name);
        input.value = value;
        f.appendChild(input);
    });

    // 3. Debug: log what will be submitted
    var payload = {};
    f.querySelectorAll('input[type=hidden]').forEach(function(inp) {
        if (inp.name) payload[inp.name] = inp.value;
    });
    console.log('📤 Submitting ' + formId, payload);

    // 4. Build URL-encoded body from hidden inputs
    var body = new URLSearchParams();
    f.querySelectorAll('input[type=hidden]').forEach(function(inp) {
        if (inp.name) body.append(inp.name, inp.value);
    });

    // 5. Submit via fetch (bypasses App Bridge form.submit interception)
    //    Use getAttribute('action') NOT f.action — form controls named "action"
    //    override the DOM .action property (HTML spec bug/quirk).
    var actionUrl = f.getAttribute('action');
    fetch(actionUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'Accept': 'text/html',
        },
        body: body.toString(),
        redirect: 'follow',
    }).then(function(response) {
        if (response.ok) {
            // Navigate to the final page (server's redirect destination)
            window.location.href = response.url;
        } else {
            console.error('Server returned:', response.status);
            window.location.reload();
        }
    }).catch(function(err) {
        console.error('Fetch failed:', err);
        window.location.reload();
    });
};
</script>
