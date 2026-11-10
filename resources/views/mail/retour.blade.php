<x-mail::message>
# Alors, cette annonce ?

Il y a quelques jours, nous avons vérifié pour vous {{ $titre ? '« ' . $titre . ' »' : 'une annonce' }}. Qu'avez-vous décidé ? Un clic suffit :

@foreach ($choix as $c)
- [{{ $c['libelle'] }}]({{ $c['lien'] }})
@endforeach

Votre réponse nous aide à distinguer les vraies arnaques des fausses alertes, et donc à mieux protéger les prochains acheteurs.

Merci !
</x-mail::message>
