<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\Tax;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = Setting::pluck('value', 'key');
        $taxes = Tax::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'rate']);

        return view('admin.settings.index', compact('settings', 'taxes'));
    }

    public function update(Request $request, Setting $setting)
    {
        $validated = $request->validate([
            'store_name' => 'required|string|max:255',
            'store_email' => 'nullable|email|max:255',
            'store_phone' => 'nullable|string|max:50',
            'store_address' => 'nullable|string',
            'default_locale' => 'required|in:en,ar',
            'timezone' => 'required|string|max:100',
            'default_currency' => 'required|in:USD,LBP',
            'tax_mode' => 'required|in:b2b,b2c',
            'default_tax_id' => [
                'nullable',
                Rule::exists('taxes', 'id')->where(fn ($query) => $query->where('status', true)),
            ],
            'manage_stock' => 'nullable|boolean',
            'allow_backorders' => 'nullable|boolean',
            'allow_guest_checkout' => 'nullable|boolean',
        ]);

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

        return back()->with('success', 'Settings updated successfully.');
    }
}
