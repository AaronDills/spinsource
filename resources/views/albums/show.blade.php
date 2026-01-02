<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $album->title }} - {{ config('app.name', 'Laravel') }}</title>
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

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden">
                <div class="p-6 sm:p-8">
                    <div class="flex flex-col sm:flex-row items-center sm:items-start gap-4 sm:gap-6">
                        @if($album->cover_image_url)
                            <img src="{{ $album->cover_image_url }}"
                                 alt="{{ $album->title }} cover"
                                 class="flex-shrink-0 w-28 h-28 sm:w-32 sm:h-32 object-cover rounded-lg bg-gray-200 dark:bg-gray-700">
                        @else
                            <div class="flex-shrink-0 w-28 h-28 sm:w-32 sm:h-32 bg-gray-200 dark:bg-gray-700 rounded-lg flex items-center justify-center">
                                <span class="text-5xl sm:text-6xl">💿</span>
                            </div>
                        @endif
                        <div class="flex-1 min-w-0 text-center sm:text-left">
                            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-gray-100 mb-2">
                                {{ $album->title }}
                            </h1>
                            @if($album->artist)
                                <p class="text-xl text-gray-600 dark:text-gray-400 mb-3">
                                    {{ $album->artist->name }}
                                </p>
                            @endif
                            <div class="flex flex-wrap justify-center sm:justify-start gap-2">
                                @if($album->album_type)
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200">
                                        {{ ucfirst($album->album_type) }}
                                    </span>
                                @endif
                                @if($album->release_year)
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200">
                                        {{ $album->release_year }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>

                    @if($album->description)
                        <div class="mt-8">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-3">About</h2>
                            <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                                {{ $album->description }}
                            </p>
                        </div>
                    @endif

                    <div class="mt-8 grid grid-cols-1 sm:grid-cols-2 gap-6">
                        @if($album->release_date)
                            <div>
                                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Release Date</h3>
                                <p class="mt-1 text-gray-900 dark:text-gray-100">
                                    {{ $album->release_date->format('F j, Y') }}
                                </p>
                            </div>
                        @endif

                        @if($album->artist?->country)
                            <div>
                                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Artist Origin</h3>
                                <p class="mt-1 text-gray-900 dark:text-gray-100">
                                    {{ $album->artist->country->name }}
                                </p>
                            </div>
                        @endif
                    </div>

                    @if($album->tracks->count() > 0)
                        <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Tracklist</h2>
                            @php
                                $tracksByDisc = $album->tracks->groupBy('disc_number');
                                $hasMultipleDiscs = $tracksByDisc->count() > 1;
                            @endphp

                            @foreach($tracksByDisc as $discNumber => $tracks)
                                @if($hasMultipleDiscs)
                                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2 @if(!$loop->first) mt-6 @endif">
                                        Disc {{ $discNumber }}
                                    </h3>
                                @endif
                                <ol class="divide-y divide-gray-100 dark:divide-gray-700">
                                    @foreach($tracks as $track)
                                        <li class="flex items-center py-2 text-sm">
                                            <span class="w-8 text-gray-400 dark:text-gray-500 tabular-nums">{{ $track->position }}</span>
                                            <span class="flex-1 text-gray-900 dark:text-gray-100 truncate">{{ $track->title }}</span>
                                            @if($track->formatted_length)
                                                <span class="ml-4 text-gray-400 dark:text-gray-500 tabular-nums">{{ $track->formatted_length }}</span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ol>
                            @endforeach
                        </div>
                    @endif

                    @if($album->wikipedia_url || $album->spotify_album_id || $album->apple_music_album_id)
                        <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Listen & Learn More</h2>
                            <div class="flex flex-wrap gap-3">
                                @if($album->spotify_album_id)
                                    <a href="https://open.spotify.com/album/{{ $album->spotify_album_id }}"
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       class="inline-flex items-center px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg transition-colors">
                                        <svg class="w-5 h-5 mr-2" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.419 1.56-.299.421-1.02.599-1.559.3z"/>
                                        </svg>
                                        Spotify
                                    </a>
                                @endif
                                @if($album->apple_music_album_id)
                                    <a href="https://music.apple.com/album/{{ $album->apple_music_album_id }}"
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       class="inline-flex items-center px-4 py-2 bg-pink-500 hover:bg-pink-600 text-white rounded-lg transition-colors">
                                        <svg class="w-5 h-5 mr-2" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M23.994 6.124a9.23 9.23 0 00-.24-2.19c-.317-1.31-1.062-2.31-2.18-3.043a5.022 5.022 0 00-1.877-.726 10.496 10.496 0 00-1.564-.15c-.04-.003-.083-.01-.124-.013H5.986c-.152.01-.303.017-.455.026-.747.043-1.49.123-2.193.4-1.336.53-2.3 1.452-2.865 2.78-.192.448-.292.925-.363 1.408-.056.392-.088.785-.1 1.18 0 .032-.007.062-.01.093v12.223c.01.14.017.283.027.424.05.815.154 1.624.497 2.373.65 1.42 1.738 2.353 3.234 2.801.42.127.856.187 1.293.228.555.053 1.11.06 1.667.06h11.03a12.5 12.5 0 001.57-.1c.822-.106 1.596-.35 2.295-.81a5.046 5.046 0 001.88-2.207c.186-.42.293-.87.37-1.324.113-.675.138-1.358.137-2.04-.002-3.8 0-7.595-.003-11.393zm-6.423 3.99v5.712c0 .417-.058.827-.244 1.206-.29.59-.76.962-1.388 1.14-.35.1-.706.157-1.07.173-.95.042-1.8-.6-1.965-1.46-.18-.944.39-1.89 1.31-2.166.306-.09.62-.16.93-.242.38-.1.736-.243.96-.595.135-.21.193-.453.2-.707.003-.06.004-.12.004-.18l-.002-4.67c0-.3-.12-.46-.41-.52-.36-.073-.723-.13-1.085-.198-1.05-.195-2.1-.39-3.148-.586-.115-.022-.23-.04-.348-.046-.242-.014-.398.14-.42.376-.002.023-.003.046-.003.07v6.72c0 .442-.044.878-.236 1.284-.29.61-.762 1.002-1.406 1.19-.322.094-.65.153-.986.17-.964.046-1.813-.593-1.984-1.456-.183-.923.378-1.854 1.295-2.142.306-.097.62-.163.932-.244.376-.1.725-.24.947-.584.14-.217.2-.46.208-.716v-8.677c0-.18.04-.34.147-.483.107-.142.256-.226.43-.263.094-.02.19-.034.287-.048.72-.105 1.44-.21 2.162-.314l3.14-.456 1.903-.277c.147-.02.295-.04.444-.05.267-.02.424.123.453.383.008.07.01.14.01.21v5.25z"/>
                                        </svg>
                                        Apple Music
                                    </a>
                                @endif
                                @if($album->wikipedia_url)
                                    <a href="{{ $album->wikipedia_url }}"
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       class="inline-flex items-center px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition-colors">
                                        <svg class="w-5 h-5 mr-2" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M12.09 13.119c-.936 1.932-2.217 4.548-2.853 5.728-.616 1.074-1.127.931-1.532.029-1.406-3.321-4.293-9.144-5.651-12.409-.251-.601-.441-.987-.619-1.139-.181-.15-.554-.24-1.122-.271C.103 5.033 0 4.982 0 4.898v-.455l.052-.045c.924-.005 5.401 0 5.401 0l.051.045v.434c0 .119-.075.176-.225.176l-.564.031c-.485.029-.727.164-.727.436 0 .135.053.33.166.601 1.082 2.646 4.818 10.521 4.818 10.521l.136.046 2.411-4.81-.482-1.067-1.658-3.264s-.318-.654-.428-.872c-.728-1.443-.712-1.518-1.447-1.617-.207-.023-.313-.05-.313-.149v-.468l.06-.045h4.292l.113.037v.451c0 .105-.076.15-.227.15l-.308.047c-.792.061-.661.381-.136 1.422l1.582 3.252 1.758-3.504c.293-.64.233-.801.111-.947-.07-.084-.305-.22-.812-.24l-.201-.021c-.052 0-.098-.015-.145-.051-.045-.031-.067-.076-.067-.129v-.427l.061-.045c1.247-.008 4.043 0 4.043 0l.059.045v.436c0 .121-.059.178-.193.178-.646.03-.782.095-1.023.439-.12.186-.375.589-.646 1.039l-2.301 4.273-.065.135 2.792 5.712.17.048 4.396-10.438c.154-.422.129-.722-.064-.895-.197-.172-.346-.273-.857-.295l-.42-.016c-.061 0-.105-.014-.152-.045-.043-.029-.072-.075-.072-.119v-.436l.059-.045h4.961l.041.045v.437c0 .119-.074.18-.209.18-.648.03-1.127.18-1.443.421-.314.255-.557.616-.736 1.067 0 0-4.043 9.258-5.426 12.339-.525 1.007-1.053.917-1.503-.031-.571-1.171-1.773-3.786-2.646-5.71l.053-.036z"/>
                                        </svg>
                                        Wikipedia
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </body>
</html>
