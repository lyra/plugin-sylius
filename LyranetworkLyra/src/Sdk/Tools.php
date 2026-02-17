<?php
/**
 * Copyright © Lyra Network.
 * This file is part of Lyra Collect plugin for Sylius. See COPYING.md for license details.
 *
 * @author    Lyra Network (https://www.lyra.com)
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/mit-license.html The MIT License (MIT)
 */

declare(strict_types=1);

namespace Lyranetwork\Lyra\Sdk;

use Lyranetwork\Lyra\Sdk\Form\Api as LyraApi;

/**
 * Utility class providing constants, configuration defaults, and helper methods
 * for the Lyra Collect payment gateway integration.
 */
final class Tools
{
    /**
     * The payment gateway factory name used for identifying Lyra payment methods.
     */
    public const FACTORY_NAME = "lyra_sylius_payment";

    /** @var string The gateway code identifier */
    private static string $GATEWAY_CODE = 'Lyra';

    /** @var string The gateway display name */
    private static string $GATEWAY_NAME = 'Lyra Collect';

    /** @var string The merchant Back Office name */
    private static string $BACKOFFICE_NAME = 'Lyra Expert';

    /** @var string The default site identifier */
    private static string $SITE_ID = '12345678';

    /** @var string The default context mode (TEST or PRODUCTION) */
    private static string $CTX_MODE = 'TEST';

    /** @var string The CMS identifier for tracking */
    private static string $CMS_IDENTIFIER = 'Sylius_2.x';

    /** @var string The support email address */
    private static string $SUPPORT_EMAIL = 'https://support.lyra.com/hc/fr/requests/new';

    /** @var string The current plugin version */
    private static string $PLUGIN_VERSION = '3.0.0';

    /** @var string The REST API base URL */
    private static string $REST_URL = 'https://api.lyra.com/api-payment/';

    /** @var string The static resources base URL */
    private static string $STATIC_URL = 'https://static.lyra.com/static/';

    /** @var string The embedded form default display mode */
    private static string $EMBEDDED_MODE = 'MODE_EMBEDDED_EXT_WITH_LOGOS';

    /** @var string The embedded form default theme */
    private static string $THEME = 'NEON';

    /**
     * Available plugin features and their enabled status.
     *
     * @var array<string, bool>
     */
    public static array $pluginFeatures = [
        'prodfaq' => false
    ];

    /**
     * Available embedded form display modes.
     *
     * @var array<int, string>
     */
    public static array $embeddedModes = [
        'MODE_EMBEDDED',
        'MODE_EMBEDDED_EXT_WITH_LOGOS',
        'MODE_EMBEDDED_EXT_WITHOUT_LOGOS'
    ];

    /**
     * Available embedded form themes.
     *
     * @var array<int, string>
     */
    public static array $themes = [
        'NEON',
        'CLASSIC'
    ];

    /**
     * Supported documentation languages with their display names.
     *
     * @var array<string, string> Language code => Language name
     */
    public static array $docLanguages = [
        'fr' => 'Français',
        'en' => 'English',
        'es' => 'Español',
        'de' => 'Deutsch',
        'br' => 'Português',
        'pt' => 'Português'
        // Complete when other languages are managed.
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
        return self::getDefault('CMS_IDENTIFIER') . '_' . self::getDefault('PLUGIN_VERSION') . '/' . constant('Sylius\Bundle\CoreBundle\SyliusCoreBundle::VERSION') . '/' . LyraApi::shortPhpVersion();
    }

    /**
     * Returns the payment data entry mode choices formatted for Symfony form field.
     * Transforms embedded modes into an array with translation keys as labels and mode values.
     *
     * @param string $translationPrefix The translation key prefix (e.g., 'sylius_lyra_plugin.')
     *
     * @return array<string, string> Array of translation keys => mode values
     */
    public static function getPaymentDataEntryModeChoices(string $translationPrefix = 'sylius_lyra_plugin.'): array
    {
        $choices = [];

        foreach (self::$embeddedModes as $mode) {
            $translationKey = $translationPrefix . 'config.embedded.' . strtolower($mode);
            $choices[$translationKey] = $mode;
        }

        return $choices;
    }

    /**
     * Returns the theme choices formatted for Symfony form field.
     * Transforms themes into an array with translation keys as labels and theme values.
     *
     * @param string $translationPrefix The translation key prefix (e.g., 'sylius_lyra_plugin.')
     *
     * @return array<string, string> Array of translation keys => theme values
     */
    public static function getThemeChoices(string $translationPrefix = 'sylius_lyra_plugin.'): array
    {
        $choices = [];

        foreach (self::$themes as $theme) {
            $translationKey = $translationPrefix . 'config.theme.' . strtolower($theme);
            $choices[$translationKey] = $theme;
        }

        return $choices;
    }
}