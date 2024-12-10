<?php
/**
 * Copyright © Lyra Network.
 * This file is part of Monetico Retail plugin for Sylius. See COPYING.md for license details.
 *
 * @author    Lyra Network <https://www.lyra.com>
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/mit-license.html The MIT License (MIT)
 */

namespace Lyranetwork\Monetico\Service;

use Lyranetwork\Monetico\Form\Type\SyliusGatewayConfigurationType as GatewayConfiguration;
use Lyranetwork\Monetico\Sdk\RefundProcessor as MoneticoRefundProcessor;
use Lyranetwork\Monetico\Sdk\RestData;
use Lyranetwork\Monetico\Sdk\Tools as MoneticoTools;
use Lyranetwork\Monetico\Sdk\Refund\Api as MoneticoRefund;
use Lyranetwork\Monetico\Sdk\Form\Api as MoneticoApi;
use Lyranetwork\Monetico\Sdk\Refund\OrderInfo as MoneticoOrderInfo;

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
     * @var MoneticoRefundProcessor
     */
    private $refundProcessor;

    public function __construct(
        RestData $restData,
        ConfigService $configService,
        MoneticoRefundProcessor $refundProcessor
    ) {
        $this->restData = $restData;
        $this->configService = $configService;
        $this->refundProcessor = $refundProcessor;
    }

    public function refund($paymentMethodCode, $order, $userInfo, $amount)
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
            $this->restData->getPrivateKey($paymentMethodCode),
            MoneticoTools::getDefault('REST_URL'),
            $this->configService->get(GatewayConfiguration::$REST_FIELDS . 'site_id', $paymentMethodCode),
            'Sylius'
        );

        return $refundApi->refund($moneticoOrderInfo, $amount);
    }
}