<x-mail::message>
# Votre annonce a été vérifiée

**Indice de confiance : {{ $evaluation->final_score }} / 100 — {{ $verdict[0] }}**

@foreach ($evaluation->signals->where('status', 'present')->sortByDesc('points')->take(5) as $s)
- **{{ \App\Verification\RegleV1::CATALOGUE[$s->code][0] ?? $s->code }}** : {{ \App\Verification\RegleV1::CATALOGUE[$s->code][1] ?? '' }}
@endforeach

@if ($evaluation->advice)
{{ $evaluation->advice }}
@endif

{{ \App\Verification\RegleV1::CONSEIL_GENERAL }}

<x-mail::button :url="$lien">
Voir le détail
</x-mail::button>

<small>Analyse indicative, fondée sur les éléments visibles le {{ $evaluation->created_at->format('d/m/Y') }}. Elle ne garantit pas la transaction.</small>
</x-mail::message>
