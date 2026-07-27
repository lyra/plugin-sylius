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

namespace Lyranetwork\Sogecommerce\OrderPay\Provider;

use Sylius\Bundle\PaymentBundle\Provider\HttpResponseProviderInterface;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfiguration;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Bundle\CoreBundle\OrderPay\Provider\UrlProviderInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * HTTP response provider for payment capture requests.
 * Renders the Sogecommerce checkout page with embedded payment form.
 */
final class CaptureHttpResponseProvider implements HttpResponseProviderInterface
{
    /**
     * @param Environment $twig Twig template engine for rendering the checkout page
     * @param UrlProviderInterface $afterPayUrlProvider Provider for generating the post-payment return URL
     */
    public function __construct(
        private Environment $twig,
        private UrlProviderInterface $afterPayUrlProvider
    ) {
    }

    /**
     * Checks if this provider supports the given payment request.
     *
     * @param RequestConfiguration $requestConfiguration The request configuration (not used)
     * @param PaymentRequestInterface $paymentRequest The payment request to check
     *
     * @return bool True if the payment request action is CAPTURE, false otherwise
     */
    public function supports(RequestConfiguration $requestConfiguration, PaymentRequestInterface $paymentRequest): bool
    {
        return $paymentRequest->getAction() === PaymentRequestInterface::ACTION_CAPTURE;
    }

    /**
     * Generates the HTTP response for the payment capture request.
     * Renders the Sogecommerce checkout page with payment form.
     *
     * @param RequestConfiguration $requestConfiguration The request configuration (not used)
     * @param PaymentRequestInterface $paymentRequest The payment request containing payment and order data
     *
     * @return Response The rendered checkout page response
     */
    public function getResponse(RequestConfiguration $requestConfiguration, PaymentRequestInterface $paymentRequest): Response
    {
        $payment = $paymentRequest->getPayment();
        $order = $payment->getOrder();
        $method = $paymentRequest->getMethod();

        // Display a Twig template.
        return new Response(
            $this->twig->render(
                '@LyranetworkSogecommercePlugin/shop/checkout/checkout.html.twig',
                [
                    'order' => $order,
                    'method' => $method,
                    'returnUrl' => $this->afterPayUrlProvider->getUrl($paymentRequest, UrlGeneratorInterface::ABSOLUTE_URL),
                    'paymentRequestHash' => $paymentRequest->getHash()
                ]
            )
        );
    }
}