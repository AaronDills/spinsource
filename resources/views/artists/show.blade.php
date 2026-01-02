<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $artist->name }} - Spinsource</title>
    @vite('resources/css/app.css')
</head>
<body class="bg-gray-50 text-gray-900">
<div class="max-w-5xl mx-auto py-10 px-6">
    <div class="bg-white shadow rounded-lg p-6">
        <div class="flex items-start gap-6">
            @if($artist->image_commons)
                <img
                    class="w-32 h-32 rounded object-cover"
                    src="https://commons.wikimedia.org/wiki/Special:FilePath/{{ urlencode($artist->image_commons) }}?width=400"
                    alt="{{ $artist->name }}"
                />
            @endif

            <div class="flex-1">
                <h1 class="text-3xl font-bold">{{ $artist->name }}</h1>

                @if($artist->description)
                    <p class="mt-2 text-gray-700">{{ $artist->description }}</p>
                @endif

                <div class="mt-4 flex flex-wrap gap-3 text-sm">
                    @if($artist->wikidata_qid)
                        <a
                            class="inline-flex items-center px-3 py-1 rounded bg-gray-100 hover:bg-gray-200"
                            href="https://www.wikidata.org/wiki/{{ $artist->wikidata_qid }}"
                            target="_blank"
                            rel="noreferrer"
                        >
                            Wikidata: {{ $artist->wikidata_qid }}
                        </a>
                    @endif

                    @if($artist->wikipedia_url)
                        <a
                            class="inline-flex items-center px-3 py-1 rounded bg-gray-100 hover:bg-gray-200"
                            href="{{ $artist->wikipedia_url }}"
                            target="_blank"
                            rel="noreferrer"
                        >
                            Wikipedia
                        </a>
                    @endif

                    @if($artist->official_website)
                        <a
                            class="inline-flex items-center px-3 py-1 rounded bg-gray-100 hover:bg-gray-200"
                            href="{{ $artist->official_website }}"
                            target="_blank"
                            rel="noreferrer"
                        >
                            Website
                        </a>
                    @endif
                </div>
            </div>
        </div>

        @if($albums->count())
            <h2 class="text-xl font-semibold mt-8">Albums</h2>
            <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($albums as $album)
                    <a href="{{ route('albums.show', $album) }}" class="block bg-gray-50 rounded p-4 hover:bg-gray-100">
                        <div class="font-semibold">{{ $album->title }}</div>
                        @if($album->release_year)
                            <div class="text-sm text-gray-600">{{ $album->release_year }}</div>
                        @endif
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</div>
</body>
</html>
