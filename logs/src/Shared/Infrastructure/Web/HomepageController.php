<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Web;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomepageController extends AbstractController
{
    #[Route('/', name: 'home_index', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->render('home/index.html.twig');
    }
}
