<?php
namespace Paytr\Services;

use Paytr\Helpers\HashHelper;
use Paytr\Exceptions\PaytrException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Request;
use GuzzleHttp\Client;

/**
 * PaymentService
 * PayTR ödeme işlemlerini yönetir.
 * Direct API, iFrame API, Token oluşturma, Filtreleme ve Pagination desteği.
 */
class PaymentService
{
    /** @var Client */
    protected $http;

    public function __construct()
    {
        $timeout = Config::get('paytr.timeout', 30);
        $verify = Config::get('paytr.verify_ssl', true);
        $this->http = new Client([
            'timeout' => $timeout,
            'verify' => $verify,
        ]);
    }

    /**
     * PayTR ile ödeme başlatır (Direct API)
     *
     * @param array $payload
     * @return array
     * @throws PaytrException
     */
    public function pay(array $payload): array
    {
        $config = Config::get('paytr');
        $data = $this->preparePaymentData($payload, $config);
        $signature = HashHelper::makeSignature($data['hash_str'], $config['merchant_key'], $config['merchant_salt']);
        $data['paytr_token'] = $signature;
        unset($data['hash_str']);

        try {
            $response = $this->http->post($config['direct_api_url'], [
                'form_params' => $data,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'PayTR-Laravel-Client/1.0',
                ],
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if (empty($result) || !isset($result['status']) || $result['status'] !== 'success') {
                throw new PaytrException($result['reason'] ?? 'PayTR ödeme başarısız.');
            }

            return $result;
        } catch (\Exception $e) {
            throw new PaytrException('PayTR API Hatası: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * iFrame API için token oluşturur
     *
     * @param array $payload
     * @return array
     * @throws PaytrException
     */
    public function createIframeToken(array $payload): array
    {
        $config = Config::get('paytr');
        $data = $this->prepareIframeData($payload, $config);
        $signature = HashHelper::makeSignature($data['hash_str'], $config['merchant_key'], $config['merchant_salt']);
        $data['paytr_token'] = $signature;
        unset($data['hash_str']);

        try {
            $response = $this->http->post($config['iframe_api_url'], [
                'form_params' => $data,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'PayTR-Laravel-Client/1.0',
                ],
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if (empty($result) || !isset($result['status']) || $result['status'] !== 'success') {
                throw new PaytrException($result['reason'] ?? 'iFrame token oluşturma başarısız.');
            }

            return $result;
        } catch (\Exception $e) {
            throw new PaytrException('PayTR iFrame API Hatası: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Ödeme durumu sorgular
     *
     * @param string $merchantOid
     * @return array
     * @throws PaytrException
     */
    public function getPaymentStatus(string $merchantOid): array
    {
        $config = Config::get('paytr');

        $data = [
            'merchant_id' => $config['merchant_id'],
            'merchant_oid' => $merchantOid,
        ];

        $hashStr = $data['merchant_id'] . $data['merchant_oid'];
        $data['paytr_token'] = HashHelper::makeSignature($hashStr, $config['merchant_key'], $config['merchant_salt']);
        try {
            $response = $this->http->post($config['api_url'] . 'payment/status', [
                'form_params' => $data,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'PayTR-Laravel-Client/1.0',
                ],
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if (empty($result) || !isset($result['status']) || $result['status'] !== 'success') {
                throw new PaytrException($result['reason'] ?? 'Ödeme durumu alınamadı.');
            }

            return $result;
        } catch (\Exception $e) {
            throw new PaytrException('PayTR Ödeme Durumu API Hatası: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Kart BIN'ine göre taksit oranlarını sorgular
     *
     * @param string $bin
     * @return array
     * @throws PaytrException
     */
    public function getInstallmentRates(string $bin): array
    {
        $config = Config::get('paytr');
        $data = [
            'merchant_id' => $config['merchant_id'],
            'bin_number' => $bin,
        ];
        $hashStr = $data['merchant_id'] . $data['bin_number'];
        $data['paytr_token'] = \Paytr\Helpers\HashHelper::makeSignature($hashStr, $config['merchant_key'], $config['merchant_salt']);
        try {
            $response = $this->http->post($config['api_url'] . 'installment/rates', [
                'form_params' => $data,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'PayTR-Laravel-Client/1.0',
                ],
            ]);
            $result = json_decode($response->getBody()->getContents(), true);
            if (empty($result) || !isset($result['status']) || $result['status'] !== 'success') {
                throw new PaytrException($result['reason'] ?? 'Taksit oranları alınamadı.');
            }
            return $result;
        } catch (\Exception $e) {
            throw new PaytrException('PayTR Taksit Oranları API Hatası: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Kart BIN bilgisini sorgular
     *
     * @param string $bin
     * @return array
     * @throws PaytrException
     */
    public function lookupBin(string $bin): array
    {
        $config = Config::get('paytr');
        $data = [
            'merchant_id' => $config['merchant_id'],
            'bin_number' => $bin,
        ];
        $hashStr = $data['merchant_id'] . $data['bin_number'];
        $data['paytr_token'] = \Paytr\Helpers\HashHelper::makeSignature($hashStr, $config['merchant_key'], $config['merchant_salt']);
        try {
            $response = $this->http->post($config['api_url'] . 'bin/lookup', [
                'form_params' => $data,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'PayTR-Laravel-Client/1.0',
                ],
            ]);
            $result = json_decode($response->getBody()->getContents(), true);
            if (empty($result) || !isset($result['status']) || $result['status'] !== 'success') {
                throw new PaytrException($result['reason'] ?? 'BIN bilgisi alınamadı.');
            }
            return $result;
        } catch (\Exception $e) {
            throw new PaytrException('PayTR BIN Lookup API Hatası: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * İşlem detayını sorgular
     *
     * @param string $merchantOid
     * @return array
     * @throws PaytrException
     */
    public function getTransactionDetail(string $merchantOid): array
    {
        $config = Config::get('paytr');
        $data = [
            'merchant_id' => $config['merchant_id'],
            'merchant_oid' => $merchantOid,
        ];
        $hashStr = $data['merchant_id'] . $data['merchant_oid'];
        $data['paytr_token'] = \Paytr\Helpers\HashHelper::makeSignature($hashStr, $config['merchant_key'], $config['merchant_salt']);
        try {
            $response = $this->http->post($config['api_url'] . 'transaction/detail', [
                'form_params' => $data,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'PayTR-Laravel-Client/1.0',
                ],
            ]);
            $result = json_decode($response->getBody()->getContents(), true);
            if (empty($result) || !isset($result['status']) || $result['status'] !== 'success') {
                throw new PaytrException($result['reason'] ?? 'İşlem detayı alınamadı.');
            }
            return $result;
        } catch (\Exception $e) {
            throw new PaytrException('PayTR İşlem Detay API Hatası: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Ödeme raporu (statement) sorgular
     *
     * @param array $payload
     * @return array
     * @throws PaytrException
     */
    public function getPaymentStatement(array $payload): array
    {
        $config = Config::get('paytr');
        $data = [
            'merchant_id' => $config['merchant_id'],
            'date_start' => $payload['date_start'],
            'date_end' => $payload['date_end'],
        ];
        $hashStr = $data['merchant_id'] . $data['date_start'] . $data['date_end'];
        $data['paytr_token'] = \Paytr\Helpers\HashHelper::makeSignature($hashStr, $config['merchant_key'], $config['merchant_salt']);

        try {
            $response = $this->http->post($config['api_url'] . 'payment/statement', [
                'form_params' => $data,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'PayTR-Laravel-Client/1.0',
                ],
            ]);
            $result = json_decode($response->getBody()->getContents(), true);
            if (empty($result) || !isset($result['status']) || $result['status'] !== 'success') {
                throw new PaytrException($result['reason'] ?? 'Ödeme raporu alınamadı.');
            }
            return $result;
        } catch (\Exception $e) {
            throw new PaytrException('PayTR Ödeme Raporu API Hatası: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Ödeme detayını sorgular
     *
     * @param string $paymentId
     * @return array
     * @throws PaytrException
     */
    public function getPaymentDetail(string $paymentId): array
    {
        $config = Config::get('paytr');
        $data = [
            'merchant_id' => $config['merchant_id'],
            'payment_id' => $paymentId,
        ];
        $hashStr = $data['merchant_id'] . $data['payment_id'];
        $data['paytr_token'] = \Paytr\Helpers\HashHelper::makeSignature($hashStr, $config['merchant_key'], $config['merchant_salt']);
        try {
            $response = $this->http->post($config['api_url'] . 'payment/detail', [
                'form_params' => $data,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'PayTR-Laravel-Client/1.0',
                ],
            ]);
            $result = json_decode($response->getBody()->getContents(), true);
            if (empty($result) || !isset($result['status']) || $result['status'] !== 'success') {
                throw new PaytrException($result['reason'] ?? 'Ödeme detayı alınamadı.');
            }
            return $result;
        } catch (\Exception $e) {
            throw new PaytrException('PayTR Ödeme Detay API Hatası: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Ödeme için gerekli verileri hazırlar (Direct API)
     *
     * @param array $payload
     * @param array $config
     * @return array
     */
    protected function preparePaymentData(array $payload, array $config): array
    {
        $data = [
            'merchant_id'    => $config['merchant_id'],
            'user_ip'        => $payload['user_ip'] ?? Request::ip(),
            'merchant_oid'   => $payload['merchant_oid'],
            'email'          => $payload['email'],
            'payment_amount' => $payload['amount'],
            'currency'       => $payload['currency'] ?? 'TL',
            'user_name'      => $payload['user_name'],
            'user_address'   => $payload['user_address'],
            'user_phone'     => $payload['user_phone'],
            'merchant_ok_url'=> $payload['ok_url'],
            'merchant_fail_url'=> $payload['fail_url'],
            'user_basket'    => $this->encodeBasket($payload['basket']),
            'no_installment' => $payload['no_installment'] ?? 0,
            'max_installment'=> $payload['max_installment'] ?? 0,
            'lang'           => $payload['lang'] ?? 'tr',
            'payment_type'   => $payload['payment_type'] ?? 'card',
            'cc_owner'       => $payload['cc_owner'] ?? '',
            'card_number'    => $payload['card_number'] ?? '',
            'expiry_month'   => $payload['expiry_month'] ?? '',
            'expiry_year'    => $payload['expiry_year'] ?? '',
            'cvv'            => $payload['cvv'] ?? '',
            'installment_count' => $payload['installment_count'] ?? 0,
            'non_3d'         => $payload['non_3d'] ?? 0,
            'timeout_limit'  => $payload['timeout_limit'] ?? Config::get('paytr.default_timeout', 0),
        ];

        // Hash stringi PayTR dokümantasyonuna göre hazırlanır
        $hash_str = $data['merchant_id'] . $data['user_ip'] . $data['merchant_oid'] . $data['email'] . $data['payment_amount'] . $data['user_basket'] . $data['no_installment'] . $data['max_installment'] . $data['currency'] . $data['lang'];
        $data['hash_str'] = $hash_str;
        return $data;
    }

    /**
     * iFrame API için verileri hazırlar
     *
     * @param array $payload
     * @param array $config
     * @return array
     */
    protected function prepareIframeData(array $payload, array $config): array
    {
        $data = [
            'merchant_id'    => $config['merchant_id'],
            'user_ip'        => $payload['user_ip'] ?? Request::ip(),
            'merchant_oid'   => $payload['merchant_oid'],
            'email'          => $payload['email'],
            'payment_amount' => $payload['amount'],
            'currency'       => $payload['currency'] ?? 'TL',
            'user_name'      => $payload['user_name'],
            'user_address'   => $payload['user_address'],
            'user_phone'     => $payload['user_phone'],
            'merchant_ok_url'=> $payload['ok_url'],
            'merchant_fail_url'=> $payload['fail_url'],
            'user_basket'    => $this->encodeBasket($payload['basket']),
            'no_installment' => $payload['no_installment'] ?? 0,
            'max_installment'=> $payload['max_installment'] ?? 0,
            'lang'           => $payload['lang'] ?? 'tr',
            'debug_on'       => $config['debug'] ? 1 : 0,
            'test_mode'      => $config['sandbox'] ? 1 : 0,
            'timeout_limit'  => $payload['timeout_limit'] ?? Config::get('paytr.default_timeout', 0),
        ];

        // Hash stringi PayTR dokümantasyonuna göre hazırlanır
        $hash_str = $data['merchant_id'] . $data['user_ip'] . $data['merchant_oid'] . $data['email'] . $data['payment_amount'] . $data['user_basket'] . $data['no_installment'] . $data['max_installment'] . $data['currency'] . $data['lang'];
        $data['hash_str'] = $hash_str;
        return $data;
    }

    /**
     * Ön provizyon işlemi başlatır
     *
     * @param array $payload
     * @return array
     * @throws PaytrException
     */
    public function preProvision(array $payload): array
    {
        $config = Config::get('paytr');
        $data = [
            'merchant_id'    => $config['merchant_id'],
            'user_ip'        => $payload['user_ip'] ?? Request::ip(),
            'merchant_oid'   => $payload['merchant_oid'],
            'email'          => $payload['email'],
            'payment_amount' => $payload['amount'],
            'currency'       => $payload['currency'] ?? 'TL',
            'user_name'      => $payload['user_name'],
            'user_address'   => $payload['user_address'],
            'user_phone'     => $payload['user_phone'],
            'user_basket'    => $this->encodeBasket($payload['basket']),
            'lang'           => $payload['lang'] ?? 'tr',
        ];
        $hashStr = $data['merchant_id'] . $data['user_ip'] . $data['merchant_oid'] . $data['email'] . $data['payment_amount'] . $data['user_basket'] . $data['currency'] . $data['lang'];
        $data['paytr_token'] = \Paytr\Helpers\HashHelper::makeSignature($hashStr, $config['merchant_key'], $config['merchant_salt']);
        try {
            $response = $this->http->post($config['api_url'] . 'preprovision', [
                'form_params' => $data,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'PayTR-Laravel-Client/1.0',
                ],
            ]);
            $result = json_decode($response->getBody()->getContents(), true);
            if (empty($result) || !isset($result['status']) || $result['status'] !== 'success') {
                throw new PaytrException($result['reason'] ?? 'Ön provizyon işlemi başarısız.');
            }
            return $result;
        } catch (\Exception $e) {
            throw new PaytrException('PayTR Ön Provizyon API Hatası: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Transfer/EFT iFrame API işlemi başlatır
     *
     * @param array $payload
     * @return array
     * @throws PaytrException
     */
    public function createEftIframe(array $payload): array
    {
        $config = Config::get('paytr');
        $data = [
            'merchant_id'    => $config['merchant_id'],
            'user_ip'        => $payload['user_ip'] ?? Request::ip(),
            'merchant_oid'   => $payload['merchant_oid'],
            'email'          => $payload['email'],
            'payment_amount' => $payload['amount'],
            'currency'       => $payload['currency'] ?? 'TL',
            'user_name'      => $payload['user_name'],
            'user_address'   => $payload['user_address'],
            'user_phone'     => $payload['user_phone'],
            'user_basket'    => $this->encodeBasket($payload['basket']),
            'lang'           => $payload['lang'] ?? 'tr',
        ];
        $hashStr = $data['merchant_id'] . $data['user_ip'] . $data['merchant_oid'] . $data['email'] . $data['payment_amount'] . $data['user_basket'] . $data['currency'] . $data['lang'];
        $data['paytr_token'] = \Paytr\Helpers\HashHelper::makeSignature($hashStr, $config['merchant_key'], $config['merchant_salt']);
        try {
            $response = $this->http->post($config['api_url'] . 'eft/iframe', [
                'form_params' => $data,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'PayTR-Laravel-Client/1.0',
                ],
            ]);
            $result = json_decode($response->getBody()->getContents(), true);
            if (empty($result) || !isset($result['status']) || $result['status'] !== 'success') {
                throw new PaytrException($result['reason'] ?? 'EFT iFrame işlemi başarısız.');
            }
            return $result;
        } catch (\Exception $e) {
            throw new PaytrException('PayTR EFT iFrame API Hatası: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * BKM Express ile ödeme başlatır
     *
     * @param array $payload
     * @return array
     * @throws PaytrException
     */
    public function payWithBkmExpress(array $payload): array
    {
        $config = Config::get('paytr');
        $data = [
            'merchant_id'    => $config['merchant_id'],
            'user_ip'        => $payload['user_ip'] ?? Request::ip(),
            'merchant_oid'   => $payload['merchant_oid'],
            'email'          => $payload['email'],
            'payment_amount' => $payload['amount'],
            'currency'       => $payload['currency'] ?? 'TL',
            'user_name'      => $payload['user_name'],
            'user_address'   => $payload['user_address'],
            'user_phone'     => $payload['user_phone'],
            'user_basket'    => $this->encodeBasket($payload['basket']),
            'lang'           => $payload['lang'] ?? 'tr',
        ];
        $hashStr = $data['merchant_id'] . $data['user_ip'] . $data['merchant_oid'] . $data['email'] . $data['payment_amount'] . $data['user_basket'] . $data['currency'] . $data['lang'];
        $data['paytr_token'] = \Paytr\Helpers\HashHelper::makeSignature($hashStr, $config['merchant_key'], $config['merchant_salt']);
        try {
            $response = $this->http->post($config['api_url'] . 'bkm/express', [
                'form_params' => $data,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'PayTR-Laravel-Client/1.0',
                ],
            ]);
            $result = json_decode($response->getBody()->getContents(), true);
            if (empty($result) || !isset($result['status']) || $result['status'] !== 'success') {
                throw new PaytrException($result['reason'] ?? 'BKM Express ile ödeme başarısız.');
            }
            return $result;
        } catch (\Exception $e) {
            throw new PaytrException('PayTR BKM Express API Hatası: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Sepet verilerini PayTR formatında encode eder
     *
     * @param array $basket
     * @return string
     */
    protected function encodeBasket(array $basket): string
    {
        return base64_encode(json_encode($basket));
    }
}
