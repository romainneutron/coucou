<?php

namespace App\Controller;

use App\Bridge\GuzzleBridge;
use GuzzleHttp\Client as GuzzleClient;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class DefaultController
{
    public function index(Request $request)
    {
        $duration = max(0, min(5000000, (int) $request->query->get('usleep', 0)));
        $requests = max(0, min(20, (int) $request->query->get('requests', 0)));

        if ($duration) {
            usleep($duration);
        }

        $results = [];

        if ($requests) {
            $guzzle = new GuzzleClient();
            $guzzle->getConfig('handler')->push(GuzzleBridge::create(), 'blackfire');

            while ($requests--) {
                $results[] = $guzzle->get($request->getUriForPath('/'))->getStatusCode();
            }
        }

        return new JsonResponse([
            'usleeped' =>  $duration,
            'message' => 'coucou',
        ] + ($requests ? ['results' => $results] : []));
    }
}
