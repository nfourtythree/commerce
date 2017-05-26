<?php

namespace craft\commerce\services;

use Commerce\Gateways\BaseGatewayAdapter;
use yii\base\Component;

/**
 * Gateways service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Gateways extends Component
{
    /** @var BaseGatewayAdapter[] */
    private $_gateways;

    /**
     * Returns all available gateways, indexed by handle.
     *
     * @return BaseGatewayAdapter[]
     */
    public function getAllGateways()
    {
        if (null === $this->_gateways) {
            $this->_gateways = $this->_getGateways();
        }

        return $this->_gateways;
    }

    /**
     * Returns all available gateways, indexed by handle.
     *
     * @return BaseGatewayAdapter[]
     */
    private function _getGateways()
    {
        $classes = $this->_getGatewayClasses();
        $gateways = [];
        $names = [];

        foreach ($classes as $class) {
            $gateway = new $class;
            $gateways[$gateway->handle()] = $gateway;
            $names[] = $gateway->displayName();
        }

        // Sort alphabetically
        array_multisort($names, SORT_NATURAL | SORT_FLAG_CASE, $gateways);

        return $gateways;
    }

    /**
     * Returns the full list of available gateway classes
     *
     * @return string[]
     */
    private function _getGatewayClasses()
    {
        $classes = [
            'craft\commerce\gateways\Dummy_GatewayAdapter',
            'craft\commerce\gateways\Manual_GatewayAdapter',
            'craft\commerce\gateways\PayPal_Express_GatewayAdapter',
            'craft\commerce\gateways\PayPal_Pro_GatewayAdapter',
            'craft\commerce\gateways\Stripe_GatewayAdapter',
        ];

        // Let plugins register more gateway adapters classes
        $allPluginClasses = Craft::$app->getPlugins()->call('commerce_registerGatewayAdapters', [], true);

        foreach ($allPluginClasses as $pluginClasses) {
            $classes = array_merge($classes, $pluginClasses);
        }

        return $classes;
    }
}