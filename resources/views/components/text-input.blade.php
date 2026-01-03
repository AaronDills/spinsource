@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'bg-gray-900 border border-gray-700 text-gray-100 placeholder-gray-500 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm']) }}>
