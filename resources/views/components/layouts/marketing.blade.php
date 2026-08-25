@props([
    'title' => 'NexABI — Adaptive Business Intelligence',
    'description' => 'NexABI brings scattered real-estate data together, finds what is going wrong, recommends what to do about it, and lets you do it.',
    'bodyClass' => 'editorial',
])
@php
    // Stamped before any CSS parses. Doing this in JS after load produces a
    // flash of the wrong background, which reads as a broken product.
    $theme = request()->cookie('nexabi_theme', 'dark');
@endphp
<!DOCTYPE html>
<html lang="en" data-theme="{{ $theme === 'light' ? 'light' : 'dark' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}">

    <link rel="stylesheet" href="{{ asset('css/fonts.css') }}">
    <link rel="stylesheet" href="{{ asset('css/tokens.css') }}">
    <link rel="stylesheet" href="{{ asset('css/base.css') }}">
    <link rel="stylesheet" href="{{ asset('css/components.css') }}">
    <link rel="stylesheet" href="{{ asset('css/editorial.css') }}">
    <link rel="icon" href="{{ asset('favicon.ico') }}">
</head>
<body class="{{ $bodyClass }}">
    {{ $slot }}
</body>
</html>
