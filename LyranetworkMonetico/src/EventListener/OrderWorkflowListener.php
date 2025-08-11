<?php
/**
 * Copyright © Lyra Network.
 * This file is part of Monetico Retail plugin for Sylius. See COPYING.md for license details.
 *
 * @author    Lyra Network <https://www.lyra.com>
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/mit-license.html The MIT License (MIT)
 */

namespace Lyranetwork\Monetico\EventListener;

use Lyranetwork\Monetico\Service\OrderService;

use Symfony\Component\Workflow\Event\CompletedEvent;

use Sylius\Component\Core\Model\PaymentInterface;

use Webmozart\Assert\Assert;

final class OrderWorkflowListener
{
    /**
     * @var OrderService
     */
    private $orderService;

    public function __construct(
        OrderService $orderService
    ){
        $this->orderService = $orderService;
    }

    public function __invoke(CompletedEvent $event): void
    {
        $payment = $event->getSubject();
        Assert::isInstanceOf($payment, PaymentInterface::class);

        $this->orderService->sendConfirmationEmail($payment);
    }
}