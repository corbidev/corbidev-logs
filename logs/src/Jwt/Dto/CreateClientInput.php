<?php

namespace App\Jwt\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateClientInput
{
    #[Assert\NotBlank]
    #[Assert\Length(
        min: 3,
        max: 100,
        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères',
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères'
    )]
    public string $name;

    #[Assert\Length(
        max: 255,
        maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères'
    )]
    public ?string $description = null;
}
