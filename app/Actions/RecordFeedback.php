<?php

namespace App\Actions;

use App\Models\FeedbackEntry;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visitor;
use App\Services\FeedbackAttribution;
use App\Support\Audit;
use InvalidArgumentException;

/**
 * Depot d'un retour (v3.2.8, point 4).
 *
 * Trois provenances, une seule ecriture : le patient depuis son portail, le
 * visiteur depuis sa page dediee, et le personnel depuis son interface
 * habituelle pour un constat.
 */
class RecordFeedback
{
    public function __construct(private readonly FeedbackAttribution $attribution) {}

    /**
     * @param  array{rating_care?: ?int, rating_staff?: ?int, content?: ?string, service_id?: ?int}  $data
     */
    public function fromPatient(Patient $patient, string $type, array $data): FeedbackEntry
    {
        $this->assertType($type);

        $visite = $patient->visits()->orderByDesc('opened_at')->first();

        return $this->create($type, array_merge($data, [
            'patient_id' => $patient->getKey(),
            'service_id' => $data['service_id'] ?? $visite?->service_id,
            'handled_by_user_id' => $this->attribution->forVisit($visite)?->getKey(),
        ]));
    }

    /**
     * @param  array{rating_care?: ?int, rating_staff?: ?int, content?: ?string}  $data
     */
    public function fromVisitor(Visitor $visitor, string $type, array $data): FeedbackEntry
    {
        $this->assertType($type);

        return $this->create($type, array_merge($data, [
            'visitor_id' => $visitor->getKey(),
            'service_id' => $data['service_id'] ?? $visitor->service_id,
            'handled_by_user_id' => $this->attribution->forVisitor($visitor)?->getKey(),
        ]));
    }

    /**
     * Constat redige par un membre du personnel. Le patient ou le visiteur
     * concerne est facultatif : un constat peut porter sur une observation
     * generale, sans viser personne.
     *
     * @param  array{content?: ?string, service_id?: ?int, patient_id?: ?int, visitor_id?: ?int}  $data
     */
    public function fromStaff(User $author, array $data): FeedbackEntry
    {
        $entry = $this->create(FeedbackEntry::TYPE_INCIDENT, array_merge($data, [
            'submitted_by_user_id' => $author->getKey(),
        ]));

        Audit::log(
            Audit::EVENT_FEEDBACK_RECORDED,
            sprintf('Constat depose par %s.', $author->name),
            $entry,
        );

        return $entry;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function create(string $type, array $attributes): FeedbackEntry
    {
        // Les notes ne s'appliquent qu'au sondage : une reclamation ou un
        // constat n'en porte pas, et en conserver donnerait a croire le
        // contraire.
        if ($type !== FeedbackEntry::TYPE_SURVEY) {
            $attributes['rating_care'] = null;
            $attributes['rating_staff'] = null;
        }

        return FeedbackEntry::create(array_merge($attributes, [
            'type' => $type,
            'status' => FeedbackEntry::STATUS_NEW,
        ]));
    }

    private function assertType(string $type): void
    {
        // Un constat vient du personnel, jamais d'un formulaire public : la
        // route publique ne doit pas pouvoir en fabriquer.
        if (! in_array($type, [FeedbackEntry::TYPE_SURVEY, FeedbackEntry::TYPE_COMPLAINT], true)) {
            throw new InvalidArgumentException('Ce type de retour ne peut pas etre depose depuis un formulaire public.');
        }
    }
}
