<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DefaultController
{
    public function index(Request $request)
    {
        usleep(max(0, min(5000000, $request->query->get('usleep', 0))));

        return new Response('Coucou');
    }
}
