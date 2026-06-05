<?php

namespace Reach\StatamicResrvVouchers\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Reach\StatamicResrv\Mail\Mailable;
use Reach\StatamicResrvVouchers\Models\Voucher;

class VoucherAttended extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Voucher $voucher) {}

    public function build()
    {
        $this->applyResrvEmailConfig(config('resrv-vouchers.email.attended') ?? []);

        return $this->markdown($this->markdownTemplate('statamic-resrv-vouchers::email.vouchers.attended'))
            ->subject($this->subjectOrDefault())
            ->with([
                'voucher' => $this->voucher,
                'reservation' => $this->voucher->reservation,
            ]);
    }

    private function subjectOrDefault(): string
    {
        return $this->subject ?: __('Thank you for attending!');
    }
}
