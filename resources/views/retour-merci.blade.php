@extends('layout')

@section('titre', 'Merci')
@section('noindex', true)
@section('largeur', 'etroit')

@section('contenu')
    <h1>Merci pour votre retour</h1>
    <div class="carte">
        <p>Chaque réponse nous aide à mieux distinguer les vraies arnaques des fausses alertes.</p>
        <a class="bouton" href="{{ route('accueil') }}">Vérifier une autre annonce</a>
    </div>
@endsection
