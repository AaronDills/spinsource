@props(['transparent' => false])

<header @class([
    'w-full',
    'bg-gray-900/80 border-b border-gray-800 shadow-lg backdrop-blur' => !$transparent,
    'bg-transparent absolute top-0 left-0 right-0 z-50' => $transparent,
])>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
            <!-- Logo -->
            <div class="shrink-0 flex items-center">
                <a href="{{ route('home') }}" class="flex items-center gap-2">
                    <x-application-logo @class([
                        'block h-9 w-auto fill-current',
                        'text-white' => !$transparent,
                        'text-white' => $transparent,
                    ]) />
                    <span @class([
                        'font-semibold text-lg',
                        'text-white' => !$transparent,
                        'text-white' => $transparent,
                    ])>{{ config('app.name', 'Spin Source') }}</span>
                </a>
            </div>

            <!-- Navigation Links -->
            <nav class="hidden sm:flex items-center space-x-6">
                @auth
                    <a href="{{ route('dashboard') }}" @class([
                        'text-sm font-medium transition-colors',
                        'text-gray-300 hover:text-white' => !$transparent,
                        'text-gray-200 hover:text-white' => $transparent,
                    ])>
                        Dashboard
                    </a>
                    <a href="{{ route('account') }}" @class([
                        'text-sm font-medium transition-colors',
                        'text-gray-300 hover:text-white' => !$transparent,
                        'text-gray-200 hover:text-white' => $transparent,
                    ])>
                        Account
                    </a>
                    @if(Auth::user()->is_admin)
                        <a href="{{ route('admin.monitoring') }}" @class([
                            'text-sm font-medium transition-colors',
                            'text-gray-300 hover:text-white' => !$transparent,
                            'text-gray-200 hover:text-white' => $transparent,
                        ])>
                            Admin
                        </a>
                    @endif
                @endauth
            </nav>

            <!-- Auth Section -->
            <div class="flex items-center">
                @auth
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" @class([
                            'flex items-center text-sm font-medium transition-colors focus:outline-none',
                            'text-gray-300 hover:text-white' => !$transparent,
                            'text-gray-200 hover:text-white' => $transparent,
                        ])>
                            <span>{{ Auth::user()->name }}</span>
                            <svg class="ml-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <div x-show="open"
                             @click.away="open = false"
                             x-transition:enter="transition ease-out duration-100"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-75"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95"
                             class="absolute right-0 mt-2 w-48 bg-gray-900 border border-gray-800 text-gray-100 rounded-md shadow-lg ring-1 ring-black ring-opacity-5 z-50"
                             style="display: none;">
                            <div class="py-1">
                                <a href="{{ route('dashboard') }}" class="block px-4 py-2 text-sm text-gray-200 hover:bg-gray-800">
                                    Dashboard
                                </a>
                                <a href="{{ route('account') }}" class="block px-4 py-2 text-sm text-gray-200 hover:bg-gray-800">
                                    Account
                                </a>
                                <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-sm text-gray-200 hover:bg-gray-800">
                                    Profile Settings
                                </a>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-gray-200 hover:bg-gray-800">
                                        Log Out
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="flex items-center space-x-4">
                        <a href="{{ route('login') }}" @class([
                            'text-sm font-medium transition-colors',
                            'text-gray-300 hover:text-white' => !$transparent,
                            'text-gray-200 hover:text-white' => $transparent,
                        ])>
                            Log in
                        </a>
                        <a href="{{ route('register') }}" class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors">
                            Sign up
                        </a>
                    </div>
                @endauth
            </div>

            <!-- Mobile Menu Button -->
            <div class="sm:hidden" x-data="{ mobileOpen: false }">
                <button @click="mobileOpen = !mobileOpen" @class([
                    'p-2 rounded-md transition-colors',
                    'text-gray-400 hover:text-white hover:bg-gray-800' => !$transparent,
                    'text-gray-200 hover:text-white' => $transparent,
                ])>
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path x-show="!mobileOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path x-show="mobileOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                <!-- Mobile Menu -->
                <div x-show="mobileOpen"
                     @click.away="mobileOpen = false"
                     class="absolute top-16 left-0 right-0 bg-gray-900 border-b border-gray-800 shadow-lg z-50">
                    <div class="px-4 py-3 space-y-2">
                        @auth
                            <a href="{{ route('dashboard') }}" class="block py-2 text-gray-200 hover:text-white">Dashboard</a>
                            <a href="{{ route('account') }}" class="block py-2 text-gray-200 hover:text-white">Account</a>
                            <a href="{{ route('profile.edit') }}" class="block py-2 text-gray-200 hover:text-white">Profile Settings</a>
                            @if(Auth::user()->is_admin)
                                <a href="{{ route('admin.monitoring') }}" class="block py-2 text-gray-200 hover:text-white">Admin</a>
                            @endif
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="block w-full text-left py-2 text-gray-200 hover:text-white">Log Out</button>
                            </form>
                        @else
                            <a href="{{ route('login') }}" class="block py-2 text-gray-200 hover:text-white">Log in</a>
                            <a href="{{ route('register') }}" class="block py-2 text-blue-400 hover:text-blue-300 font-medium">Sign up</a>
                        @endauth
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>
