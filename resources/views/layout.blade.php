<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titre', "Vérifier une annonce d'occasion avant d'acheter") · {{ config('app.name') }}</title>
    <meta name="description" content="@yield('description', "Collez le lien d'une annonce d'occasion : nous vérifions le vendeur, le prix, la description et les photos, et vous donnons un indice de confiance de 0 à 100.")">
    @hasSection('noindex')<meta name="robots" content="noindex">@endif
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="512x512" href="{{ asset('images/checkdeal-favicon-512.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">
    <meta name="theme-color" content="#09234B">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<header class="site">
    <div class="conteneur">
        <a class="marque" href="{{ route('accueil') }}"><img src="{{ asset('images/checkdeal-logo.png') }}" alt="CheckDeal" width="160" height="40"></a>
        @if (session('admin'))
            <a href="{{ route('admin.index') }}" class="petit">Back-office</a>
        @endif
    </div>
</header>
<main>
    <div class="conteneur @yield('largeur')">
        @yield('contenu')
    </div>
</main>
<footer class="site">
    <div class="conteneur">
        Analyse indicative, fondée sur les éléments visibles à la date de la vérification. Elle ne garantit pas la transaction.
        · <a href="{{ route('confidentialite') }}">Confidentialité</a>
    </div>
</footer>
</body>
</html>
