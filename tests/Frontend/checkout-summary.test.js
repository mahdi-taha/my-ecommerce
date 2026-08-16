import assert from 'node:assert/strict';
import test from 'node:test';

import { canPlaceCheckoutOrder } from '../../resources/js/shop/checkout-summary.js';

const validState = {
    summaryConfirmed: true,
    requestInProgress: false,
    submitting: false,
    shippingMethod: 'store_pickup',
    paymentMethod: 'cash_on_delivery',
};

test('a confirmed valid checkout can place an order', () => {
    assert.equal(canPlaceCheckoutOrder(validState), true);
});

test('an unconfirmed or failed summary keeps place order disabled', () => {
    assert.equal(canPlaceCheckoutOrder({ ...validState, summaryConfirmed: false }), false);
    assert.equal(canPlaceCheckoutOrder({ ...validState, requestInProgress: true }), false);
});

test('a failed coupon application cannot enable place order', () => {
    assert.equal(canPlaceCheckoutOrder({ ...validState, summaryConfirmed: false }), false);
});

test('a failed coupon removal cannot enable place order', () => {
    assert.equal(canPlaceCheckoutOrder({ ...validState, summaryConfirmed: false }), false);
});

test('missing shipping or payment selection keeps place order disabled', () => {
    assert.equal(canPlaceCheckoutOrder({ ...validState, shippingMethod: '' }), false);
    assert.equal(canPlaceCheckoutOrder({ ...validState, paymentMethod: '' }), false);
});

test('successful recovery can enable place order again', () => {
    const failedState = { ...validState, summaryConfirmed: false };

    assert.equal(canPlaceCheckoutOrder(failedState), false);
    assert.equal(canPlaceCheckoutOrder({ ...failedState, summaryConfirmed: true }), true);
});

test('an order submission in progress prevents duplicate submission', () => {
    assert.equal(canPlaceCheckoutOrder({ ...validState, submitting: true }), false);
});
