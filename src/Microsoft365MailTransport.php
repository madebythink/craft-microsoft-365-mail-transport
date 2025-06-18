<?php
/**
 * @copyright
 */

namespace madebythink\office365mailtransport;

use craft\base\Plugin;
use craft\events\RegisterComponentTypesEvent;
use craft\helpers\MailerHelper;
use madebythink\office365mailtransport\mail\Office365Adapter;
use yii\base\Event;

class Office365MailTransport extends Plugin
{
    /**
     * @var Office365MailTransport
     */
    public static Office365MailTransport $plugin;

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
                $event->types[] = Office365Adapter::class;
            }
        );
    }
}
