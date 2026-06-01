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

    /**
     * PayTR 2. Adım (Callback) için hash doğrulaması oluşturur.
     *
     * @param string $oid
     * @param string $salt
     * @param string $status
     * @param string $totalAmount
     * @param string $key
     * @return string
     */
    public static function makeCallbackSignature(string $oid, string $salt, string $status, string $totalAmount, string $key): string
    {
        $hashStr = $oid . $salt . $status . $totalAmount;
        return base64_encode(hash_hmac('sha256', $hashStr, $key, true));
    }
} 