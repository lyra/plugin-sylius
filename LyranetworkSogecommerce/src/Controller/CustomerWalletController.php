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

namespace Lyranetwork\Sogecommerce\Controller;

use Lyranetwork\Sogecommerce\Repository\PaymentMethodRepositoryInterface;
use Lyranetwork\Sogecommerce\Sdk\RestHelper;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\RouterInterface;

use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Customer\Context\CustomerContextInterface;
use Sylius\Component\Currency\Context\CurrencyContextInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;

use Twig\Environment;

/**
 * Controller for managing saved payment cards in customer account.
 * Handles the display and management of stored cards when one-click payment is enabled.
 */
final class CustomerWalletController
{
    /**
     * @param PaymentMethodRepositoryInterface $paymentMethodRepository Repository for retrieving Sogecommerce payment methods
     * @param CustomerRepositoryInterface $customerRepository Repository for accessing customer data
     * @param CustomerContextInterface $customerContext Context for accessing the current customer
     * @param CurrencyContextInterface $currencyContext Context for accessing the current currency
     * @param RestHelper $restHelper Helper service for managing REST API operations and account tokens
     * @param RouterInterface $router Router for generating URLs
     * @param Environment $twig Twig template engine for rendering views
     * @param ChannelContextInterface $channelContext Context for accessing the current channel
     */
    public function __construct(
        private PaymentMethodRepositoryInterface $paymentMethodRepository,
        private CustomerRepositoryInterface $customerRepository,
        private CustomerContextInterface $customerContext,
        private CurrencyContextInterface $currencyContext,
        private RestHelper $restHelper,
        private RouterInterface $router,
        private Environment $twig,
        private ChannelContextInterface $channelContext
    ) {
    }

    /**
     * Displays the saved cards page for the authenticated customer.
     * Checks if one-click payment is enabled and generates an account token for card management.
     * Redirects to dashboard if one-click payment is not enabled or customer is not authenticated.
     *
     * @param Request $request The HTTP request (not used in current implementation)
     *
     * @return Response The rendered saved cards page or redirect response
     */
    public function savedCardsAction(Request $request): Response
    {
        // Verify customer authentication.
        $customer = $this->customerContext->getCustomer();
        if (! $customer) {
            return $this->redirectToDashboard();
        }

        // Find payment method with one-click enabled.
        $paymentMethodCode = $this->findEnabledOneClickPaymentMethod();
        if ($paymentMethodCode === null) {
            return $this->redirectToDashboard();
        }

        // Retrieve customer entity and generate account token.
        $customerEntity = $this->customerRepository->find($customer->getId());
        if (! $customerEntity) {
            return $this->redirectToDashboard();
        }

        $accountToken = $this->restHelper->getAccountToken(
            $customerEntity,
            $this->currencyContext->getCurrencyCode(),
            $paymentMethodCode
        );

        // Render customer wallet page.
        return new Response(
            $this->twig->render(
                '@LyranetworkSogecommercePlugin/shop/account/customer_wallet.html.twig',
                [
                    'accountToken' => $accountToken,
                    'instanceCode' => $paymentMethodCode
                ]
            )
        );
    }

    /**
     * Finds the first enabled payment method with one-click payment configured.
     *
     * @return string|null The payment method code if found, null otherwise
     */
    private function findEnabledOneClickPaymentMethod(): ?string
    {
        $gatewayName = constant('Lyranetwork\Sogecommerce\Sdk\Tools::FACTORY_NAME');
        $paymentMethods = $this->paymentMethodRepository->findAllByGatewayName($gatewayName);
        $currentChannel = $this->channelContext->getChannel();

        foreach ($paymentMethods as $paymentMethod) {
            if (! $paymentMethod->isEnabled()) {
                continue;
            }

            if (! $paymentMethod->hasChannel($currentChannel)) {
                continue;
            }

            $oneClickEnabled = $this->restHelper->isOneClickEnabled($paymentMethod->getCode());

            if ($oneClickEnabled) {
                return $paymentMethod->getCode();
            }
        }

        return null;
    }

    /**
     * Creates a redirect response to the account dashboard.
     *
     * @return RedirectResponse The redirect response
     */
    private function redirectToDashboard(): RedirectResponse
    {
        return new RedirectResponse($this->router->generate('sylius_shop_account_dashboard'));
    }
}