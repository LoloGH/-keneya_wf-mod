<?php

declare(strict_types=1);

namespace Keneya\Dme\Http\Controllers\Web;

use Keneya\Dme\Contracts\DmeUser;
use Keneya\Dme\Dme;
use Keneya\Dme\Http\Controllers\Controller;
use Keneya\Dme\Models\AuditLog;
use Keneya\Dme\Models\CareOrder;
use Keneya\Dme\Models\NursingNote;
use Keneya\Dme\Models\Patient;
use Keneya\Dme\Support\Rbac;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Soins programmés : prescription, attribution, réalisation, annulation.
 *
 * Le prescripteur décrit le soin ; il peut le confier nommément, ou le
 * laisser ouvert : auquel cas il revient au personnel de garde de son
 * service. Chaque transition est journalisée et signée : un soin
 * programmé engage une responsabilité soignante.
 */
class CareOrderController extends Controller
{
    public function store(Request $request, Patient $patient): RedirectResponse
    {
        $this->authorize('create', CareOrder::class);

        $user = $request->user();

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'frequency' => ['nullable', 'string', 'max:120'],
            'priority' => ['required', Rule::in(array_keys(CareOrder::PRIORITIES))],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'hospitalization_id' => [
                'nullable',
                Rule::exists('dme_hospitalizations', 'id')->where('patient_id', $patient->id),
            ],
            'assigned_nurse_id' => ['nullable', Rule::in($this->assignableNurseIds($user))],
        ], [
            'assigned_nurse_id.in' => 'Ce soignant ne peut pas recevoir ce soin.',
            'ends_at.after' => 'La fin doit suivre le début.',
        ], [
            'title' => 'intitulé',
            'priority' => 'priorité',
            'starts_at' => 'début',
            'ends_at' => 'fin',
            'assigned_nurse_id' => 'soignant',
        ]);

        // Un soin nommément confié suppose la permission de le confier.
        if (! empty($data['assigned_nurse_id'])) {
            $this->authorize('assign', new CareOrder([
                'status' => 'planned',
                'prescriber_id' => $user->id,
                'service_id' => $user->service_id,
            ]));
        }

        $order = DB::transaction(fn () => CareOrder::create($data + [
            'patient_id' => $patient->id,
            'prescriber_id' => $user->id,
            // Le service est figé à la prescription : c'est lui qui porte
            // la garde, même si le prescripteur change d'affectation.
            'service_id' => $user->service_id,
            'status' => 'planned',
        ]));

        AuditLog::record(
            action: 'care_order_prescribed',
            subject: $order,
            properties: ['assigned' => $order->assigned_nurse_id !== null],
            description: $order->isUnassigned()
                ? 'A prescrit un soin ouvert à la garde du service'
                : 'A prescrit un soin et l\'a confié à un soignant',
        );

        return back()->with('success', $order->isUnassigned()
            ? 'Soin programmé. Il est visible par le personnel de garde du service.'
            : 'Soin programmé et confié.');
    }

    /**
     * Confier un soin ouvert, ou le rendre à la garde du service.
     */
    public function assign(Request $request, CareOrder $careOrder): RedirectResponse
    {
        $this->authorize('assign', $careOrder);

        $data = $request->validate([
            'assigned_nurse_id' => ['nullable', Rule::in($this->assignableNurseIds($request->user()))],
        ], ['assigned_nurse_id.in' => 'Ce soignant ne peut pas recevoir ce soin.']);

        $careOrder->update(['assigned_nurse_id' => $data['assigned_nurse_id'] ?: null]);

        AuditLog::record(
            action: 'care_order_assigned',
            subject: $careOrder,
            description: $careOrder->isUnassigned()
                ? 'A rendu le soin à la garde du service'
                : 'A confié le soin à '.($careOrder->assignedNurse?->displayName() ?? 'un soignant'),
        );

        return back()->with('success', $careOrder->isUnassigned()
            ? 'Soin rendu à la garde du service.'
            : 'Soin confié.');
    }

    /**
     * Réaliser ou refuser un soin.
     *
     * Une réalisation écrit aussi une transmission dans le dossier : le
     * soin programmé dit ce qui était demandé, la transmission ce qui a
     * réellement eu lieu. Sans elle, la trace resterait muette sur
     * l'exécution.
     */
    public function execute(Request $request, CareOrder $careOrder): RedirectResponse
    {
        $this->authorize('execute', $careOrder);

        $data = $request->validate([
            'status' => ['required', Rule::in(['completed', 'refused'])],
            'outcome' => ['nullable', 'string', 'max:2000', 'required_if:status,refused'],
        ], [
            'outcome.required_if' => 'Indiquez pourquoi le soin n\'a pas été réalisé.',
        ], ['outcome' => 'compte rendu']);

        $user = $request->user();

        DB::transaction(function () use ($careOrder, $data, $user): void {
            $careOrder->update($data + [
                'completed_at' => now(),
                'completed_by_id' => $user->id,
            ]);

            if ($data['status'] === 'completed' && $user->can('nursing.create')) {
                NursingNote::create([
                    'patient_id' => $careOrder->patient_id,
                    'hospitalization_id' => $careOrder->hospitalization_id,
                    'type' => 'care',
                    'occurred_at' => now(),
                    'title' => $careOrder->title,
                    'content' => $data['outcome'] ?? null,
                    'severity' => 'info',
                    'nurse_id' => $user->id,
                ]);
            }
        });

        AuditLog::record(
            action: $data['status'] === 'completed' ? 'care_order_completed' : 'care_order_refused',
            subject: $careOrder,
            description: $data['status'] === 'completed'
                ? 'A réalisé le soin programmé'
                : 'A signalé le soin comme non réalisé',
        );

        return back()->with('success', $data['status'] === 'completed'
            ? 'Soin réalisé et consigné dans les transmissions.'
            : 'Soin signalé comme non réalisé.');
    }

    public function cancel(Request $request, CareOrder $careOrder): RedirectResponse
    {
        $this->authorize('cancel', $careOrder);

        $data = $request->validate(
            ['outcome' => ['required', 'string', 'max:2000']],
            ['outcome.required' => 'Indiquez le motif d\'annulation.'],
            ['outcome' => 'motif'],
        );

        $careOrder->update($data + ['status' => 'cancelled', 'completed_at' => now()]);

        AuditLog::record(
            action: 'care_order_cancelled',
            subject: $careOrder,
            description: 'A annulé le soin programmé',
        );

        return back()->with('success', 'Soin annulé.');
    }

    /**
     * Soignants auxquels un soin peut être confié.
     *
     * Restreint au service du prescripteur : confier un soin à un
     * soignant d'un autre service reviendrait à engager une équipe qui
     * n'a pas la charge du patient. L'administration n'est pas exemptée :
     * la contrainte est métier, pas hiérarchique.
     *
     * @return list<int>
     */
    private function assignableNurseIds(DmeUser $user): array
    {
        if ($user->service_id === null) {
            return [];
        }

        return self::assignableNurses($user)->pluck('id')->all();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    public static function assignableNurses(DmeUser $user): \Illuminate\Database\Eloquent\Collection
    {
        if ($user->service_id === null) {
            return Dme::userQuery()->whereRaw('1 = 0')->get();
        }

        return Dme::usersWithRole(Rbac::ROLE_NURSE)
            ->where('is_active', true)
            ->where('service_id', $user->service_id)
            ->with('weeklySchedules')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'title', 'name', 'is_on_duty']);
    }
}
