<?php

namespace App\Http\Requests;

use App\Models\CustomerAddress;

class UpdateCustomerAddressRequest extends StoreCustomerAddressRequest
{
    public function authorize(): bool
    {
        $customer = $this->user('customer');
        $address = $this->route('customerAddress');

        abort_unless(
            $customer
                && $address instanceof CustomerAddress
                && (int) $address->user_id === (int) $customer->getKey(),
            404
        );

        return true;
    }
}
