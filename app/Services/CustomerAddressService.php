<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerAddressService
{
    public function create(User $customer, array $data): CustomerAddress
    {
        return DB::transaction(function () use ($customer, $data) {
            $customer = $this->lockedCustomer($customer);
            $this->clearRequestedDefaults($customer, $data);

            return $customer->customerAddresses()->create($this->writableData($data));
        });
    }

    public function update(User $customer, CustomerAddress $address, array $data): CustomerAddress
    {
        return DB::transaction(function () use ($customer, $address, $data) {
            $customer = $this->lockedCustomer($customer);
            $address = $this->lockedOwnedAddress($customer, $address);
            $this->clearRequestedDefaults($customer, $data, $address);
            $address->update($this->writableData($data));

            return $address->fresh();
        });
    }

    public function delete(User $customer, CustomerAddress $address): void
    {
        DB::transaction(function () use ($customer, $address) {
            $customer = $this->lockedCustomer($customer);
            $addresses = $customer->customerAddresses()
                ->oldest('created_at')
                ->oldest('id')
                ->lockForUpdate()
                ->get();
            $address = $addresses->firstWhere('id', $address->getKey());

            if (! $address) {
                throw (new ModelNotFoundException)->setModel(CustomerAddress::class);
            }

            $replaceShipping = $address->is_default_shipping;
            $replaceBilling = $address->is_default_billing;
            $address->delete();
            $replacement = $addresses->first(fn (CustomerAddress $candidate) => $candidate->getKey() !== $address->getKey());

            if ($replacement && ($replaceShipping || $replaceBilling)) {
                $replacement->update([
                    'is_default_shipping' => $replaceShipping
                        ? true
                        : $replacement->is_default_shipping,
                    'is_default_billing' => $replaceBilling
                        ? true
                        : $replacement->is_default_billing,
                ]);
            }
        });
    }

    public function setDefaultShipping(User $customer, CustomerAddress $address): CustomerAddress
    {
        return $this->setDefault($customer, $address, 'is_default_shipping');
    }

    public function setDefaultBilling(User $customer, CustomerAddress $address): CustomerAddress
    {
        return $this->setDefault($customer, $address, 'is_default_billing');
    }

    private function setDefault(User $customer, CustomerAddress $address, string $column): CustomerAddress
    {
        return DB::transaction(function () use ($customer, $address, $column) {
            $customer = $this->lockedCustomer($customer);
            $address = $this->lockedOwnedAddress($customer, $address);
            $customer->customerAddresses()->where($column, true)->update([$column => false]);
            $address->update([$column => true]);

            return $address->fresh();
        });
    }

    private function lockedCustomer(User $customer): User
    {
        $locked = User::query()->whereKey($customer->getKey())->lockForUpdate()->firstOrFail();

        if ($locked->account_type !== AccountType::Customer
            || ! $locked->has_account
            || ! $locked->is_active) {
            throw ValidationException::withMessages([
                'customer' => __('shop.account.addresses.customer_unavailable'),
            ]);
        }

        return $locked;
    }

    private function lockedOwnedAddress(User $customer, CustomerAddress $address): CustomerAddress
    {
        $owned = $customer->customerAddresses()
            ->whereKey($address->getKey())
            ->lockForUpdate()
            ->first();

        if (! $owned) {
            throw (new ModelNotFoundException)->setModel(CustomerAddress::class);
        }

        return $owned;
    }

    private function clearRequestedDefaults(
        User $customer,
        array $data,
        ?CustomerAddress $except = null
    ): void {
        foreach (['is_default_shipping', 'is_default_billing'] as $column) {
            if (! ($data[$column] ?? false)) {
                continue;
            }

            $query = $customer->customerAddresses()->where($column, true);

            if ($except) {
                $query->whereKeyNot($except->getKey());
            }

            $query->update([$column => false]);
        }
    }

    private function writableData(array $data): array
    {
        return collect($data)->only([
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
            'is_default_shipping',
            'is_default_billing',
        ])->all();
    }
}
