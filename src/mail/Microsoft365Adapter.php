<?php

namespace madebythink\microsoft365mailtransport\mail;

use Craft;
use craft\helpers\App;
use craft\mail\transportadapters\BaseTransportAdapter;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use madebythink\microsoft365mailtransport\Microsoft365MailTransport;

class Microsoft365Adapter extends BaseTransportAdapter
{
    public static function displayName(): string
    {
        return 'Microsoft 365 (Graph API)';
    }

    public ?string $tenantId = null;
    public ?string $clientId = null;
    public ?string $clientSecret = null;
    public ?string $fromEmail = null;

    public function rules(): array
    {
        return [
            [['tenantId', 'clientId', 'clientSecret', 'fromEmail'], 'required'],
            [['tenantId', 'clientId', 'clientSecret', 'fromEmail'], 'string'],
            [['fromEmail'], 'validateFromEmail'],
        ];
    }

    public function validateFromEmail(string $attribute): void
    {
        $value = $this->$attribute;
        $isEnvVar = substr(trim($value), 0, 1) === '$';

        if (!$isEnvVar && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($attribute, Craft::t('app', '{attribute} must be a valid email address or an environment variable.', [
                'attribute' => $this->getAttributeLabel($attribute),
            ]));
        }
    }

    public function getSettingsHtml(): ?string
    {
        $templatePath = Microsoft365MailTransport::$plugin->handle . '/_settings';

        return Craft::$app->getView()->renderTemplate(
            $templatePath,
            ['adapter' => $this]
        );
    }

    public function defineTransport(): AbstractTransport|array
    {
        return new Microsoft365Transport([
            'tenantId' => App::parseEnv($this->tenantId),
            'clientId' => App::parseEnv($this->clientId),
            'clientSecret' => App::parseEnv($this->clientSecret),
            'fromEmail' => App::parseEnv($this->fromEmail),
        ]);
    }
}