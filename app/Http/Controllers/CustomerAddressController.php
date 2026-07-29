<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerAddressRequest;
use App\Http\Requests\UpdateCustomerAddressRequest;
use App\Models\CustomerAddress;
use App\Services\CustomerAddressService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerAddressController extends Controller
{
    public function __construct(private CustomerAddressService $addressService) {}

    public function index(Request $request): View
    {
        $addresses = $request->user('customer')
            ->customerAddresses()
            ->oldest('created_at')
            ->oldest('id')
            ->get();

        return view('customer.account.addresses.index', compact('addresses'));
    }

    public function create(): View
    {
        return view('customer.account.addresses.create');
    }

    public function store(StoreCustomerAddressRequest $request): RedirectResponse
    {
        $this->addressService->create($request->user('customer'), $request->validated());

        return redirect()->route('customer.addresses.index')
            ->with('success', __('shop.account.addresses.saved'));
    }

    public function edit(Request $request, CustomerAddress $customerAddress): View
    {
        $this->ensureOwnership($request, $customerAddress);

        return view('customer.account.addresses.edit', ['address' => $customerAddress]);
    }

    public function update(
        UpdateCustomerAddressRequest $request,
        CustomerAddress $customerAddress
    ): RedirectResponse {
        $this->addressService->update(
            $request->user('customer'),
            $customerAddress,
            $request->validated()
        );

        return redirect()->route('customer.addresses.index')
            ->with('success', __('shop.account.addresses.updated'));
    }

    public function destroy(Request $request, CustomerAddress $customerAddress): RedirectResponse
    {
        $this->ensureOwnership($request, $customerAddress);
        $this->addressService->delete($request->user('customer'), $customerAddress);

        return back()->with('success', __('shop.account.addresses.deleted'));
    }

    public function setDefaultShipping(
        Request $request,
        CustomerAddress $customerAddress
    ): RedirectResponse {
        $this->ensureOwnership($request, $customerAddress);
        $this->addressService->setDefaultShipping($request->user('customer'), $customerAddress);

        return back()->with('success', __('shop.account.addresses.default_shipping_set'));
    }

    public function setDefaultBilling(
        Request $request,
        CustomerAddress $customerAddress
    ): RedirectResponse {
        $this->ensureOwnership($request, $customerAddress);
        $this->addressService->setDefaultBilling($request->user('customer'), $customerAddress);

        return back()->with('success', __('shop.account.addresses.default_billing_set'));
    }

    private function ensureOwnership(Request $request, CustomerAddress $address): void
    {
        abort_unless(
            (int) $address->user_id === (int) $request->user('customer')->getKey(),
            404
        );
    }
}
