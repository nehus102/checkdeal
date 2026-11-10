<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titre', "Vérifier une annonce d'occasion avant d'acheter") · {{ config('app.name') }}</title>
    <meta name="description" content="@yield('description', "Collez le lien d'une annonce d'occasion : nous vérifions le vendeur, le prix, la description et les photos, et vous donnons un indice de confiance de 0 à 100.")">
    @hasSection('noindex')<meta name="robots" content="noindex">@endif
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<header class="site">
    <div class="conteneur">
        <a class="marque" href="{{ route('accueil') }}">Vérif<span>Annonce</span></a>
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
