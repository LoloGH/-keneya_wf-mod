<?php

declare(strict_types=1);

namespace Keneya\Dme\Services\Identifiers;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Génère les identifiants métier lisibles et stables décrits en §37 :
 *
 *     PAT-2026-000001, CONS-2026-000001, ORD-2026-000001...
 *
 * L'incrément se fait sous transaction avec verrou pessimiste sur la
 * ligne de séquence, afin que deux créations concurrentes ne puissent pas
 * obtenir le même numéro. Un identifiant attribué n'est jamais réutilisé,
 * même si l'enregistrement est supprimé : c'est la condition pour qu'il
 * puisse servir de clé de correspondance externe (FHIR / HL7, §44-45).
 */
class IdentifierGenerator
{
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    /**
     * Retourne le prochain identifiant pour une clé de préfixe configurée.
     *
     * @param  string  $prefixKey  clé dans config('dme.identifiers.prefixes')
     */
    public function next(string $prefixKey, ?int $year = null): string
    {
        $prefix = config("dme.identifiers.prefixes.{$prefixKey}");

        if (! is_string($prefix) || $prefix === '') {
            throw new InvalidArgumentException(
                "Aucun préfixe d'identifiant configuré pour « {$prefixKey} »."
            );
        }

        $year ??= (int) Carbon::now()->format('Y');
        $padding = (int) config('dme.identifiers.padding', 6);

        $value = $this->connection->transaction(function () use ($prefix, $year): int {
            $row = $this->connection->table('dme_identifier_sequences')
                ->where('prefix', $prefix)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                $this->connection->table('dme_identifier_sequences')->insert([
                    'prefix' => $prefix,
                    'year' => $year,
                    'current_value' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return 1;
            }

            $next = (int) $row->current_value + 1;

            $this->connection->table('dme_identifier_sequences')
                ->where('id', $row->id)
                ->update(['current_value' => $next, 'updated_at' => now()]);

            return $next;
        });

        return sprintf('%s-%d-%s', $prefix, $year, str_pad((string) $value, $padding, '0', STR_PAD_LEFT));
    }
}
