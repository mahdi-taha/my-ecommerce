<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateNotificationConfigurationRequest;
use App\Models\Setting;
use App\Models\Tax;
use App\Services\NotificationConfigurationService;
use Illuminate\Support\Facades\Cache;

class SettingsController extends Controller
{
    public function __construct(private NotificationConfigurationService $notifications) {}

    public function index()
    {
        $settings = Setting::pluck('value', 'key');
        $taxes = Tax::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'rate']);
        $notificationEvents = $this->notifications->administrationMatrix();

        return view('admin.settings.index', compact('settings', 'taxes', 'notificationEvents'));
    }

    public function update(UpdateNotificationConfigurationRequest $request, Setting $setting)
    {
        $validated = $request->validated();
        $enabledNotificationRules = $validated['notification_rules'];
        unset($validated['notification_rules']);

        $validated['manage_stock'] = $request->boolean('manage_stock');
        $validated['allow_backorders'] = $request->boolean('allow_backorders');
        $validated['allow_guest_checkout'] = $request->boolean('allow_guest_checkout');

        foreach ($validated as $key => $value) {
            $setting = Setting::where('key', $key)->first();

            if ($setting) {
                $setting->update([
                    'value' => $value === null ? null : (string) $value,
                ]);

                Cache::forget("setting.{$setting->group}.{$setting->key}");
            }
        }

        $this->notifications->updateEnabledRules($enabledNotificationRules);

        return back()->with('success', 'Settings updated successfully.');
    }
}
