<?php

namespace App\Support;

use App\Models\PurchaseOrder;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Throwable;

class PurchaseOrderCodeGenerator
{
    public const MAX_RETRIES = 5;

    public static function generate(?CarbonInterface $date = null): string
    {
        $prefix = 'PO-' . ($date ?: now())->format('Ymd') . '-';

        $codes = PurchaseOrder::query()
            ->where('po_code', 'like', $prefix . '%')
            ->lockForUpdate()
            ->pluck('po_code');

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

        throw new \RuntimeException('Nomor PO hari ini sudah mencapai batas 9999.');
    }

    public static function isDuplicateCodeException(Throwable $e): bool
    {
        if ($e instanceof QueryException) {
            $sqlState = (string) ($e->errorInfo[0] ?? $e->getCode());
            $message = strtolower($e->getMessage());

            return $sqlState === '23000'
                && str_contains($message, 'duplicate')
                && str_contains($message, 'po_code');
        }

        return $e->getPrevious()
            ? self::isDuplicateCodeException($e->getPrevious())
            : false;
    }
}
