<?php

namespace Marijnworks\Zoomroulette\Zoom;

use Exception;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Marijnworks\Zoomroulette\Zoomroulette\UserNotFoundException;
use Marijnworks\Zoomroulette\Zoomroulette\UserRepository;
use PDOException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;
use SlimSession\Helper;

class OauthRequestHandler
{
    /**
     * @var OauthProvider
     */
    private $oauthProvider;

    /**
     * @var LoggerInterface
     */
    private $logger;

    private UserRepository $userRepository;

    private Twig $templateEngine;

    public function __construct(
        OauthProvider $oauthProvider,
        UserRepository $userRepository,
        LoggerInterface $logger,
        Twig $templateEngine
    ) {
        $this->oauthProvider = $oauthProvider;
        $this->logger = $logger;
        $this->userRepository = $userRepository;
        $this->templateEngine = $templateEngine;
    }

    /**
     * @param array<string,string> $args
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        if (!isset($_GET['code'])) {
            // Fetch the authorization URL from the provider; this returns the
            // urlAuthorize option and generates and applies any necessary parameters
            // (e.g. state).
            $authorizationUrl = $this->oauthProvider->getAuthorizationUrl();

            // Get the state generated for you and store it to the session.
            $_SESSION['oauth2state'] = $this->oauthProvider->getState();

            return $response->withHeader('Location', $authorizationUrl);
        }

        if (empty($_GET['state']) || (isset($_SESSION['oauth2state']) && $_GET['state'] !== $_SESSION['oauth2state'])) {
            if (isset($_SESSION['oauth2state'])) {
                unset($_SESSION['oauth2state']);
            }

            $response->getBody()->write(
                $this->templateEngine->getEnvironment()->render('zoomauth.html', ['error' => 'Something went wrong, please try again'])
            );

            return $response->withStatus(400, 'Invalid state');
        }

        try {
            // Try to get an access token using the authorization code grant.
            $accessToken = $this->oauthProvider->getAccessToken('authorization_code', [
                'code' => $_GET['code'],
            ]);
            //$owner = $this->oauthProvider->getResourceOwner($accessToken);
            /** @var Helper<string,string> $session */
            $session = $request->getAttribute('session');
            $user = $this->userRepository->findById($session->get('userid'));
            $user->setZoomUserid(uniqid('zm_'));
            $user->setZoomAccessToken($accessToken);
            $this->userRepository->update($user);

            $response->getBody()->write(
                $this->templateEngine->getEnvironment()->render('goodtogo.html')
            );

            return $response->withStatus(200);
        } catch (UserNotFoundException $e) {
            $response->getBody()->write(
                $this->templateEngine->getEnvironment()->render('slackauth.html', ['error' => 'Please authorize via Slack first and then link your Zoom account.'])
            );

            return $response->withStatus(400, 'Please autohrize via Slack first');
        } catch (IdentityProviderException $e) {
            $this->logger->error('Failed to get access token or user details', $e->getTrace());

            $response->getBody()->write(
                $this->templateEngine->getEnvironment()->render('zoomauth.html', ['error' => 'Something went wrong, please try again'])
            );

            return $response->withStatus(400);
        } catch (PDOException $e) {
            $this->logger->error('Database unreachable', ['exception' => $e]);

            $response->getBody()->write(
                $this->templateEngine->getEnvironment()->render('zoomauth.html', ['error' => 'Something went wrong, please try again'])
            );

            return $response->withStatus(500);
        } catch (Exception $e) {
            $this->logger->error('Failed to get access token or user details', $e->getTrace());

            $response->getBody()->write(
                $this->templateEngine->getEnvironment()->render('zoomauth.html', ['error' => 'Something went wrong, please try again'])
            );

            return $response->withStatus(500);
        }
    }
}
