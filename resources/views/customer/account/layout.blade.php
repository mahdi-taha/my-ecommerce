@extends('shop.layouts.app')

@section('content')
    <div class="container-fluid py-5">
        <div class="container py-4">
            <div class="row g-4">
                <aside class="col-12 col-lg-3">
                    @include('customer.account._navigation')
                </aside>
                <div class="col-12 col-lg-9">
                    @yield('account-content')
                </div>
            </div>
        </div>
    </div>
@endsection
