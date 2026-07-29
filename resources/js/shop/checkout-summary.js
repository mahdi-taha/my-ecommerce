export function initializeCheckoutSummary() {
    const form = document.querySelector('[data-checkout-form]');

    if (!form) {
        return;
    }

    const endpoint = form.dataset.summaryUrl;
    const placeOrderButton = form.querySelector('[data-checkout-place-order]');
    const status = form.querySelector('[data-checkout-summary-status]');
    const token = form.querySelector('input[name="_token"]')?.value;
    let activeController = null;
    let requestId = 0;

    const totals = {
        formatted_subtotal: form.querySelector('[data-checkout-subtotal]'),
        formatted_tax_total: form.querySelector('[data-checkout-tax-total]'),
        formatted_shipping_amount: form.querySelector('[data-checkout-shipping-amount]'),
        formatted_grand_total: form.querySelector('[data-checkout-grand-total]'),
    };

    form.querySelectorAll('input[name="shipping_method"]').forEach((input) => {
        input.addEventListener('change', async () => {
            activeController?.abort();
            activeController = new AbortController();
            const currentRequestId = ++requestId;
            const paymentMethod = form.querySelector('input[name="payment_method"]:checked')?.value ?? '';

            placeOrderButton.disabled = true;
            status.textContent = form.dataset.summaryLoading;
            status.classList.remove('text-danger');
            status.classList.add('text-muted');

            try {
                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token,
                    },
                    body: JSON.stringify({
                        shipping_method: input.value,
                        payment_method: paymentMethod || null,
                    }),
                    signal: activeController.signal,
                });
                const payload = await response.json();

                if (currentRequestId !== requestId) {
                    return;
                }

                if (!response.ok || !payload.success) {
                    throw { checkoutMessage: payload.errors?.[0]?.message || form.dataset.summaryError };
                }

                Object.entries(totals).forEach(([key, element]) => {
                    if (element) {
                        element.textContent = payload.summary[key];
                    }
                });
                status.textContent = '';
                placeOrderButton.disabled = false;
            } catch (error) {
                if (error.name === 'AbortError' || currentRequestId !== requestId) {
                    return;
                }

                status.textContent = error.checkoutMessage || form.dataset.summaryError;
                status.classList.remove('text-muted');
                status.classList.add('text-danger');
                placeOrderButton.disabled = true;
            }
        });
    });
}
