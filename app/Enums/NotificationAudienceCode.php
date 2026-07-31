<?php

namespace App\Enums;

enum NotificationAudienceCode: string
{
    case Customer = 'customer';
    case Administrator = 'administrator';
}
