<?php

namespace Marijnworks\Zoomroulette\Slack;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpUnauthorizedException;
use SlimSession\Helper;

class SlackCommandAuthenticationMiddleware
{
    private string $secret;

    private LoggerInterface $logger;

    public function __construct(string $secret, LoggerInterface $logger)
    {
        $this->secret = $secret;
        $this->logger = $logger;
    }

    /**
     * Called when middleware needs to be executed.
     *
     * @param Request $request PSR7 request
     * @param RequestHandler $handler PSR7 handler
     *
     * @throws HttpBadRequestException
     * @throws HttpUnauthorizedException
     */
    public function __invoke(
        ServerRequestInterface $request,
        RequestHandler $handler
    ): Response {
        $body = $request->getBody()->getContents();

        if (empty($timestamp = $request->getHeader('X-Slack-Request-Timestamp')) || empty($signature = $request->getHeader('X-Slack-Signature'))) {
            throw new HttpBadRequestException($request, 'No timestap or signature header passed');
        }
        if (abs(intval($timestamp[0]) - time()) > 300) {
            throw new HttpBadRequestException($request, sprintf('Timestamp seems off: %s vs server time of %s', $timestamp[0], time()));
        }
        if (!hash_equals(
            'v0=' . hash_hmac('sha256', 'v0:' . $timestamp[0] . ':' . $body, $this->secret),
            $signature[0]
        )) {
            throw new HttpUnauthorizedException($request, 'signature seems invalid');
        }

        return $handler->handle($request->withAttribute('session', new Helper()));
    }
}
