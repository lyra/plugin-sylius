<?php
/**
 * Copyright © Lyra Network.
 * This file is part of Monetico Retail plugin for Sylius. See COPYING.md for license details.
 *
 * @author    Lyra Network <https://www.lyra.com>
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/mit-license.html The MIT License (MIT)
 */

declare(strict_types=1);

namespace Lyranetwork\Monetico\Service;

use Lyranetwork\Monetico\Repository\PaymentMethodRepositoryInterface;
use Lyranetwork\Monetico\Sdk\Tools;

/**
 * Service for retrieving payment gateway configuration values.
 */
final class ConfigService
{
    /**
     * @param PaymentMethodRepositoryInterface $paymentMethodRepository Repository for retrieving payment methods
     */
    public function __construct(
        private PaymentMethodRepositoryInterface $paymentMethodRepository
    ) {
    }

    /**
     * Retrieves a configuration value for a specific payment gateway instance.
     *
     * @param string $configId The configuration key to retrieve
     * @param string $instanceCode The payment gateway instance code
     *
     * @return mixed The configuration value, or an empty string if not found
     */
    public function get(string $configId, string $instanceCode): mixed
    {
        if (empty($configId)) {
            return "";
        }

        $paymentMethod = $this->paymentMethodRepository->findByGatewayNameAndCode(Tools::FACTORY_NAME, $instanceCode);
        if (! $paymentMethod) {
            return "";
        }

        $gatewayConfig = $paymentMethod->getGatewayConfig();
        if (! $gatewayConfig) {
            return "";
        }

        $config = $gatewayConfig->getConfig();

        return $config[$configId] ?? "";
    }
}