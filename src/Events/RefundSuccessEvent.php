<?php
namespace Paytr\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RefundSuccessEvent
{
    use Dispatchable, SerializesModels;

    public array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }
}