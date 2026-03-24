<?php
/**
 * Copyright © Lyra Network.
 * This file is part of PayZen plugin for Sylius. See COPYING.md for license details.
 *
 * @author    Lyra Network <https://www.lyra.com>
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/mit-license.html The MIT License (MIT)
 */

declare(strict_types=1);

namespace Lyranetwork\Payzen\CommandHandler;

use Lyranetwork\Payzen\Sdk\Tools;
use Lyranetwork\Payzen\Sdk\Form\Api as PayzenApi;
use Lyranetwork\Payzen\Command\RefundPaymentRequest;
use Lyranetwork\Payzen\Service\RefundService;

use Psr\Log\LoggerInterface;

use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Bundle\PaymentBundle\Provider\PaymentRequestProviderInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface as SyliusPaymentRequestInterface;
use Sylius\Component\Payment\PaymentRequestTransitions;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Handler for processing payment refund commands.
 * Executes refund operations through the PayZen gateway and manages refund state transitions.
 */
#[AsMessageHandler]
final class RefundPaymentRequestHandler
{
    /**
     * @param PaymentRequestProviderInterface $paymentRequestProvider Provider for retrieving payment requests
     * @param StateMachineInterface $stateMachine State machine for managing payment request transitions
     * @param RefundService $refundService Service for executing refund operations with the gateway
     * @param RequestStack $requestStack Request stack for accessing session and flash messages
     * @param LoggerInterface $logger Logger for tracking refund operations
     */
    public function __construct(
        private PaymentRequestProviderInterface $paymentRequestProvider,
        private StateMachineInterface $stateMachine,
        private RefundService $refundService,
        private RequestStack $requestStack,
        private LoggerInterface $logger
    ) {}

    /**
     * Handles the refund payment request command.
     * Processes the refund through the gateway and updates payment request state.
     *
     * @param RefundPaymentRequest $refundPaymentRequest The refund payment request command
     *
     * @return void
     */
    public function __invoke(RefundPaymentRequest $refundPaymentRequest): void
    {
        $paymentRequest = $this->paymentRequestProvider->provide($refundPaymentRequest);

        if ($paymentRequest->getState() === SyliusPaymentRequestInterface::STATE_PROCESSING) {
            return;
        }

        if (! $this->isValidRefundRequest($paymentRequest)) {
            return;
        }

        $payment = $paymentRequest->getPayment();
        $order = $payment->getOrder();
        $orderId = $order->getNumber();

        $this->logger->info("Start process of refund for payment request with ID #{$payment->getId()} for order #$orderId");

        $this->stateMachine->apply(
            $paymentRequest,
            PaymentRequestTransitions::GRAPH,
            PaymentRequestTransitions::TRANSITION_PROCESS
        );

        $currency = PayzenApi::findCurrencyByAlphaCode($payment->getCurrencyCode());
        $amount = $currency->convertAmountToFloat($payment->getAmount());

        try {
            $paymentMethodCode = $paymentRequest->getMethod()->getCode();
            $this->refundService->refund($paymentMethodCode, $order, $this->getUserInfo(), $amount);

            $this->logger->info("Refund processed successfully for order #$orderId.");

            $this->stateMachine->apply(
                $paymentRequest,
                PaymentRequestTransitions::GRAPH,
                PaymentRequestTransitions::TRANSITION_COMPLETE
            );
        } catch (\Throwable $e) {
            $this->logger->error("An error occurred while processing refund request for order #$orderId: {$e->getMessage()}.");

            $this->stateMachine->apply(
                $paymentRequest,
                PaymentRequestTransitions::GRAPH,
                PaymentRequestTransitions::TRANSITION_FAIL
            );
        }
    }

    /**
     * Validates that the payment request has all necessary components for refund.
     *
     * @param SyliusPaymentRequestInterface $paymentRequest The payment request to validate
     * @return bool True if the request is valid for refund, false otherwise
     */
    private function isValidRefundRequest(SyliusPaymentRequestInterface $paymentRequest): bool
    {
        $payment = $paymentRequest->getPayment();
        if ($payment === null) {
            return false;
        }

        $order = $payment->getOrder();
        if ($order === null) {
            return false;
        }

        $paymentMethod = $payment->getMethod();
        if ($paymentMethod === null) {
            return false;
        }

        $gatewayConfig = $paymentMethod->getGatewayConfig();
        if ($gatewayConfig === null) {
            return false;
        }

        $factoryName = $gatewayConfig->getFactoryName();
        if ($factoryName !== Tools::FACTORY_NAME) {
            return false;
        }

        return true;
    }

    /**
     * Retrieves user information for refund tracking.
     *
     * @return string Formatted user information including username and IP address
     */
    private function getUserInfo(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        $user = $request->server->get('USERNAME') ?? '';
        $remoteAddr = $request->server->get('REMOTE_ADDR') ?? '';

        return "Sylius user: $user ; IP address: $remoteAddr";
    }
}