<?php
/**
 * Copyright © Lyra Network.
 * This file is part of Sogecommerce plugin for Sylius. See COPYING.md for license details.
 *
 * @author    Lyra Network <https://www.lyra.com>
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/mit-license.html The MIT License (MIT)
 */

declare(strict_types=1);

namespace Lyranetwork\Sogecommerce\CommandProvider;

use Lyranetwork\Sogecommerce\Command\StatusPaymentRequest;
use Sylius\Bundle\PaymentBundle\CommandProvider\PaymentRequestCommandProviderInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;

/**
 * Command provider for payment status requests.
 * Creates StatusPaymentRequest commands for checking payment status with the gateway.
 */
final class StatusPaymentRequestCommandProvider implements PaymentRequestCommandProviderInterface
{
    /**
     * Determines if this provider supports the given payment request.
     * This provider supports all payment requests.
     *
     * @param PaymentRequestInterface $paymentRequest The payment request to check
     *
     * @return bool Always returns true
     */
    public function supports(PaymentRequestInterface $paymentRequest): bool
    {
        return true;
    }

    /**
     * Provides a StatusPaymentRequest command for the given payment request.
     *
     * @param PaymentRequestInterface $paymentRequest The payment request to create a command for
     *
     * @return StatusPaymentRequest The status payment request command
     */
    public function provide(PaymentRequestInterface $paymentRequest): object
    {
        return new StatusPaymentRequest($paymentRequest->getId());
    }
}