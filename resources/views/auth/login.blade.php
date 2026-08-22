<x-layouts.app :title="'Connexion — '.config('keneya.name')">
    <div class="login">
        <h1 class="login__title">Connexion</h1>
        <p class="login__subtitle">{{ config('keneya.hospital') }}</p>

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
</x-layouts.app>
