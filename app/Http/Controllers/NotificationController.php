<?php

namespace App\Http\Controllers;

use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class NotificationController extends Controller
{
    public function markAllRead(Request $request): RedirectResponse
    {
        if (Schema::hasTable('notifications')) {
            $request->user()->unreadNotifications->markAsRead();
        }

        return back()->with('success', 'Notifications marked as read.');
    }

    public function open(Request $request, DatabaseNotification $notification): RedirectResponse
    {
        abort_unless(
            Schema::hasTable('notifications')
            && $notification->notifiable_type === $request->user()::class
            && (string) $notification->notifiable_id === (string) $request->user()->getKey(),
            404
        );

        if (is_null($notification->read_at)) {
            $notification->markAsRead();
        }

        $targetUrl = str_ends_with($notification->type, '\\MeterReadingNotification')
            ? route('billing.index')
            : ($notification->data['url'] ?? route('payments.index'));

        return redirect()->to($targetUrl);
    }
}
