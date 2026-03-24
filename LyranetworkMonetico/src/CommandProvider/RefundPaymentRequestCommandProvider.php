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

namespace Lyranetwork\Monetico\CommandProvider;

use Lyranetwork\Monetico\Command\RefundPaymentRequest;
use Sylius\Bundle\PaymentBundle\CommandProvider\PaymentRequestCommandProviderInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;

/**
 * Command provider for payment refund requests.
 * Creates RefundPaymentRequest commands for processing payment refunds through the gateway.
 */
final class RefundPaymentRequestCommandProvider implements PaymentRequestCommandProviderInterface
{
    /**
     * Determines if this provider supports the given payment request.
     * Only supports payment requests with REFUND action.
     *
     * @param PaymentRequestInterface $paymentRequest The payment request to check
     *
     * @return bool True if the payment request action is REFUND, false otherwise
     */
    public function supports(PaymentRequestInterface $paymentRequest): bool
    {
        return $paymentRequest->getAction() === PaymentRequestInterface::ACTION_REFUND;
    }

    /**
     * Provides a RefundPaymentRequest command for the given payment request.
     *
     * @param PaymentRequestInterface $paymentRequest The payment request to create a command for
     *
     * @return RefundPaymentRequest The refund payment request command
     */
    public function provide(PaymentRequestInterface $paymentRequest): object
    {
        return new RefundPaymentRequest($paymentRequest->getId());
    }
}
