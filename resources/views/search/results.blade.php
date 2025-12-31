<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Search: {{ $query }} - {{ config('app.name', 'Laravel') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-gray-100 dark:bg-gray-900 min-h-screen">
        <div class="max-w-6xl mx-auto px-4 py-8">
            <a href="/" class="inline-flex items-center text-blue-600 dark:text-blue-400 hover:underline mb-6">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
                Back to Search
            </a>

            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-gray-100 mb-2">
                Search Results
            </h1>
            <p class="text-gray-600 dark:text-gray-400 mb-8">
                @if($artists->count() + $albums->count() > 0)
                    Showing results for "<span class="font-medium">{{ $query }}</span>"
                @else
                    No results found for "<span class="font-medium">{{ $query }}</span>"
                @endif
            </p>

            @if($artists->count() > 0)
                <div class="mb-10">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                        Artists
                        <span class="text-gray-400 dark:text-gray-500 font-normal">({{ $artists->count() }})</span>
                    </h2>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
                        @foreach($artists as $artist)
                            <a href="{{ route('artist.show', $artist) }}" class="group">
                                <div class="aspect-square bg-gray-200 dark:bg-gray-700 rounded-lg overflow-hidden mb-2">
                                    @if($artist->image_commons)
                                        <img src="https://commons.wikimedia.org/wiki/Special:FilePath/{{ rawurlencode($artist->image_commons) }}?width=300"
                                             alt="{{ $artist->name }}"
                                             loading="lazy"
                                             class="w-full h-full object-cover group-hover:scale-105 transition-transform">
                                    @else
                                        <div class="w-full h-full flex items-center justify-center">
                                            <span class="text-4xl">🎤</span>
                                        </div>
                                    @endif
                                </div>
                                <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate group-hover:text-blue-600 dark:group-hover:text-blue-400">
                                    {{ $artist->name }}
                                </h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Artist
                                </p>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($albums->count() > 0)
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                        Albums
                        <span class="text-gray-400 dark:text-gray-500 font-normal">({{ $albums->count() }})</span>
                    </h2>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
                        @foreach($albums as $album)
                            <a href="{{ route('album.show', $album) }}" class="group">
                                <div class="aspect-square bg-gray-200 dark:bg-gray-700 rounded-lg overflow-hidden mb-2">
                                    @if($album->cover_image_url)
                                        <img src="{{ $album->cover_image_url }}"
                                             alt="{{ $album->title }}"
                                             loading="lazy"
                                             class="w-full h-full object-cover group-hover:scale-105 transition-transform">
                                    @else
                                        <div class="w-full h-full flex items-center justify-center">
                                            <span class="text-4xl">💿</span>
                                        </div>
                                    @endif
                                </div>
                                <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate group-hover:text-blue-600 dark:group-hover:text-blue-400">
                                    {{ $album->title }}
                                </h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                    @if($album->artist)
                                        {{ $album->artist->name }}
                                    @endif
                                    @if($album->release_year)
                                        · {{ $album->release_year }}
                                    @endif
                                </p>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($artists->count() === 0 && $albums->count() === 0 && strlen($query) >= 2)
                <div class="text-center py-12">
                    <div class="text-6xl mb-4">🔍</div>
                    <p class="text-gray-600 dark:text-gray-400">
                        Try a different search term
                    </p>
                </div>
            @endif
        </div>
    </body>
</html>
