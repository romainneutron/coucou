<?php

namespace App\Bridge;

use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

class GuzzleBridge
{
    private $handler;
    private $logger;
    private $autoEnable;

    public function __construct(callable $handler, LoggerInterface $logger = null, $autoEnable = true)
    {
        $this->handler = $handler;
        $this->logger = $logger;
        $this->autoEnable = (bool) $autoEnable;
    }

    public static function create(LoggerInterface $logger = null, $autoEnable = true)
    {
        return function (callable $handler) use ($logger, $autoEnable) {
            return new self($handler, $logger, $autoEnable);
        };
    }

    /**
     * @return PromiseInterface
     */
    public function __invoke(RequestInterface $request, array $options)
    {
        $fn = $this->handler;

        if ($request->hasHeader('X-Blackfire-Query') || !$this->shouldAutoEnable()) {
            return $fn($request, $options);
        }

        parse_str($_SERVER['HTTP_X_BLACKFIRE_QUERY'], $components);
        $subProfile = $components['sub_profile'] ?? '';

        if (preg_match('/^(?:[+\/=a-zA-Z0-9]{9}){0,1}:[+\/=a-zA-Z0-9]{9}$/', $subProfile)) {
            $subProfile = explode(':', $subProfile)[1];
        } else {
            $subProfile = '';
        }

        $components['sub_profile'] = $subProfile.':'.$this->generateSubId();
        $request = $request->withHeader('X-Blackfire-Query', http_build_query($components, '', '&', \PHP_QUERY_RFC3986));

        return $fn($request, $options)
            ->then(function (ResponseInterface $response) use ($request, $options) {
                return $this->processResponse($request, $options, $response);
            });
    }

    /**
     * @param RequestInterface                   $request
     * @param array                              $options
     * @param ResponseInterface|PromiseInterface $response
     *
     * @return ResponseInterface|PromiseInterface
     */
    public function processResponse(RequestInterface $request, array $options, ResponseInterface $response)
    {
        if (!$response->hasHeader('X-Blackfire-Response')) {
            if (null !== $this->logger) {
                $this->logger->warning('Profile request failed.');
            }

            return $response;
        }

        parse_str($response->getHeader('X-Blackfire-Response')[0], $values);

        if (!isset($values['continue']) || 'true' !== $values['continue']) {
            if (null !== $this->logger) {
                $this->logger->debug('Profile request succeeded.');
            }

            return $response;
        }

        Psr7\rewind_body($request);

        /* @var PromiseInterface|ResponseInterface $promise */
        return $this($request, $options);
    }

    private function shouldAutoEnable()
    {
        if (\BlackfireProbe::isEnabled() && $this->autoEnable) {
            if (isset($_SERVER['HTTP_X_BLACKFIRE_QUERY'])) {
                // Let's disable subrequest profiling if aggregation is enabled
                if (preg_match('/aggreg_samples=(\d+)/', $_SERVER['HTTP_X_BLACKFIRE_QUERY'], $matches)) {
                    return '1' === $matches[1];
                }
            }
        }

        return false;
    }

    private function generateSubId()
    {
        return substr(base64_encode(random_bytes(7)), 0, 9);
    }
}
