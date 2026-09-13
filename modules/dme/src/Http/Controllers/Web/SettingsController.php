<?php

declare(strict_types=1);

namespace Keneya\Dme\Http\Controllers\Web;

use Keneya\Dme\Http\Controllers\Controller;
use Keneya\Dme\Models\AppSetting;
use Keneya\Dme\Models\AuditLog;
use Keneya\Dme\Models\Service;
use Keneya\Dme\Contracts\SimulatesSmsDelivery;
use Keneya\Dme\Contracts\SmsDispatcherContract;
use Keneya\Dme\Models\SmsTemplate;
use Keneya\Dme\Dme;
use Keneya\Dme\Support\Rbac;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Keneya\Dme\Support\PasswordPolicy;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

/**
 * Paramètres.
 *
 * L'écran est double, et la distinction est une règle de sécurité, pas
 * une commodité d'affichage :
 *
 *  - sans `settings.manage`, l'utilisateur ne voit que son propre compte
 *    et n'y change que son mot de passe. La configuration de
 *    l'établissement, les passerelles SMS et la matrice de permissions ne
 *    le concernent pas et ne lui sont pas rendues ;
 *  - avec, il obtient la configuration effective et la matrice éditable.
 *
 * Le changement de mot de passe, lui, est ouvert à tous : c'est son
 * propre compte, aucune permission n'a à le conditionner.
 */
class SettingsController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        if (! $user->can('settings.manage')) {
            return view('dme::settings.account', [
                'user' => $user->load(['service', 'roles']),
                'roleLabels' => Rbac::allRoleLabels(),
            ]);
        }

        return view('dme::settings.index', [
            'user' => $user->load(['service', 'roles']),
            // Les coordonnées telles qu'elles s'affichent réellement : quand
            // une application hôte les administre, ce sont les siennes qui
            // font foi, et non le fichier de configuration du module.
            'facility' => Dme::facility(),
            'identifiers' => config('dme.identifiers.prefixes'),
            'documents' => config('dme.documents'),
            'smsGateway' => config('dme.sms.gateway'),
            'smsSimulated' => $this->smsIsSimulated(),
            'smsTracking' => config('dme.sms.status_tracking'),
            'smsRetry' => config('dme.sms.retry'),
            'services' => Service::orderBy('name')->get(),
            'roles' => Role::withCount('users')->orderBy('name')->get(),
            'roleLabels' => Rbac::allRoleLabels(),
            'permissionGroups' => Rbac::permissionGroups(),
            // L'état réel, tel qu'il est en base : depuis que la matrice est
            // modifiable, le tableau d'amorçage de Rbac ne la décrit plus.
            'rolePermissions' => Rbac::persistedRolePermissions(),
            'lockedPermissions' => Rbac::lockedAdminPermissions(),
            'canEditRoles' => $user->can('roles.manage'),
            'templates' => SmsTemplate::orderBy('name')->get(),
        ]);
    }

    /**
     * Changement de mot de passe par l'intéressé.
     *
     * L'ancien mot de passe est exigé : sans lui, une session laissée
     * ouverte suffirait à confisquer le compte. Les autres sessions sont
     * invalidées, et le mot de passe n'apparaît dans aucune trace.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', PasswordPolicy::rule(), 'different:current_password'],
        ], [
            'current_password.current_password' => 'Le mot de passe actuel est incorrect.',
            'password.different' => 'Le nouveau mot de passe doit différer de l\'ancien.',
        ], [
            'current_password' => 'mot de passe actuel',
            'password' => 'nouveau mot de passe',
        ]);

        $user->forceFill(['password' => Hash::make($request->string('password')->toString())])->save();

        // La session courante reste valide, les autres tombent : un mot de
        // passe changé doit couper un accès qu'on soupçonne compromis.
        $request->session()->regenerate();

        AuditLog::record(
            action: 'password_changed',
            subject: $user,
            description: 'A changé son mot de passe',
        );

        return back()->with('success', 'Mot de passe mis à jour.');
    }

    /**
     * Coordonnées de l'établissement, affichées dans l'interface et les
     * documents PDF. Réservé à settings.manage.
     */
    public function updateFacility(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('settings.manage'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'address' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:150'],
        ], [], [
            'name' => 'nom',
            'address' => 'adresse',
            'phone' => 'téléphone',
            'email' => 'adresse e-mail',
        ]);

        foreach ($data as $field => $value) {
            AppSetting::put("facility.{$field}", $value);
        }

        AuditLog::record(
            action: 'facility_settings_updated',
            description: 'A modifié les coordonnées de l\'établissement',
        );

        return back()->with('success', 'Coordonnées de l\'établissement mises à jour.');
    }

    /**
     * Préfixes des identifiants métier (PAT, CONS, ORD...). Réservé à
     * settings.manage.
     *
     * Changer un préfixe n'affecte que les identifiants générés après le
     * changement : ceux déjà attribués restent inchangés, et la séquence
     * du nouveau préfixe repart de un pour l'année en cours. C'est
     * signalé dans l'écran, pas seulement ici.
     */
    public function updateIdentifiers(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('settings.manage'), 403);

        $keys = array_keys(config('dme.identifiers.prefixes'));

        $data = $request->validate([
            'prefixes' => ['required', 'array'],
            'prefixes.*' => ['required', 'string', 'max:8', 'regex:/^[A-Z0-9]+$/'],
        ], [
            'prefixes.*.regex' => 'Le préfixe :attribute ne peut contenir que des lettres majuscules et des chiffres.',
        ]);

        $submitted = array_intersect_key($data['prefixes'], array_flip($keys));

        if (count(array_unique($submitted)) !== count($submitted)) {
            return back()->withErrors([
                'prefixes' => 'Deux types de document ne peuvent pas partager le même préfixe.',
            ])->withInput();
        }

        foreach ($submitted as $key => $prefix) {
            AppSetting::put("identifiers.prefixes.{$key}", $prefix);
        }

        AuditLog::record(
            action: 'identifier_prefixes_updated',
            properties: $submitted,
            description: 'A modifié les préfixes des identifiants métier',
        );

        return back()->with('success', 'Préfixes des identifiants mis à jour.');
    }

    /**
     * Prise et fin de garde, déclarées par l'intéressé.
     *
     * C'est ce drapeau qui décide de la visibilité des soins programmés
     * laissés ouverts : il est donc journalisé comme un acte, pas comme
     * une préférence d'affichage.
     */
    public function toggleDuty(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->can('care_orders.view'), 403);

        $onDuty = ! $user->is_on_duty;

        $user->forceFill([
            'is_on_duty' => $onDuty,
            'on_duty_since' => $onDuty ? now() : null,
        ])->save();

        AuditLog::record(
            action: $onDuty ? 'duty_started' : 'duty_ended',
            subject: $user,
            description: $onDuty ? 'A pris la garde' : 'A quitté la garde',
        );

        return back()->with('success', $onDuty
            ? 'Vous êtes de garde. Les soins ouverts de votre service vous sont visibles.'
            : 'Vous n\'êtes plus de garde.');
    }

    /**
     * L'envoi de SMS est-il simulé ?
     *
     * L'écran ne connaît pas l'implémentation qui envoie : il demande au
     * contrat, et n'obtient une réponse que si celle-ci se déclare comme
     * une simulation. Une implémentation d'hôte qui envoie réellement ne
     * dit rien, et c'est la bonne réponse.
     */
    private function smsIsSimulated(): bool
    {
        $dispatcher = app(SmsDispatcherContract::class);

        return $dispatcher instanceof SimulatesSmsDelivery && $dispatcher->isSimulated();
    }
}
