<?php
/**
 * PayTR Laravel Client - Konfigürasyon Dosyası
 * Tüm hassas bilgiler .env üzerinden alınır.
 */
return [
    'merchant_id'    => env('PAYTR_MERCHANT_ID'),
    'merchant_key'   => env('PAYTR_MERCHANT_KEY'),
    'merchant_salt'  => env('PAYTR_MERCHANT_SALT'),
    'debug'          => env('PAYTR_DEBUG', false),
    'sandbox'        => env('PAYTR_SANDBOX', true),
    
    // API Endpoints
    'api_url'        => env('PAYTR_API_URL', 'https://www.paytr.com/odeme/api/'),
    'direct_api_url' => env('PAYTR_DIRECT_API_URL', 'https://www.paytr.com/odeme/direkt/api/'),
    'iframe_api_url' => env('PAYTR_IFRAME_API_URL', 'https://www.paytr.com/odeme/api/get-token'),
    
    // Webhook ayarları
    'webhook_secret' => env('PAYTR_WEBHOOK_SECRET'),
    'allowed_ips'    => env('PAYTR_ALLOWED_IPS', ''), // Virgülle ayrılmış IP listesi
    
    // Güvenlik ayarları
    'timeout'        => env('PAYTR_TIMEOUT', 30),
    'verify_ssl'     => env('PAYTR_VERIFY_SSL', true),
    
    // Varsayılan ayarlar
    'default_currency' => env('PAYTR_DEFAULT_CURRENCY', 'TL'),
    'default_lang'     => env('PAYTR_DEFAULT_LANG', 'tr'),
    'default_timeout'  => env('PAYTR_DEFAULT_TIMEOUT', 0),
]; 