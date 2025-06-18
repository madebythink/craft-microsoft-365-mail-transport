<?php

namespace madebythink\office365mailtransport\mail;

use Craft;
use craft\helpers\App;
use craft\mail\transportadapters\BaseTransportAdapter;
use Symfony\Component\Mailer\Transport\AbstractTransport;

class Office365Adapter extends BaseTransportAdapter
{
    // Static Methods
    // =========================================================================

    public static function displayName(): string
    {
        return 'Office 365 (Graph API)';
    }

    // Public Properties
    // =========================================================================

    public ?string $tenantId = null;
    public ?string $clientId = null;
    public ?string $clientSecret = null;
    public ?string $fromEmail = null;

    // Public Methods
    // =========================================================================

    public function rules(): array
    {
        return [
            [['tenantId', 'clientId', 'clientSecret', 'fromEmail'], 'required'],
            [['tenantId', 'clientId', 'clientSecret', 'fromEmail'], 'string'],
            [['fromEmail'], 'email'],
        ];
    }

    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate(
            'office365mailtransport/_settings',
            ['adapter' => $this]
        );
    }

    public function defineTransport(): AbstractTransport|array
    {
        return new Office365Transport([
            'tenantId' => App::parseEnv($this->tenantId),
            'clientId' => App::parseEnv($this->clientId),
            'clientSecret' => App::parseEnv($this->clientSecret),
            'fromEmail' => App::parseEnv($this->fromEmail),
        ]);
    }
}