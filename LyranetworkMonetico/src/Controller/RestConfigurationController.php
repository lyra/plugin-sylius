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

namespace Lyranetwork\Monetico\Controller;

use Lyranetwork\Monetico\Form\Type\SyliusGatewayConfigurationType;
use Lyranetwork\Monetico\Service\ConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use Lyranetwork\Monetico\Sdk\Form\Api as MoneticoApi;
use Lyranetwork\Monetico\Sdk\Rest\Api as MoneticoRest;
use Lyranetwork\Monetico\Sdk\Tools as MoneticoTools;

use Psr\Log\LoggerInterface;

final class RestConfigurationController
{
    /**
     * @param ConfigService $configService Service for accessing gateway configuration
     * @param LoggerInterface $logger Logger for tracking status check operations
     */
    public function __construct(
        private ConfigService $configService,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Get a form token for the configuration widget.
     *
     * @param Request $request The request object containing form configuration.
     * @return Response The response containing the mock token result in JSON format.
     */
    public function getWidgetToken(Request $request) : Response
    {
        $formConfiguration = $request->request->all();

        if (empty($formConfiguration)) {
            $msg = 'No form configuration provided for widget token generation.';
            $this->logger->error($msg);

            return new Response($msg, Response::HTTP_BAD_REQUEST);
        }

        $siteId = $formConfiguration['shopId'];
        $key = $formConfiguration['privateKey'];

        $whiteLabel = $formConfiguration['payzenWhiteLabel'] ?? null;
        $restUrl = MoneticoApi::getWhiteLabelUrl($whiteLabel, 'restUrl') ?? MoneticoTools::$REST_URL;

        $params = array(
            "amount" => $formConfiguration['wsData']['amount'],
            "currency" => $formConfiguration['wsData']['currency'],
            "orderId" => "myOrderId-999999",
            "formAction" => $formConfiguration['wsData']['formAction'],
            "customer" => array(
                'reference' => $formConfiguration['wsData']['customer']['reference'],
                "email" => "sample@example.com"
            )
        );

        $client = new MoneticoRest($restUrl, $siteId, $key);
        $result = $client->post('V4/Charge/CreatePayment', json_encode($params));

        if ($result['status'] != 'SUCCESS') {
            $msg = "Error while creating form token for configuration widget: " . $result['answer']['errorMessage']
                . ' (' . $result['answer']['errorCode'] . ').';

            if (isset($result['answer']['detailedErrorMessage']) && ! empty($result['answer']['detailedErrorMessage'])) {
                $msg .= ' Detailed message: ' . $result['answer']['detailedErrorMessage'] . ' (' . $result['answer']['detailedErrorCode'] . ').';
            }

            $this->logger->error($msg);

            return new Response($msg, Response::HTTP_BAD_GATEWAY);
        }

        $this->logger->info("Form token created successfully for configuration widget.");

        return new Response(json_encode($result), Response::HTTP_OK);
    }

    /**
     * Get the default configuration for the REST API widget.
     *
     * @param Request $request The request object containing the instance code.
     * @return Response The response containing the default configuration in JSON format.
     */
    public function getDefaultConfiguration(Request $request) : Response
    {
        $instanceCode = $request->query->get("instanceCode");
        $shopId = $this->getRestFieldValue('site_id', $instanceCode);

        if (empty($shopId)) {
            $this->logger->info("No default configuration was found in database.");

            return new Response("", Response::HTTP_OK);
        }

        $defaultConfiguration = array(
            "activeModel" => "",
            "shopMode" => strtolower($this->getRestFieldValue('context_mode', $instanceCode)),
            "models" => []
        );

        $defaultConfiguration["models"][] = [
            "id" => "default",
            "label" => "default",
            "shopConfig" => [
                "shopId" => $shopId,
                "keys" => [
                    "test" => [
                        "publicKey" => $this->getRestFieldValue('public_test_key', $instanceCode),
                        "privateKey" => $this->getRestFieldValue('private_test_key', $instanceCode),
                        "shaKey" => $this->getRestFieldValue('hmac_test_key', $instanceCode),
                    ],
                    "production" => [
                        "publicKey" => $this->getRestFieldValue('public_prod_key', $instanceCode),
                        "privateKey" => $this->getRestFieldValue('private_prod_key', $instanceCode),
                        "shaKey" => $this->getRestFieldValue('hmac_prod_key', $instanceCode),
                    ]
                ],
                "wsData" => [
                    "formAction" => $this->getAdvancedFieldValue('oneclick_payment', $instanceCode) ? 'CUSTOMER_WALLET' : 'PAYMENT',
                    "transactionOptions" => [
                        "cardOptions" => [
                            "retry" => $this->getAdvancedFieldValue('rest_attempts', $instanceCode),
                        ]
                    ]
                ]
            ],
            "formConfig" => [
                "smartForm" => [
                    "displayOptions" => [
                        "cardHeader" => $this->getAdvancedFieldValue('payment_data_entry_mode', $instanceCode) === 'MODE_EMBEDDED_EXT_WITH_LOGOS',
                        "cardsIntegrated" => false
                    ]
                ]
            ]
        ];

        $result = json_encode($defaultConfiguration);

        return new Response($result, Response::HTTP_OK);
    }

    private function getRestFieldValue(string $key, string $instanceCode)
    {
        return $this->configService->get(SyliusGatewayConfigurationType::$REST_FIELDS . $key, $instanceCode);
    }

    private function getAdvancedFieldValue(string $key, string $instanceCode)
    {
        return $this->configService->get(SyliusGatewayConfigurationType::$ADVANCED_FIELDS . $key, $instanceCode);
    }
}