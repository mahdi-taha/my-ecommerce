<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRefundRequest;
use App\Models\Order;
use App\Models\Refund;
use App\Services\RefundService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RefundController extends Controller
{
    public function __construct(private RefundService $refunds) {}

    public function index(): View
    {
        return view('admin.refunds.index', [
            'refunds' => Refund::query()->with(['order:id,order_number', 'creator:id,name'])
                ->latest('refunded_at')->paginate(25),
        ]);
    }

    public function create(Request $request): View
    {
        $order = $request->integer('order')
            ? Order::query()->with('payment')->findOrFail($request->integer('order'))
            : null;

        return view('admin.refunds.create', [
            'order' => $order,
            'items' => $order ? $this->refunds->refundableItems($order) : collect(),
            'idempotencyKey' => hash('sha256', (string) Str::uuid()),
        ]);
    }

    public function store(StoreRefundRequest $request): RedirectResponse
    {
        $order = Order::query()->findOrFail($request->integer('order_id'));
        $refund = $this->refunds->create(
            $order,
            $request->user('admin'),
            $request->validated(),
            $request->string('idempotency_key')->toString(),
        );

        return redirect()->route('admin.refunds.show', $refund)
            ->with('success', 'Refund completed successfully.');
    }

    public function show(Refund $refund): View
    {
        $refund->load(['order', 'payment', 'items.orderItem', 'creator']);

        return view('admin.refunds.show', compact('refund'));
    }

    public function orders(Request $request)
    {
        $term = trim((string) $request->query('q'));
        $orders = Order::query()
            ->whereIn('payment_status', [PaymentStatus::Paid->value, PaymentStatus::PartiallyRefunded->value])
            ->whereHas('items', fn ($query) => $query->financiallyRefundable())
            ->when($term !== '', fn ($query) => $query->where(function ($query) use ($term) {
                $query->where('order_number', 'like', "%{$term}%")
                    ->orWhere('customer_email', 'like', "%{$term}%")
                    ->orWhereRaw("TRIM(CONCAT(customer_first_name, ' ', customer_last_name)) like ?", ["%{$term}%"]);
            }))
            ->latest('placed_at')->limit(20)->get(['id', 'order_number', 'customer_first_name', 'customer_last_name', 'customer_email']);

        return response()->json($orders);
    }
}
