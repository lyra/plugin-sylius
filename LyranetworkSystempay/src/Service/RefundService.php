<?php
/**
 * Copyright © Lyra Network.
 * This file is part of Systempay plugin for Sylius. See COPYING.md for license details.
 *
 * @author    Lyra Network <https://www.lyra.com>
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/mit-license.html The MIT License (MIT)
 */

namespace Lyranetwork\Systempay\Service;

use Lyranetwork\Systempay\Form\Type\SyliusGatewayConfigurationType as GatewayConfiguration;
use Lyranetwork\Systempay\Sdk\RefundProcessor as SystempayRefundProcessor;
use Lyranetwork\Systempay\Sdk\RestData;
use Lyranetwork\Systempay\Sdk\Tools as SystempayTools;
use Lyranetwork\Systempay\Sdk\Refund\Api as SystempayRefund;
use Lyranetwork\Systempay\Sdk\Form\Api as SystempayApi;
use Lyranetwork\Systempay\Sdk\Refund\OrderInfo as SystempayOrderInfo;

class RefundService
{
    /**
    * @var RestData
    */
    private $restData;

    /**
     * @var ConfigService
     */
    private $configService;

    /**
     * @var SystempayRefundProcessor
     */
    private $refundProcessor;

    public function __construct(
        RestData $restData,
        ConfigService $configService,
        SystempayRefundProcessor $refundProcessor
    ) {
        $this->restData = $restData;
        $this->configService = $configService;
        $this->refundProcessor = $refundProcessor;
    }

    public function refund($paymentMethodCode, $order, $userInfo, $amount)
    {
        $systempayOrderInfo = new SystempayOrderInfo();
        $systempayOrderInfo->setOrderRemoteId($order->getNumber());
        $systempayOrderInfo->setOrderId($order->getNumber());
        $systempayOrderInfo->setOrderReference($order->getNumber());
        $systempayOrderInfo->setOrderCurrencyIsoCode($order->getCurrencyCode());
        $systempayOrderInfo->setOrderCurrencySign($order->getCurrencyCode());
        $systempayOrderInfo->setOrderUserInfo($userInfo);

        $refundApi = new SystempayRefund(
            $this->refundProcessor->getProcessor(),
            $this->restData->getPrivateKey($paymentMethodCode),
            SystempayTools::getDefault('REST_URL'),
            $this->configService->get(GatewayConfiguration::$REST_FIELDS . 'site_id', $paymentMethodCode),
            'Sylius'
        );

        return $refundApi->refund($systempayOrderInfo, $amount);
    }
}