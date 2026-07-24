<?php

namespace App\Enums;

enum ProductType: string
{
    case Simple = 'simple';
    case Configurable = 'configurable';
    case Bundle = 'bundle';
}
