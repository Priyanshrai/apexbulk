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
        <button variant="primary" onclick="(function(){var f=document.getElementById('{{ $formId }}');var formData={};f.querySelectorAll('s-select,s-number-field,s-checkbox,s-text-field').forEach(function(el){var n=el.getAttribute('name');if(!n)return;var v=el.value!==undefined?el.value:(el.checked!==undefined?el.checked:'');var h=f.querySelector('input[type=hidden][data-pc='+n+']');if(!h){h=document.createElement('input');h.type='hidden';h.name=n;h.setAttribute('data-pc',n);f.appendChild(h);}h.value=v;formData[n]=v;});var hiddenInputs=f.querySelectorAll('input[type=hidden]');hiddenInputs.forEach(function(el){if(el.name&&!formData.hasOwnProperty(el.name)){formData[el.name]=el.value;}});console.log('=== Form Data ({{ $formId }}) ===');console.log(JSON.stringify(formData,null,2));console.log('Action URL:',f.action);console.log('Method:',f.method);console.log('=============================');shopify.modal.hide('{{ $modalId }}').then(function(){f.submit();});})()">Execute update</button>
    </ui-title-bar>
</ui-modal>
