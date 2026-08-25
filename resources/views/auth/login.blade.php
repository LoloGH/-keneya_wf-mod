<x-layouts.auth :title="'Connexion — '.config('keneya.name')">
    <div class="login">
        {{-- Logo complet, pleine largeur de la carte : il porte deja le nom du
             produit, inutile de le repeter a cote. --}}
        <x-brand-logo lockup class="login__logo" />

        <h1 class="login__title">Connexion</h1>
        <p class="login__subtitle">{{ hospital_name() }}</p>

        <form method="POST" action="{{ route('login.store') }}" class="form">
            @csrf

            <div class="field">
                <label for="email">Adresse e-mail</label>
                <input id="email" name="email" type="email" inputmode="email" autocomplete="username"
                       value="{{ old('email') }}" required autofocus>
                @error('email') <p class="field__error">{{ $message }}</p> @enderror
            </div>

            <div class="field">
                <label for="password">Mot de passe</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required>
                @error('password') <p class="field__error">{{ $message }}</p> @enderror
            </div>

            <label class="field field--inline">
                <input type="checkbox" name="remember" value="1">
                <span>Rester connecte sur ce poste</span>
            </label>

            <button type="submit" class="btn btn--primary btn--block">Se connecter</button>
        </form>
    </div>
</x-layouts.auth>
