<?php

namespace Marijnworks\Zoomroulette\Zoomroulette;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

class JoinCallHandler
{
    private UserRepository $userRepository;

    private LoggerInterface $logger;

    private SpinRepository $spinRepository;

    public function __construct(SpinRepository $spinRepository, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->spinRepository = $spinRepository;
    }

    /**
     * @param array<string, string> $args
     */
    public function __invoke(RequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $spin = $this->spinRepository->claimSpotByUuid(Uuid::fromString($args['id']));

            return $response->withHeader('Location', $spin->getJoinLink())->withStatus(302);
        } catch (SpinNotFoundException $e) {
            $response->getBody()->write('woops, too late! why not start your own spin using the /coffeeroulette slash command?');

            return $response->withStatus(404);
        }
    }
}
