<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Base de tests database.
 *
 * Responsabilités :
 * - démarrage kernel
 * - reset DB
 * - création schema
 * - isolation des tests
 */
abstract class DatabaseTestCase extends KernelTestCase
{
    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $this->entityManager = static::getContainer()
            ->get(EntityManagerInterface::class);

        $this->resetDatabase();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->entityManager->close();

        unset($this->entityManager);
    }

    /**
     * Recréation complète du schema.
     */
    private function resetDatabase(): void
    {
        $metadata = $this->entityManager
            ->getMetadataFactory()
            ->getAllMetadata();

        $tool = new SchemaTool($this->entityManager);

        $tool->dropSchema($metadata);

        $tool->createSchema($metadata);
    }
}