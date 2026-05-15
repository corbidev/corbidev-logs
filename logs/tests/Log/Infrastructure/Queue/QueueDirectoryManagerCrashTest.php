<?php

declare(strict_types=1);

namespace App\Tests\Log\Infrastructure\Queue;

use App\Log\Infrastructure\Exception\QueueDirectoryException;
use App\Log\Infrastructure\Queue\QueueDirectoryManager;
use PHPUnit\Framework\TestCase;

/**
 * Crash tests filesystem du QueueDirectoryManager.
 *
 * @covers \App\Log\Infrastructure\Queue\QueueDirectoryManager
 */
final class QueueDirectoryManagerCrashTest extends TestCase
{
    private string $baseDirectory;

    protected function setUp(): void
    {
        $this->baseDirectory = sys_get_temp_dir() . '/queue_crash_' . uniqid();
    }

    protected function tearDown(): void
    {
        if (is_dir($this->baseDirectory)) {
            @chmod($this->baseDirectory, 0755);

            @rmdir($this->baseDirectory);
        }

        if (file_exists($this->baseDirectory)) {
            @unlink($this->baseDirectory);
        }
    }

    /**
     * But : Vérifier que 1 000 appels successifs à ensureDirectoryExists restent stables.
     *
     * Entrée : 1 000 appels avec le même chemin
     * Résultat attendu : Le répertoire existe, aucun crash
     */
    public function testMassiveEnsureDirectoryExistsCallsRemainStable(): void
    {
        $manager = new QueueDirectoryManager();

        for ($i = 0; $i < 1000; ++$i) {
            $manager->ensureDirectoryExists($this->baseDirectory);
        }

        self::assertDirectoryExists($this->baseDirectory);
    }

    /**
     * But : Vérifier que ensureDirectoryExists échoue si un fichier existe déjà au même chemin.
     *
     * Entrée : Fichier créé à l'emplacement attendu du répertoire
     * Résultat attendu : QueueDirectoryException est levée
     */
    public function testEnsureDirectoryExistsFailsOnFileCollision(): void
    {
        file_put_contents($this->baseDirectory, 'collision');

        $manager = new QueueDirectoryManager();

        $this->expectException(QueueDirectoryException::class);

        $manager->ensureDirectoryExists($this->baseDirectory);
    }

    /**
     * But : Vérifier que ensureDirectoryExists échoue si le répertoire n'est pas lisible (chmod 0000).
     *
     * Entrée : Répertoire existant avec permissions 0000
     * Résultat attendu : QueueDirectoryException est levée
     */
    public function testEnsureDirectoryExistsFailsOnUnreadableDirectory(): void
    {
        mkdir($this->baseDirectory);

        chmod($this->baseDirectory, 0000);

        clearstatcache(true, $this->baseDirectory);

        $manager = new QueueDirectoryManager();

        $this->expectException(QueueDirectoryException::class);

        $manager->ensureDirectoryExists($this->baseDirectory);
    }

    /**
     * But : Vérifier que ensureDirectoryExists refuse un lien symbolique (non applicable sous Windows).
     *
     * Entrée : Lien symbolique créé à l'emplacement attendu du répertoire
     * Résultat attendu : QueueDirectoryException est levée (test ignoré sur Windows)
     */
    public function testEnsureDirectoryExistsFailsOnSymlink(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('Les liens symboliques ne sont pas fiables sous Windows sans privilèges élevés.');
        }

        $target = sys_get_temp_dir() . '/queue_target_' . uniqid();

        mkdir($target);

        $created = symlink($target, $this->baseDirectory);

        if (!$created) {
            @rmdir($target);
            $this->markTestSkipped('Impossible de créer un lien symbolique sur cet environnement.');
        }

        $manager = new QueueDirectoryManager();

        try {
            $this->expectException(QueueDirectoryException::class);

            $manager->ensureDirectoryExists($this->baseDirectory);
        } finally {
            @unlink($this->baseDirectory);

            @rmdir($target);
        }
    }

    /**
     * But : Vérifier que ensureDirectoryExists crée une arborescence imbriquée profonde.
     *
     * Entrée : Chemin à 5 niveaux d'imbrication inexistant
     * Résultat attendu : Le dernier répertoire imbriqué est créé
     */
    public function testEnsureDirectoryExistsSupportsNestedDirectories(): void
    {
        $nested = $this->baseDirectory . '/a/b/c/d/e';

        $manager = new QueueDirectoryManager();

        $manager->ensureDirectoryExists($nested);

        self::assertDirectoryExists($nested);
    }
}