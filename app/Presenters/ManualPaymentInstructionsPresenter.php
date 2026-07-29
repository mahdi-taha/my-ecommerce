<?php

namespace App\Presenters;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;

class ManualPaymentInstructionsPresenter
{
    private const MANUAL_METHODS = [
        'manual_wallet_transfer',
        'manual_bank_transfer',
    ];

    public function present(Order $order): ?array
    {
        $payment = $order->payment;

        if (! $payment
            || ! in_array($payment->method_code, self::MANUAL_METHODS, true)
            || $order->status === OrderStatus::Cancelled->value) {
            return null;
        }

        if ($payment->status === PaymentStatus::Paid) {
            return ['state' => 'paid'];
        }

        if ($payment->status !== PaymentStatus::Pending) {
            return null;
        }

        $fields = $this->instructionFields($payment->method_code);
        $whatsAppNumber = $this->normalizedWhatsAppNumber();

        return [
            'state' => 'pending',
            'title' => $this->settingValue($this->settingPrefix($payment->method_code).'_title')
                ?? __('shop.payment_instructions.'.$payment->method_code),
            'fields' => $fields,
            'amount' => format_store_price($order->grand_total, $order->currency_code),
            'whatsapp_url' => $whatsAppNumber
                ? $this->whatsAppUrl($whatsAppNumber, $order)
                : null,
        ];
    }

    private function instructionFields(string $methodCode): array
    {
        $definitions = $methodCode === 'manual_wallet_transfer'
            ? [
                'wallet_name' => 'wallet_name',
                'wallet_number' => 'wallet_number',
                'instructions' => 'wallet_instructions',
            ]
            : [
                'bank_name' => 'bank_name',
                'account_name' => 'bank_account_name',
                'account_number' => 'bank_account_number',
                'iban' => 'bank_iban',
                'swift' => 'bank_swift',
                'instructions' => 'bank_instructions',
            ];

        $fields = [];

        foreach ($definitions as $label => $settingSuffix) {
            $value = $this->settingValue('manual_'.$settingSuffix);

            if ($value !== null) {
                $fields[] = ['label' => $label, 'value' => $value];
            }
        }

        return $fields;
    }

    private function normalizedWhatsAppNumber(): ?string
    {
        $configured = $this->settingValue('manual_whatsapp_number');

        if ($configured === null) {
            return null;
        }

        $number = preg_replace('/\D+/', '', $configured) ?? '';

        return preg_match('/^[1-9]\d{7,14}$/', $number) === 1 ? $number : null;
    }

    private function whatsAppUrl(string $number, Order $order): string
    {
        $customerName = trim($order->customer_first_name.' '.$order->customer_last_name);
        $message = __('shop.payment_instructions.whatsapp_message', [
            'order_number' => $order->order_number,
            'customer_name' => $customerName,
            'payment_method' => $order->payment->method_name,
            'amount' => format_store_price($order->grand_total, $order->currency_code),
            'currency' => $order->currency_code,
        ]);

        return 'https://wa.me/'.$number.'?text='.rawurlencode($message);
    }

    private function settingPrefix(string $methodCode): string
    {
        return $methodCode === 'manual_wallet_transfer'
            ? 'manual_wallet'
            : 'manual_bank';
    }

    private function settingValue(string $key): ?string
    {
        $value = setting('payments.'.$key);

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
