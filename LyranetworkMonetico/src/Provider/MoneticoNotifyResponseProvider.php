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

namespace Lyranetwork\Monetico\Provider;

use Sylius\Bundle\PaymentBundle\Provider\NotifyResponseProviderInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provider for generating HTTP responses to payment notification requests.
 * Decorates the default response provider to return custom responses based on Monetico Retail payment details.
 */
final class MoneticoNotifyResponseProvider implements NotifyResponseProviderInterface
{
    /**
     * @param NotifyResponseProviderInterface $inner The decorated response provider to fall back to
     */
    public function __construct(private NotifyResponseProviderInterface $inner)
    {
    }

    /**
     * Provides an HTTP response for a payment notification request.
     * Returns a custom response with Monetico Retail response code and message if available,
     * otherwise delegates to the decorated provider.
     *
     * @param PaymentRequestInterface $paymentRequest The payment request containing payment details
     *
     * @return Response The HTTP response to return to the payment gateway
     */
    public function provide(PaymentRequestInterface $paymentRequest): Response
    {
        $payment = $paymentRequest->getPayment();
        if (! $payment) {
            return $this->inner->provide($paymentRequest);
        }

        $details = $payment->getDetails();
        if (! is_array($details) || ! isset($details['responseCode'], $details['responseMessage'])) {
            return $this->inner->provide($paymentRequest);
        }

        return new Response($details['responseMessage'], $details['responseCode']);
    }
}