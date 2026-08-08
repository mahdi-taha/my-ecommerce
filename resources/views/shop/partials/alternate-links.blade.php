@foreach ($links as $link)
    <link rel="alternate" hreflang="{{ $link['hreflang'] }}" href="{{ $link['href'] }}">
@endforeach
