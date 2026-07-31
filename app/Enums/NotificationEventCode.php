<?php

namespace App\Enums;

enum NotificationEventCode: string
{
    case OrderPlaced = 'order_placed';
    case OrderCancelled = 'order_cancelled';
    case DeliveryFailed = 'delivery_failed';
    case OrderCompleted = 'order_completed';
    case PaymentPaid = 'payment_paid';
    case PaymentFailed = 'payment_failed';
    case PaymentCancelled = 'payment_cancelled';
    case PaymentRefunded = 'payment_refunded';
    case CancellationRequestSubmitted = 'cancellation_request_submitted';
    case CancellationRequestApproved = 'cancellation_request_approved';
    case CancellationRequestRejected = 'cancellation_request_rejected';
    case CouponApplied = 'coupon_applied';
}
