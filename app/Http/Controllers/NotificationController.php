<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\SalesHandover;
use App\Models\StockRequest;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * GET jumlah notif yang belum dibaca (untuk badge sidebar/navbar)
     */
    public function unreadCount()
    {
        $count = Notification::where('user_id', auth()->id())
            ->where('is_read', false)
            ->count();

        return response()->json(['count' => $count]);
    }

    /**
     * GET list notif terbaru (untuk dropdown navbar)
     */
    public function index()
    {
        $notifications = Notification::where('user_id', auth()->id())
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(function ($n) {
                return [
                    'id'       => $n->id,
                    'type'     => $n->type,
                    'title'    => $n->title,
                    'body'     => $n->body,
                    'url'      => $n->url,
                    'is_read'  => $n->is_read,
                    'read_at'  => $n->read_at,
                    'time_ago' => $n->created_at->diffForHumans(),
                ];
            });

        $unread = $notifications->where('is_read', false)->count();

        return response()->json([
            'notifications' => $notifications,
            'unread_count'  => $unread,
        ]);
    }

    /**
     * POST mark satu notif sebagai sudah dibaca
     */
    public function markRead($id)
    {
        $notif = Notification::where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $notif->markAsRead();

        return response()->json(['success' => true]);
    }

    /**
     * POST mark SEMUA notif user ini sebagai sudah dibaca
     */
    public function markAllRead()
    {
        Notification::where('user_id', auth()->id())
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return response()->json(['success' => true]);
    }

    /**
     * GET unread count per type (untuk badge sidebar per menu)
     * Contoh: /notifications/badge?type=new_return
     */
    public function badgeByType(Request $request)
    {
        $typeString = $request->type;
        $userId = auth()->id();
        $totalCount = 0;

        if (!$typeString) {
            return response()->json(['count' => 0]);
        }

        $types = explode(',', $typeString);

        foreach ($types as $type) {
            $type = trim($type);

            if ($type === 'TASK_waiting_otp') {
                $totalCount += SalesHandover::where('sales_id', $userId)
                    ->where('status', 'waiting_morning_otp')
                    ->count();
            } elseif ($type === 'TASK_pending_stock_request') {
                $whId = auth()->user()->warehouse_id;
                $query = StockRequest::where('status', 'pending');
                
                // Jika bukan superadmin, filter per gudang dia
                if (!auth()->user()->hasRole('superadmin')) {
                    $query->where('warehouse_id', $whId);
                }

                $totalCount += $query->count();

            } elseif ($type === 'TASK_issued_stock') {
                // Count handovers that are currently being managed by sales (On Sales or Waiting Approval)
                $totalCount += SalesHandover::where('sales_id', $userId)
                    ->whereIn('status', ['on_sales', 'waiting_evening_otp'])
                    ->count();
            } elseif ($type === 'TASK_on_sales') {
                $totalCount += SalesHandover::where('sales_id', $userId)
                    ->where('status', 'on_sales')
                    ->count();
            } else {
                // Notifikasi unread biasa
                $totalCount += Notification::where('user_id', $userId)
                    ->where('type', $type)
                    ->where('is_read', false)
                    ->count();
            }
        }

        return response()->json(['count' => $totalCount]);
    }

    /**
     * POST mark as read by type (saat user buka menu tertentu, semua notif tipe itu di-clear)
     */
    public function markReadByType(Request $request)
    {
        $request->validate(['type' => 'required|string']);
        $types = explode(',', $request->type);

        Notification::where('user_id', auth()->id())
            ->whereIn('type', $types)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return response()->json(['success' => true]);
    }
}
