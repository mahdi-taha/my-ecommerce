<?php

namespace Database\Seeders;

use App\Enums\NotificationAudienceCode;
use App\Enums\NotificationChannelCode;
use App\Enums\NotificationEventCode;
use App\Models\NotificationAudience;
use App\Models\NotificationChannel;
use App\Models\NotificationEvent;
use App\Models\NotificationRule;
use Illuminate\Database\Seeder;

class NotificationConfigurationSeeder extends Seeder
{
    public function run(): void
    {
        $events = [
            NotificationEventCode::OrderPlaced->value => ['Order Placed', 'orders'],
            NotificationEventCode::OrderCancelled->value => ['Order Cancelled', 'orders'],
            NotificationEventCode::DeliveryFailed->value => ['Delivery Failed', 'orders'],
            NotificationEventCode::OrderCompleted->value => ['Order Completed', 'orders'],
            NotificationEventCode::PaymentPaid->value => ['Payment Marked Paid', 'payments'],
            NotificationEventCode::PaymentFailed->value => ['Payment Failed', 'payments'],
            NotificationEventCode::PaymentCancelled->value => ['Payment Cancelled', 'payments'],
            NotificationEventCode::PaymentRefunded->value => ['Payment Refunded', 'payments'],
            NotificationEventCode::CancellationRequestSubmitted->value => ['Cancellation Request Submitted', 'customer'],
            NotificationEventCode::CancellationRequestApproved->value => ['Cancellation Request Approved', 'customer'],
            NotificationEventCode::CancellationRequestRejected->value => ['Cancellation Request Rejected', 'customer'],
            NotificationEventCode::CouponApplied->value => ['Coupon Applied', 'promotions'],
        ];
        $channels = [
            NotificationChannelCode::Email->value => 'Email',
            NotificationChannelCode::Sms->value => 'SMS',
            NotificationChannelCode::WhatsApp->value => 'WhatsApp',
            NotificationChannelCode::Database->value => 'Database',
        ];
        $audiences = [
            NotificationAudienceCode::Customer->value => 'Customer',
            NotificationAudienceCode::Administrator->value => 'Administrator',
        ];

        $eventModels = collect($events)->map(function (array $definition, string $code) {
            $event = NotificationEvent::query()->firstOrNew(['code' => $code]);
            $event->fill(['name' => $definition[0], 'category' => $definition[1]]);
            $event->is_active ??= true;
            $event->save();

            return $event;
        });
        $channelModels = collect($channels)->map(function (string $name, string $code) {
            $channel = NotificationChannel::query()->firstOrNew(['code' => $code]);
            $channel->name = $name;
            $channel->is_active ??= true;
            $channel->save();

            return $channel;
        });
        $audienceModels = collect($audiences)->map(function (string $name, string $code) {
            $audience = NotificationAudience::query()->firstOrNew(['code' => $code]);
            $audience->name = $name;
            $audience->is_active ??= true;
            $audience->save();

            return $audience;
        });

        foreach ($eventModels as $event) {
            foreach ($audienceModels as $audience) {
                foreach ($channelModels as $channel) {
                    NotificationRule::query()->firstOrCreate([
                        'notification_event_id' => $event->getKey(),
                        'notification_audience_id' => $audience->getKey(),
                        'notification_channel_id' => $channel->getKey(),
                    ], ['is_enabled' => false]);
                }
            }
        }
    }
}
