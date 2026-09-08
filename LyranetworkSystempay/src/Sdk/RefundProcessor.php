<?php
/**
 * Copyright © Lyra Network.
 * This file is part of Systempay plugin for Sylius. See COPYING.md for license details.
 *
 * @author    Lyra Network <https://www.lyra.com>
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/mit-license.html The MIT License (MIT)
 */

namespace Lyranetwork\Systempay\Sdk;

use Doctrine\ORM\EntityManagerInterface;

use Lyranetwork\Systempay\Sdk\Refund\Processor;

use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\RequestStack;

use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Abstraction\StateMachine\StateMachineInterface;

use Psr\Log\LoggerInterface;

class RefundProcessor implements Processor
{
    private LoggerInterface $logger;

    private TranslatorInterface $translator;

    private RequestStack $requestStack;

    private OrderRepositoryInterface $orderRepository;

    private EntityManagerInterface $paymentEntityManager;

    private StateMachineInterface $stateMachine;

    public function __construct(
        LoggerInterface $logger,
        TranslatorInterface $translator,
        RequestStack $requestStack,
        OrderRepositoryInterface $orderRepository,
        EntityManagerInterface $paymentEntityManager,
        StateMachineInterface $stateMachine
    )
    {
        $this->logger = $logger;
        $this->translator = $translator;
        $this->requestStack = $requestStack;
        $this->orderRepository = $orderRepository;
        $this->paymentEntityManager = $paymentEntityManager;
        $this->stateMachine = $stateMachine;
    }

    /**
     * Action to do in case of error during refund process.
     *
     */
    public function doOnError($errorCode, $message): void
    {
        if ($errorCode === 'privateKey') {
            $this->requestStack->getSession()->getFlashBag()->add('error', $this->translate("sylius_systempay_plugin.refund.error.private_key"));
        } else {
            $message .= " " . sprintf($this->translate("sylius_systempay_plugin.refund.error.backoffice_action"), Tools::getDefault('BACKOFFICE_NAME'));
            $this->requestStack->getSession()->getFlashBag()->add('error', $message);
        }
    }

    /**
     * Action to do after sucessful refund process.
     *
     */
    public function doOnSuccess($operationResponse, $operationType)
    {
        if (isset($operationResponse['metadata']['db_order_id']) && (isset($operationResponse['uuid']) || isset($operationResponse['transactionDetails']['parentTransactionUuid']))) {
            $db_order_id = $operationResponse['metadata']['db_order_id'];
            $order = $this->orderRepository->find($db_order_id);

            $transactionUuid = ($operationResponse['detailedStatus'] ?? null) === 'CANCELLED'
                ? ($operationResponse['uuid'] ?? null)
                : ($operationResponse['transactionDetails']['parentTransactionUuid'] ?? null);

            if ($order === null || $transactionUuid === null) {
                return;
            }

            foreach ($order->getPayments() as $payment) {
                $paymentDetails = $payment->getDetails();
                if ($transactionUuid === ($paymentDetails['systempay_trans_uuid'] ?? null)) {
                    $paymentToRefund = $payment;
                    break;
                }
            }

            if (isset($paymentToRefund)) {
                if ($this->stateMachine->can($paymentToRefund, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_REFUND)) {
                    $this->stateMachine->apply($paymentToRefund, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_REFUND);
                }

                $this->paymentEntityManager->flush();
                $this->requestStack->getSession()->getFlashBag()->add('success', 'sylius.payment.refunded');

                $orderId = $operationResponse['orderDetails']['orderId'] ?? 'unknown';
                $this->logger->info("Refunded order #{$orderId} has been saved.");
            }
        }
    }

    /**
     * Action to do after failed refund process.
     *
     */
    public function doOnFailure($errorCode, $message): void
    {
        $this->doOnError($errorCode, $message);
    }

    /**
     * Log informations.
     *
     */
    public function log($message, $level): void
    {
        $this->logger->log($level, $message);
    }

    /**
     * Translate the given message.
     *
     */
    public function translate($message)
    {
        $currentRequest = $this->requestStack->getCurrentRequest();
        $locale = $currentRequest ? $currentRequest->get('admin_locale') : null;

        return $this->translator->trans($message, locale: $locale);
    }

    public function getProcessor()
    {
        return $this;
    }
}