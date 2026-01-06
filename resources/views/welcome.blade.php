<x-main-layout
    :transparentHeader="false"
    :showRecentReviews="false"
    title="{{ config('app.name', 'Spinsearch') }} - Understand the music you love"
    description="Spinsearch is a music encyclopedia for the curious listener. Explore complete discographies, discover artist histories, and navigate connections between albums, genres, and eras."
    :jsonLd="\App\Services\SeoService::websiteJsonLd()"
>
    <!-- Hero Section -->
    <section class="py-16 sm:py-24 px-4">
        <div class="max-w-3xl mx-auto text-center">
            <h1 class="text-4xl sm:text-5xl font-bold text-gray-100 mb-6">
                Understand the music you love.
            </h1>
            <p class="text-lg sm:text-xl text-gray-400 mb-10 leading-relaxed">
                Spinsearch is a music encyclopedia for the curious listener. Explore complete discographies, discover artist histories, and navigate the connections between albums, genres, and eras—all powered by structured, source-driven metadata.
            </p>
            <a href="{{ route('search.page') }}" class="inline-flex items-center px-8 py-4 text-lg font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors">
                Start Exploring
                <svg class="ml-2 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                </svg>
            </a>
        </div>
    </section>

    <!-- What Spinsearch Is -->
    <section class="py-16 px-4 border-t border-gray-800">
        <div class="max-w-4xl mx-auto">
            <h2 class="text-2xl sm:text-3xl font-bold text-gray-100 mb-10 text-center">
                A deeper way to explore music
            </h2>
            <div class="grid sm:grid-cols-2 gap-8">
                <div class="p-6 bg-gray-900/50 rounded-lg border border-gray-800">
                    <h3 class="text-lg font-semibold text-gray-100 mb-3">Complete Discographies</h3>
                    <p class="text-gray-400 leading-relaxed">
                        See every album, EP, and single an artist has released, organized chronologically. No gaps, no guesswork.
                    </p>
                </div>
                <div class="p-6 bg-gray-900/50 rounded-lg border border-gray-800">
                    <h3 class="text-lg font-semibold text-gray-100 mb-3">Historical Context</h3>
                    <p class="text-gray-400 leading-relaxed">
                        Understand when artists formed, their origins, genre evolution, and career arcs across decades.
                    </p>
                </div>
                <div class="p-6 bg-gray-900/50 rounded-lg border border-gray-800">
                    <h3 class="text-lg font-semibold text-gray-100 mb-3">Structured Metadata</h3>
                    <p class="text-gray-400 leading-relaxed">
                        Information sourced from Wikidata and authoritative databases, not user-generated guesswork.
                    </p>
                </div>
                <div class="p-6 bg-gray-900/50 rounded-lg border border-gray-800">
                    <h3 class="text-lg font-semibold text-gray-100 mb-3">Complements Your Library</h3>
                    <p class="text-gray-400 leading-relaxed">
                        Designed to work alongside Spotify, Apple Music, vinyl collections, and CDs—not replace them.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- What Spinsearch Is Not -->
    <section class="py-16 px-4 border-t border-gray-800">
        <div class="max-w-3xl mx-auto">
            <h2 class="text-2xl sm:text-3xl font-bold text-gray-100 mb-10 text-center">
                What we don't do
            </h2>
            <div class="space-y-6">
                <div class="flex items-start gap-4">
                    <span class="flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-full bg-gray-800 text-gray-500">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </span>
                    <div>
                        <h3 class="text-lg font-medium text-gray-100">Not a streaming service</h3>
                        <p class="text-gray-400 mt-1">We don't play music. Use your preferred streaming app or physical media for listening.</p>
                    </div>
                </div>
                <div class="flex items-start gap-4">
                    <span class="flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-full bg-gray-800 text-gray-500">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </span>
                    <div>
                        <h3 class="text-lg font-medium text-gray-100">No audio playback</h3>
                        <p class="text-gray-400 mt-1">Spinsearch is about understanding music, not listening to it.</p>
                    </div>
                </div>
                <div class="flex items-start gap-4">
                    <span class="flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-full bg-gray-800 text-gray-500">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </span>
                    <div>
                        <h3 class="text-lg font-medium text-gray-100">Not a recommendation feed</h3>
                        <p class="text-gray-400 mt-1">No algorithmic suggestions, trending playlists, or engagement-driven content.</p>
                    </div>
                </div>
                <div class="flex items-start gap-4">
                    <span class="flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-full bg-gray-800 text-gray-500">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </span>
                    <div>
                        <h3 class="text-lg font-medium text-gray-100">Not a replacement</h3>
                        <p class="text-gray-400 mt-1">We complement your existing music apps, not compete with them.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Why Spinsearch Exists -->
    <section class="py-16 px-4 border-t border-gray-800">
        <div class="max-w-3xl mx-auto">
            <h2 class="text-2xl sm:text-3xl font-bold text-gray-100 mb-8 text-center">
                Why we built this
            </h2>
            <div class="space-y-6 text-gray-400 leading-relaxed">
                <p>
                    Streaming apps are optimized for listening, not understanding. Important context gets buried. Discographies are incomplete or disorganized. Relationships between artists, albums, and genres are hard to explore. The deeper you want to go, the less these apps help.
                </p>
                <p>
                    Spinsearch makes music catalogs understandable, explorable, and connected. We organize the information that music lovers care about—complete discographies, historical context, and authoritative metadata—into a calm, browsable experience.
                </p>
            </div>
        </div>
    </section>

    <!-- Future Direction -->
    <section class="py-16 px-4 border-t border-gray-800">
        <div class="max-w-3xl mx-auto">
            <h2 class="text-2xl sm:text-3xl font-bold text-gray-100 mb-8 text-center">
                What's next
            </h2>
            <p class="text-gray-400 leading-relaxed text-center mb-8">
                Spinsearch is growing. We're exploring features like popularity and relevance signals, user reviews and ratings, personal collections, physical media tracking for vinyl and CDs, and deeper integrations with services like Discogs, Spotify, and Apple Music.
            </p>
        </div>
    </section>

    <!-- Final CTA -->
    <section class="py-16 px-4 border-t border-gray-800">
        <div class="max-w-2xl mx-auto text-center">
            <h2 class="text-2xl sm:text-3xl font-bold text-gray-100 mb-4">
                Ready to explore?
            </h2>
            <p class="text-gray-400 mb-8">
                Start discovering the depth behind the music you love.
            </p>
            <a href="{{ route('search.page') }}" class="inline-flex items-center px-8 py-4 text-lg font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors">
                Go to Search
                <svg class="ml-2 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
            </a>
        </div>
    </section>
</x-main-layout>
