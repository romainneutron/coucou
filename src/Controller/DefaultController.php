<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class DefaultController
{
    public function index(Request $request)
    {
        $duration = max(0, min(5000000, $request->query->get('usleep', 0)));

        if ($duration) {
            usleep($duration);
        }

        return new JsonResponse([
            'usleeped' =>  $duration,
            'message' => 'coucou',
        ]);
    }
}
