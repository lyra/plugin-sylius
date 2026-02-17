<?php
/**
 * Copyright © Lyra Network.
 * This file is part of Lyra Collect plugin for Sylius. See COPYING.md for license details.
 *
 * @author    Lyra Network <https://www.lyra.com>
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/mit-license.html The MIT License (MIT)
 */

declare(strict_types=1);

namespace Lyranetwork\Lyra\Service;

use Lyranetwork\Lyra\Form\Type\SyliusGatewayConfigurationType as GatewayConfiguration;
use Lyranetwork\Lyra\Sdk\RefundProcessor as LyraRefundProcessor;
use Lyranetwork\Lyra\Sdk\RestHelper;
use Lyranetwork\Lyra\Sdk\Tools as LyraTools;
use Lyranetwork\Lyra\Sdk\Refund\Api as LyraRefund;
use Lyranetwork\Lyra\Sdk\Form\Api as LyraApi;
use Lyranetwork\Lyra\Sdk\Refund\OrderInfo as LyraOrderInfo;

/**
 * Service for handling payment refunds through Lyra Collect payment gateway.
 *
 * This service manages the refund process by communicating with the Lyra REST API,
 * preparing order information, and executing refund transactions.
 */
final class RefundService
{
    public function __construct(
        private RestHelper $restHelper,
        private ConfigService $configService,
        private LyraRefundProcessor $refundProcessor
    ) {
    }

    /**
     * Processes a payment refund for the given order.
     *
     * This method prepares the order information, initializes the Lyra Refund API
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
        $lyraOrderInfo = new LyraOrderInfo();
        $lyraOrderInfo->setOrderRemoteId($order->getNumber());
        $lyraOrderInfo->setOrderId($order->getNumber());
        $lyraOrderInfo->setOrderReference($order->getNumber());
        $lyraOrderInfo->setOrderCurrencyIsoCode($order->getCurrencyCode());
        $lyraOrderInfo->setOrderCurrencySign($order->getCurrencyCode());
        $lyraOrderInfo->setOrderUserInfo($userInfo);

        $refundApi = new LyraRefund(
            $this->refundProcessor->getProcessor(),
            $this->restHelper->getPrivateKey($paymentMethodCode),
            LyraTools::getDefault('REST_URL'),
            $this->configService->get(GatewayConfiguration::$REST_FIELDS . 'site_id', $paymentMethodCode),
            'Sylius'
        );

        return $refundApi->refund($lyraOrderInfo, $amount);
    }
}