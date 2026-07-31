<?php

namespace App\Http\Controllers\Shop\Account;

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
        $notifications = $request->user('customer')
            ->databaseNotifications()
            ->where('audience_code', NotificationAudienceCode::Customer->value)
            ->latest('created_at')
            ->latest('id')
            ->paginate(15);

        return view('customer.account.notifications.index', compact('notifications'));
    }

    public function markAsRead(Request $request, DatabaseNotification $databaseNotification): RedirectResponse
    {
        abort_unless(
            (int) $databaseNotification->user_id === (int) $request->user('customer')->getKey()
                && $databaseNotification->audience_code === NotificationAudienceCode::Customer->value,
            404
        );

        if ($databaseNotification->read_at === null) {
            $databaseNotification->update(['read_at' => now()]);
        }

        return back()->with('success', __('shop.notifications.marked_read'));
    }
}
