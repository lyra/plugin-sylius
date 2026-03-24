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

namespace Lyranetwork\Monetico\EventListener;

use Lyranetwork\Monetico\Repository\PaymentMethodRepositoryInterface;
use Lyranetwork\Monetico\Service\ConfigService;
use Lyranetwork\Monetico\Form\Type\SyliusGatewayConfigurationType as GatewayConfiguration;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Sylius\Component\Channel\Context\ChannelContextInterface;

/**
 * Event listener for adding Monetico Retail customer wallet to the account menu.
 * Adds a "Saved Cards" menu item when one-click payment is enabled.
 */
final class AccountMenuListener
{
    /**
     * @param PaymentMethodRepositoryInterface $paymentMethodRepository Repository for retrieving Monetico Retail payment methods
     * @param ConfigService $configService Service for accessing gateway configuration
     * @param ChannelContextInterface $channelContext Context for accessing the current channel
     */
    public function __construct(
        private PaymentMethodRepositoryInterface $paymentMethodRepository,
        private ConfigService $configService,
        private ChannelContextInterface $channelContext
    ) {
    }

    /**
     * Adds the "Saved Cards" menu item to the account menu if one-click payment is enabled.
     * Checks all Monetico Retail payment methods and adds the menu item if at least one method
     * has one-click payment enabled and is available in the current channel.
     *
     * @param MenuBuilderEvent $event The menu builder event containing the menu to modify
     *
     * @return void
     */
    public function addAccountMenuItems(MenuBuilderEvent $event): void
    {
        $gatewayName = constant('Lyranetwork\\Monetico\\Sdk\\Tools::FACTORY_NAME');
        $paymentMethods = $this->paymentMethodRepository->findAllByGatewayName($gatewayName);

        if (is_array($paymentMethods) && ! empty($paymentMethods)) {
            foreach ($paymentMethods as $paymentMethod) {
                if ($paymentMethod->isEnabled()
                    && $this->configService->get(GatewayConfiguration::$ADVANCED_FIELDS . 'oneclick_payment', $paymentMethod->getCode())
                    && $paymentMethod->hasChannel($this->channelContext->getChannel())
                ) {
                    $menu = $event->getMenu();

                    $menu->addChild('customer_wallet', ['route' => 'monetico_sylius_account_customer_wallet'])
                        ->setAttribute('type', 'link')
                        ->setLabel('sylius_monetico_plugin.ui.account.customer_wallet.title')
                        ->setLabelAttribute('icon', 'tabler:credit-card');

                    break;
                }
            }
        }
    }
}