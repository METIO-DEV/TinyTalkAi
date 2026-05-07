<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title inertia>{{ config('app.name', 'TinyTalkAI') }}</title>
    <link rel="icon" href="{{ asset('TinyTalkAi_Logo.png') }}" type="image/png">

    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body class="h-full bg-background font-sans text-foreground antialiased">
    @inertia
</body>
</html>
