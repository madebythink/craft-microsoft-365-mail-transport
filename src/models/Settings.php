<?php

namespace madebythink\office365mailtransport\models;

use craft\base\Model;

class Settings extends Model
{
    public ?string $clientId = null;
    public ?string $clientSecret = null;
    public ?string $smtpUsername = null;
    public ?string $smtpHost = 'smtp.office365.com';
    public ?int $smtpPort = 587;
    public bool $useTls = true;

    public function rules(): array
    {
        return [
            [['clientId', 'clientSecret', 'smtpUsername'], 'required'],
            [['clientId', 'clientSecret', 'smtpUsername', 'smtpHost'], 'string'],
            [['smtpPort'], 'integer'],
            [['useTls'], 'boolean'],
        ];
    }
}
