<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        {{-- Page Title --}}
        <title>{{ $title ?? config('app.name', 'Spinsearch') }}</title>

        {{-- Meta Description --}}
        @if($description ?? false)
            <meta name="description" content="{{ $description }}">
        @else
            <meta name="description" content="Spinsearch is a music encyclopedia for the curious listener. Explore complete discographies, discover artist histories, and navigate connections between albums, genres, and eras.">
        @endif

        {{-- Robots Directive --}}
        <meta name="robots" content="{{ $robots ?? 'index, follow' }}">

        {{-- Canonical URL --}}
        <link rel="canonical" href="{{ $canonical ?? \App\Services\SeoService::canonicalUrl() }}">

        {{-- OpenGraph Tags --}}
        <meta property="og:type" content="{{ $ogType ?? 'website' }}">
        <meta property="og:site_name" content="{{ config('app.name', 'Spinsearch') }}">
        <meta property="og:title" content="{{ $title ?? config('app.name', 'Spinsearch') }}">
        @if($description ?? false)
            <meta property="og:description" content="{{ $description }}">
        @else
            <meta property="og:description" content="Spinsearch is a music encyclopedia for the curious listener. Explore complete discographies, discover artist histories, and navigate connections between albums, genres, and eras.">
        @endif
        <meta property="og:url" content="{{ $canonical ?? \App\Services\SeoService::canonicalUrl() }}">
        <meta property="og:image" content="{{ $ogImage ?? \App\Services\SeoService::defaultOgImage() }}">

        {{-- Twitter Card Tags --}}
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $title ?? config('app.name', 'Spinsearch') }}">
        @if($description ?? false)
            <meta name="twitter:description" content="{{ $description }}">
        @else
            <meta name="twitter:description" content="Spinsearch is a music encyclopedia for the curious listener. Explore complete discographies, discover artist histories, and navigate connections between albums, genres, and eras.">
        @endif
        <meta name="twitter:image" content="{{ $ogImage ?? \App\Services\SeoService::defaultOgImage() }}">

        {{-- JSON-LD Structured Data --}}
        @if($jsonLd ?? false)
            <script type="application/ld+json">
                {!! \App\Services\SeoService::encodeJsonLd($jsonLd) !!}
            </script>
        @endif
        @stack('jsonld')

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-gray-950 text-gray-100">
        <div class="min-h-screen flex flex-col bg-gradient-to-b from-gray-950 via-gray-900 to-gray-950">
            <x-site-header :transparent="$transparentHeader ?? false" />

            <!-- Page Heading -->
            @isset($header)
                <header class="bg-gray-900/80 border-b border-gray-800 shadow-lg backdrop-blur">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 text-gray-100">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main class="flex-1">
                {{ $slot }}
            </main>

            <x-site-footer :showRecentReviews="$showRecentReviews ?? true" />
        </div>
    </body>
</html>
