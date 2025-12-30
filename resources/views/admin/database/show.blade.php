<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-4">
            <a href="{{ route('admin.database.index') }}" class="text-gray-500 hover:text-gray-700">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Table: {{ $table }}
            </h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="flex justify-between items-center mb-4">
                        <div class="text-sm text-gray-600">
                            {{ $records->total() }} records
                        </div>
                        <div class="flex items-center gap-4">
                            <form method="GET" class="flex items-center gap-2">
                                <label class="text-sm text-gray-600">Per page:</label>
                                <select name="per_page" onchange="this.form.submit()" class="rounded-md border-gray-300 shadow-sm text-sm">
                                    @foreach([10, 25, 50, 100] as $size)
                                        <option value="{{ $size }}" {{ request('per_page', 25) == $size ? 'selected' : '' }}>{{ $size }}</option>
                                    @endforeach
                                </select>
                            </form>
                        </div>
                    </div>

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
                                @forelse($records as $record)
                                    <tr class="hover:bg-gray-50">
                                        @foreach($columns as $column)
                                            <td class="px-4 py-3 text-sm text-gray-900 max-w-xs truncate" title="{{ $record->$column }}">
                                                {{ Str::limit($record->$column, 50) }}
                                            </td>
                                        @endforeach
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ count($columns) }}" class="px-4 py-8 text-center text-gray-500">
                                            No records found
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        {{ $records->links() }}
                    </div>
                </div>
            </div>

            <!-- Sidebar with table list -->
            <div class="mt-6 bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-sm font-medium text-gray-500 uppercase mb-3">All Tables</h3>
                    <div class="flex flex-wrap gap-2">
                        @foreach($tables as $t)
                            <a href="{{ route('admin.database.show', $t) }}"
                               class="px-3 py-1 text-sm rounded-full {{ $t === $table ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                                {{ $t }}
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
