@if ($manualPayment)
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <h2 class="h5 mb-3">{{ __('shop.payment_instructions.heading') }}</h2>

            @if ($manualPayment['state'] === 'paid')
                <div class="alert alert-success mb-0" role="status">
                    <i class="bi bi-check-circle-fill me-1"></i>
                    {{ __('shop.payment_instructions.payment_received') }}
                </div>
            @else
                @if ($manualPayment['title'])
                    <h3 class="h6">{{ $manualPayment['title'] }}</h3>
                @endif

                <dl class="row g-2 mb-3">
                    @foreach ($manualPayment['fields'] as $field)
                        <dt class="col-sm-4">{{ __('shop.payment_instructions.'.$field['label']) }}</dt>
                        <dd class="col-sm-8">{{ $field['value'] }}</dd>
                    @endforeach
                    <dt class="col-sm-4">{{ __('shop.payment_instructions.amount_to_pay') }}</dt>
                    <dd class="col-sm-8 fw-bold">{{ $manualPayment['amount'] }}</dd>
                </dl>

                @if ($manualPayment['whatsapp_url'])
                    <a class="btn btn-success"
                        href="{{ $manualPayment['whatsapp_url'] }}"
                        target="_blank"
                        rel="noopener noreferrer">
                        <i class="bi bi-whatsapp me-1"></i>
                        {{ __('shop.payment_instructions.send_proof') }}
                    </a>
                @else
                    <p class="text-muted mb-0">{{ __('shop.payment_instructions.whatsapp_unavailable') }}</p>
                @endif
            @endif
        </div>
    </div>
@endif
