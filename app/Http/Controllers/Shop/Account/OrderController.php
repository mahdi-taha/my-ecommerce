<?php

namespace App\Http\Controllers\Shop\Account;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Presenters\ManualPaymentInstructionsPresenter;
use App\Services\OrderCancellationRequestService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(
        private ManualPaymentInstructionsPresenter $paymentInstructions,
        private OrderCancellationRequestService $cancellationRequests
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
        abort_unless(
            (int) $order->user_id === (int) $request->user('customer')->getKey(),
            404
        );

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
        ]);

        $manualPayment = $this->paymentInstructions->present($order);
        $canRequestCancellation = $this->cancellationRequests->canRequest($order);

        return view('customer.account.orders.show', compact(
            'order',
            'manualPayment',
            'canRequestCancellation'
        ));
    }
}
