<?php

namespace madebythink\microsoft365mailtransport\mail;

use Craft;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;

class Microsoft365Transport extends AbstractTransport
{
    private const GRAPH_API_URL = 'https://graph.microsoft.com/v1.0';
    private const GRAPH_API_SCOPE = 'https://graph.microsoft.com/.default';

    private string $tenantId;
    private string $clientId;
    private string $clientSecret;
    private string $fromEmail;
    private Client $client;

    public function __construct(array $config, LoggerInterface $logger = null)
    {
        $this->tenantId = $config['tenantId'];
        $this->clientId = $config['clientId'];
        $this->clientSecret = $config['clientSecret'];
        $this->fromEmail = $config['fromEmail'];
        $this->client = new Client();

        parent::__construct(null, $logger);
    }

    public function __toString(): string
    {
        return 'microsoft-graph';
    }

    protected function doSend(SentMessage $message): void
    {
        try {
            $accessToken = $this->getAccessToken();
            $email = MessageConverter::toEmail($message->getOriginalMessage());
            $payload = $this->buildPayload($email);

            $endpoint = self::GRAPH_API_URL . "/users/{$this->fromEmail}/sendMail";

            $this->client->post($endpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken->getToken(),
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);
        } catch (GuzzleException | IdentityProviderException $e) {
            Craft::error('Email sending failed via Graph API: ' . $e->getMessage(), __METHOD__);
            throw new TransportException('Could not send email via Microsoft Graph API.', 0, $e);
        }
    }

    /**
     * @throws IdentityProviderException
     */
    private function getAccessToken(): AccessTokenInterface
    {
        $cacheKey = 'microsoft365mailtransport_access_token.' . md5($this->clientId);
        $cachedToken = Craft::$app->getCache()->get($cacheKey);

        if ($cachedToken instanceof AccessTokenInterface && !$cachedToken->hasExpired()) {
            return $cachedToken;
        }

        $provider = new GenericProvider([
            'clientId' => $this->clientId,
            'clientSecret' => $this->clientSecret,
            'urlAuthorize' => "https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/authorize",
            'urlAccessToken' => "https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token",
            'urlResourceOwnerDetails' => '',
            'scopes' => self::GRAPH_API_SCOPE,
        ]);

        try {
            $accessToken = $provider->getAccessToken('client_credentials', [
                'scope' => self::GRAPH_API_SCOPE
            ]);
        } catch (IdentityProviderException $e) {
            $responseBody = $e->getResponseBody();
            $detailedError = $responseBody['error_description'] ?? $e->getMessage();
            Craft::error('Failed to get Microsoft Graph access token. Reason: ' . $detailedError, __METHOD__);
            throw new TransportException($detailedError, 0, $e);
        }

        // Cache the token for its lifetime, minus a 60-second buffer
        $expiresIn = $accessToken->getExpires() ? $accessToken->getExpires() - time() - 60 : 3540;
        Craft::$app->getCache()->set($cacheKey, $accessToken, $expiresIn > 0 ? $expiresIn : 3540);

        return $accessToken;
    }

    private function buildPayload(Email $email): array
    {
        $formatAddress = function (Address $address): array {
            return [
                'emailAddress' => [
                    'name' => $address->getName(),
                    'address' => $address->getAddress(),
                ],
            ];
        };

        $message = [
            'subject' => $email->getSubject(),
            'body' => [
                'contentType' => $email->getHtmlBody() ? 'HTML' : 'Text',
                'content' => $email->getHtmlBody() ?: $email->getTextBody(),
            ],
            'toRecipients' => array_map($formatAddress, $email->getTo()),
        ];

        if ($cc = $email->getCc()) {
            $message['ccRecipients'] = array_map($formatAddress, $cc);
        }
        if ($bcc = $email->getBcc()) {
            $message['bccRecipients'] = array_map($formatAddress, $bcc);
        }
        if ($replyTo = $email->getReplyTo()) {
            $message['replyTo'] = array_map($formatAddress, $replyTo);
        }

        // Handle attachments
        $attachments = [];
        foreach ($email->getAttachments() as $attachment) {
            $attachments[] = [
                '@odata.type' => '#microsoft.graph.fileAttachment',
                'name' => $attachment->getPreparedHeaders()->getHeaderParameter('Content-Disposition', 'filename'),
                'contentType' => $attachment->getMediaType(),
                'contentBytes' => base64_encode($attachment->getBody()),
            ];
        }

        if (!empty($attachments)) {
            $message['attachments'] = $attachments;
        }

        return [
            'message' => $message,
            'saveToSentItems' => 'true',
        ];
    }
}
