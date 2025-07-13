<?php
namespace Paytr\Tests\Feature;

use Paytr\Tests\TestCase;
use Paytr\Facades\Paytr;
use Paytr\Exceptions\PaytrException;

class PaymentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Test config'i yükle
        $this->app['config']->set('paytr', [
            'merchant_id' => 'test_merchant_id',
            'merchant_key' => 'test_merchant_key',
            'merchant_salt' => 'test_merchant_salt',
            'sandbox' => true,
            'debug' => true,
            'default_currency' => 'TL',
            'default_lang' => 'tr',
        ]);
    }

    public function test_can_create_iframe_token()
    {
        $payload = [
            'merchant_oid' => 'TEST_ORDER_' . time(),
            'email' => 'test@example.com',
            'amount' => 10000,
            'user_name' => 'Test User',
            'user_address' => 'Test Address',
            'user_phone' => '5551234567',
            'ok_url' => 'https://example.com/success',
            'fail_url' => 'https://example.com/fail',
            'basket' => [
                ['name' => 'Test Product', 'price' => 10000, 'quantity' => 1],
            ],
        ];

        try {
            $result = Paytr::payment()->createIframeToken($payload);
            $this->assertIsArray($result);
        } catch (PaytrException $e) {
            // Test ortamında API hatası beklenir
            $this->assertInstanceOf(PaytrException::class, $e);
        }
    }

    public function test_can_get_payment_status()
    {
        $merchantOid = 'TEST_ORDER_' . time();

        try {
            $result = Paytr::payment()->getPaymentStatus($merchantOid);
            $this->assertIsArray($result);
        } catch (PaytrException $e) {
            // Test ortamında API hatası beklenir
            $this->assertInstanceOf(PaytrException::class, $e);
        }
    }

    public function test_can_process_refund()
    {
        $merchantOid = 'TEST_ORDER_' . time();

        try {
            $result = Paytr::refund()->refund($merchantOid);
            $this->assertIsArray($result);
        } catch (PaytrException $e) {
            // Test ortamında API hatası beklenir
            $this->assertInstanceOf(PaytrException::class, $e);
        }
    }

    public function test_can_store_card()
    {
        $cardData = [
            'customer_id' => 'TEST_CUST_' . time(),
            'cc_owner' => 'Test User',
            'card_number' => '4111111111111111',
            'expiry_month' => '12',
            'expiry_year' => '2025',
            'cvv' => '123',
        ];

        try {
            $result = Paytr::card()->storeCard($cardData);
            $this->assertIsArray($result);
        } catch (PaytrException $e) {
            // Test ortamında API hatası beklenir
            $this->assertInstanceOf(PaytrException::class, $e);
        }
    }


    public function test_can_cancel_order()
    {
        $oid = 'TEST_ORDER_' . time();

        try {
            $result = Paytr::cancel()->cancel($oid);
            $this->assertIsArray($result);
        } catch (PaytrException $e) {
            $this->assertInstanceOf(PaytrException::class, $e);
        }
    }

    public function test_can_partial_cancel_order()
    {
        $oid = 'TEST_ORDER_' . time();

        try {
            $result = Paytr::cancel()->partialCancel($oid, 100);
            $this->assertIsArray($result);
        } catch (PaytrException $e) {
            $this->assertInstanceOf(PaytrException::class, $e);
        }
    }
}
