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

namespace Lyranetwork\Lyra\Twig\Extensions;

use Lyranetwork\Lyra\Sdk\RestHelper;
use Lyranetwork\Lyra\Sdk\Tools as LyraTools;
use Lyranetwork\Lyra\Sdk\Form\Api as LyraApi;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

use Sylius\Component\Locale\Context\LocaleContextInterface;

use Psr\Log\LoggerInterface;

/**
 * Provides Twig functions for Lyra Collect embedded form integration.
 *
 * This extension exposes functions to retrieve the embedded form configuration and token
 * required for payment page rendering using Lyra Collect payment gateway.
 */
final class EmbeddedFormProvider extends AbstractExtension
{
    public function __construct(
        private LoggerInterface $logger,
        private RestHelper $restHelper,
        private LocaleContextInterface $localeContext
    ) {
    }

    /**
     * Returns the list of Twig functions.
     *
     * @return array<TwigFunction> Array of TwigFunction instances
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('getEmbeddedFormConfig', [$this, 'getEmbeddedFormConfig'], ['is_safe' => ['html']]),
            new TwigFunction('getEmbeddedFormToken', [$this, 'getEmbeddedFormToken'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * Retrieves the form token required for the embedded form generation.
     *
     * This method generates a payment form token from the Lyra Collect API that will be used
     * to initialize the embedded form on the checkout page.
     *
     * @param mixed  $order              The order entity for which to generate the token
     * @param string $instanceCode       The payment method instance code
     * @param object $paymentRequestHash The payment request hash identifier
     *
     * @return array{formToken: string} Array containing the form token
     */
    public function getEmbeddedFormToken(mixed $order, string $instanceCode, object $paymentRequestHash): array
    {
        $this->logger->info("Start retrieving form token for payment page.");

        $token = $this->restHelper->getToken($order, $instanceCode, $paymentRequestHash);

        return [
            'formToken' => $token
        ];
    }

    /**
     * Retrieves the embedded form configuration settings.
     *
     * This method gathers all necessary configuration parameters to initialize
     * the Lyra Collect embedded form including theme, display mode, language, and API keys.
     *
     * @param string $instanceCode The payment method instance code
     *
     * @return array{
     *     formConfig: string,
     *     theme: string,
     *     jsClient: string,
     *     publicKey: string,
     *     language: string
     * } Array containing embedded form configuration parameters
     */
    public function getEmbeddedFormConfig(string $instanceCode): array
    {
        $activeModel = $this->restHelper->getActiveModel($instanceCode);
        $theme = $activeModel != null ? $activeModel['inteConfig']['theme']['name'] : LyraTools::getDefault('THEME');
        $jsClient = $this->restHelper->getStaticUrl($instanceCode);
        $publicKey = $this->restHelper->getPublicKey($instanceCode);
        $language = substr($this->localeContext->getLocaleCode(), 0, 2);

        return [
            'formConfig' => json_encode($activeModel['formConfig']),
            'theme' => strtolower($theme),
            'jsClient' => $jsClient,
            'publicKey' => $publicKey,
            'language' => $language,
        ];
    }
}