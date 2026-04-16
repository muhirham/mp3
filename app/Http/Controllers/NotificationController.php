<?php

namespace App\Http\Controllers;

use App\Models\Notification;
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
        $type = $request->type;

        $query = Notification::where('user_id', auth()->id())
            ->where('is_read', false);

        if ($type) {
            // Support multiple types: ?type=new_return,return_resubmit
            $types = explode(',', $type);
            $query->whereIn('type', $types);
        }

        return response()->json(['count' => $query->count()]);
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
