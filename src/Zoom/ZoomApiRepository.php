<?php

namespace Teamleader\Zoomroulette\Zoom;

use League\OAuth2\Client\Token\AccessTokenInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

class ZoomApiRepository
{
    private OauthProvider $oauthProvider;

    private LoggerInterface $logger;

    public function __construct(OauthProvider $oauthProvider, LoggerInterface $logger)
    {
        $this->oauthProvider = $oauthProvider;
        $this->logger = $logger;
    }

    public function createMeeting(string $zoomUserId, AccessTokenInterface $accessToken): ZoomMeeting
    {
        $payload = [
            'topic' => 'Zoom roulette baby!',
            'type' => 1,
            'settings' => [
                'host_video' => true,
                'participant_video' => true,
                'join_before_host' => true,
                'enforce_login' => false,
            ],
        ];
        $request = $this->oauthProvider->getAuthenticatedRequest(
            'POST',
            sprintf('https://api.zoom.us/v2/users/%s/meetings', $zoomUserId),
            $accessToken,
            [
                'body' => json_encode($payload),
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
            ]
        );
        /** @var ResponseInterface $response */
        $createMeetingResponse = $this->oauthProvider->getResponse($request);
        $data = json_decode($createMeetingResponse->getBody()->getContents(), true);
        $this->logger->debug($data['start_url']);

        return new ZoomMeeting(
            $data['start_url'],
            $data['join_url']
        );
    }
}
