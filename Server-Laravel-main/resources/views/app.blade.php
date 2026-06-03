<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'DocShare') — Documento Compartilhado</title>
 
    {{-- Google Fonts --}}
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Roboto+Mono:wght@400;500&display=swap" rel="stylesheet" />
 
    {{-- App CSS — public/css/app.css --}}
    <link rel="stylesheet" href="{{ asset('app.css') }}">
 
    @stack('styles')
</head>
<body>
 
    @yield('content')
 
    {{-- Dados injetados pelas views ANTES do app.js --}}
    @stack('scripts')

    <script src="https://cdn.jsdelivr.net/npm/pusher-js@8/dist/web/pusher.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/laravel-echo@1/dist/echo.iife.js"></script>
 
    {{-- App JS — public/js/app.js (carregado por último, window.DocShare já existe) --}}
    <script src="{{ asset('app.js') }}"></script>
 
</body>
</html>