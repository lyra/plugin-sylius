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

namespace Lyranetwork\Sogecommerce\Processor;

use Lyranetwork\Sogecommerce\Sdk\RestHelper;
use Lyranetwork\Sogecommerce\Sdk\Tools as SogecommerceTools;
use Lyranetwork\Sogecommerce\Sdk\Form\Api as SogecommerceApi;

use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\OrderPaymentTransitions;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\Component\Core\Model\PaymentInterface;

use Symfony\Component\HttpFoundation\Response;

use Psr\Log\LoggerInterface;

/**
 * Processor for handling payment gateway responses and updating payment states.
 *
 * This processor analyzes transaction responses from the Sogecommerce REST API,
 * determines the appropriate payment state transitions, and applies them using
 * the Sylius state machine. Handles various scenarios including successful payments,
 * refunds, partial payments, cancellations, and failures.
 */
final class PaymentResultProcessor {
    /**
     * @param RestHelper $restHelper Helper service for REST API data handling and extraction
     * @param StateMachineInterface $stateMachine Sylius state machine for payment transitions
     * @param LoggerInterface $logger PSR logger for payment processing operations
     */
    public function __construct(
        private RestHelper $restHelper,
        private StateMachineInterface $stateMachine,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Processes a payment result from the Sogecommerce gateway.
     *
     * Analyzes the payment response, extracts transaction details, determines the
     * appropriate state transition based on payment status and operation type, and
     * applies the transition using the state machine. Handles various scenarios:
     * - Successful payments (authorized, completed)
     * - Refunds (full and partial)
     * - Cancellations
     * - Failures
     * - Pending payments
     *
     * @param PaymentInterface $payment The payment entity to process
     */
    public function process($payment): void
    {
        $details = $payment->getDetails();
        if (! isset($details['answer'])) {
            return;
        }

        $answer = json_decode($details['answer'], true);
        if (! is_array($answer) || empty($answer)) {
            return;
        }

        // Updates transactions information in payment details.
        $this->updateTransactionDetails($details, $answer);

        // Get the main transaction from the answer.
        $transaction = $this->getTransaction($answer);

        $transactionData = $this->getTransactionData($answer, $transaction);

        // Determines the payment state and transition based on transaction data.
        $stateData = $this->nextPaymentState($payment, $transactionData);

        $this->finalizePaymentDetails($details, $stateData);
        $payment->setDetails($details);

        $this->applyPaymentTransition($payment, $stateData['transition'], $payment->getOrder()->getNumber());
    }

    /**
     * Updates transactions information in payment details array.
     *
     * @param array &$details Payment details to update (passed by reference)
     * @param array $answer The payment gateway answer
     * @param array $transaction The main transaction
     */
    private function updateTransactionDetails(array &$details, array $answer): void
    {
        $details['sogecommerce_factory_name'] = SogecommerceTools::FACTORY_NAME;
        $details['transactions'] = isset($details['transactions']) && is_array($details['transactions'])
            ? $details['transactions'] : [];

        $transactions = $this->restHelper->getProperty($answer, 'transactions');

        if (is_array($transactions) && ! empty($transactions)) {
            foreach ($transactions as $trs) {
                $transUuid = $this->restHelper->getProperty($trs, 'uuid');
                $details['transactions'][$transUuid] = $this->restHelper->addTransactionDetails($trs);
            }
        } else {
            $transUuid = $this->restHelper->getProperty($answer, 'uuid');
            $details['transactions'][$transUuid] = $this->restHelper->addTransactionDetails($answer);
        }
    }

    /**
     * Retrieves the main transaction from the answer.
     *
     * @param array $answer The payment gateway answer
     * @return array The main transaction data
     */
    private function getTransaction(array $answer): array
    {
        $transactions = $this->restHelper->getProperty($answer, 'transactions');
        if (is_array($transactions) && ! empty($transactions)) {
            return $transactions[0];
        }

        return $answer;
    }

    /**
     * Extracts relevant transaction data for processing.
     *
     * @param array $answer The payment gateway answer
     * @param array $transaction The main transaction
     * @return array Transaction data with keys: urlCheckSrc, amount, operationType, detailedStatus, orderCycle
     */
    private function getTransactionData(array $answer, array $transaction): array
    {
        return [
            'urlCheckSrc' => $this->restHelper->getProperty($answer, 'kr-src'),
            'amount' => $this->restHelper->getProperty($transaction, 'amount'),
            'operationType' => $this->restHelper->getProperty($transaction, 'operationType'),
            'detailedStatus' => $this->restHelper->getProperty($transaction, 'detailedStatus'),
            'orderCycle' => $this->restHelper->getProperty($answer, 'orderCycle'),
        ];
    }

    /**
     * Determines the payment state and transition based on transaction data.
     *
     * @param PaymentInterface $payment The payment being processed
     * @param array $transactionData Transaction data
     * @return array State data with keys: transition, state, message
     */
    private function nextPaymentState(PaymentInterface $payment, array $transactionData): array
    {
        $detailedStatus = $transactionData['detailedStatus'];

        if (SogecommerceApi::isPendingPayment($detailedStatus)) {
            return $this->handlePendingPayment($payment, $transactionData);
        }

        if (SogecommerceApi::isAcceptedPayment($detailedStatus)) {
            return $this->handleAcceptedPayment($payment, $transactionData);
        }

        $isOrderCycleClosed = $transactionData['orderCycle'] === 'CLOSED';
        $isFromMerchBo = $transactionData['urlCheckSrc'] === 'MERCH_BO';

        if ($isOrderCycleClosed || $isFromMerchBo) {
            return $this->handleCanceledOrFailedPayment($payment, $detailedStatus);
        }

        return [
            'transition' => PaymentTransitions::TRANSITION_PROCESS,
            'state' => PaymentInterface::STATE_NEW,
            'message' => '',
        ];
    }

    /**
     * Handles pending payment scenario.
     *
     * @return array State data for pending payment
     */
    private function handlePendingPayment(PaymentInterface $payment, array $transactionData): array
    {
        $amount = $transactionData['amount'];
        $urlCheckSrc = $transactionData['urlCheckSrc'];
        $paymentAmount = $payment->getAmount();

        // Update transaction amount when operation from merchant back office.
        if ($paymentAmount > $amount && $urlCheckSrc === 'MERCH_BO') {
            $payment->setAmount($amount);
        }

        return [
            'transition' => PaymentTransitions::TRANSITION_AUTHORIZE,
            'state' => PaymentInterface::STATE_AUTHORIZED,
            'message' => 'payment_ok',
        ];
    }

    /**
     * Handles accepted payment scenario including refunds and partial payments.
     *
     * @param PaymentInterface $payment The payment being processed
     * @param array $transactionData Transaction data
     * @return array State data for accepted payment
     */
    private function handleAcceptedPayment(PaymentInterface $payment, array $transactionData): array
    {
        $operationType = $transactionData['operationType'];
        $amount = $transactionData['amount'];
        $urlCheckSrc = $transactionData['urlCheckSrc'];
        $paymentAmount = $payment->getAmount();

        // Total refund.
        if ($operationType === 'CREDIT' && $paymentAmount === $amount) {
            $payment->setAmount($payment->getOrder()->getTotal());

            return [
                'transition' => PaymentTransitions::TRANSITION_REFUND,
                'state' => PaymentInterface::STATE_REFUNDED,
                'message' => 'payment_ok',
            ];
        }

        // Partial refund.
        if ($operationType === 'CREDIT' && $paymentAmount > $amount) {
            $newAmount = $paymentAmount - $amount;
            $payment->setAmount($newAmount);

            $this->applyOrderTransition($payment->getOrder(), OrderPaymentTransitions::TRANSITION_PARTIALLY_REFUND);
        }

        // Update transaction amount from merchant back office.
        if ($paymentAmount > $amount && $urlCheckSrc === 'MERCH_BO') {
            $payment->setAmount($amount);

            $order = $payment->getOrder();
            if ($this->stateMachine->can($order, OrderPaymentTransitions::GRAPH, OrderPaymentTransitions::TRANSITION_PARTIALLY_PAY)) {
                $this->stateMachine->apply($order, OrderPaymentTransitions::GRAPH, OrderPaymentTransitions::TRANSITION_PARTIALLY_PAY);
            } else {
                $this->applyOrderTransition($order, OrderPaymentTransitions::TRANSITION_PARTIALLY_REFUND);
            }
        }

        // Standard accepted payment.
        return [
            'transition' => PaymentTransitions::TRANSITION_COMPLETE,
            'state' => PaymentInterface::STATE_COMPLETED,
            'message' => 'payment_ok',
        ];
    }

    /**
     * Handles cancelled or failed payment scenario (cancellations and failures).
     *
     * @param PaymentInterface $payment The payment being processed
     * @param string $detailedStatus The detailed payment status
     * @return array State data for cancelled/failed payment
     */
    private function handleCanceledOrFailedPayment(PaymentInterface $payment, string $detailedStatus): array
    {
        if (SogecommerceApi::isCancelledPayment($detailedStatus)) {
            $payment->setAmount($payment->getOrder()->getTotal());

            if ($this->stateMachine->can($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_CANCEL)) {
                if ($payment->getOrder()->getPaymentState() === PaymentInterface::STATE_AUTHORIZED) {
                    $this->applyOrderTransition($payment->getOrder(), OrderPaymentTransitions::TRANSITION_CANCEL);
                }

                return [
                    'transition' => PaymentTransitions::TRANSITION_CANCEL,
                    'state' => PaymentInterface::STATE_CANCELLED,
                    'message' => 'payment_ko',
                ];
            }

            return [
                'transition' => PaymentTransitions::TRANSITION_REFUND,
                'state' => PaymentInterface::STATE_REFUNDED,
                'message' => 'payment_ko',
            ];
        }

        return [
            'transition' => PaymentTransitions::TRANSITION_FAIL,
            'state' => PaymentInterface::STATE_FAILED,
            'message' => 'payment_ko',
        ];
    }

    /**
     * Finalizes payment details with state information.
     *
     * @param array &$details Payment details to update (passed by reference)
     * @param array $stateData State data containing state, message, and transition
     */
    private function finalizePaymentDetails(array &$details, array $stateData): void
    {
        $details['new_status'] = $stateData['state'];
        $details['responseCode'] = Response::HTTP_OK;
        $details['responseMessage'] = SogecommerceApi::getOutputForGateway($stateData['message']);
        $details['refunded'] = $stateData['transition'] === PaymentTransitions::TRANSITION_REFUND;
    }

    /**
     * Applies payment state transition if possible.
     *
     * @param PaymentInterface $payment The payment to transition
     * @param string $transition The transition to apply
     * @param string $orderId The order ID for logging
     */
    private function applyPaymentTransition(PaymentInterface $payment, string $transition, string $orderId): void
    {
        if ($this->stateMachine->can($payment, PaymentTransitions::GRAPH, $transition)) {
            $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, $transition);
            $this->logger->info("Payment status updated successfully for order #$orderId. New status is {$payment->getState()}.");
        }
    }

    /**
     * Applies an order state transition if possible.
     *
     * @param object $order The order to transition
     * @param string $transition The transition to apply
     */
    private function applyOrderTransition(object $order, string $transition): void
    {
        if ($this->stateMachine->can($order, OrderPaymentTransitions::GRAPH, $transition)) {
            $this->stateMachine->apply($order, OrderPaymentTransitions::GRAPH, $transition);
            $this->logger->info("Payment order state updated successfully for order #{$order->getNumber()}. New state is {$order->getState()}.");
        }
    }
}