<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $artist->name }} - {{ config('app.name', 'Laravel') }}</title>
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
                    <div class="flex items-start gap-6">
                        @if($artist->image_commons)
                            <img src="https://commons.wikimedia.org/wiki/Special:FilePath/{{ rawurlencode($artist->image_commons) }}?width=300"
                                 alt="{{ $artist->name }}"
                                 class="flex-shrink-0 w-32 h-32 object-cover rounded-lg bg-gray-200 dark:bg-gray-700">
                        @else
                            <div class="flex-shrink-0 w-32 h-32 bg-gray-200 dark:bg-gray-700 rounded-lg flex items-center justify-center">
                                <span class="text-6xl">🎤</span>
                            </div>
                        @endif
                        <div class="flex-1 min-w-0">
                            <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-2">
                                {{ $artist->name }}
                            </h1>
                            @if($artist->genres->count() > 0)
                                <p class="text-xl text-gray-600 dark:text-gray-400 mb-3">
                                    {{ $artist->genres->pluck('name')->join(', ') }}
                                </p>
                            @endif
                            <div class="flex flex-wrap gap-2">
                                @if($artist->country)
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200">
                                        {{ $artist->country->name }}
                                    </span>
                                @endif
                                @if($artist->formed_year)
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200">
                                        Est. {{ $artist->formed_year }}
                                    </span>
                                @endif
                                @if($artist->disbanded_year)
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200">
                                        Disbanded {{ $artist->disbanded_year }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>

                    @if($artist->description)
                        <div class="mt-8">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-3">About</h2>
                            <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                                {{ $artist->description }}
                            </p>
                        </div>
                    @endif

                    @php
                        $streamingLinks = $deduplicatedLinks->whereIn('type', ['spotify', 'apple_music', 'deezer', 'soundcloud', 'bandcamp']);
                        $socialLinks = $deduplicatedLinks->whereIn('type', ['twitter', 'instagram', 'facebook', 'youtube', 'reddit']);
                        $websiteLinks = $deduplicatedLinks->where('type', 'website');
                    @endphp

                    @if($streamingLinks->count() > 0 || $artist->wikipedia_url)
                        <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Listen</h2>
                            <div class="flex flex-wrap gap-3">
                                @foreach($streamingLinks as $link)
                                    @switch($link->type)
                                        @case('spotify')
                                            <a href="{{ $link->url }}"
                                               target="_blank"
                                               rel="noopener noreferrer"
                                               class="inline-flex items-center px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg transition-colors">
                                                <svg class="w-5 h-5 mr-2" viewBox="0 0 24 24" fill="currentColor">
                                                    <path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.419 1.56-.299.421-1.02.599-1.559.3z"/>
                                                </svg>
                                                Spotify
                                            </a>
                                            @break
                                        @case('apple_music')
                                            <a href="{{ $link->url }}"
                                               target="_blank"
                                               rel="noopener noreferrer"
                                               class="inline-flex items-center px-4 py-2 bg-pink-500 hover:bg-pink-600 text-white rounded-lg transition-colors">
                                                <svg class="w-5 h-5 mr-2" viewBox="0 0 24 24" fill="currentColor">
                                                    <path d="M23.994 6.124a9.23 9.23 0 00-.24-2.19c-.317-1.31-1.062-2.31-2.18-3.043a5.022 5.022 0 00-1.877-.726 10.496 10.496 0 00-1.564-.15c-.04-.003-.083-.01-.124-.013H5.986c-.152.01-.303.017-.455.026-.747.043-1.49.123-2.193.4-1.336.53-2.3 1.452-2.865 2.78-.192.448-.292.925-.363 1.408-.056.392-.088.785-.1 1.18 0 .032-.007.062-.01.093v12.223c.01.14.017.283.027.424.05.815.154 1.624.497 2.373.65 1.42 1.738 2.353 3.234 2.801.42.127.856.187 1.293.228.555.053 1.11.06 1.667.06h11.03a12.5 12.5 0 001.57-.1c.822-.106 1.596-.35 2.295-.81a5.046 5.046 0 001.88-2.207c.186-.42.293-.87.37-1.324.113-.675.138-1.358.137-2.04-.002-3.8 0-7.595-.003-11.393zm-6.423 3.99v5.712c0 .417-.058.827-.244 1.206-.29.59-.76.962-1.388 1.14-.35.1-.706.157-1.07.173-.95.042-1.8-.6-1.965-1.46-.18-.944.39-1.89 1.31-2.166.306-.09.62-.16.93-.242.38-.1.736-.243.96-.595.135-.21.193-.453.2-.707.003-.06.004-.12.004-.18l-.002-4.67c0-.3-.12-.46-.41-.52-.36-.073-.723-.13-1.085-.198-1.05-.195-2.1-.39-3.148-.586-.115-.022-.23-.04-.348-.046-.242-.014-.398.14-.42.376-.002.023-.003.046-.003.07v6.72c0 .442-.044.878-.236 1.284-.29.61-.762 1.002-1.406 1.19-.322.094-.65.153-.986.17-.964.046-1.813-.593-1.984-1.456-.183-.923.378-1.854 1.295-2.142.306-.097.62-.163.932-.244.376-.1.725-.24.947-.584.14-.217.2-.46.208-.716v-8.677c0-.18.04-.34.147-.483.107-.142.256-.226.43-.263.094-.02.19-.034.287-.048.72-.105 1.44-.21 2.162-.314l3.14-.456 1.903-.277c.147-.02.295-.04.444-.05.267-.02.424.123.453.383.008.07.01.14.01.21v5.25z"/>
                                                </svg>
                                                Apple Music
                                            </a>
                                            @break
                                        @case('deezer')
                                            <a href="{{ $link->url }}"
                                               target="_blank"
                                               rel="noopener noreferrer"
                                               class="inline-flex items-center px-4 py-2 bg-purple-500 hover:bg-purple-600 text-white rounded-lg transition-colors">
                                                <svg class="w-5 h-5 mr-2" viewBox="0 0 24 24" fill="currentColor">
                                                    <path d="M18.81 4.16v3.03H24V4.16h-5.19zM6.27 8.38v3.027h5.189V8.38h-5.19zm12.54 0v3.027H24V8.38h-5.19zM6.27 12.594v3.027h5.189v-3.027h-5.19zm6.271 0v3.027h5.19v-3.027h-5.19zm6.27 0v3.027H24v-3.027h-5.19zM0 16.81v3.029h5.19v-3.03H0zm6.27 0v3.029h5.189v-3.03h-5.19zm6.271 0v3.029h5.19v-3.03h-5.19zm6.27 0v3.029H24v-3.03h-5.19z"/>
                                                </svg>
                                                Deezer
                                            </a>
                                            @break
                                        @case('soundcloud')
                                            <a href="{{ $link->url }}"
                                               target="_blank"
                                               rel="noopener noreferrer"
                                               class="inline-flex items-center px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg transition-colors">
                                                <svg class="w-5 h-5 mr-2" viewBox="0 0 24 24" fill="currentColor">
                                                    <path d="M1.175 12.225c-.051 0-.094.046-.101.1l-.233 2.154.233 2.105c.007.058.05.098.101.098.05 0 .09-.04.099-.098l.255-2.105-.27-2.154c0-.057-.045-.1-.09-.1m-.899.828c-.06 0-.091.037-.104.094L0 14.479l.165 1.308c0 .055.045.094.09.094s.089-.045.104-.104l.21-1.319-.21-1.334c0-.061-.044-.09-.09-.09m1.83-1.229c-.061 0-.12.045-.12.104l-.21 2.563.225 2.458c0 .06.045.12.119.12.061 0 .105-.061.121-.12l.254-2.474-.254-2.548c-.016-.06-.061-.12-.121-.12m.945-.089c-.075 0-.135.06-.15.135l-.193 2.64.21 2.544c.016.077.075.138.149.138.075 0 .135-.061.15-.15l.24-2.532-.24-2.623c0-.075-.06-.135-.135-.135l-.031-.017zm1.155.36c-.005-.09-.075-.149-.159-.149-.09 0-.158.06-.164.149l-.217 2.43.2 2.563c0 .09.075.157.159.157.074 0 .148-.068.148-.158l.227-2.563-.227-2.444.033.015zm.809-1.709c-.101 0-.18.09-.18.181l-.21 3.957.187 2.563c0 .09.08.164.18.164.094 0 .174-.09.18-.18l.209-2.563-.209-3.972c-.008-.104-.088-.18-.18-.18m.959-.914c-.105 0-.195.09-.203.194l-.18 4.872.165 2.548c0 .12.09.209.195.209.104 0 .194-.089.21-.209l.193-2.548-.21-4.872c-.016-.12-.105-.21-.196-.21m.989-.449c-.121 0-.211.089-.225.209l-.165 5.275.165 2.52c.014.119.104.225.225.225.119 0 .225-.105.225-.225l.195-2.52-.196-5.275c0-.12-.105-.225-.225-.225m1.245.045c0-.135-.105-.24-.24-.24-.119 0-.24.105-.24.24l-.149 5.441.149 2.503c.016.135.121.24.256.24s.24-.105.24-.24l.164-2.503-.164-5.456-.016.015zm.749-.134c-.135 0-.255.119-.255.254l-.15 5.322.15 2.473c0 .15.12.255.255.255s.255-.12.255-.27l.15-2.458-.165-5.307c0-.148-.12-.27-.271-.27m1.005.166c-.164 0-.284.135-.284.285l-.103 5.143.135 2.474c0 .149.119.277.284.277.149 0 .271-.12.284-.285l.121-2.443-.135-5.158c-.014-.164-.12-.299-.285-.299m1.184-.945c-.045-.029-.105-.044-.165-.044s-.119.015-.165.044c-.09.054-.149.15-.149.255v.061l-.104 6.048.115 2.449v.016c0 .09.03.166.09.224.075.07.165.105.255.105.075 0 .15-.022.21-.075.09-.06.135-.15.135-.27l.015-.164.165-2.31-.18-6.09c0-.104-.06-.193-.149-.239m.928-.179c-.045-.03-.105-.045-.166-.045-.074 0-.134.015-.194.06-.09.06-.135.15-.135.255v.06l-.075 6.294.074 2.437c.016.18.165.314.345.314.165 0 .315-.135.315-.314l.09-2.452-.105-6.294v-.06c0-.12-.061-.21-.135-.255m1.036.209c-.12-.075-.27-.06-.375.029-.09.06-.15.165-.15.284v.045l-.09 6.091.09 2.413c0 .195.165.345.345.345.195 0 .359-.165.359-.359l.091-2.399-.105-6.106v-.045c0-.135-.061-.239-.165-.298m1.095-.195c-.045-.015-.09-.029-.15-.029-.06 0-.105.015-.165.029-.135.06-.225.195-.225.375l-.015.062-.06 5.921.076 2.399c0 .21.165.375.375.375.18 0 .359-.165.359-.375l.09-2.399-.09-5.983c0-.195-.09-.329-.225-.375m1.095-.449c-.09-.03-.181-.045-.271-.045-.09 0-.195.015-.271.045-.195.09-.33.285-.315.525l-.015.045-.061 5.899.091 2.369c.015.255.195.435.449.435.24 0 .435-.195.435-.45l.105-2.369-.121-5.894v-.045c0-.24-.134-.449-.33-.524m1.229-.24c-.104-.045-.21-.061-.33-.061-.104 0-.21.016-.314.061-.195.09-.33.315-.315.57l-.015.016-.076 5.879.091 2.339c.016.285.225.495.495.495.255 0 .48-.21.48-.495l.105-2.354-.121-5.879v-.03c0-.27-.135-.48-.345-.57m1.5-.404c-.135-.075-.285-.135-.465-.135-.165 0-.315.06-.465.135-.24.135-.39.39-.375.704v.015l-.06 5.699.075 2.309c.015.314.255.555.555.555.285 0 .525-.24.54-.57l.09-2.294-.09-5.699c0-.315-.149-.569-.375-.704m1.589-.245c-.135-.09-.3-.15-.494-.15s-.36.061-.51.15c-.27.149-.435.435-.42.771l-.016.03-.074 5.369.09 2.309c.029.329.284.599.614.599s.585-.27.6-.599l.09-2.324-.09-5.369-.016-.03c0-.315-.149-.615-.419-.765M21.766 9c-.225-.119-.48-.18-.735-.18-.239 0-.495.061-.72.18-.359.195-.57.584-.555 1.005l-.06 4.109.06 2.249c0 .475.376.855.855.855.12 0 .255-.045.36-.105.21-.105.375-.255.48-.465.09-.136.14-.301.14-.465l.016-.344.076-1.725-.076-4.125c0-.42-.195-.796-.555-1.005m2.625-.525c-.284-.149-.615-.225-.96-.225-.344 0-.676.076-.945.225-.479.27-.766.779-.766 1.365l.016.03-.061 2.895-.016.62.076 1.628v.015c.03.705.615 1.244 1.33 1.244.27 0 .525-.06.735-.18.165-.09.3-.224.4-.366.151-.195.254-.435.27-.704l.031-.451.074-1.83-.074-3.509c-.016-.6-.315-1.11-.795-1.35"/>
                                                </svg>
                                                SoundCloud
                                            </a>
                                            @break
                                        @case('bandcamp')
                                            <a href="{{ $link->url }}"
                                               target="_blank"
                                               rel="noopener noreferrer"
                                               class="inline-flex items-center px-4 py-2 bg-teal-500 hover:bg-teal-600 text-white rounded-lg transition-colors">
                                                <svg class="w-5 h-5 mr-2" viewBox="0 0 24 24" fill="currentColor">
                                                    <path d="M0 18.75l7.437-13.5H24l-7.438 13.5H0z"/>
                                                </svg>
                                                Bandcamp
                                            </a>
                                            @break
                                    @endswitch
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if($socialLinks->count() > 0 || $websiteLinks->count() > 0)
                        <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Connect</h2>
                            <div class="flex flex-wrap gap-3">
                                @foreach($websiteLinks as $link)
                                    <a href="{{ $link->url }}"
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       class="inline-flex items-center px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition-colors">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path>
                                        </svg>
                                        Website
                                    </a>
                                @endforeach

                                @foreach($socialLinks as $link)
                                    @switch($link->type)
                                        @case('twitter')
                                            <a href="{{ $link->url }}"
                                               target="_blank"
                                               rel="noopener noreferrer"
                                               class="inline-flex items-center px-4 py-2 bg-black hover:bg-gray-800 text-white rounded-lg transition-colors">
                                                <svg class="w-5 h-5 mr-2" viewBox="0 0 24 24" fill="currentColor">
                                                    <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                                                </svg>
                                                X
                                            </a>
                                            @break
                                        @case('instagram')
                                            <a href="{{ $link->url }}"
                                               target="_blank"
                                               rel="noopener noreferrer"
                                               class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-purple-500 via-pink-500 to-orange-500 hover:from-purple-600 hover:via-pink-600 hover:to-orange-600 text-white rounded-lg transition-colors">
                                                <svg class="w-5 h-5 mr-2" viewBox="0 0 24 24" fill="currentColor">
                                                    <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                                                </svg>
                                                Instagram
                                            </a>
                                            @break
                                        @case('facebook')
                                            <a href="{{ $link->url }}"
                                               target="_blank"
                                               rel="noopener noreferrer"
                                               class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                                                <svg class="w-5 h-5 mr-2" viewBox="0 0 24 24" fill="currentColor">
                                                    <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                                                </svg>
                                                Facebook
                                            </a>
                                            @break
                                        @case('youtube')
                                            <a href="{{ $link->url }}"
                                               target="_blank"
                                               rel="noopener noreferrer"
                                               class="inline-flex items-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors">
                                                <svg class="w-5 h-5 mr-2" viewBox="0 0 24 24" fill="currentColor">
                                                    <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                                                </svg>
                                                YouTube
                                            </a>
                                            @break
                                        @case('reddit')
                                            <a href="{{ $link->url }}"
                                               target="_blank"
                                               rel="noopener noreferrer"
                                               class="inline-flex items-center px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white rounded-lg transition-colors">
                                                <svg class="w-5 h-5 mr-2" viewBox="0 0 24 24" fill="currentColor">
                                                    <path d="M12 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0zm5.01 4.744c.688 0 1.25.561 1.25 1.249a1.25 1.25 0 0 1-2.498.056l-2.597-.547-.8 3.747c1.824.07 3.48.632 4.674 1.488.308-.309.73-.491 1.207-.491.968 0 1.754.786 1.754 1.754 0 .716-.435 1.333-1.01 1.614a3.111 3.111 0 0 1 .042.52c0 2.694-3.13 4.87-7.004 4.87-3.874 0-7.004-2.176-7.004-4.87 0-.183.015-.366.043-.534A1.748 1.748 0 0 1 4.028 12c0-.968.786-1.754 1.754-1.754.463 0 .898.196 1.207.49 1.207-.883 2.878-1.43 4.744-1.487l.885-4.182a.342.342 0 0 1 .14-.197.35.35 0 0 1 .238-.042l2.906.617a1.214 1.214 0 0 1 1.108-.701zM9.25 12C8.561 12 8 12.562 8 13.25c0 .687.561 1.248 1.25 1.248.687 0 1.248-.561 1.248-1.249 0-.688-.561-1.249-1.249-1.249zm5.5 0c-.687 0-1.248.561-1.248 1.25 0 .687.561 1.248 1.249 1.248.688 0 1.249-.561 1.249-1.249 0-.687-.562-1.249-1.25-1.249zm-5.466 3.99a.327.327 0 0 0-.231.094.33.33 0 0 0 0 .463c.842.842 2.484.913 2.961.913.477 0 2.105-.056 2.961-.913a.361.361 0 0 0 .029-.463.33.33 0 0 0-.464 0c-.547.533-1.684.73-2.512.73-.828 0-1.979-.196-2.512-.73a.326.326 0 0 0-.232-.095z"/>
                                                </svg>
                                                Reddit
                                            </a>
                                            @break
                                    @endswitch
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if($artist->wikipedia_url)
                        <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Learn More</h2>
                            <div class="flex flex-wrap gap-3">
                                <a href="{{ $artist->wikipedia_url }}"
                                   target="_blank"
                                   rel="noopener noreferrer"
                                   class="inline-flex items-center px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition-colors">
                                    <svg class="w-5 h-5 mr-2" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12.09 13.119c-.936 1.932-2.217 4.548-2.853 5.728-.616 1.074-1.127.931-1.532.029-1.406-3.321-4.293-9.144-5.651-12.409-.251-.601-.441-.987-.619-1.139-.181-.15-.554-.24-1.122-.271C.103 5.033 0 4.982 0 4.898v-.455l.052-.045c.924-.005 5.401 0 5.401 0l.051.045v.434c0 .119-.075.176-.225.176l-.564.031c-.485.029-.727.164-.727.436 0 .135.053.33.166.601 1.082 2.646 4.818 10.521 4.818 10.521l.136.046 2.411-4.81-.482-1.067-1.658-3.264s-.318-.654-.428-.872c-.728-1.443-.712-1.518-1.447-1.617-.207-.023-.313-.05-.313-.149v-.468l.06-.045h4.292l.113.037v.451c0 .105-.076.15-.227.15l-.308.047c-.792.061-.661.381-.136 1.422l1.582 3.252 1.758-3.504c.293-.64.233-.801.111-.947-.07-.084-.305-.22-.812-.24l-.201-.021c-.052 0-.098-.015-.145-.051-.045-.031-.067-.076-.067-.129v-.427l.061-.045c1.247-.008 4.043 0 4.043 0l.059.045v.436c0 .121-.059.178-.193.178-.646.03-.782.095-1.023.439-.12.186-.375.589-.646 1.039l-2.301 4.273-.065.135 2.792 5.712.17.048 4.396-10.438c.154-.422.129-.722-.064-.895-.197-.172-.346-.273-.857-.295l-.42-.016c-.061 0-.105-.014-.152-.045-.043-.029-.072-.075-.072-.119v-.436l.059-.045h4.961l.041.045v.437c0 .119-.074.18-.209.18-.648.03-1.127.18-1.443.421-.314.255-.557.616-.736 1.067 0 0-4.043 9.258-5.426 12.339-.525 1.007-1.053.917-1.503-.031-.571-1.171-1.773-3.786-2.646-5.71l.053-.036z"/>
                                    </svg>
                                    Wikipedia
                                </a>
                            </div>
                        </div>
                    @endif

                    @if(count($albumsByType) > 0)
                        <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Discography</h2>

                            @foreach($albumsByType as $section)
                                <div class="@if(!$loop->first) mt-6 @endif">
                                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">
                                        {{ $section['label'] }}
                                        <span class="text-gray-400 dark:text-gray-500 font-normal">({{ $section['albums']->count() }})</span>
                                    </h3>
                                    <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 gap-3">
                                        @foreach($section['albums'] as $album)
                                            <a href="{{ route('album.show', $album) }}" class="group">
                                                <div class="aspect-square bg-gray-200 dark:bg-gray-700 rounded overflow-hidden mb-1">
                                                    @if($album->cover_image_url)
                                                        <img src="{{ $album->cover_image_url }}"
                                                             alt="{{ $album->title }}"
                                                             loading="lazy"
                                                             class="w-full h-full object-cover group-hover:scale-105 transition-transform">
                                                    @else
                                                        <div class="w-full h-full flex items-center justify-center">
                                                            <span class="text-2xl">💿</span>
                                                        </div>
                                                    @endif
                                                </div>
                                                <h4 class="text-xs font-medium text-gray-900 dark:text-gray-100 truncate group-hover:text-blue-600 dark:group-hover:text-blue-400">
                                                    {{ $album->title }}
                                                </h4>
                                                @if($album->release_year)
                                                    <p class="text-xs text-gray-400 dark:text-gray-500">
                                                        {{ $album->release_year }}
                                                    </p>
                                                @endif
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Details</h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            @if($artist->musicbrainz_id)
                                <div>
                                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">MusicBrainz</h3>
                                    <a href="https://musicbrainz.org/artist/{{ $artist->musicbrainz_id }}"
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       class="mt-1 text-blue-600 dark:text-blue-400 hover:underline text-sm truncate block">
                                        View on MusicBrainz
                                    </a>
                                </div>
                            @endif

                            @if($artist->wikidata_id)
                                <div>
                                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Wikidata</h3>
                                    <a href="https://www.wikidata.org/wiki/{{ $artist->wikidata_id }}"
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       class="mt-1 text-blue-600 dark:text-blue-400 hover:underline text-sm truncate block">
                                        {{ $artist->wikidata_id }}
                                    </a>
                                </div>
                            @endif

                            @if($artist->discogs_artist_id)
                                <div>
                                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Discogs</h3>
                                    <a href="https://www.discogs.com/artist/{{ $artist->discogs_artist_id }}"
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       class="mt-1 text-blue-600 dark:text-blue-400 hover:underline text-sm truncate block">
                                        View on Discogs
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
