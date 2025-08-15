<?php

namespace madebythink\microsoft365mailtransport\mail;

use Craft;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Psr\Log\LoggerInterface;
// use Symfony\Component\Mailer\Envelope;
// use Symfony\Component\Mailer\Exception\TransportException;
// use Symfony\Component\Mailer\SentMessage;
// use Symfony\Component\Mailer\Transport\AbstractTransport;
// use Symfony\Component\Mime\Address;
// use Symfony\Component\Mime\Email;
// use Symfony\Component\Mime\MessageConverter;

class Microsoft365Transport implements \Swift_Transport
{
    private const GRAPH_API_URL = 'https://graph.microsoft.com/v1.0';
    private const GRAPH_API_SCOPE = 'https://graph.microsoft.com/.default';

    private string $tenantId;
    private string $clientId;
    private string $clientSecret;
    private string $fromEmail;
    private Client $client;

    public function __construct(array $config)
    {
        $this->tenantId = $config['tenantId'];
        $this->clientId = $config['clientId'];
        $this->clientSecret = $config['clientSecret'];
        $this->fromEmail = $config['fromEmail'];
        $this->client = new Client();
    }

    public function __toString(): string
    {
        return 'microsoft-graph';
    }

    public function isStarted(): bool
    {
        return true;
    }

    public function start(): void
    {
    }

    public function stop(): void
    {
    }

    public function ping(): bool {
        return true;
    }

    public function registerPlugin(\Swift_Events_EventListener $plugin): void
    {
        // Not implemented
    }

    // protected function doSend(SentMessage $message): void
    // {
    //     try {
    //         $accessToken = $this->getAccessToken();
    //         $email = MessageConverter::toEmail($message->getOriginalMessage());
    //         $payload = $this->buildPayload($email);

    //         $endpoint = self::GRAPH_API_URL . "/users/{$this->fromEmail}/sendMail";

    //         $this->client->post($endpoint, [
    //             'headers' => [
    //                 'Authorization' => 'Bearer ' . $accessToken->getToken(),
    //                 'Content-Type' => 'application/json',
    //             ],
    //             'json' => $payload,
    //         ]);
    //     } catch (GuzzleException | IdentityProviderException $e) {
    //         Craft::error('Email sending failed via Graph API: ' . $e->getMessage(), __METHOD__);
    //         throw new TransportException('Could not send email via Microsoft Graph API.', 0, $e);
    //     }
    // }

    public function send(\Swift_Mime_SimpleMessage $message, &$failedRecipients = null): int
    {
        $failedRecipients = (array) $failedRecipients;

        try {
            $accessToken = $this->getAccessToken();
            $payload = $this->buildPayload($message);

            $endpoint = self::GRAPH_API_URL . "/users/{$this->fromEmail}/sendMail";

            $this->client->post($endpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken->getToken(),
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);
        } catch (GuzzleException | IdentityProviderException | \Exception $e) {
            Craft::error('Email sending failed via Graph API: ' . $e->getMessage(), __METHOD__);
            // In case of failure, you might want to add all recipients to the failed list
            $allRecipients = array_merge(
                array_keys((array)$message->getTo()),
                array_keys((array)$message->getCc()),
                array_keys((array)$message->getBcc())
            );
            $failedRecipients = array_unique(array_merge($failedRecipients, $allRecipients));
        }

        $sentRecipients = array_merge(
            array_keys((array)$message->getTo()),
            array_keys((array)$message->getCc()),
            array_keys((array)$message->getBcc())
        );

        return count($sentRecipients) - count($failedRecipients);
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
            throw $e;
        }

        // Cache the token for its lifetime, minus a 60-second buffer
        $expiresIn = $accessToken->getExpires() ? $accessToken->getExpires() - time() - 60 : 3540;
        Craft::$app->getCache()->set($cacheKey, $accessToken, $expiresIn > 0 ? $expiresIn : 3540);

        return $accessToken;
    }

    private function buildPayload(\Swift_Message $swiftMessage): array
    {
        $formatAddress = function (string $email, ?string $name): array {
            return [
                'emailAddress' => [
                    'name' => $name,
                    'address' => $email,
                ],
            ];
        };

        $toRecipients = [];
        if ($swiftMessage->getTo()) {
            foreach ($swiftMessage->getTo() as $email => $name) {
                $toRecipients[] = $formatAddress($email, $name);
            }
        }

        $message = [
            'subject' => $swiftMessage->getSubject(),
            'body' => [
                'contentType' => $swiftMessage->getContentType() === 'text/html' ? 'HTML' : 'Text',
                'content' => $swiftMessage->getBody(),
            ],
            'toRecipients' => $toRecipients,
        ];

        if ($cc = $swiftMessage->getCc()) {
            $ccRecipients = [];
            foreach ($cc as $email => $name) {
                $ccRecipients[] = $formatAddress($email, $name);
            }
            $message['ccRecipients'] = $ccRecipients;
        }

        if ($bcc = $swiftMessage->getBcc()) {
            $bccRecipients = [];
            foreach ($bcc as $email => $name) {
                $bccRecipients[] = $formatAddress($email, $name);
            }
            $message['bccRecipients'] = $bccRecipients;
        }

        if ($replyTo = $swiftMessage->getReplyTo()) {
            $replyToRecipients = [];
            foreach ($replyTo as $email => $name) {
                $replyToRecipients[] = $formatAddress($email, $name);
            }
            $message['replyTo'] = $replyToRecipients;
        }

        // Handle attachments
        $attachments = [];
        foreach ($swiftMessage->getChildren() as $child) {
            if ($child instanceof \Swift_Attachment) {
                $attachments[] = [
                    '@odata.type' => '#microsoft.graph.fileAttachment',
                    'name' => $child->getFilename(),
                    'contentType' => $child->getContentType(),
                    'contentBytes' => base64_encode($child->getBody()),
                ];
            }
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
