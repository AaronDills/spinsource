<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-4">
            <a href="{{ route('admin.database.index') }}" class="text-gray-500 hover:text-gray-700">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Run SQL Query
            </h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="POST" action="{{ route('admin.database.query') }}">
                        @csrf
                        <div class="mb-4">
                            <label for="sql" class="block text-sm font-medium text-gray-700 mb-2">
                                SQL Query (SELECT only)
                            </label>
                            <textarea
                                name="sql"
                                id="sql"
                                rows="4"
                                class="w-full rounded-md border-gray-300 shadow-sm font-mono text-sm"
                                placeholder="SELECT * FROM users LIMIT 10"
                            >{{ $sql }}</textarea>
                        </div>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition">
                            Execute Query
                        </button>
                    </form>

                    @if($error)
                        <div class="mt-6 p-4 bg-red-50 border border-red-200 rounded-md">
                            <p class="text-sm text-red-600">{{ $error }}</p>
                        </div>
                    @endif

                    @if($results !== null)
                        <div class="mt-6">
                            <h3 class="text-sm font-medium text-gray-700 mb-2">
                                Results ({{ count($results) }} rows)
                            </h3>

                            @if(count($results) > 0)
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                @foreach($columns as $column)
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                                        {{ $column }}
                                                    </th>
                                                @endforeach
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            @foreach($results as $row)
                                                <tr class="hover:bg-gray-50">
                                                    @foreach($columns as $column)
                                                        <td class="px-4 py-3 text-sm text-gray-900 max-w-xs truncate" title="{{ $row->$column }}">
                                                            {{ Str::limit($row->$column, 50) }}
                                                        </td>
                                                    @endforeach
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <p class="text-sm text-gray-500">Query executed successfully, but returned no results.</p>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            <!-- Quick reference -->
            <div class="mt-6 bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-sm font-medium text-gray-500 uppercase mb-3">Available Tables</h3>
                    <div class="flex flex-wrap gap-2">
                        @foreach($tables as $t)
                            <a href="{{ route('admin.database.show', $t) }}"
                               class="px-3 py-1 text-sm rounded-full bg-gray-100 text-gray-700 hover:bg-gray-200">
                                {{ $t }}
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
