<?php

declare(strict_types=1);

namespace Keneya\Dme;

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use Keneya\Dme\Access\HostAccessGate;
use Keneya\Dme\Console\Commands\SyncDutyPeriods;
use Keneya\Dme\Contracts\SmsDispatcherContract;
use Keneya\Dme\Http\Middleware\EnsureHostGrantsAccess;
use Keneya\Dme\Http\Middleware\RecordMedicalAccess;
use Keneya\Dme\Models\Appointment;
use Keneya\Dme\Models\AppSetting;
use Keneya\Dme\Models\AuditLog;
use Keneya\Dme\Models\CareOrder;
use Keneya\Dme\Models\Consultation;
use Keneya\Dme\Models\Diagnosis;
use Keneya\Dme\Models\Hospitalization;
use Keneya\Dme\Models\ImagingOrder;
use Keneya\Dme\Models\ImagingReport;
use Keneya\Dme\Models\LabOrder;
use Keneya\Dme\Models\LabResult;
use Keneya\Dme\Models\MedicalDocument;
use Keneya\Dme\Models\NursingNote;
use Keneya\Dme\Models\Patient;
use Keneya\Dme\Models\Prescription;
use Keneya\Dme\Models\SmsMessage;
use Keneya\Dme\Models\VitalSign;
use Keneya\Dme\Patients\PatientIdentifierResolver;
use Keneya\Dme\Policies\AppointmentPolicy;
use Keneya\Dme\Policies\AuditLogPolicy;
use Keneya\Dme\Policies\CareOrderPolicy;
use Keneya\Dme\Policies\ConsultationPolicy;
use Keneya\Dme\Policies\DiagnosisPolicy;
use Keneya\Dme\Policies\HospitalizationPolicy;
use Keneya\Dme\Policies\ImagingOrderPolicy;
use Keneya\Dme\Policies\LabOrderPolicy;
use Keneya\Dme\Policies\MedicalDocumentPolicy;
use Keneya\Dme\Policies\NursingNotePolicy;
use Keneya\Dme\Policies\PatientPolicy;
use Keneya\Dme\Policies\PrescriptionPolicy;
use Keneya\Dme\Policies\SmsMessagePolicy;
use Keneya\Dme\Policies\UserPolicy;
use Keneya\Dme\Policies\VitalSignPolicy;
use Keneya\Dme\Sms\LogSmsDispatcher;
use Keneya\Dme\Sms\Pipeline\Console\CheckSmsGateway;
use Keneya\Dme\Sms\Pipeline\Console\RefreshSmsStatuses;
use Keneya\Dme\Sms\Pipeline\QueuedSmsDispatcher;
use Keneya\Dme\Sms\Pipeline\SmsGatewayManager;
use Keneya\Dme\Standalone\StandaloneMode;
use Keneya\Dme\Standalone\StartDevSession;

/**
 * Montage du module Dossier Médical Électronique dans une application
 * Laravel hôte.
 *
 * Le module s'installe sans rien exiger de l'hôte au-delà d'une session
 * authentifiée et d'une décision d'accès : routes préfixées, vues et
 * traductions dans un espace de noms propre (`dme::`), migrations
 * chargées depuis le package, permissions internes inchangées.
 *
 * Ce qui appartient volontairement à l'hôte et n'est jamais imposé ici :
 * la configuration globale des modèles, la politique de mot de passe par
 * défaut de l'application, la décision d'accès de haut niveau et, à
 * terme, l'envoi effectif des SMS.
 */
class DmeServiceProvider extends ServiceProvider
{
    /**
     * Correspondance explicite modèle -> policy.
     *
     * La découverte automatique de Laravel ne s'applique pas à un
     * package : l'énumération est ici la seule source de vérité. Certains
     * modèles partagent volontairement la policy de leur agrégat (un
     * résultat d'analyse suit sa demande, un compte rendu suit son examen).
     *
     * @var array<class-string<Model>, class-string>
     */
    private const POLICIES = [
        Patient::class => PatientPolicy::class,
        Consultation::class => ConsultationPolicy::class,
        VitalSign::class => VitalSignPolicy::class,
        Diagnosis::class => DiagnosisPolicy::class,
        Prescription::class => PrescriptionPolicy::class,
        LabOrder::class => LabOrderPolicy::class,
        LabResult::class => LabOrderPolicy::class,
        ImagingOrder::class => ImagingOrderPolicy::class,
        ImagingReport::class => ImagingOrderPolicy::class,
        Hospitalization::class => HospitalizationPolicy::class,
        NursingNote::class => NursingNotePolicy::class,
        CareOrder::class => CareOrderPolicy::class,
        Appointment::class => AppointmentPolicy::class,
        MedicalDocument::class => MedicalDocumentPolicy::class,
        AuditLog::class => AuditLogPolicy::class,
        SmsMessage::class => SmsMessagePolicy::class,
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/dme.php', 'dme');

        $this->app->singleton(StandaloneMode::class);
        $this->app->singleton(HostAccessGate::class);
        $this->app->singleton(PatientIdentifierResolver::class);

        // Le gestionnaire de passerelles est partagé : il met en cache la
        // passerelle résolue et permet aux tests d'en substituer une.
        $this->app->singleton(SmsGatewayManager::class);

        $this->registerSmsDispatcher();
    }

    public function boot(): void
    {
        $this->registerPolicies();
        $this->registerMiddlewareAliases();
        $this->registerResources();
        $this->registerRoutes();
        $this->registerPublishing();
        $this->registerCommands();
        $this->configureRateLimiting();
        $this->configureFacilitySettings();
        $this->configureStandaloneMode();
    }

    // -----------------------------------------------------------------
    // Envoi de SMS
    // -----------------------------------------------------------------

    /**
     * Le module ne dépend que de SmsDispatcherContract. Cette liaison est
     * un repli : une application hôte qui lie elle-même le contrat, ses
     * fournisseurs de services étant enregistrés après ceux des
     * packages, remplace purement et simplement ce choix.
     */
    private function registerSmsDispatcher(): void
    {
        $this->app->singleton(SmsDispatcherContract::class, function ($app) {
            $dispatcher = (string) $app['config']->get('dme.sms.dispatcher', 'queued');

            return match ($dispatcher) {
                'log' => $app->make(LogSmsDispatcher::class),
                default => $app->make(QueuedSmsDispatcher::class),
            };
        });
    }

    /**
     * La file d'attente interne du module est-elle l'implémentation
     * retenue ? Elle seule justifie l'écran d'historique des SMS, les
     * commandes de suivi d'acheminement et leur planification.
     */
    private function usesInternalSmsPipeline(): bool
    {
        return $this->app->make(SmsDispatcherContract::class) instanceof QueuedSmsDispatcher;
    }

    // -----------------------------------------------------------------
    // Montage
    // -----------------------------------------------------------------

    private function registerPolicies(): void
    {
        foreach (self::POLICIES as $model => $policy) {
            Gate::policy($model, $policy);
        }

        $this->registerUserPolicy();
    }

    /**
     * Le modèle utilisateur est le seul qui puisse appartenir à l'hôte.
     *
     * L'écran d'administration des comptes du module a besoin d'une policy
     * sur ce modèle, mais l'hôte en a peut-être déjà déclaré une, qui
     * porte ses propres règles : on ne l'écrase jamais. Sans policy de
     * l'hôte, celle du module prend le relais et l'écran reste utilisable.
     */
    private function registerUserPolicy(): void
    {
        $model = Dme::userModel();

        if (Gate::getPolicyFor($model) === null) {
            Gate::policy($model, UserPolicy::class);
        }
    }

    private function registerMiddlewareAliases(): void
    {
        $router = $this->app['router'];

        $router->aliasMiddleware('dme.access', EnsureHostGrantsAccess::class);
        $router->aliasMiddleware('dme.dev-session', StartDevSession::class);
    }

    private function registerResources(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'dme');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'dme');
    }

    /**
     * Les routes du module vivent sous leur propre préfixe d'URL et leur
     * propre préfixe de nom (`dme.`), pour ne jamais entrer en collision
     * avec celles de l'hôte.
     */
    private function registerRoutes(): void
    {
        $config = $this->app['config'];

        Route::group([
            'prefix' => $config->get('dme.route.prefix', 'dme'),
            'as' => $config->get('dme.route.name', 'dme.'),
            'middleware' => $this->webMiddleware(),
        ], function (): void {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        });

        Route::group([
            'prefix' => $config->get('dme.route.api.prefix', 'dme/api'),
            'as' => $config->get('dme.route.name', 'dme.'),
            'middleware' => (array) $config->get('dme.route.api.middleware', ['api']),
        ], function (): void {
            $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        });
    }

    /**
     * Pile d'intergiciels des routes web du module.
     *
     * L'ordre importe : la session doit être ouverte (`web`) avant que la
     * session de développement puisse s'y installer, et l'autorisation de
     * l'hôte doit être tranchée avant que la moindre page du dossier ne
     * soit servie. La traçabilité des accès vient en dernier, une fois
     * l'utilisateur connu.
     *
     * @return list<string>
     */
    private function webMiddleware(): array
    {
        $middleware = (array) $this->app['config']->get('dme.route.middleware', ['web', 'dme.access']);

        if ($this->app->make(StandaloneMode::class)->autoLogin()) {
            $position = array_search('dme.access', $middleware, true);
            $position = $position === false ? count($middleware) : $position;
            array_splice($middleware, $position, 0, ['dme.dev-session']);
        }

        // Toute consultation d'un dossier patient est tracée (§30).
        $middleware[] = RecordMedicalAccess::class;

        return array_values($middleware);
    }

    private function registerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/dme.php' => $this->app->configPath('dme.php'),
        ], 'dme-config');

        $this->publishes([
            __DIR__.'/../resources/views' => $this->app->resourcePath('views/vendor/dme'),
        ], 'dme-views');

        $this->publishes([
            __DIR__.'/../resources/lang' => $this->app->langPath('vendor/dme'),
        ], 'dme-lang');

        $this->publishes([
            __DIR__.'/../public' => public_path((string) $this->app['config']->get('dme.assets.path', 'vendor/dme')),
        ], 'dme-assets');
    }

    private function registerCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $commands = [SyncDutyPeriods::class];

        if ($this->usesInternalSmsPipeline()) {
            $commands[] = CheckSmsGateway::class;
            $commands[] = RefreshSmsStatuses::class;
        }

        $this->commands($commands);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $this->schedule($schedule);
        });
    }

    /**
     * Tâches planifiées du module. Elles s'ajoutent au planificateur de
     * l'hôte : une seule entrée cron (`php artisan schedule:run`) suffit.
     */
    private function schedule(Schedule $schedule): void
    {
        // Gardes planifiées à l'avance : prise et fin automatiques.
        $schedule->command(SyncDutyPeriods::class)
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->runInBackground();

        if (! $this->usesInternalSmsPipeline()) {
            return;
        }

        // Suivi d'acheminement des SMS : fait évoluer « accepté » vers
        // « envoyé » puis « remis » à partir des états rapportés.
        $schedule->command(RefreshSmsStatuses::class)
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->runInBackground();
    }

    /**
     * Limitation de débit (§41), sous des noms propres au module afin de
     * ne pas écraser les limiteurs de l'application hôte.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('dme-login', fn ($request) => Limit::perMinute(5)
            ->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()));

        RateLimiter::for('dme-api', fn ($request) => Limit::perMinute(60)
            ->by($request->user()?->id ?: $request->ip()));
    }

    /**
     * Coordonnées de l'établissement et préfixes des identifiants métier :
     * modifiables depuis l'écran Paramètres, mais lus partout ailleurs via
     * config('dme.*'). La table peut ne pas exister encore (module
     * fraîchement installé, avant migrate) : dans ce cas, on garde
     * silencieusement les valeurs par défaut du fichier de configuration.
     */
    private function configureFacilitySettings(): void
    {
        try {
            if (! $this->app['db']->connection()->getSchemaBuilder()->hasTable('dme_app_settings')) {
                return;
            }

            $overrides = AppSetting::map();
        } catch (\Throwable) {
            return;
        }

        if ($overrides === []) {
            return;
        }

        foreach (['name', 'address', 'phone', 'email'] as $field) {
            if (! empty($overrides["facility.{$field}"])) {
                config(["dme.facility.{$field}" => $overrides["facility.{$field}"]]);
            }
        }

        foreach (array_keys((array) config('dme.identifiers.prefixes')) as $key) {
            if (! empty($overrides["identifiers.prefixes.{$key}"])) {
                config(["dme.identifiers.prefixes.{$key}" => $overrides["identifiers.prefixes.{$key}"]]);
            }
        }
    }

    /**
     * Garde-fous du mode autonome de développement.
     *
     * Les réglages appliqués ici valent pour toute l'application : ils ne
     * sont donc posés que lorsque le module tourne seul, jamais quand il
     * est invité chez un hôte qui a ses propres conventions.
     */
    private function configureStandaloneMode(): void
    {
        $standalone = $this->app->make(StandaloneMode::class);

        if ($standalone->refusedInProduction()) {
            Log::warning(
                'DME_STANDALONE_DEV est demandé mais ignoré : le mode autonome '
                .'de développement ne s\'active jamais en production.'
            );
        }

        if (! $standalone->enabled()) {
            return;
        }

        // Le module porte alors lui-même l'authentification : un visiteur
        // anonyme doit atterrir sur sa page de connexion locale, et non
        // sur celle d'un hôte qui n'existe pas.
        Authenticate::redirectUsing(static fn () => route('dme.login'));

        // Les relations non chargées et les attributions de masse
        // silencieuses deviennent des erreurs, ce qui fait remonter les
        // requêtes N+1 pendant le développement plutôt qu'en production.
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
        Model::unguard(false);

        Validator::excludeUnvalidatedArrayKeys();
    }
}
