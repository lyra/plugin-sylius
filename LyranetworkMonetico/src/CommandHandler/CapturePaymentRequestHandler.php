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

use Lyranetwork\Monetico\Command\CapturePaymentRequest;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Bundle\PaymentBundle\Provider\PaymentRequestProviderInterface;
use Sylius\Component\Payment\PaymentRequestTransitions;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Sylius\Component\Payment\Model\PaymentRequestInterface;

/**
 * Handler for processing payment capture commands.
 * Completes the payment request state transition for capture operations.
 */
#[AsMessageHandler]
final class CapturePaymentRequestHandler
{
    /**
     * @param PaymentRequestProviderInterface $paymentRequestProvider Provider for retrieving payment requests
     * @param StateMachineInterface $stateMachine State machine for managing payment request transitions
     */
    public function __construct(
        private PaymentRequestProviderInterface $paymentRequestProvider,
        private StateMachineInterface $stateMachine
    ) {
    }

    /**
     * Handles the capture payment request command.
     * Retrieves the payment request and completes the state transition if not already processing.
     *
     * @param CapturePaymentRequest $capturePaymentRequest The capture payment request command
     *
     * @return void
     */
    public function __invoke(CapturePaymentRequest $capturePaymentRequest): void
    {
        $paymentRequest = $this->paymentRequestProvider->provide($capturePaymentRequest);

        if (PaymentRequestInterface::STATE_PROCESSING === $paymentRequest->getState()) {
            return;
        }

        $this->stateMachine->apply(
            $paymentRequest,
            PaymentRequestTransitions::GRAPH,
            PaymentRequestTransitions::TRANSITION_PROCESS
        );
    }
}