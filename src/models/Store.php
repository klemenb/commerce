<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use Craft;
use craft\behaviors\EnvAttributeParserBehavior;
use craft\commerce\base\Model;
use craft\commerce\Plugin;
use craft\helpers\App;
use craft\helpers\UrlHelper;
use yii\base\InvalidConfigException;


/**
 * Store model.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0
 *
 * @property-read StoreSettings|null $settings
 * @property-write string $name
 * @property-read array $config
 */
class Store extends Model
{
    /**
     * @var int|null ID
     */
    public ?int $id = null;

    /**
     * @var string|null
     */
    private ?string $_name = null;

    /**
     * @var string|null Handle
     */
    public ?string $handle = null;

    /**
     * @var bool Primary store?
     */
    public bool $primary = false;

    /**
     * @var string|null Store UID
     */
    public ?string $uid = null;

    /**
     * @var StoreSettings|null
     */
    private ?StoreSettings $_storeSettings = null;

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['name', 'handle'], 'required'];
        $rules[] = [['primary', 'id', 'uid'], 'safe'];

        return $rules;
    }

    /**
     * Returns the store’s name.
     *
     * @param bool $parse Whether to parse the name for an environment variable
     * @return string
     */
    public function getName(bool $parse = true): string
    {
        return ($parse ? App::parseEnv($this->_name) : $this->_name) ?? '';
    }

    /**
     * Sets the store’s name.
     *
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->_name = $name;
    }

    /**
     * @inheritdoc
     */
    protected function defineBehaviors(): array
    {
        return [
            'parser' => [
                'class' => EnvAttributeParserBehavior::class,
                'attributes' => [
                    'name' => fn() => $this->getName(false),
                ],
            ],
        ];
    }


    /**
     * Gets the CP url to this stores settings
     * @return string
     */
    public function getStoreSettingsUrl(?string $path = null): string
    {
        $path = $path ? '/' . $path : '';
        return UrlHelper::cpUrl('commerce/store-settings/' . $this->handle . $path);
    }

    /**
     * @return StoreSettings
     */
    public function getSettings(): StoreSettings
    {
        return Plugin::getInstance()->getStoreSettings()->getStoreSettingsById($this->id);
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'name' => Craft::t('commerce', 'Name'),
            'commerce' => Craft::t('commerce', 'Handle'),
            'primary' => Craft::t('commerce', 'primary'),
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributes(): array
    {
        $attributes = parent::attributes();
        $attributes[] = 'name';
        return $attributes;
    }

    /**
     * Returns the project config data for this store.
     */
    public function getConfig(): array
    {
        return [
            'name' => $this->_name,
            'handle' => $this->handle,
            'primary' => $this->primary,
        ];
    }
}
