<?php

declare(strict_types=1);

namespace App\Service\OAuth;

use League\OAuth2\Client\Provider\Google;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class GoogleOAuthService
{
    private ?Google $client = null;

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly UrlGeneratorInterface $urlGenerator
    ) {
    }

    public function getClient(): Google
    {
        if ($this->client === null) {
            $this->client = new Google([
                'clientId'     => $this->clientId,
                'clientSecret' => $this->clientSecret,
                'redirectUri'  => $this->urlGenerator->generate('connect_google_check', [], UrlGeneratorInterface::ABSOLUTE_URL),
            ]);
        }

        return $this->client;
    }
}
