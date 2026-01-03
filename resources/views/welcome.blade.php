<x-main-layout :transparentHeader="false" :showRecentReviews="true">
    <div class="flex items-center justify-center min-h-[calc(100vh-16rem)]">
        <div class="w-full max-w-xl px-4">
            <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-200 text-center mb-8">
                {{ config('app.name', 'Spin Source') }}
            </h1>

            <div x-data="searchAutocomplete" class="relative">
                <form :action="'/search-results?q=' + encodeURIComponent(query)" method="GET" @submit.prevent="submitSearch" class="flex gap-2">
                    <input
                        type="text"
                        x-model="query"
                        @input.debounce.300ms="search"
                        @focus="open = results.length > 0"
                        @keydown.escape="open = false"
                        @keydown.arrow-down.prevent="highlightNext"
                        @keydown.arrow-up.prevent="highlightPrev"
                        @keydown.enter.prevent="handleEnter"
                        placeholder="Search artists and albums..."
                        class="flex-1 px-4 py-3 text-lg rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    >
                    <button
                        type="submit"
                        :disabled="query.length < 2"
                        class="px-4 py-3 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed text-white rounded-lg transition-colors flex items-center justify-center"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </button>
                </form>

                <div
                    x-show="open && results.length > 0"
                    @click.away="open = false"
                    x-transition:enter="transition ease-out duration-100"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-75"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"
                    class="absolute z-50 w-full mt-2 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden"
                >
                    <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                        <template x-for="(result, index) in results" :key="result.type + '-' + result.id">
                            <li
                                @click="selectResult(result)"
                                @mouseenter="highlighted = index"
                                :class="{ 'bg-blue-50 dark:bg-blue-900/30': highlighted === index }"
                                class="px-4 py-3 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50"
                            >
                                <div class="flex items-center gap-3">
                                    <span
                                        x-text="result.type === 'artist' ? '🎤' : '💿'"
                                        class="text-xl"
                                    ></span>
                                    <div class="flex-1 min-w-0">
                                        <p
                                            x-text="result.title"
                                            class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate"
                                        ></p>
                                        <p
                                            x-text="result.subtext"
                                            class="text-xs text-gray-500 dark:text-gray-400 truncate"
                                        ></p>
                                    </div>
                                </div>
                            </li>
                        </template>
                    </ul>
                </div>

                <div
                    x-show="loading"
                    class="absolute right-16 top-1/2 -translate-y-1/2"
                >
                    <svg class="animate-spin h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
            </div>

            <p class="text-center text-sm text-gray-500 dark:text-gray-400 mt-4">
                Discover and collect the music you love
            </p>
        </div>
    </div>
</x-main-layout>
