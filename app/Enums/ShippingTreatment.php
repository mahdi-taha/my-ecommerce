<?php

namespace App\Enums;

enum ShippingTreatment: string
{
    case CompanyAbsorbs = 'company_absorbs';
    case DeductFromRefund = 'deduct_from_refund';
}
