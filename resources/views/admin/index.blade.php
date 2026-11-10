@extends('layout')

@section('titre', 'File des demandes')
@section('noindex', true)

@section('contenu')
    @if (session('ok'))<div class="ok">{{ session('ok') }}</div>@endif

    <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap">
        <h1 style="margin:0">À vérifier ({{ $enAttente->count() }})</h1>
        <form method="post" action="{{ route('admin.logout') }}">@csrf<button class="bouton secondaire" type="submit">Déconnexion</button></form>
    </div>

    <div class="grille" style="margin:20px 0">
        <div class="carte"><div class="discret petit">Demandes reçues</div><strong style="font-size:24px">{{ $stats['demandes'] }}</strong></div>
        <div class="carte"><div class="discret petit">Annonces témoins</div><strong style="font-size:24px">{{ $stats['temoins'] }}</strong></div>
        <div class="carte"><div class="discret petit">Retours (acheté ou renoncé)</div><strong style="font-size:24px">{{ $stats['retours'] }}</strong></div>
        <div class="carte" title="Résultats avec preuve : la North Star"><div class="discret petit">Retours avec preuve</div><strong style="font-size:24px">{{ $stats['documentes'] }}</strong></div>
    </div>

    <div class="carte">
        @if ($enAttente->isEmpty())
            <p class="discret">Rien en attente.</p>
        @else
            <table>
                <tr><th>Attente</th><th>Plateforme</th><th>Annonce</th><th>Canal</th><th></th></tr>
                @foreach ($enAttente as $s)
                    <tr>
                        <td>{{ $s->created_at->diffForHumans(null, true) }}</td>
                        <td>{{ \App\Verification\AnnonceUrl::LIBELLES[$s->listing->platform] ?? $s->listing->platform }}</td>
                        <td class="coupe">{{ $s->listing->title ?: $s->listing->url_normalized }}</td>
                        <td>{{ $s->channel === 'control_sample' ? 'Témoin' : 'Demande' }}</td>
                        <td><a href="{{ route('admin.show', $s->listing) }}">Vérifier</a></td>
                    </tr>
                @endforeach
            </table>
        @endif
    </div>

    <div class="carte">
        <h3>Ajouter une annonce témoin</h3>
        <p class="discret petit">Une annonce tirée au hasard, évaluée avec la même grille : c'est ce qui permet de mesurer les fausses alertes. Objectif : 5 par semaine.</p>
        <form method="post" action="{{ route('admin.temoin') }}" style="display:flex; gap:8px; flex-wrap:wrap">
            @csrf
            <input type="url" name="url" placeholder="https://…" required style="flex:1; min-width:220px">
            <button class="bouton" type="submit">Ajouter</button>
        </form>
    </div>

    <h2>Dernières évaluations</h2>
    <div class="carte">
        <table>
            <tr><th>Date</th><th>Annonce</th><th>Indice</th><th></th></tr>
            @foreach ($recentes as $l)
                @php($a = $l->assessments->first())
                @php([$libelle, $couleur] = \App\Verification\RegleV1::verdict($a->final_score))
                <tr>
                    <td>{{ $a->created_at->format('d/m') }}</td>
                    <td class="coupe">{{ $l->title ?: $l->url_normalized }}</td>
                    <td><span class="pastille fond-{{ $couleur }}">{{ $a->final_score }}</span></td>
                    <td><a href="{{ route('admin.show', $l) }}">Ouvrir</a></td>
                </tr>
            @endforeach
        </table>
    </div>
@endsection
