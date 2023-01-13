<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\base\PurchasableInterface;
use craft\commerce\elements\Order;
use craft\commerce\elements\Variant;
use craft\commerce\events\PurchasableAvailableEvent;
use craft\commerce\events\PurchasableShippableEvent;
use craft\commerce\models\PurchasableStore;
use craft\commerce\records\PurchasableStore as PurchasableStoreRecord;
use craft\elements\User;
use craft\events\RegisterComponentTypesEvent;
use Illuminate\Support\Collection;
use Throwable;
use yii\base\Component;
use yii\base\InvalidArgumentException;
use yii\base\InvalidConfigException;

/**
 * Product type service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 *
 * @property array|string[] $allPurchasableElementTypes
 */
class Purchasables extends Component
{
    /**
     * @event PurchasableAvailableEvent The event that is triggered when the availability of a purchasables is checked.
     *
     * This example stop users of a certain group from having the purchasable be available to them in their order.
     *
     * ```php
     * use craft\commerce\events\PurchasableAvailableEvent;
     * use craft\commerce\services\Purchasables;
     * use yii\base\Event;
     *
     * Event::on(
     *     Purchasables::class,
     *     Purchasables::EVENT_PURCHASABLE_AVAILABLE,
     *     function(PurchasableAvailableEvent $event) {
     *         if($order && $user = $order->getUser()){
     *             $event->isAvailable = $event->isAvailable && !$user->isInGroup(1); // Group ID 1 not allowed to have purchasable in the cart.
     *         }
     *     }
     * );
     * ```
     */
    public const EVENT_PURCHASABLE_AVAILABLE = 'purchasableAvailable';

    /**
     * @event PurchasableShippableEvent The event that is triggered when determining whether a purchasable may be shipped.
     *
     * This example prevents the purchasable from being shippable in a specific user group's orders:
     *
     * ```php
     * use craft\commerce\events\PurchasableShippableEvent;
     * use craft\commerce\services\Purchasables;
     * use yii\base\Event;
     *
     * Event::on(
     *     Purchasables::class,
     *     Purchasables::EVENT_PURCHASABLE_SHIPPABLE,
     *     function(PurchasableShippableEvent $event) {
     *         if($order && $user = $order->getUser()){
     *             $event->isShippable = $event->is && !$user->isInGroup(1);
     *         }
     *     }
     * );
     * ```
     */
    public const EVENT_PURCHASABLE_SHIPPABLE = 'purchasableShippable';

    /**
     * @event RegisterComponentTypesEvent The event that is triggered for registration of additional purchasables.
     *
     * This example adds an instance of `MyPurchasable` to the event object’s `types` array:
     *
     * ```php
     * use craft\events\RegisterComponentTypesEvent;
     * use craft\commerce\services\Purchasables;
     * use yii\base\Event;
     *
     * Event::on(
     *     Purchasables::class,
     *     Purchasables::EVENT_REGISTER_PURCHASABLE_ELEMENT_TYPES,
     *     function(RegisterComponentTypesEvent $event) {
     *         $event->types[] = MyPurchasable::class;
     *     }
     * );
     * ```
     */
    public const EVENT_REGISTER_PURCHASABLE_ELEMENT_TYPES = 'registerPurchasableElementTypes';

    /**
     * @param Order|null $order
     * @param User|null $currentUser
     * @since 3.3.1
     */
    public function isPurchasableAvailable(PurchasableInterface $purchasable, Order $order = null, User $currentUser = null): bool
    {
        if ($currentUser === null) {
            $currentUser = Craft::$app->getUser()->getIdentity();
        }
        $isAvailable = $purchasable->getIsAvailable();

        $event = new PurchasableAvailableEvent(compact('order', 'purchasable', 'currentUser', 'isAvailable'));

        if ($this->hasEventHandlers(self::EVENT_PURCHASABLE_AVAILABLE)) {
            $this->trigger(self::EVENT_PURCHASABLE_AVAILABLE, $event);
        }

        return $event->isAvailable;
    }

    /**
     * @param Order|null $order
     * @param User|null $currentUser
     * @since 3.3.2
     */
    public function isPurchasableShippable(PurchasableInterface $purchasable, Order $order = null, User $currentUser = null): bool
    {
        if ($currentUser === null) {
            $currentUser = Craft::$app->getUser()->getIdentity();
        }
        $isShippable = $purchasable->getIsShippable();

        $event = new PurchasableShippableEvent(compact('order', 'purchasable', 'currentUser', 'isShippable'));

        if ($this->hasEventHandlers(self::EVENT_PURCHASABLE_SHIPPABLE)) {
            $this->trigger(self::EVENT_PURCHASABLE_SHIPPABLE, $event);
        }

        return $event->isShippable;
    }

    /**
     * Delete a purchasable by its ID.
     *
     * @throws Throwable
     * @noinspection PhpUnused
     */
    public function deletePurchasableById(int $purchasableId): bool
    {
        return Craft::$app->getElements()->deleteElementById($purchasableId);
    }

    /**
     * Get a purchasable by its ID.
     *
     * @param int $purchasableId
     * @return PurchasableInterface|null
     * @throws InvalidArgumentException if $purchasableId is an element ID but not a purchasable
     */
    public function getPurchasableById(int $purchasableId): ?PurchasableInterface
    {
        $purchasable = Craft::$app->getElements()->getElementById($purchasableId);
        if ($purchasable && !$purchasable instanceof PurchasableInterface) {
            throw new InvalidArgumentException(sprintf('Element %s does not implement %s', $purchasableId, PurchasableInterface::class));
        }
        return $purchasable;
    }

    /**
     * Returns all available purchasable element classes.
     *
     * @return string[] The available purchasable element classes.
     */
    public function getAllPurchasableElementTypes(): array
    {
        $purchasableElementTypes = [
            Variant::class,
        ];

        $event = new RegisterComponentTypesEvent([
            'types' => $purchasableElementTypes,
        ]);
        $this->trigger(self::EVENT_REGISTER_PURCHASABLE_ELEMENT_TYPES, $event);

        return $event->types;
    }

    /**
     * @param int $purchasableId
     * @return Collection
     * @throws InvalidConfigException
     * @since 5.0.0
     */
    public function getPurchasableStoresByPurchasableId(int $purchasableId): Collection
    {
        $purchasableStoresResults = PurchasableStoreRecord::find()
            ->where(['purchasableId' => $purchasableId])
            ->all();

        $purchasableStores = [];
        foreach ($purchasableStoresResults as $row) {
            $purchasableStores[] = Craft::createObject([
                'class' => PurchasableStore::class,
                'attributes' => $row,
            ]);
        }

        return collect($purchasableStores);
    }
}
