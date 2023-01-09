<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\fieldlayoutelements;

use Craft;
use craft\base\ElementInterface;
use craft\commerce\base\Purchasable;
use craft\fieldlayoutelements\BaseNativeField;
use craft\helpers\Cp;
use yii\base\InvalidArgumentException;

/**
 * PurchasableAvailableForPurchaseField represents an Available for Purchase lightswitch field that is included within a variant field layout designer.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class PurchasableAvailableForPurchaseField extends BaseNativeField
{
    /**
     * @inheritdoc
     */
    public bool $mandatory = true;

    /**
     * @inheritdoc
     */
    public ?string $label = 'Available for purchase';

    /**
     * @inheritdoc
     */
    public bool $required = true;

    /**
     * @inheritdoc
     */
    public function inputHtml(ElementInterface $element = null, bool $static = false): ?string
    {
        if (!$element instanceof Purchasable) {
            throw new InvalidArgumentException(static::class . ' can only be used in purchasable field layouts.');
        }

        return Cp::lightswitchHtml([
            'id' => 'available-for-purchase',
            'name' => 'availableForPurchase',
            'small' => true,
            'on' => $element->getAvailableForPurchase($element->getStore()),
        ]);
    }

    /**
     * @inheritdoc
     */
    protected function defaultLabel(?ElementInterface $element = null, bool $static = false): ?string
    {
        return Craft::t('commerce', 'Available for purchase');
    }

    /**
     * @inheritdoc
     */
    public function attribute(): string
    {
        return 'availableForPurchase';
    }
}
