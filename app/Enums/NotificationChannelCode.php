<?php

namespace App\Enums;

enum NotificationChannelCode: string
{
    case Email = 'email';
    case Sms = 'sms';
    case WhatsApp = 'whatsapp';
    case Database = 'database';
}
