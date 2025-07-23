<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full border-none">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Register') }} - {{ config('app.name') }}</title>

    <!-- Preload critical assets -->
    <link rel="preload" href="{{ asset('assets/images/indirimgo_logo.png') }}" as="image">
    <link rel="dns-prefetch" href="https://fonts.bunny.net">

    <!-- Fonts - Load only what we need -->
    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet">

    <!-- Critical CSS inline -->
    <style>
        html {
            border: unset !important;
        }
        /* Critical CSS for above-the-fold content */
        .login-bg {
            background: linear-gradient(135deg, #f8fafc 0%, #e0e7ff 50%, #c7d2fe 100%);
        }

        .dark .login-bg {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%);
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
        }

        .dark .glass-card {
            background: rgba(31, 41, 55, 0.9);
        }

        .gradient-text {
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .gradient-btn {
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
        }

        .gradient-btn:hover {
            background: linear-gradient(135deg, #2563eb, #7c3aed);
        }

        /* Optimized animations */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in {
            animation: fadeIn 0.6s ease-out;
        }

        /* Reduce motion for accessibility */
        @media (prefers-reduced-motion: reduce) {
            .fade-in {
                animation: none;
            }
        }
    </style>

    <!-- Scripts - Load asynchronously -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full login-bg border-none">
    <div class="min-h-screen flex items-center justify-center py-8 px-4 sm:px-6 lg:px-8">

        <!-- Main Content -->
        <div class="w-full max-w-md fade-in">

            <!-- Logo Section -->
            <div class="text-center mb-8">
                <div class="flex justify-center mb-6">
                    <div class="relative">
                        <!-- Logo Container with Gradient Border -->
                        <div class="absolute inset-0 bg-gradient-to-r from-blue-500 to-purple-600 rounded-2xl blur-lg opacity-75"></div>
                        <div class="relative bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-2xl">
                            <img src="{{ asset('assets/images/indirimgo_logo.png') }}"
                                 alt="{{ config('app.name') }}"
                                 class="w-24 h-24 mx-auto object-contain"
                                 loading="eager">
                        </div>
                    </div>
                </div>

                <!-- Welcome Text -->
                <div class="space-y-2">
                    <h1 class="text-3xl font-bold gradient-text">
                        {{ __('Create Account') }}
                    </h1>
                    <p class="text-gray-600 dark:text-gray-400 text-sm">
                        {{ __('Join us and start your journey') }}
                    </p>
                </div>
            </div>

            <!-- Register Form Card -->
            <div class="glass-card rounded-2xl p-6 shadow-xl border border-white/20 dark:border-gray-700/20">

                <!-- Validation Errors -->
                @if ($errors->any())
                    <div class="mb-6 p-3 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
                        <div class="flex items-center text-red-800 dark:text-red-200 mb-2">
                            <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                      d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                                      clip-rule="evenodd"></path>
                            </svg>
                            <span class="text-sm font-medium">{{ __('Please fix the following errors:') }}</span>
                        </div>
                        <ul class="text-xs space-y-1">
                            @foreach ($errors->all() as $error)
                                <li class="flex items-center">
                                    <span class="w-1 h-1 bg-red-600 rounded-full mr-2"></span>
                                    {{ $error }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <!-- Register Form -->
                <form method="POST" action="{{ route('register') }}"
                      x-data="{
                          showPassword: false,
                          showConfirmPassword: false,
                          isLoading: false,
                          name: '{{ old('name') }}',
                          email: '{{ old('email') }}',
                          password: '',
                          password_confirmation: '',
                          terms: false
                      }"
                      @submit="isLoading = true">
                    @csrf

                    <!-- Name Field -->
                    <div class="space-y-2 mb-4">
                        <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ __('Full Name') }}
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                            </div>
                            <input id="name"
                                   name="name"
                                   type="text"
                                   x-model="name"
                                   required
                                   autofocus
                                   autocomplete="name"
                                   class="block w-full pl-10 pr-4 py-3 border-0 rounded-lg bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:bg-white dark:focus:bg-gray-600 transition-colors duration-200"
                                   placeholder="{{ __('Enter your full name') }}">
                        </div>
                    </div>

                    <!-- Email Field -->
                    <div class="space-y-2 mb-4">
                        <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ __('Email Address') }}
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"></path>
                                </svg>
                            </div>
                            <input id="email"
                                   name="email"
                                   type="email"
                                   x-model="email"
                                   required
                                   autocomplete="username"
                                   class="block w-full pl-10 pr-4 py-3 border-0 rounded-lg bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:bg-white dark:focus:bg-gray-600 transition-colors duration-200"
                                   placeholder="{{ __('Enter your email') }}">
                        </div>
                    </div>

                    <!-- Password Field -->
                    <div class="space-y-2 mb-4">
                        <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ __('Password') }}
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                </svg>
                            </div>
                            <input id="password"
                                   name="password"
                                   :type="showPassword ? 'text' : 'password'"
                                   x-model="password"
                                   required
                                   autocomplete="new-password"
                                   class="block w-full pl-10 pr-10 py-3 border-0 rounded-lg bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:bg-white dark:focus:bg-gray-600 transition-colors duration-200"
                                   placeholder="{{ __('Create a password') }}">
                            <button type="button"
                                    @click="showPassword = !showPassword"
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors duration-200">
                                <svg x-show="!showPassword" class="h-4 w-4" fill="none" stroke="currentColor"
                                     viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                                <svg x-show="showPassword" class="h-4 w-4" fill="none" stroke="currentColor"
                                     viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"></path>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Confirm Password Field -->
                    <div class="space-y-2 mb-6">
                        <label for="password_confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ __('Confirm Password') }}
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <input id="password_confirmation"
                                   name="password_confirmation"
                                   :type="showConfirmPassword ? 'text' : 'password'"
                                   x-model="password_confirmation"
                                   required
                                   autocomplete="new-password"
                                   class="block w-full pl-10 pr-10 py-3 border-0 rounded-lg bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:bg-white dark:focus:bg-gray-600 transition-colors duration-200"
                                   placeholder="{{ __('Confirm your password') }}">
                            <button type="button"
                                    @click="showConfirmPassword = !showConfirmPassword"
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors duration-200">
                                <svg x-show="!showConfirmPassword" class="h-4 w-4" fill="none" stroke="currentColor"
                                     viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                                <svg x-show="showConfirmPassword" class="h-4 w-4" fill="none" stroke="currentColor"
                                     viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"></path>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Terms and Conditions -->
                    @if (Laravel\Jetstream\Jetstream::hasTermsAndPrivacyPolicyFeature())
                        <div class="mb-6">
                            <label class="flex items-start cursor-pointer group">
                                <div class="relative mt-0.5">
                                    <input type="checkbox"
                                           name="terms"
                                           id="terms"
                                           x-model="terms"
                                           required
                                           class="sr-only">
                                    <div
                                        class="w-4 h-4 border-2 border-gray-300 dark:border-gray-600 rounded group-hover:border-blue-500 transition-colors duration-200"
                                        :class="{ 'bg-blue-500 border-blue-500': terms }">
                                        <svg x-show="terms" class="w-2.5 h-2.5 text-white mx-auto mt-0.5" fill="currentColor"
                                             viewBox="0 0 20 20">
                                            <path fill-rule="evenodd"
                                                  d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                                  clip-rule="evenodd"></path>
                                        </svg>
                                    </div>
                                </div>
                                <span class="ml-3 text-sm text-gray-700 dark:text-gray-300 group-hover:text-gray-900 dark:group-hover:text-white transition-colors duration-200">
                                    {!! __('I agree to the :terms_of_service and :privacy_policy', [
                                            'terms_of_service' => '<a target="_blank" href="'.route('terms.show').'" class="text-blue-600 dark:text-blue-400 hover:text-blue-500 dark:hover:text-blue-300 hover:underline">'.__('Terms of Service').'</a>',
                                            'privacy_policy' => '<a target="_blank" href="'.route('policy.show').'" class="text-blue-600 dark:text-blue-400 hover:text-blue-500 dark:hover:text-blue-300 hover:underline">'.__('Privacy Policy').'</a>',
                                    ]) !!}
                                </span>
                            </label>
                        </div>
                    @endif

                    <!-- Submit Button -->
                    <button type="submit"
                            :disabled="isLoading"
                            class="group relative w-full flex justify-center py-3 px-4 border border-transparent rounded-lg text-sm font-semibold text-white gradient-btn focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200 shadow-lg hover:shadow-xl">

                        <!-- Loading Spinner -->
                        <svg x-show="isLoading" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none"
                             viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor"
                                  d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>

                        <!-- Button Text -->
                        <span x-show="!isLoading">{{ __('Create Account') }}</span>
                        <span x-show="isLoading">{{ __('Creating Account...') }}</span>
                    </button>
                </form>

                <!-- Sign In Link -->
                <div class="text-center mt-6">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        {{ __('Already have an account?') }}
                        <a href="{{ route('login') }}"
                           class="font-semibold text-blue-600 dark:text-blue-400 hover:text-blue-500 dark:hover:text-blue-300 transition-colors duration-200 hover:underline">
                            {{ __('Sign in') }}
                        </a>
                    </p>
                </div>
            </div>

            <!-- Footer -->
            <div class="text-center mt-6">
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    {{ __('By creating an account, you agree to our') }}
                    <a href="#" class="text-blue-600 dark:text-blue-400 hover:underline">{{ __('Terms of Service') }}</a>
                    {{ __('and') }}
                    <a href="#" class="text-blue-600 dark:text-blue-400 hover:underline">{{ __('Privacy Policy') }}</a>
                </p>
            </div>
        </div>
    </div>

    @livewireScripts
</body>
</html>
