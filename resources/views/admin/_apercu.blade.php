{{--
    L'aperçu de l'indice. Rendu à l'ouverture de la fiche, puis à chaque
    modification d'un champ (AdminController::apercuLive) : une seule mise en
    forme, et une seule source de règles (RegleV1), pas de copie en JavaScript.
--}}
@php([$libelle, $couleur] = \App\Verification\RegleV1::verdict($apercu['score']))
@php($catalogue = \App\Verification\RegleV1::CATALOGUE)
@php($ajuste = $scoreFinal !== null && $scoreFinal !== '' && (int) $scoreFinal !== $apercu['score'])

<div class="carte">
    <h3>Indice calculé avec les champs ci-dessus</h3>
    @if ($derniere)
        <p class="discret petit">Dernière évaluation enregistrée : <strong>{{ $derniere->final_score }}/100</strong> le {{ $derniere->created_at->format('d/m à H:i') }}@if ($derniere->final_score !== $derniere->computed_score) (calculé {{ $derniere->computed_score }}, ajusté)@endif.</p>
    @else
        <p class="discret petit">Pas encore enregistré : cliquez sur « Enregistrer et envoyer ».</p>
    @endif
    @if ($prixSaisi && $nbComparables < \App\Verification\RegleV1::COMPARABLES_MIN)
        <p class="suggestion" style="display:inline-block">Prix non jugé : {{ $nbComparables }} prix comparable{{ $nbComparables > 1 ? 's' : '' }} saisi{{ $nbComparables > 1 ? 's' : '' }}, il en faut {{ \App\Verification\RegleV1::COMPARABLES_MIN }}.</p>
    @endif

    <div class="score">
        <div><span class="nombre {{ $couleur }}">{{ $apercu['score'] }}</span><span class="sur"> / 100</span></div>
        <div>
            <span class="pastille fond-{{ $couleur }}">{{ $libelle }}</span>
            <div class="discret petit">fiabilité {{ $apercu['confiance'] }} · règles {{ $apercu['version'] }}@if ($apercu['mediane']) · médiane {{ number_format($apercu['mediane'], 0, ',', ' ') }} €@endif</div>
            @if ($ajuste)
                <div class="petit"><strong>Indice envoyé : {{ max(0, min(100, (int) $scoreFinal)) }}/100</strong> (ajusté)</div>
            @endif
        </div>
    </div>

    @if ($suggestions)
        <p class="petit" style="margin-top:12px">
            Repéré dans la description :
            @foreach ($suggestions as $code => $extraits)
                <span class="suggestion">{{ $catalogue[$code][0] }} ({{ implode(', ', array_slice($extraits, 0, 3)) }})</span>
            @endforeach
        </p>
    @endif

    <table style="margin-top:12px">
        @foreach ($apercu['signaux'] as $s)
            <tr>
                <td>{{ $catalogue[$s['code']][0] ?? $s['code'] }}</td>
                <td class="{{ $s['statut'] === 'present' ? 'rouge' : ($s['statut'] === 'absent' ? 'vert' : 'discret') }}">
                    {{ ['present' => 'oui', 'absent' => 'non', 'unknown' => 'non vérifié'][$s['statut']] }}
                    @if ($s['valeur']) ({{ $s['valeur'] }})@endif
                </td>
                <td>{{ $s['points'] ? '−' . $s['points'] : '' }}</td>
            </tr>
        @endforeach
    </table>
</div>
