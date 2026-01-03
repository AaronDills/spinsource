@props(['showRecentReviews' => true])

@php
    $recentArtists = collect();
    $recentAlbums = collect();

    if ($showRecentReviews && Auth::check()) {
        $recentArtists = Auth::user()->recentlyReviewedArtists(5);
        $recentAlbums = Auth::user()->recentlyReviewedAlbums(5);
    }
@endphp

<footer class="bg-gray-900 text-gray-300 mt-auto">
    @if($showRecentReviews && Auth::check() && ($recentArtists->count() > 0 || $recentAlbums->count() > 0))
        <div class="border-b border-gray-800">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                <h3 class="text-sm font-semibold text-gray-400 uppercase tracking-wider mb-4">
                    Your Recent Reviews
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    @if($recentArtists->count() > 0)
                        <div>
                            <h4 class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-3">
                                Artists
                            </h4>
                            <ul class="space-y-2">
                                @foreach($recentArtists as $artist)
                                    <li>
                                        <a href="{{ route('artist.show', $artist) }}"
                                           class="text-sm text-gray-400 hover:text-white transition-colors flex items-center gap-2">
                                            <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                            </svg>
                                            {{ $artist->name }}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if($recentAlbums->count() > 0)
                        <div>
                            <h4 class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-3">
                                Albums
                            </h4>
                            <ul class="space-y-2">
                                @foreach($recentAlbums as $album)
                                    <li>
                                        <a href="{{ route('album.show', $album) }}"
                                           class="text-sm text-gray-400 hover:text-white transition-colors flex items-center gap-2">
                                            <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" />
                                            </svg>
                                            <span>{{ $album->title }}</span>
                                            @if($album->artist)
                                                <span class="text-gray-600">by {{ $album->artist->name }}</span>
                                            @endif
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
            <!-- Brand -->
            <div class="col-span-1 md:col-span-2">
                <a href="{{ route('home') }}" class="flex items-center gap-2 mb-4">
                    <x-application-logo class="h-8 w-auto fill-current text-white" />
                    <span class="font-semibold text-lg text-white">{{ config('app.name', 'Spin Source') }}</span>
                </a>
                <p class="text-sm text-gray-400 max-w-md">
                    Discover and collect the music you love. Track your listening history, rate albums, and explore new artists.
                </p>
            </div>

            <!-- Quick Links -->
            <div>
                <h4 class="text-sm font-semibold text-white uppercase tracking-wider mb-4">
                    Explore
                </h4>
                <ul class="space-y-2">
                    <li>
                        <a href="{{ route('home') }}" class="text-sm text-gray-400 hover:text-white transition-colors">
                            Search Music
                        </a>
                    </li>
                    @auth
                        <li>
                            <a href="{{ route('dashboard') }}" class="text-sm text-gray-400 hover:text-white transition-colors">
                                Dashboard
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('account') }}" class="text-sm text-gray-400 hover:text-white transition-colors">
                                Account
                            </a>
                        </li>
                    @endauth
                </ul>
            </div>

            <!-- Account -->
            <div>
                <h4 class="text-sm font-semibold text-white uppercase tracking-wider mb-4">
                    Account
                </h4>
                <ul class="space-y-2">
                    @auth
                        <li>
                            <a href="{{ route('profile.edit') }}" class="text-sm text-gray-400 hover:text-white transition-colors">
                                Profile Settings
                            </a>
                        </li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}" class="inline">
                                @csrf
                                <button type="submit" class="text-sm text-gray-400 hover:text-white transition-colors">
                                    Log Out
                                </button>
                            </form>
                        </li>
                    @else
                        <li>
                            <a href="{{ route('login') }}" class="text-sm text-gray-400 hover:text-white transition-colors">
                                Log In
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('register') }}" class="text-sm text-gray-400 hover:text-white transition-colors">
                                Sign Up
                            </a>
                        </li>
                    @endauth
                </ul>
            </div>
        </div>

        <div class="border-t border-gray-800 mt-8 pt-8 text-center">
            <p class="text-sm text-gray-500">
                &copy; {{ date('Y') }} {{ config('app.name', 'Spin Source') }}. All rights reserved.
            </p>
        </div>
    </div>
</footer>
