<?php
/**
 * Copyright © Lyra Network.
 * This file is part of Monetico Retail plugin for Sylius. See COPYING.md for license details.
 *
 * @author    Lyra Network (https://www.lyra.com)
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/mit-license.html The MIT License (MIT)
 */

declare(strict_types=1);
namespace Lyranetwork\Monetico\Sdk;

use Lyranetwork\Monetico\Sdk\Form\Api as MoneticoApi;

class Tools
{
    private static $GATEWAY_CODE = 'Monetico_Retail';
    private static $GATEWAY_NAME = 'Monetico Retail';
    private static $BACKOFFICE_NAME = 'Monetico Retail';
    private static $GATEWAY_URL = 'https://secure.gateway.monetico-retail.com/vads-payment/';
    private static $SITE_ID = '12345678';
    private static $KEY_TEST = '1111111111111111';
    private static $KEY_PROD = '2222222222222222';
    private static $CTX_MODE = 'TEST';
    private static $SIGN_ALGO = 'SHA-256';
    private static $LANGUAGE = 'fr';

    private static $CMS_IDENTIFIER = 'Sylius_1.12.x';
    private static $SUPPORT_EMAIL = 'support-mr@monetico-retail.com';
    private static $PLUGIN_VERSION = '1.1.2';
    private static $GATEWAY_VERSION = 'V2';
    private static $REST_URL = 'https://api.gateway.monetico-retail.com/api-payment/';
    private static $STATIC_URL = 'https://static.gateway.monetico-retail.com/static/';

    public static $pluginFeatures = [
        'qualif' => false,
        'prodfaq' => true,
        'shatwo' => true,
        'smartform' => true
    ];

    public static $smartformModes = [
        'MODE_SMARTFORM',
        'MODE_SMARTFORM_EXT_WITH_LOGOS',
        'MODE_SMARTFORM_EXT_WITHOUT_LOGOS'
    ];

    public static $doc_languages = [
        'fr' => 'Français',
        'en' => 'English',
        'es' => 'Español',
        'de' => 'Deutsch',
        'br' => 'Português',
        'pt' => 'Português'
        // Complete when other languages are managed.
    ];

    public static function getDefault($name)
    {
        if (! is_string($name)) {
            return '';
        }

        if (! isset(self::$$name)) {
            return '';
        }

        return self::$$name;
    }

    public static function getContrib()
    {
        return self::getDefault('CMS_IDENTIFIER') . '_' . self::getDefault('PLUGIN_VERSION') . '/' . constant('Sylius\Bundle\CoreBundle\SyliusCoreBundle::VERSION') . '/' . MoneticoApi::shortPhpVersion();
    }
}