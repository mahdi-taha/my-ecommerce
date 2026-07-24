<?php

namespace App\Enums;

enum AccountType: string
{
    case Admin = 'admin';
    case Customer = 'customer';
}
