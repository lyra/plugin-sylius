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

namespace Lyranetwork\Sogecommerce\Service;

use Lyranetwork\Sogecommerce\Form\Type\SyliusGatewayConfigurationType as GatewayConfiguration;
use Lyranetwork\Sogecommerce\Sdk\RefundProcessor as SogecommerceRefundProcessor;
use Lyranetwork\Sogecommerce\Sdk\RestHelper;
use Lyranetwork\Sogecommerce\Sdk\Tools as SogecommerceTools;
use Lyranetwork\Sogecommerce\Sdk\Refund\Api as SogecommerceRefund;
use Lyranetwork\Sogecommerce\Sdk\Form\Api as SogecommerceApi;
use Lyranetwork\Sogecommerce\Sdk\Refund\OrderInfo as SogecommerceOrderInfo;

/**
 * Service for handling payment refunds through Sogecommerce payment gateway.
 *
 * This service manages the refund process by communicating with the Sogecommerce REST API,
 * preparing order information, and executing refund transactions.
 */
final class RefundService
{
    public function __construct(
        private RestHelper $restHelper,
        private ConfigService $configService,
        private SogecommerceRefundProcessor $refundProcessor
    ) {
    }

    /**
     * Processes a payment refund for the given order.
     *
     * This method prepares the order information, initializes the Sogecommerce Refund API
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
        $sogecommerceOrderInfo = new SogecommerceOrderInfo();
        $sogecommerceOrderInfo->setOrderRemoteId($order->getNumber());
        $sogecommerceOrderInfo->setOrderId($order->getNumber());
        $sogecommerceOrderInfo->setOrderReference($order->getNumber());
        $sogecommerceOrderInfo->setOrderCurrencyIsoCode($order->getCurrencyCode());
        $sogecommerceOrderInfo->setOrderCurrencySign($order->getCurrencyCode());
        $sogecommerceOrderInfo->setOrderUserInfo($userInfo);

        $refundApi = new SogecommerceRefund(
            $this->refundProcessor->getProcessor(),
            $this->restHelper->getPrivateKey($paymentMethodCode),
            SogecommerceTools::getDefault('REST_URL'),
            $this->configService->get(GatewayConfiguration::$REST_FIELDS . 'site_id', $paymentMethodCode),
            'Sylius'
        );

        return $refundApi->refund($sogecommerceOrderInfo, $amount);
    }
}