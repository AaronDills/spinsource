<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $artist->name }} - {{ config('app.name', 'Spinsource') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 dark:bg-gray-900 min-h-screen">
<div class="max-w-4xl mx-auto px-4 py-8">
    <a href="/" class="inline-flex items-center text-blue-600 dark:text-blue-400 hover:underline mb-6">
        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
        </svg>
        Back to Search
    </a>

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden p-6 sm:p-8">
        <div class="flex items-start gap-6">
            @if($artist->image_commons)
                <img
                    class="w-32 h-32 rounded object-cover"
                    src="https://commons.wikimedia.org/wiki/Special:FilePath/{{ urlencode($artist->image_commons) }}?width=400"
                    alt="{{ $artist->name }}"
                />
            @endif

            <div class="flex-1 min-w-0">
                <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-gray-100">{{ $artist->name }}</h1>

                @if($artist->description)
                    <p class="mt-2 text-gray-700 dark:text-gray-300">{{ $artist->description }}</p>
                @endif

                <div class="mt-4 flex flex-wrap gap-2 text-sm">
                    @if($artist->wikidata_qid)
                        <a
                            class="inline-flex items-center px-3 py-1 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600"
                            href="https://www.wikidata.org/wiki/{{ $artist->wikidata_qid }}"
                            target="_blank"
                            rel="noreferrer"
                        >
                            Wikidata
                        </a>
                    @endif

                    @if($artist->wikipedia_url)
                        <a
                            class="inline-flex items-center px-3 py-1 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600"
                            href="{{ $artist->wikipedia_url }}"
                            target="_blank"
                            rel="noreferrer"
                        >
                            Wikipedia
                        </a>
                    @endif

                    @if($artist->official_website)
                        <a
                            class="inline-flex items-center px-3 py-1 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600"
                            href="{{ $artist->official_website }}"
                            target="_blank"
                            rel="noreferrer"
                        >
                            Website
                        </a>
                    @endif

                    @foreach($deduplicatedLinks as $link)
                        <a
                            class="inline-flex items-center px-3 py-1 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600"
                            href="{{ $link->url }}"
                            target="_blank"
                            rel="noreferrer"
                        >
                            @switch($link->type)
                                @case('twitter')
                                    Twitter
                                    @break
                                @case('instagram')
                                    Instagram
                                    @break
                                @case('facebook')
                                    Facebook
                                    @break
                                @case('youtube')
                                    YouTube
                                    @break
                                @case('spotify')
                                    Spotify
                                    @break
                                @case('apple_music')
                                    Apple Music
                                    @break
                                @case('bandcamp')
                                    Bandcamp
                                    @break
                                @case('soundcloud')
                                    SoundCloud
                                    @break
                                @case('deezer')
                                    Deezer
                                    @break
                                @case('reddit')
                                    Reddit
                                    @break
                                @default
                                    {{ ucfirst($link->type) }}
                            @endswitch
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        @if(count($albumsByType))
            <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
                @foreach($albumsByType as $group)
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 @if(!$loop->first) mt-8 @endif mb-4">{{ $group['label'] }}</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($group['albums'] as $album)
                            <a href="{{ route('album.show', $album) }}" class="block bg-gray-50 dark:bg-gray-700 rounded-lg p-4 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                                <div class="font-semibold text-gray-900 dark:text-gray-100">{{ $album->title }}</div>
                                @if($album->release_year)
                                    <div class="text-sm text-gray-600 dark:text-gray-400">{{ $album->release_year }}</div>
                                @endif
                            </a>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
</body>
</html>
