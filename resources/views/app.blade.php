<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="description" content="{{ $seo['description'] ?? 'Trade global markets, manage your portfolio, and access NGN and USD wallets with Xavier.' }}" />
    <meta name="robots" content="{{ $seo['robots'] ?? 'noindex,nofollow' }}" />
    <meta name="theme-color" content="#0B132B" />
    <meta property="og:type" content="website" />
    <meta property="og:site_name" content="Xavier" />
    <meta property="og:title" content="{{ $seo['title'] ?? 'Xavier Trading Platform' }}" />
    <meta property="og:description" content="{{ $seo['description'] ?? 'Trade global markets, manage your portfolio, and access NGN and USD wallets with Xavier.' }}" />
    <meta property="og:url" content="{{ $seo['canonical'] ?? request()->url() }}" />
    <meta name="twitter:card" content="summary" />
    <meta name="twitter:title" content="{{ $seo['title'] ?? 'Xavier Trading Platform' }}" />
    <meta name="twitter:description" content="{{ $seo['description'] ?? 'Trade global markets, manage your portfolio, and access NGN and USD wallets with Xavier.' }}" />
    <link rel="canonical" href="{{ $seo['canonical'] ?? request()->url() }}" />
    <title>{{ $seo['title'] ?? 'Xavier Trading Platform' }}</title>
<!--
  <script src="https://js.paystack.co/v1/inline.js"></script>
-->
  
	@vite('resources/js/app.js')
  </head>
  <body class="antialiased">
    <div id="app"></div>
  </body>
</html>
