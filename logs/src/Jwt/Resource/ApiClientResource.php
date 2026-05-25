<?php

namespace App\Jwt\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Jwt\Dto\CreateClientInput;
use App\Jwt\Dto\RotateSecretInput;
use App\Jwt\Processor\CreateClientProcessor;
use App\Jwt\Processor\RotateSecretProcessor;

#[ApiResource(
    shortName: 'JwtClient',
    operations: [

        // 🔑 Création client
        new Post(
            uriTemplate: '/jwt/clients',
            input: CreateClientInput::class,
            processor: CreateClientProcessor::class,
            name: 'jwt_create_client',
        ),

        // 🔁 Rotation secret
        new Post(
            uriTemplate: '/jwt/clients/{id}/rotate-secret',
            input: RotateSecretInput::class,
            processor: RotateSecretProcessor::class,
            name: 'jwt_rotate_secret',
        ),
    ]
)]
final class ApiClientResource
{
    // volontairement vide
}