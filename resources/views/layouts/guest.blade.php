<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'AbiPlaner') }}</title>

        <!-- Favicons -->
        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
        <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">

        <!-- Fonts: selbstgehostet via @fontsource/figtree in resources/css/app.css -->

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen flex flex-col sm:justify-center items-center px-4 pt-10 sm:pt-0 bg-gradient-to-b from-slate-50 to-gray-100 dark:from-gray-900 dark:to-gray-900">
            <a href="/" class="flex flex-col items-center">
                <img class="h-28 w-auto rounded-2xl bg-white p-2 shadow-sm ring-1 ring-gray-200" src="{{ asset('assets/landing/logo.png') }}" alt="AbiPlaner" />
            </a>
            <p class="mt-4 text-sm text-gray-500 dark:text-gray-400 text-center">
                Planung von Abitur, Kolloquium und Oberstufe
            </p>

            <div class="w-full sm:max-w-md mt-6 px-6 py-6 sm:px-8 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                {{ $slot }}
            </div>
            <div class="h-10"></div>
        </div>
    </body>
</html>
