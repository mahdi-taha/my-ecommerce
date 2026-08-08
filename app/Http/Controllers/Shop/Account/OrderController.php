<?php

namespace App\Http\Controllers\Shop\Account;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Presenters\ManualPaymentInstructionsPresenter;
use App\Presenters\OrderPrintPresenter;
use App\Services\OrderCancellationRequestService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(
        private ManualPaymentInstructionsPresenter $paymentInstructions,
        private OrderCancellationRequestService $cancellationRequests,
        private OrderPrintPresenter $orderPrint
    ) {}

    public function index(Request $request): View
    {
        $orders = $request->user('customer')
            ->orders()
            ->orderByDesc('placed_at')
            ->orderByDesc('id')
            ->paginate(10);

        return view('customer.account.orders.index', compact('orders'));
    }

    public function show(Request $request, Order $order): View
    {
        $this->authorizeOwnedOrder($request, $order);

        $order->load([
            'addresses',
            'shipping',
            'payment',
            'items.options',
            'statusHistory' => fn ($query) => $query
                ->latest('created_at')
                ->latest('id'),
            'cancellationRequests' => fn ($query) => $query
                ->with('reviewer:id,name')
                ->latest('created_at')
                ->latest('id'),
            'refunds' => fn ($query) => $query
                ->with(['items.orderItem:id,name,sku'])
                ->latest('refunded_at')
                ->latest('id'),
        ]);

        $manualPayment = $this->paymentInstructions->present($order);
        $canRequestCancellation = $this->cancellationRequests->canRequest($order);

        return view('customer.account.orders.show', compact(
            'order',
            'manualPayment',
            'canRequestCancellation'
        ));
    }

    public function printOrder(Request $request, Order $order): View
    {
        $this->authorizeOwnedOrder($request, $order);

        return view('orders.print', $this->orderPrint->present($order));
    }

    private function authorizeOwnedOrder(Request $request, Order $order): void
    {
        abort_unless(
            (int) $order->user_id === (int) $request->user('customer')->getKey(),
            404
        );
    }
}
