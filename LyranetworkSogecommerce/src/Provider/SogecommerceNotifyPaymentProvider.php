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

namespace Lyranetwork\Sogecommerce\Provider;

use Sylius\Bundle\PaymentBundle\Provider\NotifyPaymentProviderInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Repository\PaymentRequestRepositoryInterface;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Payment\PaymentRequestTransitions;

use Symfony\Component\HttpFoundation\Request;

use Lyranetwork\Sogecommerce\Sdk\RestHelper;

use Psr\Log\LoggerInterface;

/**
 * Provider for retrieving payment information from Sogecommerce IPN (Instant Payment Notification) requests.
 * Validates the notification signature and extracts payment data from the gateway response.
 */
final class SogecommerceNotifyPaymentProvider implements NotifyPaymentProviderInterface
{
    /**
     * @param PaymentRequestRepositoryInterface $paymentRequestRepository Repository for retrieving payment requests
     * @param LoggerInterface $logger Logger for recording info, errors and security issues
     * @param RestHelper $restHelper Helper service for validating and processing REST API responses
     * @param StateMachineInterface $stateMachine State machine for managing payment request transitions
     */
    public function __construct(
        private PaymentRequestRepositoryInterface $paymentRequestRepository,
        private LoggerInterface $logger,
        private RestHelper $restHelper,
        private StateMachineInterface $stateMachine
    ) {
    }

    /**
     * Retrieves the payment from an IPN notification request.
     * Validates the request signature, extracts transaction data, and updates payment details.
     *
     * @param Request $request The HTTP request containing the IPN notification
     * @param PaymentMethodInterface $paymentMethod The payment method used for this payment
     *
     * @return PaymentInterface The payment entity with updated details from the gateway response
     *
     * @throws \RuntimeException If the response is invalid or the payment request is not found (via die())
     */
    public function getPayment(Request $request, PaymentMethodInterface $paymentMethod): PaymentInterface
    {
        // Validate REST response format and signature.
        if (! $this->restHelper->checkRestResponseValidity($request)) {
            $this->logger->error('Invalid response received. Content: ' . json_encode($request->request->all()));

            die('<span style="display:none">KO-Invalid IPN request received.' . "\n" . '</span>');
        }

        // Decode and validate answer structure.
        $answer = json_decode((string) $request->get('kr-answer'), true);
        if (! is_array($answer) || empty($answer)) {
            $this->logger->error('Invalid response received. Content of kr-answer: ' . json_encode($request->get('kr-answer')));

            die('<span style="display:none">KO-Invalid IPN request received.' . "\n" . '</span>');
        }

        // Extract transaction data (first transaction or whole answer if no transactions array).
        $transaction = $this->getTransaction($answer);

        // Ignore VERIFICATION operations (card wallet additions).
        if (isset($transaction['operationType']) && $transaction['operationType'] === 'VERIFICATION') {
            die();
        }

        // Validate metadata exists.
        if (! isset($transaction['metadata']['dbMethodCode'], $transaction['metadata']['paymentRequestHash'])) {
            $this->logger->error('Missing required metadata in transaction: ' . json_encode($transaction));

            die('<span style="display:none">KO-Missing transaction metadata.' . "\n" . '</span>');
        }

        // Verify signature with private key.
        $instanceCode = $transaction['metadata']['dbMethodCode'];
        $key = $this->restHelper->getPrivateKey($instanceCode);
        if (! $this->restHelper->checkResponseHash($request, $key)) {
            $this->logger->error("Tried to access IPN endpoint without valid signature.");

            die('<span style="display:none">An error occurred while computing the signature.' . "\n" . '</span>');
        }

        // Add source to answer.
        $answer['kr-src'] = $request->get('kr-src');

        // Find payment request by hash.
        $hash = $transaction['metadata']['paymentRequestHash'];
        $paymentRequest = $this->paymentRequestRepository->findOneBy(['hash' => $hash]);
        if ($paymentRequest === null) {
            $this->logger->error("No payment request found for hash: {$hash}");

            die('<span style="display:none">KO-No payment request found.' . "\n" . '</span>');
        }

        if ($this->stateMachine->can($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_COMPLETE)) {
            $this->stateMachine->apply($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_COMPLETE);
        }

        // Update payment details with gateway response.
        $payment = $paymentRequest->getPayment();
        $details = $payment->getDetails();
        $details['answer'] = json_encode($answer);
        $payment->setDetails($details);

        return $payment;
    }

    /**
     * Extracts the transaction data from the answer.
     *
     * @param array<string, mixed> $answer The decoded answer from the gateway
     *
     * @return array<string, mixed> The transaction data
     */
    private function getTransaction(array $answer): array
    {
        if (isset($answer['transactions']) && is_array($answer['transactions']) && ! empty($answer['transactions'])) {
            return $answer['transactions'][0];
        }

        return $answer;
    }

    /**
     * Checks if this provider supports the given payment method.
     *
     * @param Request $request The HTTP request (not used in this implementation)
     * @param PaymentMethodInterface $paymentMethod The payment method to check
     *
     * @return bool True if the payment method is a Sogecommerce gateway, false otherwise
     */
    public function supports(Request $request, PaymentMethodInterface $paymentMethod): bool
    {
        return $paymentMethod->getGatewayConfig()?->getFactoryName() === constant('Lyranetwork\Sogecommerce\Sdk\Tools::FACTORY_NAME');
    }
}