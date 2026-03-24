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

namespace Lyranetwork\Monetico\CommandHandler;

use Lyranetwork\Monetico\Command\NotifyPaymentRequest;
use Lyranetwork\Monetico\Processor\PaymentResultProcessor;

use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Bundle\PaymentBundle\Provider\PaymentRequestProviderInterface;
use Sylius\Component\Payment\PaymentRequestTransitions;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

use Psr\Log\LoggerInterface;

/**
 * Handler for processing payment notification commands from the gateway.
 * Handles IPN callbacks and updates payment status accordingly.
 */
#[AsMessageHandler]
final class NotifyPaymentRequestHandler
{
    /**
     * @param PaymentRequestProviderInterface $paymentRequestProvider Provider for retrieving payment requests
     * @param StateMachineInterface $stateMachine State machine for managing payment request transitions
     * @param PaymentResultProcessor $paymentResultProcessor Processor for handling payment results
     * @param LoggerInterface $logger Logger for tracking notification processing
     */
    public function __construct(
        private PaymentRequestProviderInterface $paymentRequestProvider,
        private StateMachineInterface $stateMachine,
        private PaymentResultProcessor $paymentResultProcessor,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Handles the notify payment request command.
     * Processes IPN notifications from the gateway and updates payment status.
     *
     * @param NotifyPaymentRequest $notifyPaymentRequest The notify payment request command
     *
     * @return void
     */
    public function __invoke(NotifyPaymentRequest $notifyPaymentRequest): void
    {
        $paymentRequest = $this->paymentRequestProvider->provide($notifyPaymentRequest);
        $payment = $paymentRequest->getPayment();

        if ($payment === null) {
            $this->logger->error('Cannot process IPN notification: payment is null.');

            return;
        }

        $order = $payment->getOrder();
        $orderId = $order ? $order->getNumber() : 'unknown';

        $this->logger->info("Server call process starts for order #$orderId.");

        $this->stateMachine->apply(
            $paymentRequest,
            PaymentRequestTransitions::GRAPH,
            PaymentRequestTransitions::TRANSITION_PROCESS,
        );

        $this->paymentResultProcessor->process($payment);

        $this->logger->info("IPN URL process end for order #$orderId.");

        $this->stateMachine->apply(
            $paymentRequest,
            PaymentRequestTransitions::GRAPH,
            PaymentRequestTransitions::TRANSITION_COMPLETE,
        );
    }
}