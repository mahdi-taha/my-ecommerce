<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

class CheckoutAddressResolver
{
    private const SNAPSHOT_FIELDS = [
        'first_name',
        'last_name',
        'company',
        'email',
        'phone',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'country_code',
    ];

    public function __construct(private CustomerAddressService $customerAddressService) {}

    public function resolve(array $checkoutData, ?User $customer): array
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Checkout address resolution requires an active database transaction.');
        }

        $source = $checkoutData['address_source'] ?? null;

        if ($customer) {
            $customer = $this->lockedEligibleCustomer($customer);
        }

        if ($source === 'saved') {
            return $this->resolveSavedAddress($checkoutData, $customer);
        }

        if ($source !== 'manual') {
            throw ValidationException::withMessages([
                'address_source' => __('shop.checkout.addresses.invalid_source'),
            ]);
        }

        return $this->resolveManualAddress($checkoutData, $customer);
    }

    private function resolveSavedAddress(array $checkoutData, ?User $customer): array
    {
        if (! $customer) {
            $this->unavailableSavedAddress();
        }

        $address = $customer->customerAddresses()
            ->whereKey($checkoutData['saved_address_id'] ?? null)
            ->lockForUpdate()
            ->first();

        if (! $address) {
            $this->unavailableSavedAddress();
        }

        return $this->savedAddressData($address, $customer);
    }

    private function resolveManualAddress(array $checkoutData, ?User $customer): array
    {
        $address = collect($checkoutData['manual_address'] ?? [])
            ->only(self::SNAPSHOT_FIELDS)
            ->all();

        if ($customer && ($checkoutData['save_address'] ?? false)) {
            $this->customerAddressService->create($customer, [
                ...collect($checkoutData['manual_address'])->only([
                    'label',
                    'first_name',
                    'last_name',
                    'company',
                    'phone',
                    'country_code',
                    'state',
                    'city',
                    'address_line_1',
                    'address_line_2',
                    'postal_code',
                ])->all(),
                'is_default_shipping' => (bool) ($checkoutData['make_default_shipping'] ?? false),
                'is_default_billing' => (bool) ($checkoutData['make_default_billing'] ?? false),
            ]);
        }

        return $address;
    }

    private function lockedEligibleCustomer(User $customer): User
    {
        $locked = User::query()->whereKey($customer->getKey())->lockForUpdate()->first();

        if (! $locked
            || $locked->account_type !== AccountType::Customer
            || ! $locked->has_account
            || ! $locked->is_active) {
            throw ValidationException::withMessages([
                'customer' => __('shop.checkout.addresses.customer_unavailable'),
            ]);
        }

        return $locked;
    }

    private function savedAddressData(CustomerAddress $address, User $customer): array
    {
        return [
            'first_name' => $address->first_name,
            'last_name' => $address->last_name,
            'company' => $address->company,
            'email' => $customer->email,
            'phone' => $address->phone,
            'address_line_1' => $address->address_line_1,
            'address_line_2' => $address->address_line_2,
            'city' => $address->city,
            'state' => $address->state,
            'postal_code' => $address->postal_code,
            'country_code' => $address->country_code,
        ];
    }

    private function unavailableSavedAddress(): never
    {
        throw ValidationException::withMessages([
            'saved_address_id' => __('shop.checkout.addresses.saved_unavailable'),
        ]);
    }
}
