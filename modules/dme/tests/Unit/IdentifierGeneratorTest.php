<?php

declare(strict_types=1);

namespace Keneya\Dme\Tests\Unit;

use Keneya\Dme\Services\Identifiers\IdentifierGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Keneya\Dme\Tests\TestCase;

/**
 * Identifiants métier (§37) : format, incrément et stabilité.
 */
class IdentifierGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_format_respecte_la_specification(): void
    {
        $identifier = app(IdentifierGenerator::class)->next('patient', 2026);

        $this->assertSame('PAT-2026-000001', $identifier);
        $this->assertMatchesRegularExpression('/^PAT-\d{4}-\d{6}$/', $identifier);
    }

    public function test_les_numeros_s_incrementent_sans_collision(): void
    {
        $generator = app(IdentifierGenerator::class);

        $identifiers = collect(range(1, 25))
            ->map(fn () => $generator->next('consultation', 2026));

        $this->assertSame('CONS-2026-000001', $identifiers->first());
        $this->assertSame('CONS-2026-000025', $identifiers->last());
        $this->assertCount(25, $identifiers->unique(), 'Deux identifiants identiques ont été générés.');
    }

    public function test_chaque_prefixe_possede_sa_propre_sequence(): void
    {
        $generator = app(IdentifierGenerator::class);

        $generator->next('patient', 2026);
        $generator->next('patient', 2026);

        $this->assertSame('ORD-2026-000001', $generator->next('prescription', 2026));
    }

    public function test_les_sequences_sont_cloisonnees_par_annee(): void
    {
        $generator = app(IdentifierGenerator::class);

        $generator->next('patient', 2025);

        $this->assertSame('PAT-2026-000001', $generator->next('patient', 2026));
    }

    public function test_un_prefixe_inconnu_est_refuse(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(IdentifierGenerator::class)->next('inexistant');
    }
}
