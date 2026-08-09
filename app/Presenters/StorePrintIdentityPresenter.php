<?php

namespace App\Presenters;

use Illuminate\Support\Facades\Storage;

class StorePrintIdentityPresenter
{
    /** @return array{name: string, email: string, phone: string, address: string, logo_url: ?string} */
    public function present(): array
    {
        $logoPath = trim((string) setting('store.store_logo_path', ''));

        return [
            'name' => (string) setting('store.store_name', config('app.name')),
            'email' => trim((string) setting('store.store_email', '')),
            'phone' => trim((string) setting('store.store_phone', '')),
            'address' => trim((string) setting('store.store_address', '')),
            'logo_url' => $logoPath !== '' && Storage::disk('public')->exists($logoPath)
                ? Storage::disk('public')->url($logoPath)
                : null,
        ];
    }
}
