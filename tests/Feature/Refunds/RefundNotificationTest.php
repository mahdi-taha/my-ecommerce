<?php

namespace Tests\Feature\Refunds;

use App\DTOs\Notifications\NotificationDispatchDecision;
use App\Enums\NotificationEventCode;
use App\Enums\ShippingTreatment;
use App\Events\CommerceEventOccurred;
use App\Services\NotificationMessageBuilder;
use App\Services\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Support\CreatesRefundOrders;
use Tests\TestCase;

class RefundNotificationTest extends TestCase
{
    use CreatesRefundOrders;
    use RefreshDatabase;

    public function test_completed_refund_dispatches_the_refund_aggregate_event(): void
    {
        Event::fake([CommerceEventOccurred::class]);
        [$order, , $admin] = $this->paidRefundOrder();
        $item = $this->refundOrderItem($order);

        $refund = app(RefundService::class)->create($order, $admin, [
            'items' => [['order_item_id' => $item->id, 'quantity' => '1']],
            'return_shipping_cost' => '0',
            'shipping_treatment' => ShippingTreatment::CompanyAbsorbs->value,
        ], str_repeat('1', 64));

        Event::assertDispatched(CommerceEventOccurred::class, fn ($event) => $event->event === NotificationEventCode::PaymentRefunded
            && $event->entityType === 'refund' && $event->entityId === $refund->id);

        $message = app(NotificationMessageBuilder::class)->build(new NotificationDispatchDecision(
            NotificationEventCode::PaymentRefunded->value,
            'refund',
            $refund->id,
            ['customer'],
            ['database'],
            [],
            true,
        ), 'en');
        $this->assertStringContainsString($refund->refund_number, $message['body']);
        $this->assertSame($order->id, $message['payload']['order_id']);
        $this->assertSame($refund->id, $message['payload']['refund_id']);
    }
}
