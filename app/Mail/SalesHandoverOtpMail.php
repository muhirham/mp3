<?php

namespace App\Mail;

use App\Models\SalesHandover;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SalesHandoverOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public $handover;
    public $otp;
    public $items;
    public $grandTotal;

    public function __construct(SalesHandover $handover, string $otp, array $items, int $grandTotal)
    {
        $this->handover   = $handover;
        $this->otp        = $otp;
        $this->items      = $items;
        $this->grandTotal = $grandTotal;
    }

    public function build()
    {
        return $this->subject('OTP Handover Pagi & Sore - '.$this->handover->code)
            ->view('emails.sales_handover_otp');
    }
}
