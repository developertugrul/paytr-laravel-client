<?php

namespace Tests\Unit;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Paytr\Events\PaymentSuccessEvent;
use Paytr\Events\PaymentFailedEvent;
use Paytr\Events\RefundSuccessEvent;

class PaytrEventsTest extends BaseTestCase
{
    public function test_payment_success_event_holds_data()
    {
        $data = [
            'event' => 'payment_success',
            'merchant_oid' => 'TEST123',
            'amount' => 100
        ];
        
        $event = new PaymentSuccessEvent($data);
        
        $this->assertEquals($data, $event->data);
    }

    public function test_payment_failed_event_holds_data()
    {
        $data = [
            'event' => 'payment_failed',
            'merchant_oid' => 'TEST456',
            'reason' => 'Insufficient funds'
        ];
        
        $event = new PaymentFailedEvent($data);
        
        $this->assertEquals($data, $event->data);
    }

    public function test_refund_success_event_holds_data()
    {
        $data = [
            'event' => 'refund_success',
            'merchant_oid' => 'TEST789',
            'refund_amount' => 50
        ];
        
        $event = new RefundSuccessEvent($data);
        
        $this->assertEquals($data, $event->data);
    }
}