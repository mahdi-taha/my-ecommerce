<?php

namespace App\Http\Controllers;

use App\Enums\CouponType;
use App\Http\Requests\StoreCouponRequest;
use App\Http\Requests\UpdateCouponRequest;
use App\Models\Coupon;
use App\Services\CouponService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CouponController extends Controller
{
    public function __construct(private CouponService $couponService) {}

    public function index(): View
    {
        $coupons = Coupon::query()
            ->withCount([
                'usages',
                'unreleasedUsages as effective_usage_count',
            ])
            ->latest('id')
            ->paginate(20);

        return view('admin.coupons.index', compact('coupons'));
    }

    public function create(): View
    {
        return view('admin.coupons.create', [
            'types' => CouponType::cases(),
            'storeTimezone' => $this->storeTimezone(),
        ]);
    }

    public function store(StoreCouponRequest $request): RedirectResponse
    {
        $this->couponService->create($request->validated());

        return redirect()->route('admin.coupons.index')
            ->with('success', 'Coupon created successfully.');
    }

    public function edit(Coupon $coupon): View
    {
        $coupon->loadCount('usages');

        return view('admin.coupons.edit', [
            'coupon' => $coupon,
            'types' => CouponType::cases(),
            'storeTimezone' => $this->storeTimezone(),
        ]);
    }

    public function update(UpdateCouponRequest $request, Coupon $coupon): RedirectResponse
    {
        $this->couponService->update($coupon, $request->validated());

        return redirect()->route('admin.coupons.index')
            ->with('success', 'Coupon updated successfully.');
    }

    public function deactivate(Coupon $coupon): RedirectResponse
    {
        $this->couponService->deactivate($coupon);

        return back()->with('success', 'Coupon deactivated successfully.');
    }

    public function destroy(Coupon $coupon): RedirectResponse
    {
        $this->couponService->deleteUnused($coupon);

        return redirect()->route('admin.coupons.index')
            ->with('success', 'Coupon deleted successfully.');
    }

    private function storeTimezone(): string
    {
        return (string) setting('localization.timezone', config('app.timezone'));
    }
}
