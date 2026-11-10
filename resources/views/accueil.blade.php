@extends('layout')

@section('contenu')
    <div class="deux-colonnes">
        <div>
            <h1>Avant d'acheter d'occasion, faites vérifier l'annonce.</h1>
            <p class="chapeau">Collez le lien. Nous examinons le vendeur, le prix, la description et les photos, et vous renvoyons un <strong>indice de confiance de 0 à 100</strong>, avec les raisons et nos conseils.</p>
            <ol class="etapes">
                <li>Vous collez le lien de l'annonce (Leboncoin, Vinted, eBay, Facebook Marketplace…).</li>
                <li>Un analyste la vérifie point par point.</li>
                <li>Vous recevez le résultat par e-mail, généralement sous 24 h.</li>
            </ol>
        </div>

        <div class="carte">
            <form method="post" action="{{ route('demande.store') }}" enctype="multipart/form-data">
                @csrf
                @if ($errors->any())
                    <div class="erreurs">{{ $errors->first() }}</div>
                @endif

                <label for="url">Lien de l'annonce</label>
                <input type="url" id="url" name="url" value="{{ old('url') }}" placeholder="https://www.leboncoin.fr/…" required>

                <label for="email">Votre e-mail</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="pour recevoir le résultat" required>

                <label for="captures">Captures d'écran <span class="discret">(facultatif)</span></label>
                <input type="file" id="captures" name="captures[]" accept="image/png,image/jpeg,image/webp" multiple>
                <p class="discret petit">Utile si l'annonce risque d'être retirée, ou pour nous montrer vos échanges avec le vendeur. 5 images au maximum.</p>

                <label class="case">
                    <input type="checkbox" name="consent_followup" value="1" @checked(old('consent_followup'))>
                    <span>Demandez-moi dans quelques jours si j'ai acheté (2 e-mails au plus). Vos retours aident à mieux repérer les arnaques.</span>
                </label>

                <button class="bouton large" type="submit">Vérifier cette annonce</button>
            </form>
        </div>
    </div>

    <h2>Ce que nous vérifions</h2>
    <div class="grille">
        <div class="carte"><h3>Le vendeur</h3><p class="discret">Sa note, son nombre d'avis, l'ancienneté de son compte.</p></div>
        <div class="carte"><h3>Le prix</h3><p class="discret">Comparé aux annonces équivalentes : un prix trop bas est l'appât le plus courant.</p></div>
        <div class="carte"><h3>La description et les photos</h3><p class="discret">Texte copié, photos trouvées ailleurs, incohérences.</p></div>
        <div class="carte"><h3>Les conditions proposées</h3><p class="discret">Paiement ou contact hors de la plateforme, pression pour conclure vite.</p></div>
    </div>
@endsection
