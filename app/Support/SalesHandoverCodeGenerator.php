<?php

namespace App\Support;

use App\Models\SalesHandover;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Throwable;

class SalesHandoverCodeGenerator
{
    public const MAX_RETRIES = 5;

    /**
     * Generate kode handover dengan sequence terkecil yang belum terpakai.
     * Berlaku untuk prefix HDO (Sales Handover pagi) dan SI (Direct Sales).
     *
     * Contoh: jika HDO-260521-0001, HDO-260521-0003 sudah ada,
     * maka method ini akan menghasilkan HDO-260521-0002 (isi celah).
     *
     * @param  string                $type  'HDO' atau 'SI'
     * @param  CarbonInterface|string|null  $date  Tanggal transaksi (default: hari ini)
     * @return string
     */
    public static function generate(string $type, CarbonInterface|string|null $date = null): string
    {
        $carbon = is_string($date) ? \Carbon\Carbon::parse($date) : ($date ?: now());
        $prefix = strtoupper($type) . '-' . $carbon->format('ymd') . '-';

        $codes = SalesHandover::where('code', 'like', $prefix . '%')
            ->lockForUpdate()
            ->pluck('code');

        $used = [];
        foreach ($codes as $code) {
            $suffix = substr((string) $code, strlen($prefix));

            if (preg_match('/^\d{4}$/', $suffix) === 1) {
                $used[(int) $suffix] = true;
            }
        }

        for ($seq = 1; $seq <= 9999; $seq++) {
            if (empty($used[$seq])) {
                return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
            }
        }

        throw new \RuntimeException("Nomor {$type} hari ini sudah mencapai batas 9999.");
    }

    public static function isDuplicateCodeException(Throwable $e): bool
    {
        if ($e instanceof QueryException) {
            $sqlState = (string) ($e->errorInfo[0] ?? $e->getCode());
            $message  = strtolower($e->getMessage());

            return $sqlState === '23000'
                && str_contains($message, 'duplicate')
                && str_contains($message, 'code');
        }

        return $e->getPrevious()
            ? self::isDuplicateCodeException($e->getPrevious())
            : false;
    }
}
