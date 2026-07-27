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

use Lyranetwork\Sogecommerce\Command\NotifyPaymentRequest;
use Sylius\Bundle\PaymentBundle\CommandProvider\PaymentRequestCommandProviderInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;

/**
 * Command provider for payment notification requests.
 * Creates NotifyPaymentRequest commands for handling IPN (Instant Payment Notification) callbacks from the gateway.
 */
final class NotifyPaymentRequestCommandProvider implements PaymentRequestCommandProviderInterface
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
     * Provides a NotifyPaymentRequest command for the given payment request.
     *
     * @param PaymentRequestInterface $paymentRequest The payment request to create a command for
     *
     * @return NotifyPaymentRequest The notify payment request command
     */
    public function provide(PaymentRequestInterface $paymentRequest): object
    {
        return new NotifyPaymentRequest($paymentRequest->getId());
    }
}