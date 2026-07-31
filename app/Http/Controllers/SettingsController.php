<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateNotificationConfigurationRequest;
use App\Models\Setting;
use App\Models\Tax;
use App\Services\NotificationConfigurationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class SettingsController extends Controller
{
    private const SETTING_FIELDS = [
        'store_name' => ['store', 'store_name'],
        'store_email' => ['store', 'store_email'],
        'store_phone' => ['store', 'store_phone'],
        'store_address' => ['store', 'store_address'],
        'default_locale' => ['localization', 'default_locale'],
        'timezone' => ['localization', 'timezone'],
        'default_currency' => ['currency', 'default_currency'],
        'tax_mode' => ['tax', 'tax_mode'],
        'default_tax_id' => ['tax', 'default_tax_id'],
        'manage_stock' => ['inventory', 'manage_stock'],
        'allow_backorders' => ['inventory', 'allow_backorders'],
        'allow_guest_checkout' => ['checkout', 'allow_guest_checkout'],
        'manual_whatsapp_number' => ['payments', 'manual_whatsapp_number'],
        'manual_wallet_title' => ['payments', 'manual_wallet_title'],
        'manual_wallet_name' => ['payments', 'manual_wallet_name'],
        'manual_wallet_number' => ['payments', 'manual_wallet_number'],
        'manual_wallet_instructions' => ['payments', 'manual_wallet_instructions'],
        'manual_bank_title' => ['payments', 'manual_bank_title'],
        'manual_bank_name' => ['payments', 'manual_bank_name'],
        'manual_bank_account_name' => ['payments', 'manual_bank_account_name'],
        'manual_bank_account_number' => ['payments', 'manual_bank_account_number'],
        'manual_bank_iban' => ['payments', 'manual_bank_iban'],
        'manual_bank_swift' => ['payments', 'manual_bank_swift'],
        'manual_bank_instructions' => ['payments', 'manual_bank_instructions'],
    ];

    public function __construct(private NotificationConfigurationService $notifications) {}

    public function index(): View
    {
        $settingsByIdentity = Setting::query()
            ->where(function (Builder $query): void {
                foreach (self::SETTING_FIELDS as [$group, $key]) {
                    $query->orWhere(fn (Builder $pair): Builder => $pair
                        ->where('group', $group)
                        ->where('key', $key));
                }
            })
            ->get(['group', 'key', 'value'])
            ->keyBy(fn (Setting $setting): string => "{$setting->group}.{$setting->key}");
        $settings = collect(self::SETTING_FIELDS)->mapWithKeys(
            fn (array $identity, string $field): array => [
                $field => $settingsByIdentity->get(implode('.', $identity))?->value,
            ]
        );
        $taxes = Tax::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'rate']);
        $notificationEvents = $this->notifications->administrationMatrix();

        return view('admin.settings.index', compact('settings', 'taxes', 'notificationEvents'));
    }

    public function update(UpdateNotificationConfigurationRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $enabledNotificationRules = $validated['notification_rules'];
        unset($validated['notification_rules']);

        $validated['manage_stock'] = $request->boolean('manage_stock');
        $validated['allow_backorders'] = $request->boolean('allow_backorders');
        $validated['allow_guest_checkout'] = $request->boolean('allow_guest_checkout');

        foreach ($validated as $field => $value) {
            [$group, $key] = self::SETTING_FIELDS[$field];
            $setting = Setting::query()
                ->where('group', $group)
                ->where('key', $key)
                ->first();
            $storedValue = $value === null ? null : (string) $value;

            if ($setting && $setting->value !== $storedValue) {
                $setting->update([
                    'value' => $storedValue,
                ]);

                Cache::forget("setting.{$group}.{$key}");
            }
        }

        $this->notifications->updateEnabledRules($enabledNotificationRules);

        return back()->with('success', 'Settings updated successfully.');
    }
}
