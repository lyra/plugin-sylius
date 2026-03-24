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

namespace Lyranetwork\Payzen\Service;

use Lyranetwork\Payzen\Form\Type\SyliusGatewayConfigurationType as GatewayConfiguration;
use Lyranetwork\Payzen\Sdk\RefundProcessor as PayzenRefundProcessor;
use Lyranetwork\Payzen\Sdk\RestHelper;
use Lyranetwork\Payzen\Sdk\Tools as PayzenTools;
use Lyranetwork\Payzen\Sdk\Refund\Api as PayzenRefund;
use Lyranetwork\Payzen\Sdk\Form\Api as PayzenApi;
use Lyranetwork\Payzen\Sdk\Refund\OrderInfo as PayzenOrderInfo;

/**
 * Service for handling payment refunds through PayZen payment gateway.
 *
 * This service manages the refund process by communicating with the Payzen REST API,
 * preparing order information, and executing refund transactions.
 */
final class RefundService
{
    public function __construct(
        private RestHelper $restHelper,
        private ConfigService $configService,
        private PayzenRefundProcessor $refundProcessor
    ) {
    }

    /**
     * Processes a payment refund for the given order.
     *
     * This method prepares the order information, initializes the Payzen Refund API
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
        $payzenOrderInfo = new PayzenOrderInfo();
        $payzenOrderInfo->setOrderRemoteId($order->getNumber());
        $payzenOrderInfo->setOrderId($order->getNumber());
        $payzenOrderInfo->setOrderReference($order->getNumber());
        $payzenOrderInfo->setOrderCurrencyIsoCode($order->getCurrencyCode());
        $payzenOrderInfo->setOrderCurrencySign($order->getCurrencyCode());
        $payzenOrderInfo->setOrderUserInfo($userInfo);

        $refundApi = new PayzenRefund(
            $this->refundProcessor->getProcessor(),
            $this->restHelper->getPrivateKey($paymentMethodCode),
            PayzenTools::getDefault('REST_URL'),
            $this->configService->get(GatewayConfiguration::$REST_FIELDS . 'site_id', $paymentMethodCode),
            'Sylius'
        );

        return $refundApi->refund($payzenOrderInfo, $amount);
    }
}