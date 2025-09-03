<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class IndexController extends AbstractController {

    #[Route('/', name: 'index')]
    public function index() {
        $webroot = $this->getParameter('webroot');
        $index = sprintf("%s/index.html", $webroot);

        if (!file_exists($index)) {
            return new Response("Front-End is missing!");
        }

        return new Response(file_get_contents($index));
    }
}
