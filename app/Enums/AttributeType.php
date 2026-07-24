<?php

namespace App\Enums;

enum AttributeType: string
{
    case Text = 'text';
    case Select = 'select';
    case Multiselect = 'multiselect';
}
