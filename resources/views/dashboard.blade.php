<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-100 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Welcome Section -->
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-100 mb-2">
                        Welcome back, {{ $user->name }}!
                    </h3>
                    <p class="text-gray-400">
                        Track your music journey, rate albums, and discover new artists.
                    </p>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="card p-6">
                    <div class="text-3xl font-bold text-blue-400">{{ $stats['totalRatings'] }}</div>
                    <div class="text-sm text-gray-400 mt-1">Albums Rated</div>
                </div>
                <div class="card p-6">
                    <div class="text-3xl font-bold text-green-600">{{ $stats['averageRating'] }}</div>
                    <div class="text-sm text-gray-400 mt-1">Average Rating</div>
                </div>
                <div class="card p-6">
                    <div class="text-3xl font-bold text-purple-400">{{ $stats['uniqueArtists'] }}</div>
                    <div class="text-sm text-gray-400 mt-1">Artists Explored</div>
                </div>
                <div class="card p-6">
                    <div class="text-3xl font-bold text-orange-400">{{ $stats['memberSince']->diffForHumans(null, true) }}</div>
                    <div class="text-sm text-gray-400 mt-1">Member Since</div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Recent Ratings -->
                <div class="card">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-gray-100">Recent Ratings</h3>
                            @if($recentRatings->count() > 0)
                                <a href="{{ route('account') }}" class="text-sm text-blue-400 hover:text-blue-300">View all</a>
                            @endif
                        </div>

                        @if($recentRatings->count() > 0)
                            <ul class="divide-y divide-gray-800">
                                @foreach($recentRatings as $rating)
                                    <li class="py-3 flex items-center justify-between">
                                        <div class="flex items-center gap-3 min-w-0">
                                            @if($rating->album?->cover_image_url)
                                                <img src="{{ $rating->album->cover_image_url }}"
                                                     alt="{{ $rating->album->title }}"
                                                     class="w-10 h-10 rounded object-cover flex-shrink-0">
                                            @else
                                                <div class="w-10 h-10 rounded bg-gray-800 flex items-center justify-center flex-shrink-0">
                                                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" />
                                                    </svg>
                                                </div>
                                            @endif
                                            <div class="min-w-0">
                                                <a href="{{ route('album.show', $rating->album) }}"
                                                   class="text-sm font-medium text-gray-100 hover:text-blue-400 truncate block">
                                                    {{ $rating->album?->title ?? 'Unknown Album' }}
                                                </a>
                                                @if($rating->album?->artist)
                                                    <a href="{{ route('artist.show', $rating->album->artist) }}"
                                                       class="text-xs text-gray-400 hover:text-gray-200 truncate block">
                                                        {{ $rating->album->artist->name }}
                                                    </a>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2 flex-shrink-0 ml-2">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                @if($rating->rating >= 8) bg-green-100 text-green-800
                                                @elseif($rating->rating >= 5) bg-yellow-100 text-yellow-800
                                                @else bg-red-100 text-red-800
                                                @endif">
                                                {{ $rating->rating }}/10
                                            </span>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <div class="text-center py-8">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" />
                                </svg>
                                <p class="mt-2 text-sm text-gray-400">No ratings yet</p>
                                <a href="{{ route('home') }}" class="mt-3 inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg">
                                    Discover Music
                                </a>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Top Rated -->
                <div class="card">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-100 mb-4">Your Top Rated Albums</h3>

                        @if($topRatedAlbums->count() > 0)
                            <ul class="divide-y divide-gray-800">
                                @foreach($topRatedAlbums as $rating)
                                    <li class="py-3 flex items-center justify-between">
                                        <div class="flex items-center gap-3 min-w-0">
                                            @if($rating->album?->cover_image_url)
                                                <img src="{{ $rating->album->cover_image_url }}"
                                                     alt="{{ $rating->album->title }}"
                                                     class="w-10 h-10 rounded object-cover flex-shrink-0">
                                            @else
                                                <div class="w-10 h-10 rounded bg-gray-800 flex items-center justify-center flex-shrink-0">
                                                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" />
                                                    </svg>
                                                </div>
                                            @endif
                                            <div class="min-w-0">
                                                <a href="{{ route('album.show', $rating->album) }}"
                                                   class="text-sm font-medium text-gray-100 hover:text-blue-400 truncate block">
                                                    {{ $rating->album?->title ?? 'Unknown Album' }}
                                                </a>
                                                @if($rating->album?->artist)
                                                    <a href="{{ route('artist.show', $rating->album->artist) }}"
                                                       class="text-xs text-gray-400 hover:text-gray-200 truncate block">
                                                        {{ $rating->album->artist->name }}
                                                    </a>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-1 flex-shrink-0 ml-2">
                                            @for($i = 1; $i <= 5; $i++)
                                                <svg class="w-4 h-4 {{ $i <= ceil($rating->rating / 2) ? 'text-yellow-400' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20">
                                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                                </svg>
                                            @endfor
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <div class="text-center py-8">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                                </svg>
                                <p class="mt-2 text-sm text-gray-400">Start rating albums to see your favorites here</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-100 mb-4">Quick Actions</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <a href="{{ route('home') }}"
                           class="flex flex-col items-center p-4 rounded-lg border border-gray-800 bg-gray-900 hover:border-blue-500 hover:bg-gray-800 transition-colors">
                            <svg class="w-8 h-8 text-blue-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            <span class="text-sm font-medium text-gray-100">Search Music</span>
                        </a>
                        <a href="{{ route('account') }}"
                           class="flex flex-col items-center p-4 rounded-lg border border-gray-800 bg-gray-900 hover:border-blue-500 hover:bg-gray-800 transition-colors">
                            <svg class="w-8 h-8 text-green-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                            </svg>
                            <span class="text-sm font-medium text-gray-100">My Reviews</span>
                        </a>
                        <a href="{{ route('profile.edit') }}"
                           class="flex flex-col items-center p-4 rounded-lg border border-gray-800 bg-gray-900 hover:border-blue-500 hover:bg-gray-800 transition-colors">
                            <svg class="w-8 h-8 text-purple-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <span class="text-sm font-medium text-gray-100">Settings</span>
                        </a>
                        @if($user->is_admin)
                            <a href="{{ route('admin.monitoring') }}"
                               class="flex flex-col items-center p-4 rounded-lg border border-gray-800 bg-gray-900 hover:border-blue-500 hover:bg-gray-800 transition-colors">
                                <svg class="w-8 h-8 text-orange-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <span class="text-sm font-medium text-gray-100">Admin Panel</span>
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
