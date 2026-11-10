@extends('layout')

@section('titre', 'Back-office')
@section('noindex', true)
@section('largeur', 'etroit')

@section('contenu')
    <h1>Back-office</h1>
    <div class="carte">
        <form method="post" action="{{ route('admin.login') }}">
            @csrf
            @if ($errors->any())
                <div class="erreurs">{{ $errors->first() }}</div>
            @endif
            <label for="password">Mot de passe</label>
            <input type="password" id="password" name="password" required autofocus>
            <button class="bouton large" type="submit">Entrer</button>
        </form>
    </div>
@endsection
