@extends('shop.layouts.app')
@section('title',$translation->meta_title?:$translation->title)
@if (filled($translation->meta_description))
@section('meta_description', trim($translation->meta_description))
@endif
@section('canonical', route('shop.pages.show',['slug' => $translation->slug]))
@section('open_graph', 'enabled')
@section('content')<div class="container-fluid py-5"><div class="container"><article class="card shadow-sm"><div class="card-body p-4 p-lg-5"><h1 class="mb-4">{{ $translation->title }}</h1><div class="cms-page-body">{!! nl2br(e($translation->body)) !!}</div>@if($page->code==='contact')<hr><address><div>{{ setting('store.store_address','') }}</div><div><a href="mailto:{{ setting('store.store_email','') }}">{{ setting('store.store_email','') }}</a></div><div><a href="tel:{{ setting('store.store_phone','') }}">{{ setting('store.store_phone','') }}</a></div></address>@endif</div></article></div></div>@endsection
