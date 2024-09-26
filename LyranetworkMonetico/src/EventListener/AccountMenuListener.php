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
        $gatewayName = constant('Lyranetwork\Monetico\Payum\SyliusPaymentGatewayFactory::FACTORY_NAME');
        $moneticoPaymentMethods = $this->paymentMethodRepository->findAllByGatewayName($gatewayName);

        if (is_array($moneticoPaymentMethods) && ! empty($moneticoPaymentMethods)) {
            foreach ($moneticoPaymentMethods as $moneticoPaymentMethod) {
                if ($moneticoPaymentMethod->isEnabled()
                    && $this->configService->get(GatewayConfiguration::$ADVANCED_FIELDS . 'oneclick_payment', $moneticoPaymentMethod->getCode())
                    && $moneticoPaymentMethod->hasChannel($this->channelContext->getChannel())
                ) {
                    $menu = $event->getMenu();

                    $menu->addChild('card', ['route' => 'monetico_sylius_account_saved_cards'])
                        ->setAttribute('type', 'link')
                        ->setLabel('sylius_monetico_plugin.ui.account.saved_cards.title')
                        ->setLabelAttribute('icon', 'credit card');

                    break;
                }
            }
        }
    }
}