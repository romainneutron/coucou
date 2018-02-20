<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DefaultController
{
    public function index(Request $request)
    {
        usleep(min(0, max(5000000, $request->query->get('usleep', 0))));

        return new Response('Coucou');
    }
}
