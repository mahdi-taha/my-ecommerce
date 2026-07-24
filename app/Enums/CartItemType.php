<?php

namespace App\Enums;

enum CartItemType: string
{
    case Simple = 'simple';
    case Configurable = 'configurable';
}
