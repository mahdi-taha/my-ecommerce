<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateNotificationConfigurationRequest;
use App\Models\Setting;
use App\Models\Tax;
use App\Services\NotificationConfigurationService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class SettingsController extends Controller
{
    private const SETTING_FIELDS = [
        'store_name' => ['store', 'store_name'],
        'store_email' => ['store', 'store_email'],
        'store_phone' => ['store', 'store_phone'],
        'store_address' => ['store', 'store_address'],
        'store_logo_path' => ['store', 'store_logo_path'],
        'facebook_url' => ['store', 'facebook_url'],
        'whatsapp_url' => ['store', 'whatsapp_url'],
        'instagram_url' => ['store', 'instagram_url'],
        'default_locale' => ['localization', 'default_locale'],
        'tax_mode' => ['tax', 'tax_mode'],
        'default_tax_id' => ['tax', 'default_tax_id'],
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

    private const SETUP_SETTING_FIELDS = [
        'timezone' => ['localization', 'timezone'],
        'default_currency' => ['currency', 'default_currency'],
    ];

    public function __construct(
        private NotificationConfigurationService $notifications,
        private DatabaseManager $database,
    ) {}

    public function index(): View
    {
        $displayFields = self::SETTING_FIELDS + self::SETUP_SETTING_FIELDS;
        $settingsByIdentity = Setting::query()
            ->where(function (Builder $query) use ($displayFields): void {
                foreach ($displayFields as [$group, $key]) {
                    $query->orWhere(fn (Builder $pair): Builder => $pair
                        ->where('group', $group)
                        ->where('key', $key));
                }
            })
            ->get(['group', 'key', 'value'])
            ->keyBy(fn (Setting $setting): string => "{$setting->group}.{$setting->key}");
        $settings = collect($displayFields)->mapWithKeys(
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
        $logo = $request->file('store_logo');
        unset($validated['notification_rules'], $validated['store_logo']);

        $validated['allow_guest_checkout'] = $request->boolean('allow_guest_checkout');
        $newLogoPath = $logo?->store('store', 'public');
        $oldLogoPath = null;

        try {
            $this->database->transaction(function () use (
                $validated,
                $enabledNotificationRules,
                $newLogoPath,
                &$oldLogoPath,
            ): void {
                foreach ($validated as $field => $value) {
                    [$group, $key] = self::SETTING_FIELDS[$field];
                    $this->updateSetting($group, $key, $value);
                }

                if ($newLogoPath !== null) {
                    $logoSetting = Setting::query()
                        ->where('group', 'store')
                        ->where('key', 'store_logo_path')
                        ->lockForUpdate()
                        ->firstOrFail();
                    $oldLogoPath = $logoSetting->value;
                    $this->updateSetting('store', 'store_logo_path', $newLogoPath, $logoSetting);
                }

                $this->notifications->updateEnabledRules($enabledNotificationRules);
            });
        } catch (Throwable $exception) {
            if ($newLogoPath !== null) {
                Storage::disk('public')->delete($newLogoPath);
            }

            throw $exception;
        }

        if ($newLogoPath !== null && filled($oldLogoPath) && $oldLogoPath !== $newLogoPath) {
            Storage::disk('public')->delete($oldLogoPath);
        }

        return back()->with('success', 'Settings updated successfully.');
    }

    private function updateSetting(string $group, string $key, mixed $value, ?Setting $setting = null): void
    {
        $setting ??= Setting::query()
            ->where('group', $group)
            ->where('key', $key)
            ->first();
        $storedValue = $value === null ? null : (string) $value;

        if ($setting && $setting->value !== $storedValue) {
            $setting->update(['value' => $storedValue]);
            Cache::forget("setting.{$group}.{$key}");
        }
    }
}
