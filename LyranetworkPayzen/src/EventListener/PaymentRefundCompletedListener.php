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

namespace Lyranetwork\Payzen\EventListener;

use Sylius\Component\Payment\Repository\PaymentRequestRepositoryInterface;
use Sylius\Bundle\PaymentBundle\Checker\FinalizedPaymentRequestCheckerInterface;
use Sylius\Bundle\PaymentBundle\Announcer\PaymentRequestAnnouncerInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\Factory\PaymentRequestFactoryInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;

use Psr\Log\LoggerInterface;

/**
 * Event listener for handling completed payment refunds.
 * Creates and dispatches refund payment requests to the PayZen gateway
 * when a payment refund is completed in Sylius.
 */
final class PaymentRefundCompletedListener
{
    /**
     * @param PaymentRequestAnnouncerInterface $paymentRequestAnnouncer Announcer for dispatching payment request commands
     * @param PaymentRequestRepositoryInterface $paymentRequestRepository Repository for managing payment requests
     * @param FinalizedPaymentRequestCheckerInterface $finalizedPaymentRequestChecker Checker for determining if a payment request is finalized
     * @param PaymentRequestFactoryInterface $paymentRequestFactory Factory for creating new payment requests
     * @param LoggerInterface $logger Logger for tracking refund operations
     */
    public function __construct(
        private PaymentRequestAnnouncerInterface $paymentRequestAnnouncer,
        private PaymentRequestRepositoryInterface $paymentRequestRepository,
        private FinalizedPaymentRequestCheckerInterface $finalizedPaymentRequestChecker,
        private PaymentRequestFactoryInterface $paymentRequestFactory,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Handles the payment refund completion event.
     * Checks if the payment has already been refunded, and if not, creates or retrieves
     * a refund payment request and dispatches it to the gateway.
     *
     * @param CompletedEvent $event The workflow completion event containing the payment subject
     *
     * @return void
     */
    public function __invoke(CompletedEvent $event): void
    {
        $payment = $event->getSubject();

        // Ensure the subject is a PaymentInterface.
        if (! $payment instanceof PaymentInterface) {
            $this->logger->warning('Refund completed event subject is not a PaymentInterface.');

            return;
        }

        // Check if payment has already been refunded.
        $details = $payment->getDetails();
        if (is_array($details) && isset($details['refunded']) && $details['refunded'] === true) {
            $this->logger->info('Payment has already been refunded.');

            return;
        }

        // Get payment method.
        $paymentMethod = $payment->getMethod();
        if (! $paymentMethod) {
            $this->logger->error("Payment #{$payment->getId()} has no payment method. Cannot process refund.");

            return;
        }

        // Log refund initiation.
        $order = $payment->getOrder();
        $orderNumber = $order ? $order->getNumber() : 'unknown';

        $this->logger->info("Payment #{$payment->getId()} for order #{$orderNumber} has been refunded on Sylius. Let's start online refund.");

        // Find or create refund payment request.
        $paymentRequest = $this->findOrCreateRefundRequest($payment, $paymentMethod);

        // Dispatch the refund request to the gateway.
        $this->paymentRequestAnnouncer->dispatchPaymentRequestCommand($paymentRequest);
    }

    /**
     * Finds an existing refund payment request or creates a new one if needed.
     *
     * @param PaymentInterface $payment The payment to refund
     * @param mixed $paymentMethod The payment method
     *
     * @return PaymentRequestInterface The refund payment request
     */
    private function findOrCreateRefundRequest(PaymentInterface $payment, mixed $paymentMethod): PaymentRequestInterface
    {
        $paymentRequest = $this->paymentRequestRepository->findOneByActionPaymentAndMethod(
            PaymentRequestInterface::ACTION_REFUND,
            $payment,
            $paymentMethod,
        );

        // Create new request if none exists or the existing one is finalized.
        if ($paymentRequest === null || $this->finalizedPaymentRequestChecker->isFinal($paymentRequest)) {
            $paymentRequest = $this->paymentRequestFactory->create($payment, $paymentMethod);
            $paymentRequest->setAction(PaymentRequestInterface::ACTION_REFUND);

            $this->paymentRequestRepository->add($paymentRequest);
        }

        return $paymentRequest;
    }
}