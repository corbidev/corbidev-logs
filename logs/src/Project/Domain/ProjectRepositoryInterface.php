<?php

declare(strict_types=1);

namespace App\Project\Domain;

/**
 * Contrat de lecture des projets.
 */
interface ProjectRepositoryInterface
{
    /**
     * Retourne un projet par id.
     */
    public function findById(int $id): ?Project;

    /**
     * Retourne un projet par slug.
     */
    public function findBySlug(string $slug): ?Project;
}
