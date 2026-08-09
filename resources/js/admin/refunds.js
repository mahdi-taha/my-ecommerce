const lookup = document.querySelector('[data-refund-order-lookup]');

if (lookup) {
    const search = lookup.querySelector('#refund-order-search');
    const status = lookup.querySelector('#refund-order-search-status');
    const results = lookup.querySelector('[data-refund-order-results]');
    let debounceTimer = null;
    let requestController = null;

    const setStatus = message => {
        status.textContent = message;
    };

    const createDetail = (label, value) => {
        const detail = document.createElement('span');
        detail.className = 'small text-muted';
        detail.textContent = `${label}: ${value}`;

        return detail;
    };

    const renderResults = orders => {
        results.replaceChildren();

        if (!orders.length) {
            setStatus('No eligible Orders found.');
            return;
        }

        orders.forEach(order => {
            const link = document.createElement('a');
            link.className = 'list-group-item list-group-item-action';
            link.href = order.select_url;

            const heading = document.createElement('div');
            heading.className = 'd-flex flex-wrap justify-content-between gap-2 fw-semibold';

            const orderNumber = document.createElement('span');
            orderNumber.textContent = order.order_number;

            const total = document.createElement('bdi');
            total.dir = 'ltr';
            total.textContent = order.formatted_grand_total;
            heading.append(orderNumber, total);

            const customer = document.createElement('div');
            customer.className = 'mt-1';
            customer.textContent = `${order.customer_name} — ${order.customer_email}`;

            const metadata = document.createElement('div');
            metadata.className = 'd-flex flex-wrap gap-3 mt-1';
            metadata.append(
                createDetail('Payment', order.payment_status_label),
                createDetail('Fulfillment', order.fulfillment_status_label),
                createDetail('Currency', order.currency_code),
            );

            link.append(heading, customer, metadata);
            results.append(link);
        });

        setStatus(`${orders.length} eligible ${orders.length === 1 ? 'Order' : 'Orders'} found.`);
    };

    const searchOrders = async term => {
        requestController?.abort();
        requestController = new AbortController();
        setStatus('Searching Orders…');
        results.replaceChildren();

        try {
            const url = new URL(lookup.dataset.lookupUrl, window.location.origin);
            url.searchParams.set('q', term);
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                signal: requestController.signal,
            });

            if (!response.ok) {
                throw new Error('Order lookup failed.');
            }

            renderResults(await response.json());
        } catch (error) {
            if (error.name !== 'AbortError') {
                setStatus('Orders could not be loaded. Please try again.');
            }
        }
    };

    search.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        const term = search.value.trim();

        if (term.length < 2) {
            requestController?.abort();
            results.replaceChildren();
            setStatus(term.length ? 'Enter at least 2 characters.' : '');
            return;
        }

        debounceTimer = setTimeout(() => searchOrders(term), 300);
    });
}
