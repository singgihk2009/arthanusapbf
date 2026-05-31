<?php

$financeHubBaseUrl = env('FINANCE_HUB_BASE_URL');
$financeHubBaseUrl = is_string($financeHubBaseUrl) && $financeHubBaseUrl !== '' ? rtrim($financeHubBaseUrl, '/') : null;
$financeHubEventsUrl = env('FINANCE_HUB_EVENTS_URL');
$financeHubEventsUrl = is_string($financeHubEventsUrl) && $financeHubEventsUrl !== '' ? $financeHubEventsUrl : null;
$financeHubVendorInvoiceEventsUrl = env('FINANCE_HUB_VENDOR_INVOICE_EVENTS_URL');
$financeHubVendorInvoiceEventsUrl = is_string($financeHubVendorInvoiceEventsUrl) && $financeHubVendorInvoiceEventsUrl !== '' ? $financeHubVendorInvoiceEventsUrl : null;

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],


    'finance_hub' => [
        'base_url' => $financeHubBaseUrl,
        'events_url' => $financeHubEventsUrl ?: ($financeHubBaseUrl ? $financeHubBaseUrl.'/api/integrations/inventory/events' : null),
        'vendor_invoice_events_url' => $financeHubVendorInvoiceEventsUrl ?: ($financeHubBaseUrl ? $financeHubBaseUrl.'/api/integrations/vendor-invoices/events' : null),
        'client_key' => env('FINANCE_HUB_CLIENT_KEY'),
        'client_secret' => env('FINANCE_HUB_CLIENT_SECRET'),
        'timeout' => (int) env('FINANCE_HUB_TIMEOUT', 10),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
