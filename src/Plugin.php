<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license MIT
 */

namespace craft\commerce\paypalcheckout;

use craft\commerce\paypalcheckout\gateways\Gateway;
use craft\commerce\paypalcheckout\models\Settings;
use craft\commerce\services\Gateways;
use craft\events\RegisterComponentTypesEvent;
use yii\base\Event;


/**
 * Plugin represents the PayPal Checkout integration plugin.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 1.0
 */
class Plugin extends \craft\base\Plugin
{
    // Public Properties
    // =========================================================================

    /**
     * @inheritDoc
     */
    public $schemaVersion = '1.0';

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        Event::on(Gateways::class, Gateways::EVENT_REGISTER_GATEWAY_TYPES, function(RegisterComponentTypesEvent $event) {
            $event->types[] = Gateway::class;
        });
    }

    /**
     * @inheritdoc
     */
    protected function createSettingsModel()
    {
        return new Settings();
    }
}
