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

namespace Lyranetwork\Monetico\Service;

use Lyranetwork\Monetico\Form\Type\SyliusGatewayConfigurationType as GatewayConfiguration;
use Lyranetwork\Monetico\Sdk\RefundProcessor as MoneticoRefundProcessor;
use Lyranetwork\Monetico\Sdk\RestHelper;
use Lyranetwork\Monetico\Sdk\Tools as MoneticoTools;
use Lyranetwork\Monetico\Sdk\Refund\Api as MoneticoRefund;
use Lyranetwork\Monetico\Sdk\Form\Api as MoneticoApi;
use Lyranetwork\Monetico\Sdk\Refund\OrderInfo as MoneticoOrderInfo;

/**
 * Service for handling payment refunds through Monetico Retail payment gateway.
 *
 * This service manages the refund process by communicating with the Monetico REST API,
 * preparing order information, and executing refund transactions.
 */
final class RefundService
{
    public function __construct(
        private RestHelper $restHelper,
        private ConfigService $configService,
        private MoneticoRefundProcessor $refundProcessor
    ) {
    }

    /**
     * Processes a payment refund for the given order.
     *
     * This method prepares the order information, initializes the Monetico Refund API
     * with the appropriate credentials and configuration, and executes the transaction
     * refund through the payment gateway.
     *
     * @param string $paymentMethodCode The payment method code/instance identifier
     * @param mixed  $order             The order entity to be refunded
     * @param string $userInfo          User information (typically admin user details initiating the refund)
     * @param int    $amount            The refund amount in the smallest currency unit (e.g., cents)
     *
     * @return bool True if the refund was successful, false otherwise
     */
    public function refund($paymentMethodCode, $order, $userInfo, $amount): bool
    {
        $moneticoOrderInfo = new MoneticoOrderInfo();
        $moneticoOrderInfo->setOrderRemoteId($order->getNumber());
        $moneticoOrderInfo->setOrderId($order->getNumber());
        $moneticoOrderInfo->setOrderReference($order->getNumber());
        $moneticoOrderInfo->setOrderCurrencyIsoCode($order->getCurrencyCode());
        $moneticoOrderInfo->setOrderCurrencySign($order->getCurrencyCode());
        $moneticoOrderInfo->setOrderUserInfo($userInfo);

        $refundApi = new MoneticoRefund(
            $this->refundProcessor->getProcessor(),
            $this->restHelper->getPrivateKey($paymentMethodCode),
            MoneticoTools::getDefault('REST_URL'),
            $this->configService->get(GatewayConfiguration::$REST_FIELDS . 'site_id', $paymentMethodCode),
            'Sylius'
        );

        return $refundApi->refund($moneticoOrderInfo, $amount);
    }
}