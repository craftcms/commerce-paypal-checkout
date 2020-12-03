<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license MIT
 */

namespace craft\commerce\paypalcheckout\models;

use craft\base\Model;

/**
 * Settings model.
 *
 * @property bool $sendTotalsBreakdown
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 1.x
 */
class Settings extends Model
{
    // Properties
    // =========================================================================

    /**
     * @var bool Whether to send discount, tax toals etc to PayPal.
     */
    public $sendTotalsBreakdown = true;
}
