@extends('layout')

@section('titre', 'Votre vérification')
@section('noindex', true)
@section('largeur', 'etroit')

@section('contenu')
    @php($plateforme = \App\Verification\AnnonceUrl::LIBELLES[$listing->platform] ?? 'Autre site')

    @if (! $evaluation)
        <h1>Vérification en cours</h1>
        <div class="carte">
            <p>Nous avons bien reçu votre demande pour cette annonce {{ $plateforme }} :</p>
            <p class="coupe"><a href="{{ $listing->url }}" rel="nofollow noopener" target="_blank">{{ $listing->url }}</a></p>
            <p class="discret">Vous recevrez le résultat par e-mail, généralement sous 24 h. Vous pouvez aussi garder cette page : elle affichera le résultat dès qu'il sera prêt.</p>
        </div>
    @else
        @php([$libelle, $couleur] = $verdict)
        @php($catalogue = \App\Verification\RegleV1::CATALOGUE)
        @php($presents = $evaluation->signals->where('status', 'present')->sortByDesc('points'))
        @php($rassurants = $evaluation->signals->where('status', 'absent')->whereIn('code', ['price_below_market', 'seller_no_reviews', 'seller_rating_low', 'account_new', 'off_platform_payment', 'image_reused']))

        <h1>{{ $listing->title ?: 'Votre annonce' }}</h1>
        <p class="discret">{{ $plateforme }} · vérifiée le {{ $evaluation->created_at->format('d/m/Y') }}</p>

        <div class="carte">
            <div class="score">
                <div><span class="nombre {{ $couleur }}">{{ $evaluation->final_score }}</span><span class="sur"> / 100</span></div>
                <div>
                    <span class="pastille fond-{{ $couleur }}">{{ $libelle }}</span>
                    <div class="discret petit">Indice de confiance · fiabilité de l'analyse : {{ $evaluation->confidence }}</div>
                </div>
            </div>
            <div class="jauge"><div class="barre-{{ $couleur }}" style="width: {{ $evaluation->final_score }}%"></div></div>
        </div>

        @if ($presents->isNotEmpty())
            <div class="carte">
                <h3>Ce qui doit vous alerter</h3>
                <ul class="signaux">
                    @foreach ($presents as $s)
                        <li>
                            <strong>{{ $catalogue[$s->code][0] ?? $s->code }}</strong>
                            @if ($s->value)<span class="discret">({{ $s->value }})</span>@endif
                            <div class="discret">{{ $catalogue[$s->code][1] ?? '' }}</div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($rassurants->isNotEmpty())
            <div class="carte">
                <h3>Ce qui rassure</h3>
                <ul class="signaux">
                    @foreach ($rassurants as $s)
                        <li>Pas de signal « {{ mb_strtolower($catalogue[$s->code][0] ?? $s->code) }} »</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="carte">
            <h3>Nos conseils</h3>
            @if ($evaluation->advice)<p>{{ $evaluation->advice }}</p>@endif
            <p>{{ \App\Verification\RegleV1::CONSEIL_GENERAL }}</p>
            <p class="discret petit">Analyse indicative, fondée sur les éléments visibles le {{ $evaluation->created_at->format('d/m/Y') }}. Elle ne garantit pas la transaction et ne constitue pas une accusation envers le vendeur.</p>
        </div>
    @endif
@endsection
