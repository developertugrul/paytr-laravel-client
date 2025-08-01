<?php

namespace Paytr\Tests\Feature;

use Paytr\Tests\TestCase;
use Paytr\Services\PaymentService;
use Paytr\Services\CardService;
use Paytr\Services\LinkService;
use Paytr\Services\PlatformService;
use Paytr\Services\RefundService;
use Paytr\Services\CancelService;
use Paytr\Helpers\HashHelper;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Config;
use ReflectionClass;

class ServicePayloadTest extends TestCase
{
    protected array $historyContainer = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['config']->set('paytr', [
            'merchant_id' => 'test_merchant_id',
            'merchant_key' => 'test_merchant_key',
            'merchant_salt' => 'test_merchant_salt',
            'direct_api_url' => 'https://api.paytr.com/pay',
            'sandbox' => false,
            'api_url' => 'https://api.paytr.com/v1/',
            'default_currency' => 'TL',
            'default_lang' => 'tr',
        ]);
    }

    protected function mockService($service)
    {
        $this->historyContainer = [];
        $history = Middleware::history($this->historyContainer);
        $mock = new MockHandler([new Response(200, [], json_encode(['status' => 'success']))]);
        $handler = HandlerStack::create($mock);
        $handler->push($history);
        $client = new Client(['handler' => $handler]);
        $ref = new ReflectionClass($service);
        $prop = $ref->getProperty('http');
        $prop->setAccessible(true);
        $prop->setValue($service, $client);
        return $service;
    }

    public static function payloadProvider(): array
    {
        $basket = [['Prod', '100.00', 1]];
        return [
            // CardService
            [CardService::class, 'storeCard', [[
                'customer_id' => 'CUST1',
                'cc_owner' => 'John Doe',
                'card_number' => '4111111111111111',
                'expiry_month' => '12',
                'expiry_year' => '25',
                'cvv' => '123',
            ]]],
            [CardService::class, 'payWithCard', ['TOKEN1', [
                'amount' => 1000,
                'merchant_oid' => 'OID1',
            ]]],
            [CardService::class, 'recurringPayment', ['TOKEN2', [
                'amount' => 1000,
                'merchant_oid' => 'OID2',
            ]]],
            [CardService::class, 'listCards', ['CUST2']],
            [CardService::class, 'deleteCard', ['TOKDEL']],

            // LinkService
            [LinkService::class, 'createLink', [[
                'email' => 'mail@example.com',
                'amount' => 1000,
                'user_name' => 'User',
                'user_address' => 'Addr',
                'user_phone' => '123',
                'basket' => $basket,
            ]]],
            [LinkService::class, 'deleteLink', ['LINK1']],
            [LinkService::class, 'sendLinkNotification', ['LINK2', 'sms']],

            // PlatformService
            [PlatformService::class, 'createTransfer', [[
                'amount' => 1000,
                'iban' => 'TR000000000000000000000000',
            ]]],
            [PlatformService::class, 'getTransferResult', ['TRF1']],

            // RefundService
            [RefundService::class, 'partialRefund', ['OID3', 500]],
            [RefundService::class, 'getRefundStatus', ['OID4']],

            // CancelService
            [CancelService::class, 'partialCancel', ['OID5', 500]],
            [CancelService::class, 'cancel', ['OID6']],

            // PaymentService new methods
            [PaymentService::class, 'preProvision', [[
                'merchant_oid' => 'OID5',
                'email' => 'test@example.com',
                'amount' => 1000,
                'user_name' => 'User',
                'user_address' => 'Addr',
                'user_phone' => '123',
                'basket' => $basket,
                'user_ip' => '1.1.1.1',
            ]]],
            [PaymentService::class, 'createEftIframe', [[
                'merchant_oid' => 'OID6',
                'email' => 'test@example.com',
                'amount' => 1000,
                'user_name' => 'User',
                'user_address' => 'Addr',
                'user_phone' => '123',
                'basket' => $basket,
                'user_ip' => '1.1.1.1',
            ]]],
            [PaymentService::class, 'payWithBkmExpress', [[
                'merchant_oid' => 'OID7',
                'email' => 'test@example.com',
                'amount' => 1000,
                'user_name' => 'User',
                'user_address' => 'Addr',
                'user_phone' => '123',
                'basket' => $basket,
                'user_ip' => '1.1.1.1',
            ]]],
            [PaymentService::class, 'pay', [[
                'merchant_oid' => 'OIDPAY',
                'email' => 'test@example.com',
                'payment_amount' => 100.00,
                'currency' => 'TL',
                'user_name' => 'User',
                'user_address' => 'Addr',
                'user_phone' => '123',
                'merchant_ok_url' => 'https://ok',
                'merchant_fail_url' => 'https://fail',
                'basket' => $basket,
                'installment_count' => 0,
                'non_3d' => 0,
                'payment_type' => 'card',
                'user_ip' => '1.1.1.1',
                'request_exp_date' => date('Y-m-d H:i:s', strtotime('+1 hour')),
            ]]],
            [PaymentService::class, 'createIframeToken', [[
                'merchant_oid' => 'OIDIFRAME',
                'email' => 'test@example.com',
                'payment_amount' => 100.00,
                'currency' => 'TL',
                'user_name' => 'User',
                'user_address' => 'Addr',
                'user_phone' => '123',
                'merchant_ok_url' => 'https://ok',
                'merchant_fail_url' => 'https://fail',
                'basket' => $basket,
                'user_ip' => '1.1.1.1',
            ]]],
        ];
    }

    protected function computeHashString(string $class, string $method, array $params): string
    {
        switch ($class . '::' . $method) {
            case CardService::class . '::storeCard':
                return $params['merchant_id'] . $params['customer_id'] . $params['card_number'];
            case CardService::class . '::payWithCard':
            case CardService::class . '::recurringPayment':
                return $params['merchant_id'] . $params['token'] . $params['amount'] . $params['merchant_oid'];
            case CardService::class . '::listCards':
                return $params['merchant_id'] . $params['customer_id'];
            case CardService::class . '::deleteCard':
                return $params['merchant_id'] . $params['token'];
            case LinkService::class . '::createLink':
                return $params['merchant_id'] . $params['email'] . $params['payment_amount'] . $params['user_basket'] . $params['currency'] . $params['lang'];
            case LinkService::class . '::deleteLink':
                return $params['merchant_id'] . $params['link_id'];
            case LinkService::class . '::sendLinkNotification':
                return $params['merchant_id'] . $params['link_id'] . $params['type'];
            case PlatformService::class . '::createTransfer':
                return $params['merchant_id'] . $params['amount'] . $params['iban'];
            case PlatformService::class . '::getTransferResult':
                return $params['merchant_id'] . $params['transfer_id'];
            case RefundService::class . '::refund':
            case RefundService::class . '::partialRefund':
                $amount = $params['amount'] ?? '';
                return $params['merchant_id'] . $params['merchant_oid'] . $amount;
            case RefundService::class . '::getRefundStatus':
                return $params['merchant_id'] . $params['merchant_oid'];
            case CancelService::class . '::cancel':
            case CancelService::class . '::partialCancel':
                $amount = $params['amount'] ?? '';
                return $params['merchant_id'] . $params['merchant_oid'] . $amount;
            case PaymentService::class . '::preProvision':
            case PaymentService::class . '::createEftIframe':
            case PaymentService::class . '::payWithBkmExpress':
                return $params['merchant_id']
                    . $params['user_ip']
                    . $params['merchant_oid']
                    . $params['email']
                    . $params['payment_amount']
                    . $params['user_basket']
                    . $params['currency']
                    . $params['lang'];
            case PaymentService::class . '::createIframeToken':
                return $params['merchant_id']
                    . $params['user_ip']
                    . $params['merchant_oid']
                    . $params['email']
                    . $params['payment_amount']
                    . $params['user_basket']
                    . $params['no_installment']
                    . $params['max_installment']
                    . $params['currency']
                    . $params['lang'];
            case PaymentService::class . '::pay':
                return $params['merchant_id']
                    . $params['user_ip']
                    . $params['merchant_oid']
                    . $params['email']
                    . $params['payment_amount']
                    . $params['payment_type']
                    . $params['installment_count']
                    . $params['currency']
                    . $params['test_mode']
                    . $params['non_3d']
                    . $params['request_exp_date'];
        }
        return '';
    }

    /**
     * @dataProvider payloadProvider
     */
    public function test_payload_structure($class, $method, $args)
    {
        $service = new $class();
        $service = $this->mockService($service);
        call_user_func_array([$service, $method], (array) $args);
        $request = $this->historyContainer[0]['request'];
        parse_str($request->getBody()->getContents(), $params);
        $this->assertArrayHasKey('paytr_token', $params);
        $hashStr = $this->computeHashString($class, $method, $params);
        $expected = HashHelper::makeSignature($hashStr, 'test_merchant_key', 'test_merchant_salt');
        $this->assertEquals($expected, $params['paytr_token']);
        $this->assertArrayNotHasKey('hash', $params);
        $this->assertArrayNotHasKey('merchant_key', $params);
        $this->assertArrayNotHasKey('merchant_salt', $params);
    }
}
