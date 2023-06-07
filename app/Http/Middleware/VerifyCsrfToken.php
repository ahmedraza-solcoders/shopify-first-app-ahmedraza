<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        'create_charge',
        'cancel_charge',
        'get_current_charge_status',
        'check_billing',
        'send_support_email',
        'save_setting',
        'new_setting_save',
        'gdpr_view_customer',
        'gdpr_delete_customer',
        'gdpr_delete_shop',
        'uninstall',
        'get_settings',
        'get_products',
        'get_variants',
        'save_variant_table_settings',
        'get_product_types',
        'get_collections',
        'check_product_collection',
        'specific_table_save',
        'get_specific_table',
        'update_specific_table',
        'delete_specific_table',
        'get_variant_settings',
        'get_product_variants',
    ];
}
