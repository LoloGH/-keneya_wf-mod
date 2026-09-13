<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hôte de test — module Keneya-DME</title>
    <style>
        :root { color-scheme: light; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            background: #f8fafc;
            color: #0f172a;
            font: 15px/1.6 ui-sans-serif, system-ui, 'Segoe UI', Roboto, sans-serif;
        }
        .carte {
            max-width: 34rem;
            padding: 2.25rem 2.5rem;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            box-shadow: 0 1px 2px rgb(15 23 42 / 6%);
        }
        h1 { margin: 0 0 .35rem; font-size: 1.35rem; }
        p.sous-titre { margin: 0 0 1.5rem; color: #64748b; }
        a.bouton {
            display: inline-block;
            padding: .6rem 1.15rem;
            border-radius: 9px;
            background: #2563eb;
            color: #fff;
            font-weight: 600;
            text-decoration: none;
        }
        ul { margin: 1.75rem 0 0; padding-left: 1.1rem; color: #475569; font-size: 14px; }
        li { margin-bottom: .35rem; }
        code { background: #f1f5f9; padding: .1rem .35rem; border-radius: 5px; font-size: 13px; }
    </style>
</head>
<body>
    <main class="carte">
        <h1>Application hôte de test</h1>
        <p class="sous-titre">
            Application Laravel minimale servant uniquement à vérifier que le module
            Dossier Médical Électronique se monte et se navigue sans Keneya Workflow.
        </p>

        <a class="bouton" href="{{ url(config('dme.route.prefix', 'dme')) }}">
            Ouvrir le dossier médical
        </a>

        <ul>
            <li>Préfixe du module : <code>/{{ config('dme.route.prefix', 'dme') }}</code></li>
            <li>Mode autonome de développement :
                <code>{{ app(\Keneya\Dme\Standalone\StandaloneMode::class)->enabled() ? 'actif' : 'inactif' }}</code>
            </li>
            <li>Envoi de SMS : <code>{{ get_class(app(\Keneya\Dme\Contracts\SmsDispatcherContract::class)) }}</code></li>
            <li>Version du module : <code>{{ \Keneya\Dme\Dme::version() }}</code></li>
        </ul>
    </main>
</body>
</html>
