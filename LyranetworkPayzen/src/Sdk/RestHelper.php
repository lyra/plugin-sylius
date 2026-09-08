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

namespace Lyranetwork\Payzen\Sdk;

use Lyranetwork\Payzen\Form\Type\SyliusGatewayConfigurationType as GatewayConfiguration;
use Lyranetwork\Payzen\Sdk\Tools as PayzenTools;
use Lyranetwork\Payzen\Service\ConfigService;
use Lyranetwork\Payzen\Sdk\Form\Api as PayzenApi;
use Lyranetwork\Payzen\Sdk\Rest\Api as PayzenRest;
use Lyranetwork\Payzen\Sdk\Form\Request as PayzenRequest;

use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RouterInterface;

use Sylius\Component\Resource\Repository\RepositoryInterface;

use Psr\Log\LoggerInterface;

/**
 * Helper service for managing REST API interactions with the PayZen payment gateway.
 *
 * This class handles all REST API operations including token generation, key management,
 * request preparation, response validation, and data formatting for the PayZen gateway.
 * It manages both payment and customer wallet operations through the REST API.
 */
final class RestHelper
{
    const PAYZEN_CART_MAX_NB_PRODUCTS = 85;
    const PRODUCT_LABEL_REGEX_NOT_ALLOWED = '#[^A-Z0-9ÁÀÂÄÉÈÊËÍÌÎÏÓÒÔÖÚÙÛÜÇ ]#ui';
    const SHIPPING_METHOD_NAME_NOT_ALLOWED = "#[^A-Z0-9ÁÀÂÄÉÈÊËÍÌÎÏÓÒÔÖÚÙÛÜÇ -]#ui";

    /**
     * @param ConfigService $configService Service for retrieving gateway configuration
     * @param LoggerInterface $logger PSR logger for operation logging
     * @param RouterInterface $router Symfony router for generating callback URLs
     * @param RepositoryInterface $customerRepository Repository for customer data access
     */
    public function __construct(
        private ConfigService $configService,
        private LoggerInterface $logger,
        private RouterInterface $router,
        private RepositoryInterface $customerRepository
    ) {
    }

    /**
     * Retrieves the shop ID for the specified payment method instance.
     *
     * Uses the active model's shop ID if available, falling back to
     * the configured site ID from the database.
     *
     * @param string $instanceCode The payment method instance code
     * @return string The shop ID
     */
    public function getShopId(string $instanceCode): string
    {
        $activeModel = $this->getActiveModel($instanceCode);

        return $activeModel['shopConfig']['shopId'] ?? $this->configService->get(GatewayConfiguration::$REST_FIELDS . 'site_id', $instanceCode);
    }

    /**
     * Retrieves the private key for the specified payment method instance.
     *
     * @param string $instanceCode The payment method instance code
     * @return string The private key (test or production based on mode)
     */
    public function getPrivateKey(string $instanceCode): string
    {
        return $this->getKey('private', $instanceCode);
    }

    /**
     * Retrieves the public key for the specified payment method instance.
     *
     * @param string $instanceCode The payment method instance code
     * @return string The public key (test or production based on mode)
     */
    public function getPublicKey(string $instanceCode): string
    {
        return $this->getKey('public', $instanceCode);
    }

    /**
     * Retrieves the HMAC key used for validating payment return responses.
     *
     * @param string $instanceCode The payment method instance code
     * @return string The HMAC return key for response signature validation
     */
    public function getReturnKey(string $instanceCode): string
    {
        return $this->getKey('sha', $instanceCode);
    }

    private function getKey(string $type, string $instanceCode): string
    {
        $activeModel = $this->getActiveModel($instanceCode);
        $ctxMode = $this->getContextMode($instanceCode);

        return $activeModel['shopConfig']['keys'][$ctxMode][$type . 'Key'] ?? '';
    }

    /**
     * Retrieves the context mode (test or production) for the specified payment method instance.
     *
     * First checks the active widget model's shop mode, then falls back to the configured
     * context mode from the database. If neither is available, returns the default context mode.
     *
     * @param string $instanceCode The payment method instance code
     * @return string The context mode ('TEST' or 'PRODUCTION')
     */
    public function getContextMode(string $instanceCode): string
    {
        $activeModel = $this->getActiveModel($instanceCode);
        $ctxMode = strtolower($activeModel['shopMode'] ?? $this->configService->get(GatewayConfiguration::$REST_FIELDS . 'context_mode', $instanceCode));

        return ! empty($ctxMode) ? $ctxMode : strtolower(PayzenTools::getDefault('CTX_MODE'));
    }

    /**
     * Generates a form token for processing payment for the specified order.
     *
     * Creates a REST API form token by preparing order data, customer information,
     * and transaction options. This token is used to initialize the payment form.
     *
     * @param object|null $order The Sylius order to process
     * @param string $instanceCode The payment method instance code
     * @param object $paymentRequestHash Hash identifier for the payment request
     * @return bool|string The form token on success, false on failure
     */
    public function getToken(?object $order, string $instanceCode, object $paymentRequestHash): bool|string
    {
        $params = $this->getRestApiFormTokenData($order, $instanceCode, $paymentRequestHash);

        if (! $params) {
            return false;
        }

        $this->logger->info("Creating form token for order #{$order->getNumber()} with parameters: {$params}");

        try {
            $metadata = "order #{$order->getNumber()}";

            return $this->createFormToken($params, $metadata, $instanceCode);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());

            return false;
        }
    }

    /**
     * Generates a form token for managing customer wallet (saved payment methods).
     *
     * Creates a REST API form token for customer wallet operations, allowing users
     * to manage their saved payment methods for one-click payments.
     *
     * @param object $customer The Sylius customer
     * @param string $currency The currency code
     * @param string $instanceCode The payment method instance code
     * @return bool|string The form token on success, false on failure
     */
    public function getAccountToken(object $customer, string $currency, string $instanceCode): bool|string
    {
        $params = [
            'formAction' => 'CUSTOMER_WALLET',
            'customer' => [
                'email' => $customer->getEmail(),
                'reference' => $customer->getId(),
                'billingDetails' => [
                    'firstName' => $customer->getFirstName(),
                    'lastName' => $customer->getLastName()
                ]
            ],
            'contrib' => PayzenTools::getContrib(),
            'currency' => $currency,
            'metadata' => [
                'fromAccount' => true
            ]
        ];

        $address = $customer->getAddresses()[0] ?? null;
        if ($address !== null) {
            $params['customer']['billingDetails']['address'] = $address->getStreet();
            $params['customer']['billingDetails']['zipCode'] = $address->getPostcode();
            $params['customer']['billingDetails']['city'] = $address->getCity();
            $params['customer']['billingDetails']['country'] = $address->getCountryCode();

            if ($state = $address->getProvinceName()) {
                $params['customer']['billingDetails']['state'] = $state;
            }
        }

        return $this->createFormToken(json_encode($params), "user {$customer->getEmail()}", $instanceCode, 'CreateToken');
    }

    /**
     * Validates that a REST response contains all required signature fields.
     *
     * Checks for the presence of kr-hash, kr-hash-algorithm, and kr-answer fields
     * which are necessary for validating the response signature.
     *
     * @param object $request The HTTP request object containing response data
     * @return bool True if response contains required fields, false otherwise
     */
    public static function checkRestResponseValidity(object $request): bool
    {
        return $request->get('kr-hash') !== null && $request->get('kr-hash-algorithm') !== null && $request->get('kr-answer') !== null;
    }

    /**
     * Validates the HMAC signature of a REST API response.
     *
     * Verifies that the response has not been tampered with by comparing the
     * received hash with a calculated hash using the provided key.
     *
     * @param object $data The response data containing kr-hash, kr-hash-algorithm, and kr-answer
     * @param string $key The HMAC key for signature validation
     * @return bool True if signature is valid, false otherwise
     */
    public function checkResponseHash(object $data, string $key): bool
    {
        $supportedSignAlgos = ['sha256_hmac'];
        $algorithm = $data->get('kr-hash-algorithm');

        // Check if the hash algorithm is supported.
        if (! in_array($algorithm, $supportedSignAlgos)) {
            $this->logger->error("Hash algorithm is not supported: {$algorithm}");
            return false;
        }

        // On some servers / can be escaped.
        $krAnswer = str_replace('\/', '/', $data->get('kr-answer') ?: '');
        $hash = hash_hmac('sha256', $krAnswer, $key);

        // Return true if calculated hash and received hash are the same.
        return hash_equals($hash, $data->get('kr-hash'));
    }

    private function getRestApiFormTokenData(?object $order, string $instanceCode, object $paymentRequestHash): bool|string
    {
        if (! $this->validateOrder($order, $instanceCode)) {
            return false;
        }

        $request = $this->prepareRequest($order, $instanceCode);
        $data = $this->getFormTokenData($order, $request, $instanceCode, $paymentRequestHash);

        return json_encode($data);
    }

    /**
     * Validates order before token creation.
     *
     * @param object|null $order The order to validate
     * @param string $instanceCode The payment instance code
     * @return bool True if valid, false otherwise
     */
    private function validateOrder(?object $order, string $instanceCode): bool
    {
        if (! $order || ! $order->getNumber()) {
            $this->logger->error('Cannot create a form token. Empty order passed.');

            return false;
        }

        if ($order->getTotal() <= 0) {
            $this->logger->error('Cannot create a form token. Invalid amount passed.');

            return false;
        }

        $whiteLabel = $this->getWhiteLabel($instanceCode);
        if (! PayzenApi::findCurrencyByAlphaCode($order->getCurrencyCode(), $whiteLabel)) {
            $this->logger->error('Cannot create a form token. Unsupported currency passed.');

            return false;
        }

        return true;
    }

    /**
     * Prepares base token data structure.
     *
     * @param object $order The order entity
     * @param PayzenRequest $request The prepared PayZen request
     * @param string $instanceCode The payment method instance code
     * @param object $paymentRequestHash The payment request hash
     * @return array Base token data
     */
    private function getFormTokenData(object $order, PayzenRequest $request, string $instanceCode, object $paymentRequestHash): array
    {
        $data = [
            'orderId' => $request->get('order_id'),
            'customer' => [
                'email' => $request->get('cust_email'),
                'reference' => $request->get('cust_id'),
                'billingDetails' => [
                    'language' => $request->get('language'),
                    'firstName' => $request->get('cust_first_name'),
                    'lastName' => $request->get('cust_last_name'),
                    'address' => $request->get('cust_address'),
                    'zipCode' => $request->get('cust_zip'),
                    'state' => $request->get('cust_state'),
                    'city' => $request->get('cust_city'),
                    'phoneNumber' => $request->get('cust_phone'),
                    'country' => $request->get('cust_country'),
                    'category' => $request->get('cust_status')
                ],
                'shoppingCart' => [
                    'cartItemInfo' => $this->getCartData($request)
                ]
            ],
            'transactionOptions' => [
                'cardOptions' => [
                    'paymentSource' => 'EC'
                ]
            ],
            'contrib' => $request->get('contrib'),
            'currency' => $request->get('currency'),
            'amount' => $request->get('amount'),
            'ipnTargetUrl' => $this->router->generate('sylius_payment_method_notify', ["code" => $instanceCode], UrlGenerator::ABSOLUTE_URL),
            'metadata' => [
                'dbOrderId' => $order->getId(),
                'dbMethodCode' => $instanceCode,
                'paymentRequestHash' => $paymentRequestHash
            ]
        ];

        if ($taxAmount = $request->get('tax_amount')) {
            $data['customer']['shoppingCart']['taxAmount'] = $taxAmount;
        }

        $shippingAmount = $request->get('shipping_amount');
        if (! empty($shippingAmount) && $shippingAmount !== '0') {
            $data['customer']['shoppingCart']['shippingAmount'] = $shippingAmount;
        }

        $activeModel = $this->getActiveModel($instanceCode);

        // Set Number of attempts in case of rejected payment.
        $restAttempts = isset($activeModel['shopConfig']['wsData']['transactionOptions']) ? $activeModel['shopConfig']['wsData']['transactionOptions']['cardOptions']['retry'] : null;
        if ($restAttempts && is_numeric($restAttempts)) {
            $data['transactionOptions']['cardOptions']['retry'] = $restAttempts;
        }

        // Set shipping information.
        if ($order->getShippingAddress()) {
            $data['customer']['shippingDetails'] = [
                'firstName' => $request->get('ship_to_first_name'),
                'lastName' => $request->get('ship_to_last_name'),
                'phoneNumber' => $request->get('ship_to_phone_num'),
                'address' => $request->get('ship_to_street'),
                'zipCode' => $request->get('ship_to_zip'),
                'city' => $request->get('ship_to_city'),
                'state' => $request->get('ship_to_state'),
                'country' => $request->get('ship_to_country'),
                'deliveryCompanyName' => $request->get('ship_to_delivery_company_name')
            ];

            if ($shippingState = $order->getShippingAddress()->getProvinceCode()) {
                $data['customer']['shippingDetails']['state'] = $shippingState;
            }
        }

        if ($billingState = $order->getBillingAddress()->getProvinceCode()) {
            $data['customer']['billingDetails']['state'] = $billingState;
        }

        $customer = $this->customerRepository->findOneBy(['id' => $request->get('cust_id')]);
        $oneclickEnabled = $this->isOneclickEnabled($instanceCode);
        if ($oneclickEnabled && $customer && $customer->getUser() !== null) {
            $data['formAction'] = 'CUSTOMER_WALLET';
        }

        return $data;
    }

    private function prepareRequest(object $order, string $instanceCode): PayzenRequest
    {
        $request = new PayzenRequest();

        // Get the shop language code.
        $language = $order->getLocaleCode();

        // Retrieve amount.
        $total = $order->getTotal();

        $whiteLabel = $this->getWhiteLabel($instanceCode);
        $currency = PayzenApi::findCurrencyByAlphaCode($order->getCurrencyCode(), $whiteLabel);

        $customer = $order->getCustomer();
        $billingAddress = $order->getBillingAddress();

        // Other parameters.
        $data = [
            // Order info.
            'amount' => $total,
            'order_id' => $order->getNumber(),
            'contrib' => PayzenTools::getContrib(),

            // Misc data.
            'currency' => $currency->getAlpha3(),
            'language' => $language,

            // Customer info.
            'cust_id' => $customer->getId(),
            'cust_email' => $customer->getEmail(),
            'cust_phone' => $customer->getPhoneNumber() ?? $billingAddress->getPhoneNumber(),
            'cust_first_name' => $customer->getFirstName() ?? $billingAddress->getFirstName(),
            'cust_last_name' => $customer->getLastName() ?? $billingAddress->getLastName(),
            'cust_address' => $billingAddress->getStreet(),
            'cust_city' => $billingAddress->getCity(),
            'cust_state' => $billingAddress->getProvinceName(),
            'cust_zip' => $billingAddress->getPostcode(),
            'cust_country' => $billingAddress->getCountryCode(),

            // By default Sylius doesn't manage customer type.
            'cust_status' => 'PRIVATE',

            'shipping_amount' => $order->getAdjustmentsTotal(),
            'tax_amount' => $order->getTaxTotal(),
        ];

        // Delivery data.
        $shippingAddress = $order->getShippingAddress();
        if ($shippingAddress) {
            $address = $shippingAddress->getStreet();
            $zipCode = $shippingAddress->getPostcode();
            $city = $shippingAddress->getCity();

            $data['ship_to_first_name'] = $shippingAddress->getFirstName();
            $data['ship_to_last_name'] = $shippingAddress->getLastName();
            $data['ship_to_street'] = $address;
            $data['ship_to_city'] = $city;
            $data['ship_to_state'] = $shippingAddress->getProvinceName();
            $data['ship_to_country'] = $shippingAddress->getCountryCode();
            $data['ship_to_zip'] = $zipCode;
            $data['ship_to_phone_num'] = $shippingAddress->getPhoneNumber();

            $shippingMethod = $this->getShippingMethod($order);

            if ($shippingMethod && $shippingMethod->getName()) {
                $delivery_company = preg_replace(self::SHIPPING_METHOD_NAME_NOT_ALLOWED, ' ', $shippingMethod->getName());
            } else {
                $delivery_company = preg_replace(self::SHIPPING_METHOD_NAME_NOT_ALLOWED, ' ', $address . ' ' . $zipCode . ' ' . $city);
            }

            $data['ship_to_delivery_company_name'] = $delivery_company;
        }

        $request->setFromArray($data);

        // Send shopping cart details.
        $this->setCartData($order, $request);

        return $request;
    }

    /**
     * Extracts and formats transaction details for storage.
     *
     * Retrieves key transaction information including transaction ID, card brand,
     * operation type, and status for storage in payment details.
     *
     * @param array $transaction The transaction data from the REST API response
     * @return array Formatted array with transaction details
     */
    public function addTransactionDetails(array $transaction): array
    {
        $transactionDetails = $this->getProperty($transaction, 'transactionDetails');
        $cardDetails = $transactionDetails != null ? $this->getProperty($transactionDetails, 'cardDetails') : null;

        $cardBrand = ($this->getProperty($transaction, 'operationType') === 'CREDIT')
            ? ''
            : ($this->getProperty($transactionDetails, 'wallet') ?? $this->getProperty($cardDetails, 'effectiveBrand') ?? '');

        return array(
            'payzen_trans_id' => $this->getProperty($cardDetails, 'legacyTransId') ?? '',
            'payzen_card_brand' => $cardBrand,
            'payzen_operation_type' => $this->getProperty($transaction, 'operationType'),
            'payzen_trans_status' => $this->getProperty($transaction, 'detailedStatus'),
        );
    }

    /**
     * Safely retrieves a property from a REST API result array.
     *
     * Provides null-safe access to array properties, returning null if the
     * property doesn't exist rather than throwing an error.
     *
     * @param array|null $restResult The REST API result array
     * @param string $key The property key to retrieve
     * @return mixed The property value if it exists, null otherwise
     */
    public function getProperty(?array $restResult, string $key): mixed
    {
        if ($restResult != null && isset($restResult[$key])) {
            return $restResult[$key];
        }

        return null;
    }

    /**
     * Get the active widget model for the given instance code.
     *
     * @param string $instanceCode The instance code.
     * @return array|null The active model data as an array, or null if not found.
     */
    public function getActiveModel(string $instanceCode): ?array
    {
        $formConfig = json_decode($this->configService->get(GatewayConfiguration::$REST_FIELDS . 'widget', $instanceCode), true);

        $activeId = $formConfig['activeModel'] ?? null;
        $models = $formConfig['models'] ?? null;

        if (! $activeId || ! $models || ! is_array($models)) {
            return null;
        }

        $indexedModels = array_column($models, null, 'id');

        return $indexedModels[$activeId] ?? null;
    }

    /**
     * Check if one-click payment is enabled for the given instance code.
     *
     * @param string $instanceCode The instance code.
     * @return bool True if one-click is enabled, false otherwise.
     */
    public function isOneClickEnabled(string $instanceCode): bool
    {
        $formConfig = $this->getActiveModel($instanceCode);

        return ($formConfig['shopConfig']['wsData']['formAction'] ?? '') === 'CUSTOMER_WALLET';
    }

    /**
     * Return the absolute URL to the JS and CSS static assets.
     *
     * @return string
     */
    public function getStaticUrl($instanceCode): string
    {
        return PayzenApi::getWhiteLabelUrl($this->getWhiteLabel($instanceCode), 'staticUrl') ?? PayzenTools::$STATIC_URL;
    }

    /**
     * Return the absolute URL to the REST API URL.
     *
     * @return string
     */
    public function getRestUrl(string $instanceCode): string
    {
        return PayzenApi::getWhiteLabelUrl($this->getWhiteLabel($instanceCode), 'restUrl') ?? PayzenTools::$REST_URL;
    }

    public function getWhiteLabel(string $instanceCode): string
    {
        if (! (PayzenTools::$pluginFeatures['whitelabelall'] ?? false)) {
            return '';
        }

        $activeModel = $this->getActiveModel($instanceCode);
        $whiteLabel = $activeModel['shopConfig']['payzenWhiteLabel'] ?? '';
        if ($whiteLabel !== '' && $whiteLabel !== 'EU') {
            return $whiteLabel;
        }

        return '';
    }

    private function createFormToken(string $params, string $metadata, string $instanceCode, string $webService = 'CreatePayment'): bool|string
    {
        $client = new PayzenRest(
            $this->getRestUrl($instanceCode),
            $this->getShopId($instanceCode),
            $this->getPrivateKey($instanceCode)
        );

        $response = $client->post('V4/Charge/' . $webService, $params);

        if ($response['status'] !== 'SUCCESS') {
            $msg = "Error while creating form token for {$metadata}: " . $response['answer']['errorMessage'] . ' (' . $response['answer']['errorCode'] . ').';

            if (! empty($response['answer']['detailedErrorMessage'])) {
                $msg .= ' Detailed message: ' . $response['answer']['detailedErrorMessage'] . ' (' . $response['answer']['detailedErrorCode'] . ').';
            }

            $this->logger->error($msg);

            return false;
        }

        $this->logger->info("Form token created successfully for {$metadata}.");

        return $response['answer']['formToken'];
    }

    private function setCartData(object $order, PayzenRequest &$request): void
    {
        $items = $order->getItems();
        if (count($items) > self::PAYZEN_CART_MAX_NB_PRODUCTS) {
            return;
        }

        foreach ($items as $item) {
            // Get item tax rate.
            $totalTax = 0;

            $taxAdjustments = $item->getAdjustmentsRecursively('tax');
            foreach ($taxAdjustments as $adjustment) {
                $totalTax += $adjustment->getAmount();
            }

            $request->addProduct(
                substr(preg_replace(self::PRODUCT_LABEL_REGEX_NOT_ALLOWED, ' ', $item->getProductName()), 0, 255),
                $item->getUnitPrice(),
                (int) $item->getQuantity(),
                $item->getId(),
                number_format($totalTax, 4, '.', '')
            );
        }
    }

    private function getCartData(PayzenRequest $request): array
    {
        $nbProducts = $request->get("nb_products");
        if (! $nbProducts) {
            return [];
        }

        $products = [];
        for ($index = 0; $index < $nbProducts; ++$index) {
            $products[] = [
                "productLabel" => $request->get("product_label" . $index),
                "productRef" => $request->get("product_ref" . $index),
                "productQty" => $request->get("product_qty" . $index),
                "productAmount" => $request->get("product_amount" . $index),
                "productVat" => $request->get("product_vat" . $index)
            ];
        }

        return $products;
    }

    private function getShippingMethod(object $order): ?object
    {
        foreach ($order->getShipments() as $shipment) {
            if ($method = $shipment->getMethod()) {
                return $method;
            }
        }

        return null;
    }
}