<?php

namespace App\Enums;

enum BundleOptionType: string
{
    case Select = 'select';
    case Radio = 'radio';
    case Checkbox = 'checkbox';
    case Multiselect = 'multiselect';
}
