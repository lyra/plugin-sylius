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

namespace Lyranetwork\Sogecommerce\EventListener;

use Lyranetwork\Sogecommerce\Repository\PaymentMethodRepositoryInterface;
use Lyranetwork\Sogecommerce\Sdk\RestHelper;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Sylius\Component\Channel\Context\ChannelContextInterface;

/**
 * Event listener for adding Sogecommerce customer wallet to the account menu.
 * Adds a "Saved Cards" menu item when one-click payment is enabled.
 */
final class AccountMenuListener
{
    /**
     * @param PaymentMethodRepositoryInterface $paymentMethodRepository Repository for retrieving Sogecommerce payment methods
     * @param ChannelContextInterface $channelContext Context for accessing the current channel
     * @param RestHelper $restHelper Helper service for REST API data handling
     */
    public function __construct(
        private PaymentMethodRepositoryInterface $paymentMethodRepository,
        private ChannelContextInterface $channelContext,
        private RestHelper $restHelper
    ) {
    }

    /**
     * Adds the "Saved Cards" menu item to the account menu if one-click payment is enabled.
     * Checks all Sogecommerce payment methods and adds the menu item if at least one method
     * has one-click payment enabled and is available in the current channel.
     *
     * @param MenuBuilderEvent $event The menu builder event containing the menu to modify
     *
     * @return void
     */
    public function addAccountMenuItems(MenuBuilderEvent $event): void
    {
        $gatewayName = constant('Lyranetwork\\Sogecommerce\\Sdk\\Tools::FACTORY_NAME');
        $paymentMethods = $this->paymentMethodRepository->findAllByGatewayName($gatewayName);

        if (is_array($paymentMethods) && ! empty($paymentMethods)) {
            foreach ($paymentMethods as $paymentMethod) {
                if ($paymentMethod->isEnabled()
                    && $this->restHelper->isOneclickEnabled($paymentMethod->getCode())
                    && $paymentMethod->hasChannel($this->channelContext->getChannel())
                ) {
                    $menu = $event->getMenu();

                    $menu->addChild('customer_wallet', ['route' => 'sogecommerce_sylius_account_customer_wallet'])
                        ->setAttribute('type', 'link')
                        ->setLabel('sylius_sogecommerce_plugin.ui.account.customer_wallet.title')
                        ->setLabelAttribute('icon', 'tabler:credit-card');

                    break;
                }
            }
        }
    }
}