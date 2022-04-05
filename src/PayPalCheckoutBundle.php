<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license MIT
 */

namespace craft\commerce\paypalcheckout;

use craft\web\AssetBundle;

/**
 * Asset bundle for the PayPal REST payment
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 1.0
 */
class PayPalCheckoutBundle extends AssetBundle
{
    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->sourcePath = '@craft/commerce/paypalcheckout/resources';

        $this->js = [
            'js/paymentForm.js',
        ];

        parent::init();
    }
}
