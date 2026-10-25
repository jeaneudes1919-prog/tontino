<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Services\PaymentSimulator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $paymentId) {}

    public function handle(PaymentSimulator $simulator): void
    {
        if ($payment = Payment::find($this->paymentId)) {
            $simulator->process($payment);
        }
    }
}
