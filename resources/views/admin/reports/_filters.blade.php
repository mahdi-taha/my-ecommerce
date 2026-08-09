<form method="GET" class="card card-body mb-4" data-report-filters>
    <div class="row g-3">
        @if (in_array('date', $availableFilters, true))
            <div class="col-md-3">
                <label class="form-label" for="report-date-from">From</label>
                <input class="form-control" id="report-date-from" type="date" name="date_from" value="{{ request('date_from') }}">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="report-date-to">To</label>
                <input class="form-control" id="report-date-to" type="date" name="date_to" value="{{ request('date_to') }}">
            </div>
        @endif

        @foreach ([
            'customer_id' => ['Customer', 'Search Customers'],
            'product_id' => ['Product', 'Search Products'],
            'category_id' => ['Category', 'Search Categories'],
            'administrator_id' => ['Administrator', 'Search Administrators'],
        ] as $field => [$label, $placeholder])
            @if (in_array($field, $availableFilters, true))
                <div class="col-md-3">
                    <label class="form-label" for="report-{{ str($field)->replace('_', '-') }}">{{ $label }}</label>
                    <select class="form-select report-entity-select" id="report-{{ str($field)->replace('_', '-') }}"
                        name="{{ $field }}" data-lookup-url="{{ $filterOptions['lookup_urls'][$field] }}"
                        data-placeholder="{{ $placeholder }}">
                        @if ($selected = $filterOptions['selected'][$field] ?? null)
                            <option value="{{ $selected['id'] }}" selected>{{ $selected['text'] }}</option>
                        @endif
                    </select>
                </div>
            @endif
        @endforeach

        @foreach ([
            'order_status' => ['Order Status', 'order_statuses'],
            'payment_status' => ['Payment Status', 'payment_statuses'],
            'fulfillment_status' => ['Fulfillment Status', 'fulfillment_statuses'],
            'shipping_treatment' => ['Shipping Treatment', 'shipping_treatments'],
        ] as $field => [$label, $optionKey])
            @if (in_array($field, $availableFilters, true))
                <div class="col-md-3">
                    <label class="form-label" for="report-{{ str($field)->replace('_', '-') }}">{{ $label }}</label>
                    <select class="form-select" id="report-{{ str($field)->replace('_', '-') }}" name="{{ $field }}">
                        <option value="">All</option>
                        @foreach ($filterOptions[$optionKey] as $value => $optionLabel)
                            <option value="{{ $value }}" @selected(request($field) === $value)>{{ $optionLabel }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
        @endforeach

        @if (in_array('payment_method', $availableFilters, true))
            <div class="col-md-3">
                <label class="form-label" for="report-payment-method">Payment Method</label>
                <select class="form-select" id="report-payment-method" name="payment_method">
                    <option value="">All</option>
                    @foreach ($filterOptions['payment_methods'] as $value => $optionLabel)
                        <option value="{{ $value }}" @selected(request('payment_method') === $value)>{{ $optionLabel }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <div class="col-md-2">
            <label class="form-label" for="report-per-page">Rows per page</label>
            <select class="form-select" id="report-per-page" name="per_page">
                @foreach ([25, 50, 100] as $size)
                    <option value="{{ $size }}" @selected((int) request('per_page', 25) === $size)>{{ $size }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 d-flex flex-wrap gap-2">
            <button class="btn btn-primary" type="submit">Apply Filters</button>
            <a class="btn btn-outline-secondary" href="{{ route('admin.reports.show', ['report' => $reportName]) }}">Clear Filters</a>
        </div>
    </div>
</form>
