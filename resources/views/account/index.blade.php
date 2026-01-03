<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('My Account') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Account Overview -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- User Info Card -->
                <div class="card">
                    <div class="p-6">
                        <div class="flex items-center gap-4">
                            <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center">
                                <span class="text-2xl font-bold text-blue-600">
                                    {{ strtoupper(substr($user->name, 0, 1)) }}
                                </span>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">{{ $user->name }}</h3>
                                <p class="text-sm text-gray-500">{{ $user->email }}</p>
                                <p class="text-xs text-gray-400 mt-1">
                                    Member since {{ $user->created_at->format('M Y') }}
                                </p>
                            </div>
                        </div>
                        <div class="mt-4 pt-4 border-t border-gray-100">
                            <a href="{{ route('profile.edit') }}" class="text-sm text-blue-600 hover:text-blue-800">
                                Edit Profile Settings
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="card md:col-span-2">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Stats</h3>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div class="text-center">
                                <div class="text-2xl font-bold text-blue-600">{{ $stats['totalRatings'] }}</div>
                                <div class="text-xs text-gray-500">Total Reviews</div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-green-600">{{ $stats['averageRating'] }}</div>
                                <div class="text-xs text-gray-500">Avg Rating</div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-purple-600">{{ $stats['highestRated'] ?? '-' }}</div>
                                <div class="text-xs text-gray-500">Highest</div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-orange-600">{{ $stats['lowestRated'] ?? '-' }}</div>
                                <div class="text-xs text-gray-500">Lowest</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Navigation Tabs -->
            <div class="card">
                <div class="border-b border-gray-200">
                    <nav class="flex -mb-px">
                        <a href="{{ route('account') }}"
                           class="border-b-2 border-blue-500 py-4 px-6 text-sm font-medium text-blue-600">
                            Overview
                        </a>
                        <a href="{{ route('account.reviews') }}"
                           class="border-b-2 border-transparent py-4 px-6 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300">
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

                <!-- Monthly Activity Chart -->
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Activity (Last 12 Months)</h3>
                    <div class="flex items-end gap-1 h-32">
                        @php
                            $maxCount = max(array_column($monthlyActivity, 'count')) ?: 1;
                        @endphp
                        @foreach($monthlyActivity as $month)
                            <div class="flex-1 flex flex-col items-center">
                                <div class="w-full bg-blue-500 rounded-t transition-all"
                                     style="height: {{ ($month['count'] / $maxCount) * 100 }}%"
                                     title="{{ $month['month'] }}: {{ $month['count'] }} reviews"></div>
                                <span class="text-xs text-gray-400 mt-1 transform -rotate-45 origin-left whitespace-nowrap">
                                    {{ \Illuminate\Support\Str::before($month['month'], ' ') }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Recent Reviews -->
            <div class="card">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Recent Reviews</h3>
                        <a href="{{ route('account.reviews') }}" class="text-sm text-blue-600 hover:text-blue-800">
                            View all
                        </a>
                    </div>

                    @if($ratings->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Album</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Artist</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rating</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach($ratings as $rating)
                                        <tr>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <a href="{{ route('album.show', $rating->album) }}"
                                                   class="text-sm font-medium text-gray-900 hover:text-blue-600">
                                                    {{ $rating->album?->title ?? 'Unknown' }}
                                                </a>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                @if($rating->album?->artist)
                                                    <a href="{{ route('artist.show', $rating->album->artist) }}"
                                                       class="text-sm text-gray-500 hover:text-gray-700">
                                                        {{ $rating->album->artist->name }}
                                                    </a>
                                                @else
                                                    <span class="text-sm text-gray-400">-</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                    @if($rating->rating >= 8) bg-green-100 text-green-800
                                                    @elseif($rating->rating >= 5) bg-yellow-100 text-yellow-800
                                                    @else bg-red-100 text-red-800
                                                    @endif">
                                                    {{ $rating->rating }}/10
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                {{ $rating->created_at->format('M d, Y') }}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                                <a href="{{ route('account.reviews.edit', $rating) }}"
                                                   class="text-blue-600 hover:text-blue-800 mr-3">Edit</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4">
                            {{ $ratings->links() }}
                        </div>
                    @else
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No reviews yet</h3>
                            <p class="mt-1 text-sm text-gray-500">Start rating albums to build your music profile.</p>
                            <div class="mt-6">
                                <a href="{{ route('home') }}"
                                   class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg">
                                    Discover Music
                                </a>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
