<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#11100e">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Elegant Lion">
    @php
        $socialTitle = 'Elegant Lion Barbershop | Reserva tu próximo corte';
        $socialDescription = 'Tu próximo corte empieza aquí. Reserva en Elegant Lion Barbershop: elige tu servicio, barbero, fecha y horario en minutos.';
        $socialUrl = 'https://elegant-lion.ofsystem.com.pe/';
        $socialImage = $socialUrl.'logo.png';
    @endphp
    <title>{{ $socialTitle }}</title>
    <meta name="description" content="{{ $socialDescription }}">
    <meta name="robots" content="{{ request()->is('admin') ? 'noindex, nofollow' : 'index, follow' }}">
    <link rel="canonical" href="{{ $socialUrl }}">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="es_PE">
    <meta property="og:site_name" content="Elegant Lion Barbershop">
    <meta property="og:title" content="{{ $socialTitle }}">
    <meta property="og:description" content="{{ $socialDescription }}">
    <meta property="og:url" content="{{ $socialUrl }}">
    <meta property="og:image" content="{{ $socialImage }}">
    <meta property="og:image:secure_url" content="{{ $socialImage }}">
    <meta property="og:image:type" content="image/png">
    <meta property="og:image:alt" content="Logo de Elegant Lion Barbershop">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="{{ $socialTitle }}">
    <meta name="twitter:description" content="{{ $socialDescription }}">
    <meta name="twitter:image" content="{{ $socialImage }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="/logo.png" type="image/png">
    <link rel="apple-touch-icon" href="/pwa-192.png">
    <link rel="manifest" href="/manifest.webmanifest">
    @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body>
    <div id="app"></div>
</body>
</html>
