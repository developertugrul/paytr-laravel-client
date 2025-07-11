<?php
namespace Paytr\Helpers;

/**
 * HashHelper
 * PayTR API için hash ve imzalama işlemlerini yapar.
 */
class HashHelper
{
    /**
     * PayTR hash algoritması ile imza oluşturur.
     *
     * @param string $data
     * @param string $key
     * @param string $salt
     * @return string
     */
    public static function makeSignature(string $data, string $key, string $salt): string
    {
        // PayTR dokümantasyonuna göre hash_hmac('sha256', ...)
        return base64_encode(hash_hmac('sha256', $data . $salt, $key, true));
    }
} 