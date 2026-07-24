<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerPasswordRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Requests\UpdateCustomerStatusRequest;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Services\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class CustomerController extends Controller
{
    public function __construct(private CustomerService $customerService) {}

    public function index(Request $request): JsonResponse|View
    {
        if ($request->ajax()) {
            $customers = User::query()
                ->customers()
                ->withCount([
                    'orders as completed_orders_count' => fn ($query) => $query
                        ->where('status', OrderStatus::Completed->value),
                ])
                ->withSum([
                    'orders as total_spent' => fn ($query) => $query
                        ->where('status', OrderStatus::Completed->value),
                ], 'grand_total');

            if ($request->string('status')->toString() === 'active') {
                $customers->where('is_active', true);
            } elseif ($request->string('status')->toString() === 'inactive') {
                $customers->where('is_active', false);
            }

            if ($request->string('verification')->toString() === 'verified') {
                $customers->whereNotNull('email_verified_at');
            } elseif ($request->string('verification')->toString() === 'unverified') {
                $customers->whereNull('email_verified_at');
            }

            $currencyCode = $this->currencyCode();

            return DataTables::eloquent($customers)
                ->editColumn('name', fn (User $customer) => $customer->name)
                ->filterColumn('name', function ($query, $keyword) {
                    $query->where(function ($query) use ($keyword) {
                        $query->where('first_name', 'like', "%{$keyword}%")
                            ->orWhere('last_name', 'like', "%{$keyword}%")
                            ->orWhere('name', 'like', "%{$keyword}%");
                    });
                })
                ->editColumn('email', fn (User $customer) => $customer->email ?: '-')
                ->editColumn('phone', fn (User $customer) => $customer->phone ?: '-')
                ->editColumn(
                    'completed_orders_count',
                    fn (User $customer) => (int) $customer->completed_orders_count
                )
                ->orderColumn(
                    'completed_orders_count',
                    fn ($query, $direction) => $query->orderBy(
                        Order::query()
                            ->selectRaw('COUNT(*)')
                            ->whereColumn('orders.user_id', 'users.id')
                            ->where('status', OrderStatus::Completed->value),
                        $direction
                    )
                )
                ->editColumn('total_spent', function (User $customer) use ($currencyCode) {
                    return $currencyCode.' '.number_format((float) ($customer->total_spent ?? 0), 2);
                })
                ->orderColumn(
                    'total_spent',
                    fn ($query, $direction) => $query->orderBy(
                        Order::query()
                            ->selectRaw('COALESCE(SUM(grand_total), 0)')
                            ->whereColumn('orders.user_id', 'users.id')
                            ->where('status', OrderStatus::Completed->value),
                        $direction
                    )
                )
                ->editColumn('is_active', function (User $customer) {
                    return $customer->is_active
                        ? '<span class="badge bg-success">Active</span>'
                        : '<span class="badge bg-danger">Inactive</span>';
                })
                ->editColumn(
                    'created_at',
                    fn (User $customer) => $customer->created_at?->format('Y-m-d H:i:s')
                )
                ->addColumn('actions', fn (User $customer) => $this->customerActions($customer))
                ->rawColumns(['is_active', 'actions'])
                ->toJson();
        }

        return view('admin.customers.index');
    }

    public function create(): View
    {
        return view('admin.customers.create');
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $customer = $this->customerService->create($request->validated());

        return redirect()
            ->route('admin.customers.edit', $customer)
            ->with('success', 'Customer created successfully.');
    }

    public function show(User $customer): View
    {
        $customer->load('defaultAddress')
            ->loadCount([
                'orders as completed_orders_count' => fn ($query) => $query
                    ->where('status', OrderStatus::Completed->value),
            ])
            ->loadSum([
                'orders as total_spent' => fn ($query) => $query
                    ->where('status', OrderStatus::Completed->value),
            ], 'grand_total');

        $recentOrders = $customer->orders()
            ->latest('placed_at')
            ->limit(10)
            ->get();

        return view('admin.customers.show', [
            'customer' => $customer,
            'recentOrders' => $recentOrders,
            'currencyCode' => $this->currencyCode(),
            'orderBadges' => $recentOrders->mapWithKeys(fn ($order) => [
                $order->id => [
                    'order' => $this->statusBadge($order->status, [
                        'pending' => 'bg-warning text-dark',
                        'processing' => 'bg-primary',
                        'completed' => 'bg-success',
                        'cancelled' => 'bg-danger',
                    ]),
                    'payment' => $this->statusBadge($order->payment_status, [
                        'pending' => 'bg-warning text-dark',
                        'paid' => 'bg-success',
                        'failed' => 'bg-danger',
                        'cancelled' => 'bg-secondary',
                    ]),
                    'fulfillment' => $this->statusBadge($order->fulfillment_status, [
                        'unfulfilled' => 'bg-secondary',
                        'out_for_delivery' => 'bg-info text-dark',
                        'fulfilled' => 'bg-success',
                        'delivery_failed' => 'bg-danger',
                    ]),
                ],
            ]),
        ]);
    }

    public function edit(User $customer): View
    {
        return view('admin.customers.edit', compact('customer'));
    }

    public function update(UpdateCustomerRequest $request, User $customer): RedirectResponse
    {
        $this->customerService->update($customer, $request->validated());

        return redirect()
            ->route('admin.customers.edit', $customer)
            ->with('success', 'Customer updated successfully.');
    }

    public function editPassword(User $customer): View
    {
        abort_unless($customer->has_account, 404);

        return view('admin.customers.password', compact('customer'));
    }

    public function updatePassword(
        UpdateCustomerPasswordRequest $request,
        User $customer
    ): RedirectResponse {
        $this->customerService->updatePassword(
            $customer,
            $request->validated('password')
        );

        return redirect()
            ->route('admin.customers.edit', $customer)
            ->with('success', 'Customer password updated successfully.');
    }

    public function updateStatus(
        UpdateCustomerStatusRequest $request,
        User $customer
    ): JsonResponse|RedirectResponse {
        $customer = $this->customerService->updateStatus(
            $customer,
            $request->boolean('is_active')
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Customer status updated successfully.',
                'is_active' => $customer->is_active,
            ]);
        }

        return back()->with('success', 'Customer status updated successfully.');
    }

    private function customerActions(User $customer): string
    {
        $targetStatus = ! $customer->is_active;
        $statusLabel = $targetStatus ? 'Activate' : 'Deactivate';
        $statusClass = $targetStatus ? 'text-success' : 'text-danger';
        $statusIcon = $targetStatus ? 'ti-user-check' : 'ti-user-off';

        $passwordAction = $customer->has_account
            ? sprintf(
                '<a href="%s" class="btn text-primary p-0" title="Change Password"><i class="ti ti-key fs-6"></i></a>',
                e(route('admin.customers.password.edit', $customer))
            )
            : '';

        return sprintf(
            '<div class="d-flex align-items-center gap-2">
                <a href="%s" class="btn text-primary p-0" title="View"><i class="ti ti-eye fs-6"></i></a>
                <a href="%s" class="btn text-primary p-0" title="Edit"><i class="ti ti-edit fs-6"></i></a>
                %s
                <button type="button" class="btn %s p-0 customer-status-toggle" title="%s"
                    data-url="%s" data-is-active="%s" data-customer-name="%s">
                    <i class="ti %s fs-6"></i>
                </button>
            </div>',
            e(route('admin.customers.show', $customer)),
            e(route('admin.customers.edit', $customer)),
            $passwordAction,
            $statusClass,
            $statusLabel,
            e(route('admin.customers.status.update', $customer)),
            $targetStatus ? '1' : '0',
            e($customer->name),
            $statusIcon
        );
    }

    private function currencyCode(): string
    {
        return Setting::query()
            ->where('group', 'currency')
            ->where('key', 'default_currency')
            ->value('value') ?? 'USD';
    }

    private function statusBadge(string $status, array $classes): string
    {
        $class = $classes[$status] ?? 'bg-secondary';
        $label = ucwords(str_replace('_', ' ', $status));

        return '<span class="badge '.$class.'">'.e($label).'</span>';
    }
}
