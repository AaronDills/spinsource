<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('My Statistics') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Navigation Tabs -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="border-b border-gray-200">
                    <nav class="flex -mb-px">
                        <a href="{{ route('account') }}"
                           class="border-b-2 border-transparent py-4 px-6 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300">
                            Overview
                        </a>
                        <a href="{{ route('account.reviews') }}"
                           class="border-b-2 border-transparent py-4 px-6 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300">
                            All Reviews
                        </a>
                        <a href="{{ route('account.statistics') }}"
                           class="border-b-2 border-blue-500 py-4 px-6 text-sm font-medium text-blue-600">
                            Statistics
                        </a>
                        <a href="{{ route('profile.edit') }}"
                           class="border-b-2 border-transparent py-4 px-6 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300">
                            Settings
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Stats Overview -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 text-center">
                    <div class="text-3xl font-bold text-blue-600">{{ $stats['totalRatings'] }}</div>
                    <div class="text-sm text-gray-500 mt-1">Total Reviews</div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 text-center">
                    <div class="text-3xl font-bold text-green-600">{{ $stats['averageRating'] }}</div>
                    <div class="text-sm text-gray-500 mt-1">Average Rating</div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 text-center">
                    <div class="text-3xl font-bold text-purple-600">{{ $stats['uniqueArtists'] }}</div>
                    <div class="text-sm text-gray-500 mt-1">Artists</div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 text-center">
                    <div class="text-3xl font-bold text-orange-600">{{ $stats['ratingsWithNotes'] }}</div>
                    <div class="text-sm text-gray-500 mt-1">With Notes</div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 text-center">
                    <div class="text-3xl font-bold text-pink-600">{{ $stats['ratingsThisMonth'] }}</div>
                    <div class="text-sm text-gray-500 mt-1">This Month</div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 text-center">
                    <div class="text-3xl font-bold text-indigo-600">{{ $stats['ratingsThisYear'] }}</div>
                    <div class="text-sm text-gray-500 mt-1">This Year</div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Rating Distribution -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Rating Distribution</h3>
                        @php
                            $maxDistribution = max($ratingDistribution) ?: 1;
                        @endphp
                        <div class="space-y-2">
                            @for($i = 10; $i >= 1; $i--)
                                <div class="flex items-center gap-2">
                                    <span class="w-8 text-sm font-medium text-gray-600 text-right">{{ $i }}</span>
                                    <div class="flex-1 bg-gray-100 rounded-full h-6">
                                        <div class="h-6 rounded-full transition-all
                                            @if($i >= 8) bg-green-500
                                            @elseif($i >= 5) bg-yellow-500
                                            @else bg-red-500
                                            @endif"
                                             style="width: {{ ($ratingDistribution[$i] / $maxDistribution) * 100 }}%">
                                        </div>
                                    </div>
                                    <span class="w-8 text-sm text-gray-500">{{ $ratingDistribution[$i] }}</span>
                                </div>
                            @endfor
                        </div>
                    </div>
                </div>

                <!-- Top Artists -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Most Reviewed Artists</h3>
                        @if(count($topArtists) > 0)
                            <div class="space-y-3">
                                @foreach($topArtists as $index => $artist)
                                    <div class="flex items-center gap-3">
                                        <span class="w-6 h-6 flex items-center justify-center rounded-full
                                            @if($index === 0) bg-yellow-100 text-yellow-600
                                            @elseif($index === 1) bg-gray-200 text-gray-600
                                            @elseif($index === 2) bg-orange-100 text-orange-600
                                            @else bg-gray-100 text-gray-500
                                            @endif
                                            text-xs font-medium">
                                            {{ $index + 1 }}
                                        </span>
                                        <div class="flex-1 min-w-0">
                                            <a href="{{ route('artist.show', $artist['id']) }}"
                                               class="text-sm font-medium text-gray-900 hover:text-blue-600 truncate block">
                                                {{ $artist['name'] }}
                                            </a>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-sm font-medium text-gray-900">{{ $artist['rating_count'] }} reviews</div>
                                            <div class="text-xs text-gray-500">avg {{ $artist['avg_rating'] }}</div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-sm text-gray-500 text-center py-8">No data available yet</p>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Yearly Stats -->
            @if(count($yearlyStats) > 0)
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Activity by Year</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Year</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reviews</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Rating</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Activity</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @php
                                        $maxYearlyCount = max(array_column($yearlyStats, 'count')) ?: 1;
                                    @endphp
                                    @foreach($yearlyStats as $stat)
                                        <tr>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                                {{ $stat['year'] }}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                                {{ $stat['count'] }}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                    @if($stat['avg_rating'] >= 8) bg-green-100 text-green-800
                                                    @elseif($stat['avg_rating'] >= 5) bg-yellow-100 text-yellow-800
                                                    @else bg-red-100 text-red-800
                                                    @endif">
                                                    {{ $stat['avg_rating'] }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <div class="w-32 bg-gray-100 rounded-full h-2">
                                                    <div class="h-2 bg-blue-500 rounded-full"
                                                         style="width: {{ ($stat['count'] / $maxYearlyCount) * 100 }}%"></div>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
