<?php

namespace App\Http\Controllers\Shop\Account;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
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
        ]);

        return view('customer.account.orders.show', compact('order'));
    }
}
