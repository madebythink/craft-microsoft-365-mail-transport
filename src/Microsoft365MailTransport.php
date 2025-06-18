<?php
/**
 * @copyright
 */

namespace madebythink\microsoft365mailtransport;

use craft\base\Plugin;
use craft\events\RegisterComponentTypesEvent;
use craft\helpers\MailerHelper;
use madebythink\microsoft365mailtransport\mail\Microsoft365Adapter;
use yii\base\Event;

class Microsoft365MailTransport extends Plugin
{
    /**
     * @var Microsoft365MailTransport
     */
    public static Microsoft365MailTransport $plugin;

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        self::$plugin = $this;

        Event::on(
            MailerHelper::class,
            MailerHelper::EVENT_REGISTER_MAILER_TRANSPORTS,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = Microsoft365Adapter::class;
            }
        );
    }
}
