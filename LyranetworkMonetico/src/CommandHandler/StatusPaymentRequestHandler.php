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

use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Bundle\PaymentBundle\Provider\PaymentRequestProviderInterface;
use Sylius\Component\Payment\PaymentRequestTransitions;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

use Lyranetwork\Monetico\Sdk\Tools as MoneticoTools;
use Lyranetwork\Monetico\Sdk\RestHelper;
use Lyranetwork\Monetico\Command\StatusPaymentRequest;
use Lyranetwork\Monetico\Processor\PaymentResultProcessor;

use Psr\Log\LoggerInterface;

/**
 * Handler for processing payment status check commands.
 * Verifies payment completion and processes the result after customer return from gateway.
 */
#[AsMessageHandler]
final class StatusPaymentRequestHandler
{
    /**
     * @param PaymentRequestProviderInterface $paymentRequestProvider Provider for retrieving payment requests
     * @param StateMachineInterface $stateMachine State machine for managing payment request transitions
     * @param LoggerInterface $logger Logger for tracking status check operations
     * @param RequestStack $requestStack Request stack for accessing session and request data
     * @param RestHelper $restHelper Helper service for managing REST API operations and account tokens
     * @param TranslatorInterface $translator Translator for user-facing messages
     * @param PaymentResultProcessor $paymentResultProcessor Processor for handling payment results
     */
    public function __construct(
        private PaymentRequestProviderInterface $paymentRequestProvider,
        private StateMachineInterface $stateMachine,
        private LoggerInterface $logger,
        private RequestStack $requestStack,
        private RestHelper $restHelper,
        private TranslatorInterface $translator,
        private PaymentResultProcessor $paymentResultProcessor
    ) {
    }

    /**
     * Handles the status payment request command.
     * Processes the return from gateway, verifies IPN completion, and finalizes payment status.
     *
     * @param StatusPaymentRequest $statusPaymentRequest The status payment request command
     *
     * @return void
     */
    public function __invoke(StatusPaymentRequest $statusPaymentRequest): void
    {
        $paymentRequest = $this->paymentRequestProvider->provide($statusPaymentRequest);
        $payment = $paymentRequest->getPayment();

        if ($payment === null) {
            $this->logger->error('Cannot process status check: payment is null.');

            return;
        }

        $order = $payment->getOrder();
        $orderId = $order ? $order->getNumber() : 'unknown';

        $this->logger->info("Return call process starts for order #$orderId.");

        $this->stateMachine->apply(
            $paymentRequest,
            PaymentRequestTransitions::GRAPH,
            PaymentRequestTransitions::TRANSITION_PROCESS,
        );

        $session = $this->requestStack->getSession();
        $locale = $this->requestStack->getCurrentRequest()->getLocale();
        $instanceCode = $paymentRequest->getMethod()->getCode();

        $testMode = $this->restHelper->getContextMode($instanceCode) !== 'production';

        $details = $payment->getDetails();
        if (! isset($details['answer']) || ! isset($details['new_status'])
            || ($details['new_status'] == PaymentInterface::STATE_NEW) || ($payment->getState() != $details['new_status'])) {
            $this->logger->error("Something went wrong, IPN didn't worked properly.");

            if ($testMode) {
                $session->getFlashBag()->add('warning', $this->translator->trans('sylius_monetico_plugin.payment.check_url_warn', locale: $locale));
            }
        }

        if ($testMode && MoneticoTools::$pluginFeatures['prodfaq']) {
            $session->getFlashBag()->add('info', $this->translator->trans('sylius_monetico_plugin.payment.prodfaq', locale: $locale));
        }

        $this->paymentResultProcessor->process($payment);

        $this->logger->info("Return call process ends for order #$orderId.");

        $this->stateMachine->apply(
            $paymentRequest,
            PaymentRequestTransitions::GRAPH,
            PaymentRequestTransitions::TRANSITION_COMPLETE,
        );
    }
}