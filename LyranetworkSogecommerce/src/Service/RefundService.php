<?php
/**
 * Copyright © Lyra Network.
 * This file is part of Sogecommerce plugin for Sylius. See COPYING.md for license details.
 *
 * @author    Lyra Network <https://www.lyra.com>
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/mit-license.html The MIT License (MIT)
 */

namespace Lyranetwork\Sogecommerce\Service;

use Lyranetwork\Sogecommerce\Form\Type\SyliusGatewayConfigurationType as GatewayConfiguration;
use Lyranetwork\Sogecommerce\Sdk\RefundProcessor as SogecommerceRefundProcessor;
use Lyranetwork\Sogecommerce\Sdk\RestData;
use Lyranetwork\Sogecommerce\Sdk\Tools as SogecommerceTools;
use Lyranetwork\Sogecommerce\Sdk\Refund\Api as SogecommerceRefund;
use Lyranetwork\Sogecommerce\Sdk\Form\Api as SogecommerceApi;
use Lyranetwork\Sogecommerce\Sdk\Refund\OrderInfo as SogecommerceOrderInfo;

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
     * @var SogecommerceRefundProcessor
     */
    private $refundProcessor;

    public function __construct(
        RestData $restData,
        ConfigService $configService,
        SogecommerceRefundProcessor $refundProcessor
    ) {
        $this->restData = $restData;
        $this->configService = $configService;
        $this->refundProcessor = $refundProcessor;
    }

    public function refund($paymentMethodCode, $order, $userInfo, $amount)
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
            $this->restData->getPrivateKey($paymentMethodCode),
            SogecommerceTools::getDefault('REST_URL'),
            $this->configService->get(GatewayConfiguration::$REST_FIELDS . 'site_id', $paymentMethodCode),
            'Sylius'
        );

        return $refundApi->refund($sogecommerceOrderInfo, $amount);
    }
}