<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('My Reviews') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if(session('status'))
                <div class="bg-green-900/30 border border-green-700 rounded-lg p-4">
                    <p class="text-sm text-green-200">{{ session('status') }}</p>
                </div>
            @endif

            <!-- Navigation Tabs -->
            <div class="card">
                <div class="border-b border-gray-200">
                    <nav class="flex -mb-px">
                        <a href="{{ route('account') }}"
                           class="border-b-2 border-transparent py-4 px-6 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300">
                            Overview
                        </a>
                        <a href="{{ route('account.reviews') }}"
                           class="border-b-2 border-blue-500 py-4 px-6 text-sm font-medium text-blue-600">
                            All Reviews
                        </a>
                        <a href="{{ route('account.statistics') }}"
                           class="border-b-2 border-transparent py-4 px-6 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300">
                            Statistics
                        </a>
                        <a href="{{ route('profile.edit') }}"
                           class="border-b-2 border-transparent py-4 px-6 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300">
                            Settings
                        </a>
                    </nav>
                </div>

                <!-- Filters -->
                <div class="p-4 bg-gray-50 border-b border-gray-200">
                    <form method="GET" action="{{ route('account.reviews') }}" class="flex flex-wrap items-center gap-4">
                        <div class="flex items-center gap-2">
                            <label for="sort" class="text-sm font-medium text-gray-700">Sort by:</label>
                            <select name="sort" id="sort"
                                    class="rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500"
                                    onchange="this.form.submit()">
                                <option value="recent" {{ $currentSort === 'recent' ? 'selected' : '' }}>Most Recent</option>
                                <option value="highest" {{ $currentSort === 'highest' ? 'selected' : '' }}>Highest Rated</option>
                                <option value="lowest" {{ $currentSort === 'lowest' ? 'selected' : '' }}>Lowest Rated</option>
                                <option value="album" {{ $currentSort === 'album' ? 'selected' : '' }}>Album Name</option>
                                <option value="artist" {{ $currentSort === 'artist' ? 'selected' : '' }}>Artist Name</option>
                            </select>
                        </div>
                        <div class="flex items-center gap-2">
                            <label for="rating" class="text-sm font-medium text-gray-700">Rating:</label>
                            <select name="rating" id="rating"
                                    class="rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500"
                                    onchange="this.form.submit()">
                                <option value="">All</option>
                                @for($i = 10; $i >= 1; $i--)
                                    <option value="{{ $i }}" {{ $currentRating == $i ? 'selected' : '' }}>{{ $i }}/10</option>
                                @endfor
                            </select>
                        </div>
                        @if($currentSort !== 'recent' || $currentRating)
                            <a href="{{ route('account.reviews') }}" class="text-sm text-gray-500 hover:text-gray-700">
                                Clear filters
                            </a>
                        @endif
                    </form>
                </div>

                <div class="p-6">
                    @if($ratings->count() > 0)
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            @foreach($ratings as $rating)
                                <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                                    <div class="flex gap-4">
                                        @if($rating->album?->cover_image_url)
                                            <img src="{{ $rating->album->cover_image_url }}"
                                                 alt="{{ $rating->album->title }}"
                                                 class="w-16 h-16 rounded object-cover flex-shrink-0">
                                        @else
                                            <div class="w-16 h-16 rounded bg-gray-200 flex items-center justify-center flex-shrink-0">
                                                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" />
                                                </svg>
                                            </div>
                                        @endif
                                        <div class="flex-1 min-w-0">
                                            <a href="{{ route('album.show', $rating->album) }}"
                                               class="font-medium text-gray-900 hover:text-blue-600 block truncate">
                                                {{ $rating->album?->title ?? 'Unknown Album' }}
                                            </a>
                                            @if($rating->album?->artist)
                                                <a href="{{ route('artist.show', $rating->album->artist) }}"
                                                   class="text-sm text-gray-500 hover:text-gray-700 block truncate">
                                                    {{ $rating->album->artist->name }}
                                                </a>
                                            @endif
                                            <div class="flex items-center gap-2 mt-2">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                                    @if($rating->rating >= 8) bg-green-100 text-green-800
                                                    @elseif($rating->rating >= 5) bg-yellow-100 text-yellow-800
                                                    @else bg-red-100 text-red-800
                                                    @endif">
                                                    {{ $rating->rating }}/10
                                                </span>
                                                <span class="text-xs text-gray-400">
                                                    {{ $rating->created_at->format('M d, Y') }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    @if($rating->notes)
                                        <p class="text-sm text-gray-600 mt-3 line-clamp-2">{{ $rating->notes }}</p>
                                    @endif
                                    <div class="flex gap-2 mt-3 pt-3 border-t border-gray-100">
                                        <a href="{{ route('account.reviews.edit', $rating) }}"
                                           class="text-xs text-blue-600 hover:text-blue-800">Edit</a>
                                        <form method="POST" action="{{ route('account.reviews.destroy', $rating) }}"
                                              onsubmit="return confirm('Are you sure you want to delete this review?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs text-red-600 hover:text-red-800">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-6">
                            {{ $ratings->links() }}
                        </div>
                    @else
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No reviews found</h3>
                            <p class="mt-1 text-sm text-gray-500">
                                @if($currentRating)
                                    No reviews match the selected rating filter.
                                @else
                                    Start rating albums to build your music profile.
                                @endif
                            </p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
