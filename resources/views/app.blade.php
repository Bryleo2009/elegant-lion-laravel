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
    <meta name="description" content="Agenda tu próximo corte en Elegant Lion Barbershop.">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Elegant Lion | Barbería</title>
    <link rel="icon" href="/logo.png" type="image/png">
    <link rel="apple-touch-icon" href="/pwa-192.png">
    <link rel="manifest" href="/manifest.webmanifest">
    @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body>
    <div id="app"></div>
</body>
</html>
