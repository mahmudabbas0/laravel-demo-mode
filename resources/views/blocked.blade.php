<!doctype html>
<html lang="{{ str_replace('_', '-', \PortfolioLabs\DemoMode\DemoMode::locale()) }}" dir="{{ \PortfolioLabs\DemoMode\DemoMode::message('direction') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ \PortfolioLabs\DemoMode\DemoMode::message('title') }}</title>
</head>
<body>
    <main>
        <h1>{{ \PortfolioLabs\DemoMode\DemoMode::message('title') }}</h1>
        <p>{{ $message }}</p>
        <a href="{{ $returnTo }}">{{ \PortfolioLabs\DemoMode\DemoMode::message('return') }}</a>
    </main>
</body>
</html>
