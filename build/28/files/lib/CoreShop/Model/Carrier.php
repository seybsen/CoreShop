<?php
/**
 * CoreShop
 *
 * LICENSE
 *
 * This source file is subject to the GNU General Public License version 3 (GPLv3)
 * For the full copyright and license information, please view the LICENSE.md and gpl-3.0.txt
 * files that are distributed with this source code.
 *
 * @copyright  Copyright (c) 2015 Dominik Pfaffenbauer (http://dominik.pfaffenbauer.at)
 * @license    http://www.coreshop.org/license     GNU General Public License version 3 (GPLv3)
 */

namespace CoreShop\Model;

use CoreShop\Model\Carrier\AbstractRange;
use CoreShop\Model\Carrier\DeliveryPrice;
use CoreShop\Plugin;
use CoreShop\Tool;
use Pimcore\Cache;
use Pimcore\Db;
use Pimcore\Model\Asset;
use Pimcore\Model\Object\Fieldcollection\Data\CoreShopUserAddress;

class Carrier extends AbstractModel
{
    const SHIPPING_METHOD_WEIGHT = "weight";
    const SHIPPING_METHOD_PRICE = "price";

    const RANGE_BEHAVIOUR_DEACTIVATE = "deactivate";
    const RANGE_BEHAVIOUR_LARGEST = "largest";

    public static $shippingMethods = array(self::SHIPPING_METHOD_PRICE, self::SHIPPING_METHOD_WEIGHT);
    public static $rangeBehaviours = array(self::RANGE_BEHAVIOUR_LARGEST, self::RANGE_BEHAVIOUR_DEACTIVATE);

    /**
     * @var int
     */
    public $id;

    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $label;

    /**
     * @var string
     */
    public $delay;

    /**
     * @var boolean
     */
    public $needsRange;

    /**
     * @var int
     */
    public $grade;

    /**
     * @var int
     */
    public $image;

    /**
     * @var string
     */
    public $trackingUrl;

    /**
     * @var bool
     */
    public $isFree;

    /**
     * @var string
     */
    public $shippingMethod;

    /**
     * @var int
     */
    public $taxRuleGroupId;

    /**
     * @var TaxRuleGroup
     */
    public $taxRuleGroup;

    /**
     * @var int
     */
    public $rangeBehaviour;

    /**
     * @var float
     */
    public $maxHeight;

    /**
     * @var float
     */
    public $maxWidth;

    /**
     * @var float
     */
    public $maxDepth;

    /**
     * @var float
     */
    public $maxWeight;

    /**
     * @var string
     */
    public $class;

    /**
     * Save carrier
     *
     * @return mixed
     */
    public function save()
    {
        return $this->getDao()->save();
    }

    /**
     * get Carrier by ID
     *
     * @param $id
     * @return Carrier|null
     */
    public static function getById($id)
    {
        $id = intval($id);

        if ($id < 1) {
            return null;
        }

        $cacheKey = "coreshop_carrier_" . $id;

        try {
            $carrier = \Zend_Registry::get($cacheKey);
            if (!$carrier) {
                throw new \Exception("Carrier in registry is null");
            }

            return $carrier;
        } catch (\Exception $e) {
            try {
                if (!$carrier = Cache::load($cacheKey)) {
                    $db = Db::get();
                    //Todo: TableName already definied within 2 Dao files
                    $data = $db->fetchRow('SELECT class FROM coreshop_carriers WHERE id = ?', $id);

                    $class = get_called_class();
                    if (is_array($data) && $data['class']) {
                        if (\Pimcore\Tool::classExists($data['class'])) {
                            $class = $data['class'];
                        } else {
                            \Logger::warning(sprintf("Carrier with ID %s has definied class '%s' which cannot be loaded.", $id, $data['class']));
                        }
                    }

                    $carrier = new $class();
                    $carrier->getDao()->getById($id);

                    \Zend_Registry::set($cacheKey, $carrier);
                    Cache::save($carrier, $cacheKey);
                } else {
                    \Zend_Registry::set($cacheKey, $carrier);
                }

                return $carrier;
            } catch (\Exception $e) {
                \Logger::warning($e->getMessage());
            }
        }

        return null;
    }

    /**
     * get all carriers
     *
     * @return Carrier[]
     */
    public static function getAll()
    {
        $list = new Carrier\Listing();
        $list->setOrder("ASC");
        $list->setOrderKey("grade");

        return $list->getData();
    }

    /**
     * Get all available Carriers for cart
     *
     * @param Zone $zone
     * @param Cart|null $cart
     * @return Carrier[]
     */
    public static function getCarriersForCart(Cart $cart = null, Zone $zone = null)
    {
        if (is_null($cart)) {
            $cart = Tool::prepareCart();
        }
        if (is_null($zone)) {
            $zone = Tool::getCountry()->getZone();
        }

        $carriers = self::getAll();
        $availableCarriers = array();

        foreach ($carriers as $carrier) {
            if ($carrier->checkCarrierForCart($cart, $zone)) {
                $availableCarriers[] = $carrier;
            }
        }

        return $availableCarriers;
    }

    /**
     * Get cheapest carrier for cart
     *
     * @param Cart $cart
     * @param Zone $zone
     * @return Carrier|null
     */
    public static function getCheapestCarrierForCart(Cart $cart, Zone $zone = null) {
        $cacheKey = "cheapest_carrier_" - $cart->getId();

        try {
            $cheapestProvider = \Zend_Registry::get($cacheKey);
            if (!$cheapestProvider) {
                throw new \Exception($cacheKey . " in registry is null");
            }

            return $cheapestProvider;
        } catch (\Exception $e) {
            try {
                if (!$cheapestProvider = Cache::load($cacheKey))
                {
                    $providers = self::getCarriersForCart($cart, $zone);
                    $cheapestProvider = null;

                    foreach ($providers as $p) {
                        if ($cheapestProvider === null) {
                            $cheapestProvider = $p;
                        } elseif ($cheapestProvider->getDeliveryPrice($cart) > $p->getDeliveryPrice($cart)) {
                            $cheapestProvider = $p;
                        }
                    }

                    if ($cheapestProvider instanceof Carrier) {
                        return $cheapestProvider;
                    }

                    \Zend_Registry::set($cacheKey, $cheapestProvider);
                    Cache::save($cheapestProvider, $cacheKey);
                } else {
                    \Zend_Registry::set($cacheKey, $cheapestProvider);
                }

                return $cheapestProvider;
            } catch (\Exception $e) {
                \Logger::warning($e->getMessage());
            }
        }

        return null;
    }

    /**
     * Check if carrier is allowed for cart and zone
     *
     * @param Cart|null $cart
     * @param Zone|null $zone
     * @return bool
     * @throws \CoreShop\Exception\UnsupportedException
     */
    public function checkCarrierForCart(Cart $cart = null, Zone $zone = null)
    {
        if (!$this->getMaxDeliveryPrice()) {
            return false;
        }

        //Check for Ranges
        if ($this->getRangeBehaviour() == self::RANGE_BEHAVIOUR_DEACTIVATE) {
            if ($this->getShippingMethod() == self::SHIPPING_METHOD_PRICE) {
                if (!$this->checkDeliveryPriceByValue($zone, $cart->getTotal())) {
                    return false;
                }
            }

            if ($this->getShippingMethod() == self::SHIPPING_METHOD_WEIGHT) {
                if (!$this->checkDeliveryPriceByValue($zone, $cart->getTotalWeight())) {
                    return false;
                }
            }
        }

        $carrierIsAllowed = true;

        //Check for max-size
        foreach ($cart->getItems() as $item) {
            $product = $item->getProduct();

            if (($this->getMaxWidth() > 0 && $product->getWidth() > $this->getMaxWidth())
                || ($this->getMaxHeight() > 0 && $product->getHeight() > $this->getMaxHeight())
                || ($this->getMaxDepth() > 0 && $product->getDepth() > $this->getMaxDepth())
                || ($this->getMaxWeight() > 0 && $product->getWeight() > $this->getMaxWeight())) {
                $carrierIsAllowed = false;
                break;
            }
        }

        if (!$carrierIsAllowed) {
            return false;
        }

        return true;
    }

    /**
     * Get the Ranges for this carrier
     *
     * @return AbstractRange[]
     */
    public function getRanges()
    {
        if ($this->getShippingMethod() == "weight") {
            $list = new Carrier\RangeWeight\Listing();
        } else {
            $list = new Carrier\RangePrice\Listing();
        }

        $list->setCondition("carrierId=?", array($this->getId()));
        $list->load();

        return $list->getData();
    }

    /**
     * Get max possible delivery price for this carrier
     *
     * @param Zone $zone
     * @return float|bool
     */
    public function getMaxDeliveryPrice(Zone $zone = null)
    {
        if (is_null($zone)) {
            $zone = Tool::getCountry()->getZone();
        }

        $ranges = $this->getRanges();

        if (count($ranges) === 0) {
            return false;
        }

        $maxPrice = 0;

        foreach ($ranges as $range) {
            $price = $range->getPriceForZone($zone);

            if ($price instanceof DeliveryPrice) {
                if ($price->getPrice() > $maxPrice) {
                    $maxPrice = $price->getPrice();
                }
            }
        }

        return $maxPrice;
    }

    /**
     * Get DeliveryPrice without Tax
     *
     * @param Cart $cart
     * @param Zone|null $zone
     * @return bool|float
     */
    public function getDeliveryPriceWithoutTax(Cart $cart, Zone $zone = null) {
        if (is_null($zone)) {
            $zone = Tool::getCountry()->getZone();
        }

        if ($this->getShippingMethod() === self::SHIPPING_METHOD_PRICE) {
            $value = $cart->getTotal();
        } else {
            $value = $cart->getTotalWeight();
        }

        $ranges = $this->getRanges();

        foreach ($ranges as $range) {
            $price = $range->getPriceForZone($zone);

            if ($price instanceof DeliveryPrice) {
                if ($value >= $range->getDelimiter1() && $value < $range->getDelimiter2()) {
                    $deliveryPrice = $price->getPrice();

                    return $deliveryPrice;
                }
            }
        }

        if ($this->getRangeBehaviour() === self::RANGE_BEHAVIOUR_LARGEST) {
            $deliveryPrice = $this->getMaxDeliveryPrice($zone);

            return $deliveryPrice;
        }

        return false;
    }

    /**
     * Get delivery Price for cart
     *
     * @param Zone $zone
     * @param Cart $cart
     * @return bool|float
     */
    public function getDeliveryPrice(Cart $cart, Zone $zone = null)
    {
        $taxCalculator = $this->getTaxCalculator($cart->getCustomerShippingAddress() ? $cart->getCustomerShippingAddress() : null);
        $deliveryPrice = $this->getDeliveryPriceWithoutTax($cart, $zone);

        if ($taxCalculator) {
            return $taxCalculator->addTaxes($deliveryPrice);
        }

        return $deliveryPrice;
    }

    /**
     * get delivery Tax for cart
     *
     * @param Cart $cart
     * @param Zone|null $zone
     *
     * @return float;
     */
    public function getTaxAmount(Cart $cart, Zone $zone = null) {
        $taxCalculator = $this->getTaxCalculator($cart->getCustomerShippingAddress() ? $cart->getCustomerShippingAddress() : null);
        $deliveryPrice = $this->getDeliveryPriceWithoutTax($cart, $zone);

        if($taxCalculator) {
            return $taxCalculator->getTaxesAmount($deliveryPrice);
        }

        return $deliveryPrice;
    }

    /**
     * get TaxCalculator
     *
     * @param CoreShopUserAddress $address
     * @return bool|TaxCalculator
     */
    public function getTaxCalculator(CoreShopUserAddress $address = null)
    {
        if(is_null($address)) {
            $address = new CoreShopUserAddress();
            $address->setCountry(Tool::getCountry());
        }

        $taxRule = $this->getTaxRuleGroup();

        if ($taxRule instanceof TaxRuleGroup) {
            $taxManager = TaxManagerFactory::getTaxManager($address, $taxRule->getId());
            $taxCalculator = $taxManager->getTaxCalculator();

            return $taxCalculator;
        }

        return false;
    }

    /**
     * Check if carrier is available for zone and value
     *
     * @param Zone $zone
     * @param $value
     * @return bool
     */
    public function checkDeliveryPriceByValue(Zone $zone, $value)
    {
        $ranges = $this->getRanges();

        foreach ($ranges as $range) {
            $price = $range->getPriceForZone($zone);

            if ($price instanceof DeliveryPrice) {
                if ($value >= $range->getDelimiter1() && $value < $range->getDelimiter2()) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * @param string $label
     */
    public function setLabel($label)
    {
        $this->label = $label;
    }

    /**
     * @return string
     */
    public function getDelay()
    {
        return $this->delay;
    }

    /**
     * @param string $delay
     */
    public function setDelay($delay)
    {
        $this->delay = $delay;
    }

    /**
     * @return int
     */
    public function getGrade()
    {
        return $this->grade;
    }

    /**
     * @param int $grade
     */
    public function setGrade($grade)
    {
        $this->grade = $grade;
    }

    /**
     * @return int
     */
    public function getImage()
    {
        if(is_string($this->image)) {
            $asset = Asset::getByPath($this->image);

            if($asset instanceof Asset) {
                $this->image = $asset;
            }
        }
        return $this->image;
    }

    /**
     * @param int $image
     */
    public function setImage($image)
    {
        $this->image = $image;
    }

    /**
     * @return string
     */
    public function getTrackingUrl()
    {
        return $this->trackingUrl;
    }

    /**
     * @param string $trackingUrl
     */
    public function setTrackingUrl($trackingUrl)
    {
        $this->trackingUrl = $trackingUrl;
    }

    /**
     * @return boolean
     */
    public function getIsFree()
    {
        return $this->isFree;
    }

    /**
     * @param boolean $isFree
     */
    public function setIsFree($isFree)
    {
        $this->isFree = $isFree;
    }

    /**
     * @return string
     */
    public function getShippingMethod()
    {
        return $this->shippingMethod;
    }

    /**
     * @param string $shippingMethod
     */
    public function setShippingMethod($shippingMethod)
    {
        $this->shippingMethod = $shippingMethod;
    }

    /**
     * @return int
     */
    public function getTaxRuleGroupId()
    {
        return $this->taxRuleGroupId;
    }

    /**
     * @param int $taxRuleGroupId
     * @throws \Exception
     */
    public function setTaxRuleGroupId($taxRuleGroupId)
    {
        $taxRuleGroup = TaxRuleGroup::getById($taxRuleGroupId);

        if (!$taxRuleGroup instanceof TaxRuleGroup) {
            return;
        }

        $this->taxRuleGroupId = $taxRuleGroupId;
        $this->taxRuleGroup = $taxRuleGroup;
    }

    /**
     * @return TaxRuleGroup
     */
    public function getTaxRuleGroup()
    {
        return $this->taxRuleGroup;
    }

    /**
     * @param int|TaxRuleGroup $taxRuleGroup
     * @throws \Exception
     */
    public function setTaxRuleGroup($taxRuleGroup)
    {
        if (is_int($taxRuleGroup)) {
            $taxRuleGroup = TaxRuleGroup::getById($taxRuleGroup);
        }

        if (!$taxRuleGroup instanceof TaxRuleGroup) {
            throw new \Exception("\$taxRuleGroup must be instance of TaxRuleGroup");
        }

        $this->taxRuleGroup = $taxRuleGroup;
        $this->taxRuleGroupId = $taxRuleGroup->getId();
    }

    /**
     * @return int
     */
    public function getRangeBehaviour()
    {
        return $this->rangeBehaviour;
    }

    /**
     * @param int $rangeBehaviour
     */
    public function setRangeBehaviour($rangeBehaviour)
    {
        $this->rangeBehaviour = $rangeBehaviour;
    }

    /**
     * @return float
     */
    public function getMaxHeight()
    {
        return $this->maxHeight;
    }

    /**
     * @param float $maxHeight
     */
    public function setMaxHeight($maxHeight)
    {
        $this->maxHeight = $maxHeight;
    }

    /**
     * @return float
     */
    public function getMaxWidth()
    {
        return $this->maxWidth;
    }

    /**
     * @param float $maxWidth
     */
    public function setMaxWidth($maxWidth)
    {
        $this->maxWidth = $maxWidth;
    }

    /**
     * @return float
     */
    public function getMaxDepth()
    {
        return $this->maxDepth;
    }

    /**
     * @param float $maxDepth
     */
    public function setMaxDepth($maxDepth)
    {
        $this->maxDepth = $maxDepth;
    }

    /**
     * @return float
     */
    public function getMaxWeight()
    {
        return $this->maxWeight;
    }

    /**
     * @param float $maxWeight
     */
    public function setMaxWeight($maxWeight)
    {
        $this->maxWeight = $maxWeight;
    }

    /**
     * @return boolean
     */
    public function getNeedsRange()
    {
        return $this->needsRange;
    }

    /**
     * @param boolean $needsRange
     */
    public function setNeedsRange($needsRange)
    {
        $this->needsRange = $needsRange;
    }

    /**
     * @return string
     */
    public function getClass()
    {
        return $this->class;
    }

    /**
     * @param string $class
     */
    public function setClass($class)
    {
        $this->class = $class;
    }
}
