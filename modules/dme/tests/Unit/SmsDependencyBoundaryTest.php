<?php

declare(strict_types=1);

namespace Keneya\Dme\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Frontière SMS du module.
 *
 * Le module n'aura plus de pipeline SMS propre à terme : la file d'attente
 * interne, les passerelles et l'écran d'historique vivent tous sous
 * `src/Sms/Pipeline` et pourront être supprimés d'un bloc le jour où
 * Keneya Workflow fournira son implémentation.
 *
 * Ce test garantit que ce jour-là, rien d'autre ne cassera : aucune classe
 * métier du module ne connaît le pipeline, elles ne connaissent que
 * SmsDispatcherContract. C'est une vérification structurelle, exécutée sur
 * les fichiers sources eux-mêmes, donc impossible à contourner par
 * inadvertance.
 */
class SmsDependencyBoundaryTest extends TestCase
{
    /**
     * Répertoires du code métier : tout ce qui n'est pas le pipeline.
     *
     * @var list<string>
     */
    private const BUSINESS_PATHS = [
        'src/Access',
        'src/Console',
        'src/Http',
        'src/Models',
        'src/Patients',
        'src/Policies',
        'src/Services',
        'src/Standalone',
        'src/Support',
    ];

    private function sourceRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * @return list<string>
     */
    private function businessFiles(): array
    {
        $files = [];

        foreach (self::BUSINESS_PATHS as $path) {
            $directory = $this->sourceRoot().'/'.$path;

            if (! is_dir($directory)) {
                continue;
            }

            /** @var iterable<\SplFileInfo> $iterator */
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }

        sort($files);

        return $files;
    }

    public function test_le_code_metier_ne_reference_jamais_le_pipeline_sms(): void
    {
        $files = $this->businessFiles();

        $this->assertNotEmpty($files, 'Aucun fichier métier trouvé : le test ne vérifie rien.');

        $coupables = [];

        foreach ($files as $file) {
            $contenu = (string) file_get_contents($file);

            if (str_contains($contenu, 'Keneya\\Dme\\Sms\\Pipeline')) {
                $coupables[] = str_replace($this->sourceRoot().DIRECTORY_SEPARATOR, '', $file);
            }
        }

        $this->assertSame([], $coupables, sprintf(
            "Ces classes dépendent du pipeline SMS interne au lieu du contrat :\n- %s",
            implode("\n- ", $coupables)
        ));
    }

    public function test_le_service_de_notifications_ne_depend_que_du_contrat(): void
    {
        $contenu = (string) file_get_contents(
            $this->sourceRoot().'/src/Services/Notifications/NotificationService.php'
        );

        $this->assertStringContainsString(
            'use Keneya\\Dme\\Contracts\\SmsDispatcherContract;',
            $contenu
        );

        $this->assertStringNotContainsString('SmsService', $contenu);
        $this->assertStringNotContainsString('SmsGateway', $contenu);
    }

    public function test_le_pipeline_implemente_bien_le_contrat(): void
    {
        $this->assertTrue(
            is_subclass_of(
                \Keneya\Dme\Sms\Pipeline\QueuedSmsDispatcher::class,
                \Keneya\Dme\Contracts\SmsDispatcherContract::class
            )
        );

        $this->assertTrue(
            is_subclass_of(
                \Keneya\Dme\Sms\LogSmsDispatcher::class,
                \Keneya\Dme\Contracts\SmsDispatcherContract::class
            )
        );
    }
}
