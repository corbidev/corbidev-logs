<?php

declare(strict_types=1);

namespace App\Tests\Log\Infrastructure\Queue;

use App\Log\Infrastructure\Exception\QueueDirectoryException;
use App\Log\Infrastructure\Queue\QueueDirectoryManager;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Log\Infrastructure\Queue\QueueDirectoryManager
 */
final class QueueDirectoryManagerTest extends TestCase
{
    private string $baseDirectory;

    protected function setUp(): void
    {
        $this->baseDirectory = sys_get_temp_dir() . '/queue_test_' . uniqid();
    }

    protected function tearDown(): void
    {
        if (is_dir($this->baseDirectory)) {
            @rmdir($this->baseDirectory);
        }
    }

    /**
     * But : Vérifier que ensureDirectoryExists crée le répertoire quand il n'existe pas.
     *
     * Entrée : Chemin vers un répertoire inexistant
     * Résultat attendu : Le répertoire existe après l'appel
     */
    public function testEnsureDirectoryExistsCreatesDirectory(): void
    {
        $manager = new QueueDirectoryManager();

        $manager->ensureDirectoryExists($this->baseDirectory);

        self::assertDirectoryExists($this->baseDirectory);
    }

    /**
     * But : Vérifier que ensureDirectoryExists est idempotent si le répertoire existe déjà.
     *
     * Entrée : Répertoire préalablement créé via mkdir()
     * Résultat attendu : Pas d'exception, répertoire toujours existant
     */
    public function testEnsureDirectoryExistsDoesNothingIfDirectoryAlreadyExists(): void
    {
        mkdir($this->baseDirectory);

        $manager = new QueueDirectoryManager();

        $manager->ensureDirectoryExists($this->baseDirectory);

        self::assertDirectoryExists($this->baseDirectory);
    }

    /**
     * But : Vérifier que ensureDirectoryExists lève une exception si le chemin est un fichier.
     *
     * Entrée : Fichier créé à l'emplacement du répertoire attendu
     * Résultat attendu : QueueDirectoryException est levée
     */
    public function testEnsureDirectoryExistsThrowsIfPathIsAFile(): void
    {
        file_put_contents($this->baseDirectory, 'test');

        $manager = new QueueDirectoryManager();

        $this->expectException(QueueDirectoryException::class);

        $manager->ensureDirectoryExists($this->baseDirectory);
    }

    /**
     * But : Vérifier que ensureDirectoryExists refuse un chemin vide.
     *
     * Entrée : Chaîne vide ''
     * Résultat attendu : QueueDirectoryException est levée
     */
    public function testEnsureDirectoryExistsThrowsOnEmptyPath(): void
    {
        $manager = new QueueDirectoryManager();

        $this->expectException(QueueDirectoryException::class);

        $manager->ensureDirectoryExists('');
    }

    /**
     * But : Vérifier que ensureDirectoryExists refuse le chemin '.' (répertoire courant).
     *
     * Entrée : Chemin '.'
     * Résultat attendu : QueueDirectoryException est levée
     */
    public function testEnsureDirectoryExistsThrowsOnDotPath(): void
    {
        $manager = new QueueDirectoryManager();

        $this->expectException(QueueDirectoryException::class);

        $manager->ensureDirectoryExists('.');
    }

    /**
     * But : Vérifier que ensureDirectoryExists refuse un chemin contenant un octet nul.
     *
     * Entrée : "invalid\0path"
     * Résultat attendu : QueueDirectoryException est levée
     */
    public function testEnsureDirectoryExistsThrowsOnNullByte(): void
    {
        $manager = new QueueDirectoryManager();

        $this->expectException(QueueDirectoryException::class);

        $manager->ensureDirectoryExists("invalid\0path");
    }
}
