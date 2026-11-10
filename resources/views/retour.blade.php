@extends('layout')

@section('titre', 'Votre retour')
@section('noindex', true)
@section('largeur', 'etroit')

@section('contenu')
    <h1>Qu'avez-vous décidé ?</h1>
    <p class="discret">{{ $submission->listing->title ?: $submission->listing->url }}</p>

    <div class="carte">
        <form method="post" action="{{ route('retour.store', $submission->token) }}" enctype="multipart/form-data">
            @csrf
            @if ($errors->any())
                <div class="erreurs">{{ $errors->first() }}</div>
            @endif

            @foreach (\App\Http\Controllers\RetourController::CHOIX as $code => $libelle)
                <label class="case">
                    <input type="radio" name="choix" value="{{ $code }}" @checked(old('choix', $choix) === $code)>
                    <span>{{ $libelle }}</span>
                </label>
            @endforeach

            <div class="carte" style="margin-top: 16px">
                <h3>En cas de problème</h3>
                <label for="probleme">Ce qui s'est passé</label>
                <select id="probleme" name="probleme">
                    <option value="">—</option>
                    @foreach (\App\Http\Controllers\RetourController::PROBLEMES as $code => [$libelle])
                        <option value="{{ $code }}" @selected(old('probleme') === $code)>{{ $libelle }}</option>
                    @endforeach
                </select>

                <label for="preuves">Une preuve <span class="discret">(facultatif, mais précieux)</span></label>
                <input type="file" id="preuves" name="preuves[]" accept="image/png,image/jpeg,image/webp,application/pdf" multiple>
                <p class="discret petit">Capture des échanges, photo de l'objet reçu, page de paiement suspecte… Elle sert uniquement à améliorer nos vérifications.</p>
            </div>

            <label for="commentaire">Un commentaire <span class="discret">(facultatif)</span></label>
            <textarea id="commentaire" name="commentaire">{{ old('commentaire') }}</textarea>

            <button class="bouton large" type="submit">Envoyer ma réponse</button>
        </form>
    </div>
@endsection
