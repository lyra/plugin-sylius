<?php
/**
 * Copyright © Lyra Network.
 * This file is part of Systempay plugin for Sylius. See COPYING.md for license details.
 *
 * @author    Lyra Network <https://www.lyra.com>
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/mit-license.html The MIT License (MIT)
 */

declare(strict_types=1);

namespace Lyranetwork\Systempay\EventListener;

use Lyranetwork\Systempay\Repository\PaymentMethodRepositoryInterface;
use Lyranetwork\Systempay\Service\ConfigService;
use Lyranetwork\Systempay\Form\Type\SyliusGatewayConfigurationType as GatewayConfiguration;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Sylius\Component\Channel\Context\ChannelContextInterface;

final class AccountMenuListener
{
    /**
     * @var PaymentMethodRepositoryInterface
     */
    private $paymentMethodRepository;

    /**
     * @var ConfigService
     */
    private $configService;

    /**
     * @var ChannelContextInterface
     */
    private $channelContext;

    public function __construct(
        PaymentMethodRepositoryInterface $paymentMethodRepository,
        ConfigService $configService,
        ChannelContextInterface $channelContext
    ) {
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->configService = $configService;
        $this->channelContext = $channelContext;
    }

    public function addAccountMenuItems(MenuBuilderEvent $event): void
    {
        $gatewayName = constant('Lyranetwork\\Systempay\\Sdk\\Tools::FACTORY_NAME');
        $systempayPaymentMethods = $this->paymentMethodRepository->findAllByGatewayName($gatewayName);

        if (is_array($systempayPaymentMethods) && ! empty($systempayPaymentMethods)) {
            foreach ($systempayPaymentMethods as $systempayPaymentMethod) {
                if ($systempayPaymentMethod->isEnabled()
                    && $this->configService->get(GatewayConfiguration::$ADVANCED_FIELDS . 'oneclick_payment', $systempayPaymentMethod->getCode())
                    && $systempayPaymentMethod->hasChannel($this->channelContext->getChannel())
                ) {
                    $menu = $event->getMenu();

                    $menu->addChild('card', ['route' => 'systempay_sylius_account_saved_cards'])
                        ->setAttribute('type', 'link')
                        ->setLabel('sylius_systempay_plugin.ui.account.saved_cards.title')
                        ->setLabelAttribute('icon', 'tabler:credit-card');

                    break;
                }
            }
        }
    }
}