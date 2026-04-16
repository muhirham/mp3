<?php

namespace App\Helpers;

use App\Models\Notification;
use App\Models\User;

class NotificationHelper
{
    /**
     * Kirim notif ke satu user.
     */
    public static function send(int $userId, string $type, string $title, string $body = '', string $url = '', string $refType = '', int $refId = null): void
    {
        Notification::create([
            'user_id'        => $userId,
            'type'           => $type,
            'title'          => $title,
            'body'           => $body,
            'url'            => $url,
            'reference_type' => $refType,
            'reference_id'   => $refId,
            'is_read'        => false,
        ]);
    }

    /**
     * Kirim notif ke semua Superadmin & Admin.
     */
    public static function notifyAdmins(string $type, string $title, string $body = '', string $url = '', string $refType = '', int $refId = null): void
    {
        $admins = User::whereHas('roles', fn($q) => $q->whereIn('slug', ['superadmin', 'admin']))->get();

        foreach ($admins as $admin) {
            static::send($admin->id, $type, $title, $body, $url, $refType, $refId);
        }
    }

    /**
     * Kirim notif ke semua Warehouse Admin di warehouse tertentu.
     */
    public static function notifyWarehouse(int $warehouseId, string $type, string $title, string $body = '', string $url = '', string $refType = '', int $refId = null): void
    {
        $users = User::where('warehouse_id', $warehouseId)
            ->whereHas('roles', fn($q) => $q->where('slug', 'warehouse'))
            ->get();

        foreach ($users as $user) {
            static::send($user->id, $type, $title, $body, $url, $refType, $refId);
        }

        // Superadmin/Admin juga dapat notif
        static::notifyAdmins($type, $title, $body, $url, $refType, $refId);
    }

    /**
     * Kirim notif ke satu user Sales.
     */
    public static function notifySales(int $salesId, string $type, string $title, string $body = '', string $url = '', string $refType = '', int $refId = null): void
    {
        static::send($salesId, $type, $title, $body, $url, $refType, $refId);
    }

    /**
     * Tandai notifikasi sebagai sudah dibaca berdasarkan referensi (agar badge hilang untuk semua admin).
     */
    public static function markAsReadByReference(string $type, string $refType, int $refId): void
    {
        Notification::where('type', $type)
            ->where('reference_type', $refType)
            ->where('reference_id', $refId)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
    }
}
