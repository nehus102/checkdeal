@extends('layout')

@section('titre', 'Vérifier une annonce')
@section('noindex', true)

@section('contenu')
    {{-- old() d'abord : après une saisie refusée, on retrouve ce qu'on avait tapé. --}}
    @php($v = fn (string $champ) => old($champ, $saisie[$champ] ?? $obs[$champ] ?? ''))
    @php($catalogue = \App\Verification\RegleV1::CATALOGUE)

    <p><a href="{{ route('admin.index') }}">← File des demandes</a></p>
    @if (session('ok'))<div class="ok">{{ session('ok') }}</div>@endif
    <h1 style="font-size:26px">{{ $listing->title ?: 'Annonce #' . $listing->id }}</h1>
    <p class="coupe">
        {{ \App\Verification\AnnonceUrl::LIBELLES[$listing->platform] ?? $listing->platform }} ·
        <a href="{{ $listing->url }}" target="_blank" rel="noopener noreferrer">ouvrir l'annonce</a>
    </p>

    <div class="deux-colonnes">
        {{-- Un double clic enregistrait deux évaluations : le second envoi est bloqué. --}}
        <form method="post" action="{{ route('admin.evaluer', $listing) }}" onsubmit="if (this.dataset.envoye) { return false; } this.dataset.envoye = '1';">
            @csrf
            @if ($errors->any())
                <div class="erreurs">{{ $errors->first() }}</div>
            @endif

            <div class="carte">
                <h3>L'annonce</h3>
                <div class="ligne">
                    <div><label for="title">Titre</label><input type="text" id="title" name="title" value="{{ $v('title') }}"></div>
                    <div><label for="category">Catégorie</label><input type="text" id="category" name="category" value="{{ $v('category') }}" placeholder="smartphone, vélo, console…"></div>
                </div>
                <div class="ligne">
                    <div><label for="price_amount">Prix (€)</label><input type="text" id="price_amount" name="price_amount" value="{{ $v('price_amount') }}" inputmode="decimal"></div>
                    <div>
                        <label for="comparable_prices">Prix comparables</label>
                        <input type="text" id="comparable_prices" name="comparable_prices" value="{{ $v('comparable_prices') }}" placeholder="450 ; 520 ; 480">
                    </div>
                </div>
                <p class="discret petit">Au moins {{ \App\Verification\RegleV1::COMPARABLES_MIN }} annonces équivalentes (même produit, même état), séparées par « ; ». Sinon le prix reste « non vérifié ».</p>
                <label for="description">Description de l'annonce (copiée-collée)</label>
                <textarea id="description" name="description">{{ $v('description') }}</textarea>
            </div>

            <div class="carte">
                <h3>Le vendeur</h3>
                <div class="ligne">
                    <div><label for="seller_rating">Note</label><input type="text" id="seller_rating" name="seller_rating" value="{{ $v('seller_rating') }}" inputmode="decimal"></div>
                    <div><label for="seller_rating_max">Sur</label><input type="text" id="seller_rating_max" name="seller_rating_max" value="{{ $v('seller_rating_max') ?: 5 }}" inputmode="decimal"></div>
                    <div><label for="seller_review_count">Nombre d'avis</label><input type="number" min="0" id="seller_review_count" name="seller_review_count" value="{{ $v('seller_review_count') }}"></div>
                    <div><label for="seller_account_created_at">Compte créé le</label><input type="date" id="seller_account_created_at" name="seller_account_created_at" value="{{ $v('seller_account_created_at') }}"></div>
                </div>
                <p class="discret petit">Note sur 100 pour un pourcentage d'avis positifs (eBay). Seul le mois est affiché ? Mettez le 1er du mois. On ne relève jamais le nom du vendeur.</p>
            </div>

            <div class="carte">
                <h3>Signaux à confirmer</h3>
                <p class="discret petit">« Non vérifié » n'est pas « non » : il ne compte ni pour ni contre, mais baisse la fiabilité des statistiques.</p>
                @foreach (\App\Verification\RegleV1::DRAPEAUX as $code => $poids)
                    @php($valeur = old('flag_' . $code, $saisie['flag_' . $code] ?? $obs['flag_' . $code] ?? (isset($suggestions[$code]) ? 'oui' : '')))
                    <div class="ligne" style="align-items:center">
                        <label for="flag_{{ $code }}" style="margin:8px 0">
                            {{ $catalogue[$code][0] }} <span class="discret petit">(−{{ $poids }})</span>
                            @isset($suggestions[$code])
                                <span class="suggestion" title="Repéré dans la description">repéré : {{ implode(', ', array_slice($suggestions[$code], 0, 3)) }}</span>
                            @endisset
                        </label>
                        <select id="flag_{{ $code }}" name="flag_{{ $code }}">
                            <option value="" @selected($valeur === '')>Non vérifié</option>
                            <option value="non" @selected($valeur === 'non')>Non</option>
                            <option value="oui" @selected($valeur === 'oui')>Oui</option>
                        </select>
                    </div>
                @endforeach
            </div>

            @if ($apercu)
                @php([$libelle, $couleur] = \App\Verification\RegleV1::verdict($apercu['score']))
                @php($derniere = $historique->first())
                @php($nbComparables = count(\App\Verification\RegleV1::liste($v('comparable_prices'))))
                <div class="carte">
                    <h3>Indice calculé avec les champs ci-dessus</h3>
                    @if ($derniere)
                        <p class="discret petit">Dernière évaluation enregistrée : <strong>{{ $derniere->final_score }}/100</strong> le {{ $derniere->created_at->format('d/m à H:i') }}@if ($derniere->final_score !== $derniere->computed_score) (calculé {{ $derniere->computed_score }}, ajusté)@endif.</p>
                    @else
                        <p class="discret petit">Pas encore enregistré : cliquez sur « Enregistrer et envoyer ».</p>
                    @endif
                    @if ($v('price_amount') !== '' && $nbComparables < \App\Verification\RegleV1::COMPARABLES_MIN)
                        <p class="suggestion" style="display:inline-block">Prix non jugé : {{ $nbComparables }} prix comparable{{ $nbComparables > 1 ? 's' : '' }} saisi{{ $nbComparables > 1 ? 's' : '' }}, il en faut {{ \App\Verification\RegleV1::COMPARABLES_MIN }}.</p>
                    @endif
                    <div class="score">
                        <div><span class="nombre {{ $couleur }}">{{ $apercu['score'] }}</span><span class="sur"> / 100</span></div>
                        <div><span class="pastille fond-{{ $couleur }}">{{ $libelle }}</span><div class="discret petit">fiabilité {{ $apercu['confiance'] }} · règles {{ $apercu['version'] }}@if ($apercu['mediane']) · médiane {{ number_format($apercu['mediane'], 0, ',', ' ') }} €@endif</div></div>
                    </div>
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
            @endif

            <div class="carte">
                <h3>Résultat envoyé</h3>
                <div class="ligne">
                    <div><label for="score_final">Ajuster l'indice <span class="discret">(facultatif)</span></label><input type="number" min="0" max="100" id="score_final" name="score_final" value="{{ old('score_final', $saisie['score_final'] ?? '') }}"></div>
                    <div><label for="raison_ajustement">Pourquoi <span class="discret">(obligatoire si vous ajustez)</span></label><input type="text" id="raison_ajustement" name="raison_ajustement" value="{{ old('raison_ajustement', $saisie['raison_ajustement'] ?? '') }}"></div>
                </div>
                <label for="conseil">Conseil personnalisé pour l'acheteur</label>
                <textarea id="conseil" name="conseil" placeholder="Ex. : demandez la facture et le numéro de série avant de vous déplacer.">{{ old('conseil', $saisie['conseil'] ?? '') }}</textarea>
                <label for="justification">Note interne <span class="discret">(non envoyée)</span></label>
                <textarea id="justification" name="justification">{{ old('justification', $saisie['justification'] ?? '') }}</textarea>

                <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:16px">
                    <button class="bouton secondaire" type="submit" name="action" value="apercu">Calculer l'aperçu</button>
                    <button class="bouton" type="submit" name="action" value="envoyer">Enregistrer et envoyer</button>
                </div>
            </div>
        </form>

        <div>
            <div class="carte">
                <h3>Pièces jointes</h3>
                @php($images = $listing->evidence->filter(fn ($e) => $e->path && str_starts_with((string) $e->mime, 'image/')))
                @if ($listing->evidence->isEmpty())
                    <p class="discret">Aucune.</p>
                @else
                    <div class="vignettes">
                        @foreach ($images as $e)
                            <a href="{{ route('admin.preuve', $e) }}" target="_blank"><img src="{{ route('admin.preuve', $e) }}" alt="{{ $e->type }}"></a>
                        @endforeach
                    </div>
                    @foreach ($listing->evidence->reject(fn ($e) => $images->contains($e)) as $e)
                        <div class="petit">{{ $e->type }} · {{ $e->path ? '' : 'purgée' }} @if ($e->path)<a href="{{ route('admin.preuve', $e) }}" target="_blank">{{ $e->original_name }}</a>@endif</div>
                    @endforeach
                @endif
            </div>

            <div class="carte">
                <h3>Demandes ({{ $listing->submissions->count() }})</h3>
                @foreach ($listing->submissions as $s)
                    <div class="petit">{{ $s->created_at->format('d/m H:i') }} · {{ $s->channel === 'control_sample' ? 'témoin' : 'demande' }} · {{ $s->status === 'done' ? 'répondue' : 'en attente' }}</div>
                @endforeach
            </div>

            <div class="carte">
                <h3>Retours</h3>
                @forelse ($listing->outcomes as $o)
                    <div class="petit">{{ $o->created_at->format('d/m') }} · {{ $o->purchase_decision }} · {{ $o->label ?? '—' }} · {{ $o->certainty }}@if ($o->comment)<br><span class="discret">{{ $o->comment }}</span>@endif</div>
                @empty
                    <p class="discret">Aucun pour l'instant.</p>
                @endforelse
            </div>

            <div class="carte">
                <h3>Évaluations précédentes</h3>
                @forelse ($historique as $a)
                    <div class="petit">{{ $a->created_at->format('d/m H:i') }} · {{ $a->final_score }}/100 (calculé {{ $a->computed_score }}) · {{ $a->ruleset_version }}@if ($a->override_reason) · {{ $a->override_reason }}@endif</div>
                @empty
                    <p class="discret">Aucune.</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection
