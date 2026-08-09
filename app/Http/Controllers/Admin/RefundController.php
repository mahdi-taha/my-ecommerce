<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRefundRequest;
use App\Models\Order;
use App\Models\Refund;
use App\Services\RefundService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
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
            ? $this->eligibleOrdersQuery()->with('payment')->findOrFail($request->integer('order'))
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


    public function orders(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q'));
        $nameTerms = preg_split('/\s+/', $term, flags: PREG_SPLIT_NO_EMPTY) ?: [];
        $orders = $this->eligibleOrdersQuery()
            ->when($term !== '', fn ($query) => $query->where(function ($query) use ($term, $nameTerms) {
                $query->where('order_number', 'like', "%{$term}%")
                    ->orWhere('customer_email', 'like', "%{$term}%")
                    ->orWhere('customer_first_name', 'like', "%{$term}%")
                    ->orWhere('customer_last_name', 'like', "%{$term}%")
                    ->orWhere(function ($query) use ($nameTerms) {
                        foreach ($nameTerms as $nameTerm) {
                            $query->where(function ($query) use ($nameTerm) {
                                $query->where('customer_first_name', 'like', "%{$nameTerm}%")
                                    ->orWhere('customer_last_name', 'like', "%{$nameTerm}%");
                            });
                        }
                    });
            }))
            ->latest('placed_at')
            ->latest('id')
            ->limit(20)
            ->get([
                'id', 'order_number', 'customer_first_name', 'customer_last_name', 'customer_email',
                'payment_status', 'fulfillment_status', 'grand_total', 'currency_code',
            ])
            ->map(fn (Order $order) => [
                'order_number' => $order->order_number,
                'customer_name' => trim("{$order->customer_first_name} {$order->customer_last_name}"),
                'customer_email' => $order->customer_email,
                'payment_status' => $order->payment_status,
                'payment_status_label' => Str::of($order->payment_status)->replace('_', ' ')->title()->toString(),
                'fulfillment_status' => $order->fulfillment_status,
                'fulfillment_status_label' => Str::of($order->fulfillment_status)->replace('_', ' ')->title()->toString(),
                'grand_total' => $order->grand_total,
                'currency_code' => $order->currency_code,
                'formatted_grand_total' => format_store_price($order->grand_total, $order->currency_code),
                'select_url' => route('admin.refunds.create', ['order' => $order->id]),
            ]);

        return response()->json($orders);
    }

    private function eligibleOrdersQuery(): Builder
    {
        return Order::query()
            ->whereIn('payment_status', [PaymentStatus::Paid->value, PaymentStatus::PartiallyRefunded->value])
            ->whereHas('items', fn ($query) => $query->financiallyRefundable());
    }
}
