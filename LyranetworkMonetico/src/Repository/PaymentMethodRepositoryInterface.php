<?php
/**
 * Copyright © Lyra Network.
 * This file is part of Monetico Retail plugin for Sylius. See COPYING.md for license details.
 *
 * @author    Lyra Network (https://www.lyra.com/)
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/mit-license.html The MIT License (MIT)
 */

declare(strict_types=1);

namespace Lyranetwork\Monetico\Repository;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface as BasePaymentMethodRepositoryInterface;

/**
 * Interface for Monetico Retail payment method repository operations.
 * Defines custom query methods for retrieving payment methods by gateway configurations.
 */
interface PaymentMethodRepositoryInterface extends BasePaymentMethodRepositoryInterface
{
    /**
     * Finds a payment method by gateway factory name and payment method code.
     *
     * @param string $gatewayFactoryName The gateway factory name (e.g., 'monetico_sylius_payment')
     * @param string $code The payment method code
     *
     * @return PaymentMethodInterface|null The payment method if found, null otherwise
     */
    public function findByGatewayNameAndCode(string $gatewayFactoryName, string $code);

    /**
     * Finds all payment methods for a specific gateway factory.
     *
     * @param mixed $gatewayName The gateway factory name to search for
     *
     * @return array<int, PaymentMethodInterface> Array of payment methods matching the gateway name
     */
    public function findAllByGatewayName(mixed $gatewayName);
}