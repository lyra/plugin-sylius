<?php
/**
 * Copyright © Lyra Network.
 * This file is part of PayZen plugin for Sylius. See COPYING.md for license details.
 *
 * @author    Lyra Network (https://www.lyra.com)
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/mit-license.html The MIT License (MIT)
 */

declare(strict_types=1);

namespace Lyranetwork\Payzen\Sdk;

use Lyranetwork\Payzen\Sdk\Form\Api as PayzenApi;

/**
 * Utility class providing constants, configuration defaults, and helper methods
 * for the PayZen payment gateway integration.
 */
final class Tools
{
    /**
     * The payment gateway factory name used for identifying Payzen payment methods.
     */
    public const FACTORY_NAME = "payzen_sylius_payment";

    /** @var string The gateway code identifier */
    private static string $GATEWAY_CODE = 'PayZen';

    /** @var string The gateway display name */
    private static string $GATEWAY_NAME = 'PayZen';

    /** @var string The merchant Back Office name */
    private static string $BACKOFFICE_NAME = 'PayZen';

    /** @var string The default context mode (TEST or PRODUCTION) */
    private static string $CTX_MODE = 'TEST';

    /** @var string The CMS identifier for tracking */
    private static string $CMS_IDENTIFIER = 'Sylius_2.x';

    /** @var string The support email address */
    private static string $SUPPORT_EMAIL = 'https://payzen.io/fr-FR/support/';

    /** @var string The current plugin version */
    private static string $PLUGIN_VERSION = '3.1.0';

    /** @var string The REST API base URL */
    private static string $REST_URL = 'https://api.payzen.eu/api-payment/';

    /** @var string The static resources base URL */
    private static string $STATIC_URL = 'https://static.payzen.eu/static/';

    /** @var string The epsilon resources path */
    private static string $EPSILON_PATH = 'js/epsilon/stable/';

    /** @var string The embedded form default theme */
    private static string $THEME = 'neon';

    /** @var string The default language */
    private static string $LANGUAGE = 'fr';

    /**
     * Available plugin features and their enabled status.
     *
     * @var array<string, bool>
     */
    public static array $pluginFeatures = [
        'prodfaq' => true,
        'whitelabelall' => true
    ];

    /**
     * Retrieves the default value of a static property by name.
     *
     * @param string $name The name of the static property to retrieve
     *
     * @return string The property value, or an empty string if not found
     */
    public static function getDefault(string $name): string
    {
        if (! isset(self::${$name})) {
            return '';
        }

        return self::${$name};
    }

    /**
     * Generates the contribution string containing CMS identifier, plugin version,
     * Sylius version, and PHP version information.
     *
     * @return string The formatted contribution string
     */
    public static function getContrib(): string
    {
        return self::getDefault('CMS_IDENTIFIER') . '_' . self::getDefault('PLUGIN_VERSION') . '/' . constant('Sylius\Bundle\CoreBundle\SyliusCoreBundle::VERSION') . '/' . PayzenApi::shortPhpVersion();
    }

    public static function getEpsilonUrl(): string
    {
        return self::getDefault('STATIC_URL') . self::getDefault('EPSILON_PATH');
    }
}