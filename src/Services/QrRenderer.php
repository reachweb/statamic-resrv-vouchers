<?php

namespace Reach\StatamicResrvVouchers\Services;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use FPDF;
use Reach\StatamicResrvVouchers\Models\Voucher;

class QrRenderer
{
    public function png(string $payload, int $size = 320): string
    {
        return Builder::create()
            ->writer(new PngWriter)
            ->data($payload)
            ->size($size)
            ->build()
            ->getString();
    }

    public function pdf(Voucher $voucher, ?string $pngBytes = null): string
    {
        $pngBytes ??= $this->png($voucher->token);

        $tmp = tempnam(sys_get_temp_dir(), 'voucher-qr-');
        file_put_contents($tmp, $pngBytes);

        try {
            $pageWidth = 105;
            $pageHeight = 148;
            $pdf = new FPDF('P', 'mm', [$pageWidth, $pageHeight]);
            $pdf->AddPage();

            $qrSize = 70;
            $x = ($pageWidth - $qrSize) / 2;
            $pdf->Image($tmp, $x, 10, $qrSize, $qrSize, 'PNG');

            $pdf->SetY(10 + $qrSize + 6);
            $pdf->SetFont('Helvetica', 'B', 12);
            $pdf->Cell(0, 6, $this->safeText($this->customerName($voucher)), 0, 1, 'C');

            $pdf->SetFont('Helvetica', '', 10);
            $pdf->Cell(0, 5, $this->safeText('Ref: '.$this->reference($voucher)), 0, 1, 'C');
            $pdf->Cell(0, 5, $this->safeText($this->dateRange($voucher)), 0, 1, 'C');

            return $pdf->Output('S');
        } finally {
            @unlink($tmp);
        }
    }

    private function customerName(Voucher $voucher): string
    {
        $data = $voucher->reservation?->customer_data ?? collect();

        $name = trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? ''));

        if ($name !== '') {
            return $name;
        }

        return (string) ($data['name'] ?? $data['email'] ?? 'Guest');
    }

    private function reference(Voucher $voucher): string
    {
        return (string) ($voucher->reservation?->reference ?? $voucher->reservation_id);
    }

    private function dateRange(Voucher $voucher): string
    {
        $start = $voucher->reservation?->date_start;
        $end = $voucher->reservation?->date_end;

        if (! $start || ! $end) {
            return '';
        }

        return $start->format('Y-m-d').' - '.$end->format('Y-m-d');
    }

    private function safeText(string $value): string
    {
        $converted = iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $value);

        return $converted !== false
            ? $converted
            : mb_convert_encoding($value, 'ASCII');
    }
}
