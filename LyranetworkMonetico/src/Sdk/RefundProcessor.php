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

namespace Lyranetwork\Monetico\Sdk;

use Doctrine\ORM\EntityManagerInterface;

use Lyranetwork\Monetico\Sdk\Refund\Processor;

use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\RequestStack;

use Sylius\Component\Core\OrderPaymentTransitions;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Repository\PaymentRequestRepositoryInterface;
use Sylius\Abstraction\StateMachine\StateMachineInterface;

use Psr\Log\LoggerInterface;

/**
 * Processor for handling refund operations through the Monetico Retail gateway.
 *
 * Implements the Processor interface to handle the complete refund workflow including
 * error handling, success processing, failure scenarios, and state transitions.
 * Manages both full and partial refunds with appropriate order state updates.
 */
final class RefundProcessor implements Processor
{
    /**
     * @param LoggerInterface $logger PSR logger for refund operation logging
     * @param TranslatorInterface $translator Symfony translator for error messages
     * @param RequestStack $requestStack Symfony request stack for session access
     * @param PaymentRequestRepositoryInterface $paymentRequestRepository Repository for payment request entities
     * @param RestHelper $restHelper Helper service for REST API data handling
     * @param StateMachineInterface $stateMachine Sylius state machine for payment transitions
     */
    public function __construct(
        private LoggerInterface $logger,
        private TranslatorInterface $translator,
        private RequestStack $requestStack,
        private PaymentRequestRepositoryInterface $paymentRequestRepository,
        private RestHelper $restHelper,
        private StateMachineInterface $stateMachine,
    ) {
    }

    /**
     * Handles error scenarios during the refund process.
     *
     * Processes refund errors by translating error messages, adding them to flash messages,
     * and throwing an exception to halt the refund workflow.
     *
     * @param mixed $errorCode The error code identifying the type of error
     * @param string $message The error message to display
     * @throws \Exception Always throws exception with the error message
     */
    public function doOnError($errorCode, $message): void
    {
        $errorMessage = $this->formatErrorMessage($errorCode, $message);
        $this->addFlashMessage('error', $errorMessage);

        throw new \Exception($errorMessage);
    }

    /**
     * Processes successful refund operations.
     *
     * Updates payment details with refund transaction information, manages state transitions
     * for both full and partial refunds, and logs the successful refund operation.
     *
     * @param array $operationResponse The REST API response from the refund operation
     * @param string $operationType The type of operation performed (CREDIT, CAPTURE, etc.)
     */
    public function doOnSuccess($operationResponse, $operationType): void
    {
        $metadata = $this->restHelper->getProperty($operationResponse, 'metadata');
        $hash = $this->restHelper->getProperty($metadata, 'paymentRequestHash') ?? '';

        $paymentRequest = $this->paymentRequestRepository->findOneBy(['hash' => $hash]);
        if ($paymentRequest === null) {
            $this->doOnFailure("payment_not_found", "No Sylius payment request has been found for hash {$hash}.");
        }

        $payment = $paymentRequest->getPayment();
        if ($payment === null) {
            $this->doOnFailure("payment_not_found", "No Sylius payment has been found for this payment request hash {$hash}.");
        }

        $details = $payment->getDetails();

        $this->updateTransactionDetails($details, $operationResponse);
        $this->handleRefundStateTransition($payment, $operationResponse);

        $payment->setDetails($details);

        $orderId = $operationResponse['orderDetails']['orderId'] ?? 'unknown';
        $this->logger->info("Refunded order #{$orderId} has been saved.");
    }

    /**
     * Handles failed refund operations.
     *
     * Delegates to the error handler to process the failure and display appropriate messages.
     *
     * @param string $errorCode The error code identifying the type of failure
     * @param string $message The failure message to display
     * @throws \Exception Via doOnError method
     */
    public function doOnFailure($errorCode, $message): void
    {
        $this->doOnError($errorCode, $message);
    }

    /**
     * Logs a message at the specified log level.
     *
     * @param string $message The message to log
     * @param string $level The PSR-3 log level (debug, info, warning, error, etc.)
     */
    public function log($message, $level): void
    {
        $this->logger->log($level, $message);
    }

    /**
     * Translates a message using the admin locale.
     *
     * @param string $message The translation key
     * @return string The translated message in the admin locale
     */
    public function translate($message): string
    {
        return $this->translator->trans($message, locale: $this->requestStack->getCurrentRequest()->get('admin_locale'));
    }

    /**
     * Returns this processor instance (self-reference for interface compliance).
     *
     * @return RefundProcessor This processor instance
     */
    public function getProcessor(): RefundProcessor
    {
        return $this;
    }

    /**
     * Formats error message based on error code.
     *
     * @param mixed $errorCode The error code
     * @param string $message The base error message
     * @return string The formatted error message
     */
    private function formatErrorMessage(mixed $errorCode, string $message): string
    {
        if ($errorCode === 'privateKey') {
            return $this->translate("sylius_monetico_plugin.refund.error.private_key");
        }

        $refundError = sprintf(
            $this->translate("sylius_monetico_plugin.refund.error.backoffice_action"),
            Tools::getDefault('BACKOFFICE_NAME')
        );

        return "$message $refundError";
    }

    /**
     * Adds a flash message to the session.
     *
     * @param string $type The message type (error, success, warning, etc.)
     * @param string $message The message to display
     */
    private function addFlashMessage(string $type, string $message): void
    {
        $this->requestStack->getSession()->getFlashBag()->add($type, $message);
    }

    /**
     * Updates transaction details in payment details array.
     *
     * @param array &$details Payment details to update (passed by reference)
     * @param array $operationResponse The REST API response
     */
    private function updateTransactionDetails(array &$details, array $operationResponse): void
    {
        $transUuid = $this->restHelper->getProperty($operationResponse, 'uuid');
        $operationType = $this->restHelper->getProperty($operationResponse, 'operationType');

        if ($operationType === 'CREDIT') {
            $details['transactions'][$transUuid] = $this->restHelper->addTransactionDetails($operationResponse);
        } else {
            $detailedStatus = $this->restHelper->getProperty($operationResponse, 'detailedStatus') ?? '';
            $details['transactions'][$transUuid]['monetico_trans_status'] = $detailedStatus;
        }
    }

    /**
     * Handles refund state transitions for full refunds.
     *
     * @param PaymentInterface $payment The payment to process
     * @param array $operationResponse The REST API response
     */
    private function handleRefundStateTransition(PaymentInterface $payment, array $operationResponse): void
    {
        $operationAmount = $this->restHelper->getProperty($operationResponse, 'amount');

        // Only process full refund scenario.
        if ($payment->getAmount() !== $operationAmount) {
            return;
        }

        $payment->setAmount($payment->getOrder()->getTotal());
        $order = $payment->getOrder();

        // Try cancel transition first, then refund.
        if ($this->stateMachine->can($order, OrderPaymentTransitions::GRAPH, OrderPaymentTransitions::TRANSITION_CANCEL)) {
            $this->stateMachine->apply($order, OrderPaymentTransitions::GRAPH, OrderPaymentTransitions::TRANSITION_CANCEL);
        } elseif ($this->stateMachine->can($order, OrderPaymentTransitions::GRAPH, OrderPaymentTransitions::TRANSITION_REFUND)) {
            $this->stateMachine->apply($order, OrderPaymentTransitions::GRAPH, OrderPaymentTransitions::TRANSITION_REFUND);
        }
    }
}