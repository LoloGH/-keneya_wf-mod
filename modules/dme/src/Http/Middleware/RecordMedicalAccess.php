<?php

declare(strict_types=1);

namespace Keneya\Dme\Http\Middleware;

use Keneya\Dme\Models\AuditLog;
use Keneya\Dme\Models\Patient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Trace la consultation des dossiers patients (§30).
 *
 * Toute requête GET aboutissant sur une route liée à un patient produit
 * une entrée « viewed » dans le journal d'audit. Les réponses en échec
 * d'autorisation (403) sont enregistrées avec le résultat « denied », ce
 * qui rend visibles les tentatives d'accès non autorisé exigées par §57.
 *
 * Les écritures passent par les modèles (trait RecordsMedicalActivity) :
 * ce middleware ne couvre que la lecture, afin de ne rien journaliser
 * deux fois.
 */
class RecordMedicalAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $patient = $this->resolvePatient($request);

        if ($patient === null || ! $request->user()) {
            return $response;
        }

        $status = $response->getStatusCode();

        if ($request->isMethod('GET') && $status < 300) {
            AuditLog::record(
                action: 'viewed',
                subject: $patient,
                patientId: $patient->getKey(),
                description: 'A consulté '.$patient->patient_number,
            );
        } elseif (in_array($status, [401, 403], true)) {
            AuditLog::record(
                action: 'denied',
                subject: $patient,
                patientId: $patient->getKey(),
                outcome: 'denied',
                description: 'Accès refusé sur '.$patient->patient_number,
            );
        }

        return $response;
    }

    /**
     * Retrouve le patient concerné par la route courante, qu'il soit lié
     * directement ou porté par un enregistrement clinique.
     */
    private function resolvePatient(Request $request): ?Patient
    {
        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if ($parameter instanceof Patient) {
                return $parameter;
            }

            if (is_object($parameter) && method_exists($parameter, 'auditPatientId')) {
                $patientId = $parameter->auditPatientId();

                if ($patientId !== null) {
                    return Patient::find($patientId);
                }
            }
        }

        return null;
    }
}
