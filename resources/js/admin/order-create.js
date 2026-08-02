const form = document.querySelector('#admin-order-form');

if (form) {
    const customer = $('#admin-order-customer');
    const product = $('#admin-order-product');
    const linesBody = form.querySelector('#admin-order-lines');
    const configuration = form.querySelector('#configurable-selection');
    const createButton = form.querySelector('[data-create-order]');
    const errorBox = form.querySelector('[data-summary-error]');
    let lines = [];
    let selectedCustomer = null;
    let pendingProduct = null;
    let requestController = null;

    const remoteSelect = (element, url, placeholder) => element.select2({
        width: '100%',
        placeholder,
        minimumInputLength: 0,
        ajax: {
            url,
            dataType: 'json',
            delay: 200,
            data: params => ({ q: params.term || '' }),
            processResults: response => response,
        },
    });

    remoteSelect(customer, form.dataset.customerUrl, 'Search Customers');
    remoteSelect(product, form.dataset.productUrl, 'Search Products');

    customer.on('select2:select', event => {
        selectedCustomer = event.params.data;
        const saved = form.querySelector('#saved-address-id');
        saved.replaceChildren();
        (selectedCustomer.addresses || []).forEach(address => {
            saved.append(new Option(address.label, address.id));
        });
        form.querySelector('#address-source-saved').disabled = !selectedCustomer.addresses?.length;
        refreshSummary();
    });

    product.on('select2:select', event => {
        pendingProduct = event.params.data;
        configuration.classList.add('d-none');
        configuration.replaceChildren();
    });

    form.querySelectorAll('[name="address_source"]').forEach(input => input.addEventListener('change', () => {
        const saved = input.value === 'saved' && input.checked;
        form.querySelector('#saved-address-section').classList.toggle('d-none', !saved);
        form.querySelector('#saved-address-id').disabled = !saved;
        form.querySelector('#manual-address-section').classList.toggle('d-none', saved);
        form.querySelectorAll('#manual-address-section input').forEach(field => {
            field.disabled = saved;
        });
        refreshSummary();
    }));

    form.querySelector('#add-admin-order-product').addEventListener('click', async () => {
        if (!pendingProduct) return;
        if (pendingProduct.type === 'simple') {
            addLine({
                product_id: Number(pendingProduct.id), parent_product_id: null,
                product_type: 'simple', quantity: 1, options: {},
                name: pendingProduct.text, sku: pendingProduct.sku,
            });
            return;
        }

        const url = form.dataset.configurationUrl.replace('__PRODUCT__', pendingProduct.id);
        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!response.ok) return showError('The Product configuration is unavailable.');
        const data = await response.json();
        renderConfiguration(data);
    });

    function renderConfiguration(data) {
        configuration.replaceChildren();
        configuration.classList.remove('d-none');
        const heading = document.createElement('h5');
        heading.textContent = 'Choose Options';
        configuration.append(heading);
        data.attributes.forEach(attribute => {
            const wrapper = document.createElement('div');
            wrapper.className = 'mb-2';
            const label = document.createElement('label');
            label.className = 'form-label';
            label.textContent = attribute.name;
            const select = document.createElement('select');
            select.className = 'form-select';
            select.dataset.attributeId = attribute.id;
            select.append(new Option('Select', ''));
            attribute.options.forEach(option => select.append(new Option(option.label, option.id)));
            wrapper.append(label, select);
            configuration.append(wrapper);
        });
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-primary';
        button.textContent = 'Add Selected Variant';
        button.addEventListener('click', () => {
            const options = {};
            configuration.querySelectorAll('select').forEach(select => {
                if (select.value) options[select.dataset.attributeId] = Number(select.value);
            });
            const variant = data.variants.find(candidate => Object.entries(options).every(
                ([attributeId, optionId]) => Number(candidate.options[attributeId]) === optionId
            ) && Object.keys(candidate.options).length === Object.keys(options).length);
            if (!variant) return showError('The selected Product configuration is unavailable.');
            addLine({
                product_id: Number(variant.id), parent_product_id: Number(data.product_id),
                product_type: 'configurable', quantity: 1, options,
                name: pendingProduct.text, sku: variant.sku,
            });
            configuration.classList.add('d-none');
        });
        configuration.append(button);
    }

    function addLine(line) {
        const existing = lines.find(candidate => candidate.product_id === line.product_id);
        if (existing) existing.quantity += 1;
        else lines.push(line);
        renderLines();
        refreshSummary();
    }

    function renderLines() {
        linesBody.replaceChildren();
        if (!lines.length) {
            const row = document.createElement('tr');
            row.innerHTML = '<td colspan="4" class="text-center text-muted">No Products added.</td>';
            linesBody.append(row);
            return;
        }
        lines.forEach((line, index) => {
            const row = document.createElement('tr');
            const name = document.createElement('td');
            name.textContent = line.name;
            const sku = document.createElement('td');
            sku.textContent = line.sku;
            const quantityCell = document.createElement('td');
            const quantity = document.createElement('input');
            quantity.type = 'number'; quantity.min = '1'; quantity.step = '1';
            quantity.className = 'form-control'; quantity.value = line.quantity;
            quantity.addEventListener('change', () => {
                line.quantity = Math.max(1, Number.parseInt(quantity.value, 10) || 1);
                syncInputs(); refreshSummary();
            });
            quantityCell.append(quantity);
            const action = document.createElement('td');
            const remove = document.createElement('button');
            remove.type = 'button'; remove.className = 'btn btn-sm btn-outline-danger'; remove.textContent = 'Remove';
            remove.addEventListener('click', () => { lines.splice(index, 1); renderLines(); refreshSummary(); });
            action.append(remove);
            row.append(name, sku, quantityCell, action);
            linesBody.append(row);
        });
        syncInputs();
    }

    function syncInputs() {
        form.querySelectorAll('[data-order-line-input]').forEach(input => input.remove());
        lines.forEach((line, index) => {
            Object.entries(line).forEach(([key, value]) => {
                if (['name', 'sku', 'options'].includes(key)) return;
                hidden(`items[${index}][${key}]`, value ?? '');
            });
            Object.entries(line.options).forEach(([attributeId, optionId]) => {
                hidden(`items[${index}][options][${attributeId}]`, optionId);
            });
        });
    }

    function hidden(name, value) {
        const input = document.createElement('input');
        input.type = 'hidden'; input.name = name; input.value = String(value);
        input.dataset.orderLineInput = 'true'; form.append(input);
    }

    async function refreshSummary() {
        createButton.disabled = true;
        hideError();
        syncInputs();
        if (!selectedCustomer || !lines.length) return;
        requestController?.abort();
        requestController = new AbortController();
        try {
            const response = await fetch(form.dataset.summaryUrl, {
                method: 'POST', body: new FormData(form), signal: requestController.signal,
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value },
            });
            const data = await response.json();
            if (!response.ok || !data.success) {
                return showError(Object.values(data.errors || {}).flat().map(error => error.message || error).join(' '));
            }
            form.querySelector('[data-summary-subtotal]').textContent = data.summary.formatted_subtotal;
            form.querySelector('[data-summary-discount]').textContent = data.summary.formatted_discount_total;
            form.querySelector('[data-summary-tax]').textContent = data.summary.formatted_tax_total;
            form.querySelector('[data-summary-shipping]').textContent = data.summary.formatted_shipping_amount;
            form.querySelector('[data-summary-grand]').textContent = data.summary.formatted_grand_total;
            createButton.disabled = false;
        } catch (error) {
            if (error.name !== 'AbortError') showError('The authoritative Order summary could not be loaded.');
        }
    }

    function showError(message) { errorBox.textContent = message || 'The Order data is invalid.'; errorBox.classList.remove('d-none'); }
    function hideError() { errorBox.textContent = ''; errorBox.classList.add('d-none'); }

    form.addEventListener('change', event => {
        if (event.target.matches('#shipping-method, #payment-method, #saved-address-id, #manual-address-section input')) refreshSummary();
    });
    form.addEventListener('submit', () => { createButton.disabled = true; });
}
