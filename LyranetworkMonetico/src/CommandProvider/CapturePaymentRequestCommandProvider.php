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

use Lyranetwork\Monetico\Command\CapturePaymentRequest;
use Sylius\Bundle\PaymentBundle\CommandProvider\PaymentRequestCommandProviderInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;

/**
 * Command provider for payment capture requests.
 * Creates CapturePaymentRequest commands for processing payment captures through the gateway.
 */
final class CapturePaymentRequestCommandProvider implements PaymentRequestCommandProviderInterface
{
    /**
     * Determines if this provider supports the given payment request.
     * Only supports payment requests with CAPTURE action.
     *
     * @param PaymentRequestInterface $paymentRequest The payment request to check
     *
     * @return bool True if the payment request action is CAPTURE, false otherwise
     */
    public function supports(PaymentRequestInterface $paymentRequest): bool
    {
        return $paymentRequest->getAction() === PaymentRequestInterface::ACTION_CAPTURE;
    }

    /**
     * Provides a CapturePaymentRequest command for the given payment request.
     *
     * @param PaymentRequestInterface $paymentRequest The payment request to create a command for
     *
     * @return CapturePaymentRequest The capture payment request command
     */
    public function provide(PaymentRequestInterface $paymentRequest): object
    {
        return new CapturePaymentRequest($paymentRequest->getId());
    }
}