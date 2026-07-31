<?php

namespace App\Http\Controllers\Admin;

use App\Enums\NotificationAudienceCode;
use App\Http\Controllers\Controller;
use App\Models\DatabaseNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $notifications = $request->user('admin')
            ->databaseNotifications()
            ->where('audience_code', NotificationAudienceCode::Administrator->value)
            ->latest('created_at')
            ->latest('id')
            ->paginate(15);

        return view('admin.notifications.index', compact('notifications'));
    }

    public function markAsRead(Request $request, DatabaseNotification $databaseNotification): RedirectResponse
    {
        abort_unless(
            (int) $databaseNotification->user_id === (int) $request->user('admin')->getKey()
                && $databaseNotification->audience_code === NotificationAudienceCode::Administrator->value,
            404
        );

        if ($databaseNotification->read_at === null) {
            $databaseNotification->update(['read_at' => now()]);
        }

        return back()->with('success', 'Notification marked as read.');
    }
}
