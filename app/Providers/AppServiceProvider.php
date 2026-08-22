<?php

namespace App\Providers;

use App\Listeners\LogAuthenticationActivity;
use App\Models\Patient;
use App\Models\PatientHistory;
use App\Models\Visitor;
use App\Observers\PatientHistoryObserver;
use App\Observers\PatientObserver;
use App\Observers\VisitorObserver;
use App\Services\SmsGateway;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SmsGateway::class, function ($app): SmsGateway {
            $config = $app['config']->get('services.smsgate');

            return new SmsGateway(
                http: $app->make(HttpFactory::class),
                baseUrl: $config['url'] ?? null,
                login: $config['login'] ?? null,
                password: $config['password'] ?? null,
                timeout: (int) ($config['timeout'] ?? 10),
                enabled: (bool) ($config['enabled'] ?? true),
            );
        });
    }

    public function boot(): void
    {
        Patient::observe(PatientObserver::class);
        Visitor::observe(VisitorObserver::class);
        PatientHistory::observe(PatientHistoryObserver::class);

        Event::listen(Login::class, [LogAuthenticationActivity::class, 'handleLogin']);
        Event::listen(Logout::class, [LogAuthenticationActivity::class, 'handleLogout']);

        // Nom de l'etablissement affiche dans la barre de navigation : lu en
        // base (table `settings`) pour etre modifiable sans redeploiement, avec
        // repli sur la configuration tant qu'aucune valeur n'est enregistree.
        View::composer('components.layouts.*', function ($view): void {
            $view->with('hospitalName', hospital_name());
        });

        // Dates et durees en francais (« il y a 5 minutes »), comme le reste
        // de l'interface.
        Date::setLocale(config('app.locale'));
    }
}
