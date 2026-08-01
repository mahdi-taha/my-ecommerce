<?php

namespace App\Http\Controllers;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderCancellationRequestStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Services\OrderCancellationRequestService;
use App\Services\OrderStatusService;
use App\Services\PaymentStatusService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use LogicException;
use RuntimeException;
use Yajra\DataTables\Facades\DataTables;

class OrderController extends Controller
{
    public function __construct(
        private OrderStatusService $orderStatusService,
        private PaymentStatusService $paymentStatusService,
        private OrderCancellationRequestService $cancellationRequests
    ) {}

    public function index(Request $request): JsonResponse|View
    {
        if ($request->ajax()) {
            $orders = Order::query()
                ->withCount('items')
                ->latest('placed_at');

            if ($request->filled('order_status')) {
                $orders->where('status', (string) $request->string('order_status'));
            }

            if ($request->filled('payment_status')) {
                $orders->where('payment_status', (string) $request->string('payment_status'));
            }

            if ($request->filled('fulfillment_status')) {
                $orders->where('fulfillment_status', (string) $request->string('fulfillment_status'));
            }

            if ($request->filled('customer')) {
                $this->filterCustomer($orders, (string) $request->string('customer'));
            }

            if ($request->filled('date_from')) {
                $orders->where(
                    'placed_at',
                    '>=',
                    $this->orderDateBoundary((string) $request->string('date_from'))
                );
            }

            if ($request->filled('date_to')) {
                $orders->where(
                    'placed_at',
                    '<',
                    $this->orderDateBoundary((string) $request->string('date_to'), true)
                );
            }

            return DataTables::eloquent($orders)
                ->filter(function ($query) use ($request) {
                    $keyword = trim((string) $request->input('search.value'));

                    if ($keyword === '') {
                        return;
                    }

                    $query->where(function ($query) use ($keyword) {
                        $query->where('order_number', 'like', "%{$keyword}%");
                        $this->filterCustomer($query, $keyword, 'or');
                    });
                })
                ->addColumn('customer', function (Order $order) {
                    $name = trim($order->customer_first_name.' '.$order->customer_last_name);

                    return e($name).'<br><small class="text-muted">'.e($order->customer_email).'</small>';
                })
                ->editColumn('placed_at', function (Order $order) {
                    return date('Y-m-d H:i', strtotime($order->placed_at));
                })
                ->editColumn('grand_total', function (Order $order) {
                    return e($order->currency_code).' '.number_format((float) $order->grand_total, 2);
                })
                ->editColumn('status', fn (Order $order) => $this->statusBadge($order->status, [
                    'pending' => 'bg-warning text-dark',
                    'processing' => 'bg-primary',
                    'completed' => 'bg-success',
                    'cancelled' => 'bg-danger',
                ]))
                ->editColumn('payment_status', fn (Order $order) => $this->statusBadge($order->payment_status, [
                    'pending' => 'bg-warning text-dark',
                    'awaiting_verification' => 'bg-info text-dark',
                    'paid' => 'bg-success',
                    'partially_paid' => 'bg-info text-dark',
                    'failed' => 'bg-danger',
                    'cancelled' => 'bg-secondary',
                    'refunded' => 'bg-dark',
                    'partially_refunded' => 'bg-dark',
                ]))
                ->editColumn('fulfillment_status', fn (Order $order) => $this->statusBadge($order->fulfillment_status, [
                    'unfulfilled' => 'bg-secondary',
                    'out_for_delivery' => 'bg-info text-dark',
                    'fulfilled' => 'bg-success',
                    'delivery_failed' => 'bg-danger',
                ]))
                ->addColumn('action', function (Order $order) {
                    return '<a href="'.route('admin.orders.show', $order).'" class="btn text-primary p-0" title="View Order">
                        <i class="ti ti-eye fs-6"></i>
                    </a>';
                })
                ->rawColumns([
                    'customer',
                    'status',
                    'payment_status',
                    'fulfillment_status',
                    'action',
                ])
                ->toJson();
        }

        return view('admin.orders.index');
    }

    public function show(Order $order): View
    {
        $order->load([
            'items' => fn ($query) => $query->orderBy('id'),
            'items.options' => fn ($query) => $query
                ->orderBy('attribute_code')
                ->orderBy('id'),
            'items.children' => fn ($query) => $query->orderBy('id'),
            'items.children.options' => fn ($query) => $query
                ->orderBy('attribute_code')
                ->orderBy('id'),
            'billingAddress',
            'shippingAddress',
            'payment' => fn ($query) => $query->with([
                'paymentMethod',
                'attempts' => fn ($query) => $query->latest('attempt_number'),
            ]),
            'statusHistory' => fn ($query) => $query->with('user:id,name')->latest('created_at'),
            'cancellationRequests' => fn ($query) => $query
                ->with(['requester:id,name,email', 'reviewer:id,name'])
                ->latest('created_at')
                ->latest('id'),
        ]);

        $rootItems = $order->items
            ->whereNull('parent_order_item_id')
            ->sortBy('id')
            ->values();

        $badges = [
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
                'awaiting_verification' => 'bg-info text-dark',
                'partially_paid' => 'bg-info text-dark',
                'refunded' => 'bg-dark',
                'partially_refunded' => 'bg-dark',
            ]),
            'fulfillment' => $this->statusBadge($order->fulfillment_status, [
                'unfulfilled' => 'bg-secondary',
                'out_for_delivery' => 'bg-info text-dark',
                'fulfilled' => 'bg-success',
                'delivery_failed' => 'bg-danger',
            ]),
        ];

        $paymentBadgeClasses = [
            'pending' => 'bg-warning text-dark',
            'paid' => 'bg-success',
            'failed' => 'bg-danger',
            'cancelled' => 'bg-secondary',
            'requires_action' => 'bg-info text-dark',
            'processing' => 'bg-primary',
            'expired' => 'bg-dark',
        ];

        $availableActions = [
            'process' => $order->status === OrderStatus::Pending->value,
            'process_blocked' => $order->status === OrderStatus::Pending->value
                && $order->requires_payment_before_processing
                && $order->payment_status !== PaymentStatus::Paid->value,
            'mark_paid' => $order->payment_status === PaymentStatus::Pending->value
                && ! in_array($order->status, [
                    OrderStatus::Cancelled->value,
                    OrderStatus::Completed->value,
                ], true),
            'mark_failed' => $order->payment_status === PaymentStatus::Pending->value
                && ! in_array($order->status, [
                    OrderStatus::Cancelled->value,
                    OrderStatus::Completed->value,
                ], true),
            'retry_payment' => $order->payment_status === PaymentStatus::Failed->value
                && ! in_array($order->status, [
                    OrderStatus::Cancelled->value,
                    OrderStatus::Completed->value,
                ], true),
            'out_for_delivery' => $order->status === OrderStatus::Processing->value
                && $order->fulfillment_status === FulfillmentStatus::Unfulfilled->value,
            'fulfill' => $order->status === OrderStatus::Processing->value
                && $order->fulfillment_status === FulfillmentStatus::OutForDelivery->value,
            'delivery_failed' => $order->status === OrderStatus::Processing->value
                && $order->fulfillment_status === FulfillmentStatus::OutForDelivery->value,
            'cancel' => ! $order->cancellationRequests->contains(
                fn ($request) => $request->status === OrderCancellationRequestStatus::Pending
            ) && ($order->status === OrderStatus::Pending->value
                || ($order->status === OrderStatus::Processing->value
                    && $order->fulfillment_status === FulfillmentStatus::Unfulfilled->value))
                && $order->payment_status !== PaymentStatus::Paid->value
                && $order->fulfillment_status === FulfillmentStatus::Unfulfilled->value,
        ];

        return view('admin.orders.show', compact(
            'order',
            'rootItems',
            'badges',
            'paymentBadgeClasses',
            'availableActions'
        ));
    }

    public function process(Order $order): RedirectResponse
    {
        return $this->runLifecycleAction(
            $order,
            fn () => $this->orderStatusService->process($order),
            'Order moved to processing successfully.'
        );
    }

    public function fulfill(Order $order): RedirectResponse
    {
        return $this->runLifecycleAction(
            $order,
            fn () => $this->orderStatusService->fulfill($order),
            'Order fulfilled successfully.'
        );
    }

    public function markOutForDelivery(Order $order): RedirectResponse
    {
        return $this->runLifecycleAction(
            $order,
            fn () => $this->orderStatusService->markOutForDelivery($order),
            'Order marked as out for delivery successfully.'
        );
    }

    public function markDeliveryFailed(Order $order): RedirectResponse
    {
        return $this->runLifecycleAction(
            $order,
            fn () => $this->orderStatusService->markDeliveryFailed($order),
            'Order marked as delivery failed and inventory restored successfully.'
        );
    }

    public function cancel(Order $order): RedirectResponse
    {
        return $this->runLifecycleAction(
            $order,
            fn () => $this->cancellationRequests->cancelDirectly($order),
            'Order cancelled successfully.'
        );
    }

    public function markPaid(Order $order): RedirectResponse
    {
        return $this->runLifecycleAction(
            $order,
            fn () => $this->paymentStatusService->markPaid($order),
            'Payment marked as paid successfully.'
        );
    }

    public function markFailed(Order $order): RedirectResponse
    {
        return $this->runLifecycleAction(
            $order,
            fn () => $this->paymentStatusService->markFailed($order),
            'Payment marked as failed successfully.'
        );
    }

    public function retryPayment(Order $order): RedirectResponse
    {
        return $this->runLifecycleAction(
            $order,
            fn () => $this->paymentStatusService->retry($order),
            'Payment retry created successfully.'
        );
    }

    private function runLifecycleAction(Order $order, callable $action, string $successMessage): RedirectResponse
    {
        try {
            $action();
        } catch (ValidationException $exception) {
            return redirect()
                ->route('admin.orders.show', $order)
                ->with('error', $this->validationMessage($exception));
        } catch (RuntimeException|LogicException $exception) {
            return redirect()
                ->route('admin.orders.show', $order)
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('admin.orders.show', $order)
            ->with('success', $successMessage);
    }

    private function validationMessage(ValidationException $exception): string
    {
        foreach ($exception->errors() as $messages) {
            if (! empty($messages)) {
                return (string) $messages[0];
            }
        }

        return 'The order action could not be completed.';
    }

    private function filterCustomer($query, string $keyword, string $boolean = 'and'): void
    {
        $method = $boolean === 'or' ? 'orWhere' : 'where';

        $query->{$method}(function ($query) use ($keyword) {
            $query->whereRaw(
                "CONCAT(customer_first_name, ' ', customer_last_name) LIKE ?",
                ["%{$keyword}%"]
            )->orWhere('customer_email', 'like', "%{$keyword}%");
        });
    }

    private function statusBadge(string $status, array $classes): string
    {
        $class = $classes[$status] ?? 'bg-secondary';
        $label = ucwords(str_replace('_', ' ', $status));

        return '<span class="badge '.$class.'">'.e($label).'</span>';
    }

    private function orderDateBoundary(string $date, bool $followingDay = false): CarbonImmutable
    {
        $boundary = CarbonImmutable::parse(
            $date,
            (string) setting('localization.timezone', config('app.timezone'))
        )->startOfDay();

        return ($followingDay ? $boundary->addDay() : $boundary)->utc();
    }
}
