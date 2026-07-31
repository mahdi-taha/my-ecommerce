<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectOrderCancellationRequest;
use App\Models\Order;
use App\Models\OrderCancellationRequest;
use App\Services\OrderCancellationRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use LogicException;
use RuntimeException;

class OrderCancellationRequestController extends Controller
{
    public function __construct(private OrderCancellationRequestService $service) {}

    public function approve(
        Request $request,
        Order $order,
        OrderCancellationRequest $cancellationRequest
    ): RedirectResponse {
        return $this->review(function () use ($request, $order, $cancellationRequest) {
            $this->service->approve($order, $cancellationRequest, $request->user('admin'));

            return 'Cancellation request approved and Order cancelled successfully.';
        }, $order);
    }

    public function reject(
        RejectOrderCancellationRequest $request,
        Order $order,
        OrderCancellationRequest $cancellationRequest
    ): RedirectResponse {
        return $this->review(function () use ($request, $order, $cancellationRequest) {
            $this->service->reject(
                $order,
                $cancellationRequest,
                $request->user('admin'),
                $request->validated('admin_note')
            );

            return 'Cancellation request rejected.';
        }, $order);
    }

    private function review(callable $action, Order $order): RedirectResponse
    {
        try {
            $message = $action();
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first()
                ?? 'The cancellation request could not be reviewed.';

            return redirect()->route('admin.orders.show', $order)->with('error', $message);
        } catch (RuntimeException|LogicException $exception) {
            return redirect()->route('admin.orders.show', $order)->with('error', $exception->getMessage());
        }

        return redirect()->route('admin.orders.show', $order)->with('success', $message);
    }
}
