<?php

namespace Teamleader\Zoomroulette\Slack;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Teamleader\Zoomroulette\Zoom\ZoomApiRepository;
use Teamleader\Zoomroulette\Zoomroulette\User;
use Teamleader\Zoomroulette\Zoomroulette\UserRepository;

class SpinCommandHandler
{
    /**
     * @var OauthProvider
     */
    private OauthProvider $oauthProvider;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var UserRepository
     */
    private UserRepository $userRepository;
    /**
     * @var ZoomApiRepository
     */
    private ZoomApiRepository $zoomApiRepository;

    public function __construct(OauthProvider $oauthProvider, UserRepository $userRepository,  LoggerInterface $logger, ZoomApiRepository $zoomApiRepository)
    {
        $this->oauthProvider = $oauthProvider;
        $this->logger = $logger;
        $this->userRepository = $userRepository;
        $this->zoomApiRepository = $zoomApiRepository;
    }


    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        /**
         * {"args":[],
         * "body":
         * {
         * "token":"310AK8HlSecUl0YW8BmVk52V",
         * "team_id":"T013WK2C7PE",
         * "team_domain":"marikittens",
         * "channel_id":"D013WKMBBV2",
         * "channel_name":"directmessage",
         * "user_id":"U013QDTBF5Y",
         * "user_name":"marijn.vandevoorde",
         * "command":"/zoomroulette",
         * "text":"",
         * "response_url":"https://hooks.slack.com/commands/T013WK2C7PE/1137744844515/N8OoNCLe7Wzila1epxcIAHRT",
         * "trigger_id":"1137535912434.1132648415796.5af99977fc98a807032f91cc2f5e12a2"}}
         */
        $body = $request->getParsedBody();
        $this->logger->debug("slash command received", [
            'args' => $args,
            'body' => $body
        ]);
        /** @var User $user */
        $user = $this->userRepository->findBySsoId('slack', $body['user_id']);
        $this->logger->debug('slash command for user', ['user' => $user]);
        $zoomMeetingId = $this->zoomApiRepository->createMeeting($user->getZoomUserid(), $user->getZoomAccessToken());
        $this->logger->debug($zoomMeetingId);
        return $response->withBody($zoomMeetingId);
    }
}
