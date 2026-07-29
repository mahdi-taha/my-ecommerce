export function initializeCheckoutSummary() {
    const form = document.querySelector('[data-checkout-form]');

    if (!form) {
        return;
    }

    const endpoint = form.dataset.summaryUrl;
    const placeOrderButton = form.querySelector('[data-checkout-place-order]');
    const status = form.querySelector('[data-checkout-summary-status]');
    const couponStatus = form.querySelector('[data-checkout-coupon-status]');
    const couponInput = form.querySelector('[data-checkout-coupon-code]');
    const couponEntry = form.querySelector('[data-checkout-coupon-entry]');
    const couponApplied = form.querySelector('[data-checkout-coupon-applied]');
    const couponName = form.querySelector('[data-checkout-coupon-name]');
    const couponApply = form.querySelector('[data-checkout-coupon-apply]');
    const couponRemove = form.querySelector('[data-checkout-coupon-remove]');
    const token = form.querySelector('input[name="_token"]')?.value;
    let activeController = null;
    let requestId = 0;

    const totals = {
        formatted_subtotal: form.querySelector('[data-checkout-subtotal]'),
        formatted_discount_total: form.querySelector('[data-checkout-discount-total]'),
        formatted_tax_total: form.querySelector('[data-checkout-tax-total]'),
        formatted_shipping_amount: form.querySelector('[data-checkout-shipping-amount]'),
        formatted_grand_total: form.querySelector('[data-checkout-grand-total]'),
    };

    const updateSummary = (summary) => {
        Object.entries(totals).forEach(([key, element]) => {
            if (element) {
                element.textContent = key === 'formatted_discount_total'
                    ? `-${summary[key]}`
                    : summary[key];
            }
        });

        const hasDiscount = Number(summary.discount_total) > 0;
        form.querySelector('[data-checkout-discount-label]')?.toggleAttribute('hidden', !hasDiscount);
        totals.formatted_discount_total?.toggleAttribute('hidden', !hasDiscount);
        couponEntry?.toggleAttribute('hidden', Boolean(summary.coupon));
        couponApplied?.toggleAttribute('hidden', !summary.coupon);
        if (couponName) couponName.textContent = summary.coupon?.code ?? '';
    };

    const send = async (endpoint, method, extra = {}, disableOnError = true) => {
        activeController?.abort();
        activeController = new AbortController();
        const currentRequestId = ++requestId;
        const shippingMethod = form.querySelector('input[name="shipping_method"]:checked')?.value ?? '';
        const paymentMethod = form.querySelector('input[name="payment_method"]:checked')?.value ?? '';

        placeOrderButton.disabled = true;
        status.textContent = form.dataset.summaryLoading;
        status.classList.remove('text-danger');
        status.classList.add('text-muted');

        try {
            const response = await fetch(endpoint, {
                method,
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                },
                body: JSON.stringify({
                    shipping_method: shippingMethod,
                    payment_method: paymentMethod || null,
                    ...extra,
                }),
                signal: activeController.signal,
            });
            const payload = await response.json();

            if (currentRequestId !== requestId) return null;
            if (!response.ok || !payload.success) {
                throw { checkoutMessage: payload.errors?.[0]?.message || form.dataset.summaryError };
            }

            updateSummary(payload.summary);
            status.textContent = payload.summary.warnings?.[0] ?? '';
            placeOrderButton.disabled = false;

            return payload;
        } catch (error) {
            if (error.name === 'AbortError' || currentRequestId !== requestId) return null;
            status.textContent = error.checkoutMessage || form.dataset.summaryError;
            status.classList.remove('text-muted');
            status.classList.add('text-danger');
            placeOrderButton.disabled = disableOnError;
            throw error;
        }
    };

    form.querySelectorAll('input[name="shipping_method"]').forEach((input) => {
        input.addEventListener('change', async () => {
            try {
                await send(endpoint, 'POST');
            } catch (_) {}
        });
    });

    couponApply?.addEventListener('click', async (event) => {
        event.preventDefault();
        couponStatus.textContent = '';
        couponStatus.classList.remove('text-danger');
        try {
            const payload = await send(form.dataset.couponApplyUrl, 'POST', {
                coupon_code: couponInput?.value ?? '',
            }, false);
            if (payload) couponStatus.textContent = payload.message;
        } catch (error) {
            couponStatus.textContent = error.checkoutMessage || form.dataset.summaryError;
            couponStatus.classList.add('text-danger');
        }
    });

    couponRemove?.addEventListener('click', async () => {
        couponStatus.textContent = '';
        couponStatus.classList.remove('text-danger');
        try {
            const payload = await send(form.dataset.couponRemoveUrl, 'DELETE', {}, false);
            if (payload) couponStatus.textContent = payload.message;
        } catch (error) {
            couponStatus.textContent = error.checkoutMessage || form.dataset.summaryError;
            couponStatus.classList.add('text-danger');
        }
    });
}
