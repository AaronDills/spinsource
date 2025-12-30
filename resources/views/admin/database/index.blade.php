<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Database Viewer
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-medium">Tables</h3>
                        <a href="{{ route('admin.database.query') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition">
                            Run Query
                        </a>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($tables as $table)
                            <a href="{{ route('admin.database.show', $table) }}" class="block p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                                <div class="font-medium text-gray-900">{{ $table }}</div>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
