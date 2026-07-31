<x-admin-main page="Attributes">
    <x-slot name="header">
        @vite(['resources/css/app.css', 'resources/css/styles.min.css', 'resources/css/myStyle.css', 'resources/js/app.js'])

        <style>

        </style>
    </x-slot>
    <!--  Body Wrapper -->
    <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full"
        data-sidebar-position="fixed" data-header-position="fixed">
        <x-admin-sidebar />
        <!--  Main wrapper -->
        <div class="body-wrapper">
            <x-admin-topbar />
            <div class="body-wrapper-inner">
                <div class="container-fluid">

                    <form action="{{ route('admin.settings.update') }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="row">

                            {{-- Store --}}
                            <div class="col-lg-6 mb-4">
                                <div class="card shadow-sm h-100">
                                    <div class="card-header">
                                        <h5 class="mb-0">Store Information</h5>
                                    </div>

                                    <div class="card-body">

                                        <div class="mb-3">
                                            <label class="form-label">Store Name</label>
                                            <input type="text" class="form-control" name="store_name"
                                                value="{{ old('store_name', $settings['store_name'] ?? '') }}">
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Store Email</label>
                                            <input type="email" class="form-control" name="store_email"
                                                value="{{ old('store_email', $settings['store_email'] ?? '') }}">
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Store Phone</label>
                                            <input type="text" class="form-control" name="store_phone"
                                                value="{{ old('store_phone', $settings['store_phone'] ?? '') }}">
                                        </div>

                                        <div>
                                            <label class="form-label">Store Address</label>
                                            <textarea class="form-control" rows="3" name="store_address">{{ old('store_address', $settings['store_address'] ?? '') }}</textarea>
                                        </div>

                                    </div>
                                </div>
                            </div>

                            {{-- Localization --}}
                            <div class="col-lg-6 mb-4">
                                <div class="card shadow-sm h-100">
                                    <div class="card-header">
                                        <h5 class="mb-0">Localization</h5>
                                    </div>

                                    <div class="card-body">

                                        <div class="mb-3">
                                            <label class="form-label">Default Language</label>
                                            <select class="form-select" name="default_locale">
                                                <option value="en" @selected(($settings['default_locale'] ?? '') == 'en')>English</option>
                                                <option value="ar" @selected(($settings['default_locale'] ?? '') == 'ar')>Arabic</option>
                                            </select>
                                        </div>

                                        <div>
                                            <label class="form-label">Timezone</label>
                                            <input type="text" class="form-control" name="timezone"
                                                value="{{ old('timezone', $settings['timezone'] ?? '') }}">
                                        </div>

                                    </div>
                                </div>
                            </div>

                            {{-- Currency --}}
                            <div class="col-lg-6 mb-4">
                                <div class="card shadow-sm h-100">
                                    <div class="card-header">
                                        <h5 class="mb-0">Currency</h5>
                                    </div>

                                    <div class="card-body">

                                        <label class="form-label">Default Currency</label>

                                        <select class="form-select" name="default_currency">
                                            <option value="USD" @selected(($settings['default_currency'] ?? '') == 'USD')>USD ($)</option>
                                            <option value="LBP" @selected(($settings['default_currency'] ?? '') == 'LBP')>L.L.</option>
                                        </select>

                                    </div>
                                </div>
                            </div>

                            {{-- Tax --}}
                            <div class="col-lg-6 mb-4">
                                <div class="card shadow-sm h-100">
                                    <div class="card-header">
                                        <h5 class="mb-0">Tax</h5>
                                    </div>

                                    <div class="card-body">

                                        <label class="form-label">Tax Mode</label>

                                        <select class="form-select" name="tax_mode">
                                            <option value="b2c" @selected(($settings['tax_mode'] ?? '') == 'b2c')>B2C</option>
                                            <option value="b2b" @selected(($settings['tax_mode'] ?? '') == 'b2b')>B2B</option>
                                        </select>

                                        <label class="form-label mt-3" for="default_tax_id">Default Tax</label>

                                        <select class="form-select" id="default_tax_id" name="default_tax_id">
                                            <option value="">No Default Tax</option>
                                            @foreach ($taxes as $tax)
                                                <option value="{{ $tax->id }}" @selected((string) old('default_tax_id', $settings['default_tax_id'] ?? '') === (string) $tax->id)>
                                                    {{ $tax->name }} ({{ rtrim(rtrim(number_format((float) $tax->rate, 4, '.', ''), '0'), '.') }}%)
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('default_tax_id')
                                            <div class="text-danger mt-1">{{ $message }}</div>
                                        @enderror

                                    </div>
                                </div>
                            </div>

                            {{-- Inventory --}}
                            <div class="col-12 mb-4">
                                <div class="card shadow-sm">
                                    <div class="card-header">
                                        <h5 class="mb-0">Inventory</h5>
                                    </div>

                                    <div class="card-body">

                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="manage_stock"
                                                name="manage_stock" value="1" @checked(($settings['manage_stock'] ?? 0) == 1)>

                                            <label class="form-check-label" for="manage_stock">
                                                Manage Stock
                                            </label>
                                        </div>

                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="allow_backorders"
                                                name="allow_backorders" value="1" @checked(($settings['allow_backorders'] ?? 0) == 1)>

                                            <label class="form-check-label" for="allow_backorders">
                                                Allow Backorders
                                            </label>
                                        </div>

                                    </div>
                                </div>
                            </div>

                            {{-- Checkout --}}
                            <div class="col-12 mb-4">
                                <div class="card shadow-sm">
                                    <div class="card-header">
                                        <h5 class="mb-0">Checkout</h5>
                                    </div>

                                    <div class="card-body">
                                        <input type="hidden" name="allow_guest_checkout" value="0">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input @error('allow_guest_checkout') is-invalid @enderror"
                                                type="checkbox" id="allow_guest_checkout" name="allow_guest_checkout"
                                                value="1" @checked(old('allow_guest_checkout', $settings['allow_guest_checkout'] ?? 1))>
                                            <label class="form-check-label" for="allow_guest_checkout">
                                                Allow Guest Checkout
                                            </label>
                                            @error('allow_guest_checkout')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Manual Payments --}}
                            <div class="col-12 mb-4">
                                <div class="card shadow-sm">
                                    <div class="card-header">
                                        <h5 class="mb-0">Manual Payment Instructions</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-4">
                                            <label class="form-label" for="manual_whatsapp_number">WhatsApp Number</label>
                                            <input class="form-control @error('manual_whatsapp_number') is-invalid @enderror"
                                                id="manual_whatsapp_number" name="manual_whatsapp_number" type="text"
                                                value="{{ old('manual_whatsapp_number', $settings['manual_whatsapp_number'] ?? '') }}"
                                                placeholder="Country code and number">
                                            @error('manual_whatsapp_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>

                                        <div class="row g-4">
                                            <div class="col-lg-6">
                                                <h6>Manual Wallet Transfer</h6>
                                                @foreach ([
                                                    'manual_wallet_title' => 'Title',
                                                    'manual_wallet_name' => 'Wallet Name',
                                                    'manual_wallet_number' => 'Wallet Number',
                                                ] as $key => $label)
                                                    <div class="mb-3">
                                                        <label class="form-label" for="{{ $key }}">{{ $label }}</label>
                                                        <input class="form-control @error($key) is-invalid @enderror"
                                                            id="{{ $key }}" name="{{ $key }}" type="text"
                                                            value="{{ old($key, $settings[$key] ?? '') }}">
                                                        @error($key)<div class="invalid-feedback">{{ $message }}</div>@enderror
                                                    </div>
                                                @endforeach
                                                <label class="form-label" for="manual_wallet_instructions">Additional Instructions</label>
                                                <textarea class="form-control @error('manual_wallet_instructions') is-invalid @enderror"
                                                    id="manual_wallet_instructions" name="manual_wallet_instructions" rows="4">{{ old('manual_wallet_instructions', $settings['manual_wallet_instructions'] ?? '') }}</textarea>
                                                @error('manual_wallet_instructions')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                            </div>

                                            <div class="col-lg-6">
                                                <h6>Manual Bank Transfer</h6>
                                                @foreach ([
                                                    'manual_bank_title' => 'Title',
                                                    'manual_bank_name' => 'Bank Name',
                                                    'manual_bank_account_name' => 'Account Name',
                                                    'manual_bank_account_number' => 'Account Number',
                                                    'manual_bank_iban' => 'IBAN',
                                                    'manual_bank_swift' => 'SWIFT',
                                                ] as $key => $label)
                                                    <div class="mb-3">
                                                        <label class="form-label" for="{{ $key }}">{{ $label }}</label>
                                                        <input class="form-control @error($key) is-invalid @enderror"
                                                            id="{{ $key }}" name="{{ $key }}" type="text"
                                                            value="{{ old($key, $settings[$key] ?? '') }}">
                                                        @error($key)<div class="invalid-feedback">{{ $message }}</div>@enderror
                                                    </div>
                                                @endforeach
                                                <label class="form-label" for="manual_bank_instructions">Additional Instructions</label>
                                                <textarea class="form-control @error('manual_bank_instructions') is-invalid @enderror"
                                                    id="manual_bank_instructions" name="manual_bank_instructions" rows="4">{{ old('manual_bank_instructions', $settings['manual_bank_instructions'] ?? '') }}</textarea>
                                                @error('manual_bank_instructions')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Notification Configuration --}}
                            <div class="col-12 mb-4">
                                <div class="card shadow-sm">
                                    <div class="card-header">
                                        <h5 class="mb-0">Notifications</h5>
                                    </div>
                                    <div class="card-body">
                                        <p class="text-muted">
                                            These rules configure future delivery channels. No notifications are sent in this version.
                                        </p>

                                        @foreach ($notificationEvents->groupBy('category') as $category => $events)
                                            <h6 class="text-uppercase mt-4">{{ str_replace('_', ' ', $category) }}</h6>
                                            <div class="table-responsive mb-3">
                                                <table class="table table-bordered align-middle mb-0">
                                                    <thead>
                                                        <tr>
                                                            <th>Event</th>
                                                            <th>Audience</th>
                                                            <th>Channels</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach ($events as $notificationEvent)
                                                            @foreach ($notificationEvent->rules->groupBy('notification_audience_id') as $audienceRules)
                                                                <tr>
                                                                    <td>{{ $notificationEvent->name }}</td>
                                                                    <td>{{ $audienceRules->first()->audience->name }}</td>
                                                                    <td>
                                                                        <div class="d-flex flex-wrap gap-3">
                                                                            @foreach ($audienceRules as $notificationRule)
                                                                                <div class="form-check form-switch">
                                                                                    <input class="form-check-input" type="checkbox"
                                                                                        id="notification-rule-{{ $notificationRule->id }}"
                                                                                        name="notification_rules[]"
                                                                                        value="{{ $notificationRule->id }}"
                                                                                        @checked(in_array($notificationRule->id, old('notification_rules', $notificationEvent->rules->where('is_enabled', true)->pluck('id')->all())))>
                                                                                    <label class="form-check-label" for="notification-rule-{{ $notificationRule->id }}">
                                                                                        {{ $notificationRule->channel->name }}
                                                                                    </label>
                                                                                </div>
                                                                            @endforeach
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            @endforeach
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        @endforeach
                                        @error('notification_rules.*')
                                            <div class="text-danger mt-2">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                        </div>

                        <div class="text-end">
                            <button class="btn btn-primary">
                                Save Settings
                            </button>
                        </div>

                    </form>

                </div>
            </div>
        </div>
    </div>
</x-admin-main>
