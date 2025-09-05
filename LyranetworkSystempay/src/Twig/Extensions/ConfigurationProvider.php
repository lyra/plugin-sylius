<?php
/**
 * Copyright © Lyra Network.
 * This file is part of Systempay plugin for Sylius. See COPYING.md for license details.
 *
 * @author    Lyra Network <https://www.lyra.com>
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/mit-license.html The MIT License (MIT)
 */

declare(strict_types=1);
namespace Lyranetwork\Systempay\Twig\Extensions;

use Lyranetwork\Systempay\Sdk\Tools as SystempayTools;
use Lyranetwork\Systempay\Sdk\Form\Api as SystempayApi;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class ConfigurationProvider extends AbstractExtension
{
    private TranslatorInterface $translator;

    private RequestStack $requestStack;

    public function __construct(
        TranslatorInterface $translator,
        RequestStack $requestStack
    )
    {
        $this->translator = $translator;
        $this->requestStack = $requestStack;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('systempay_get_config_info', [$this, 'getConfigurationInformation'], ['is_safe' => ['html']]),
        ];
    }

    public function getConfigurationInformation(): array
    {
        $docsUrls = [];
        foreach (SystempayApi::getOnlineDocUri() as $lang => $docUri) {
            $docsUrls[SystempayTools::$doc_languages[$lang]] = $docUri . 'sylius2/sitemap.html';
        }

        return [
            'systempayDocUrls' => $docsUrls,
            'systempaySupport' => SystempayApi::formatSupportEmails(SystempayTools::getDefault('SUPPORT_EMAIL'), $this->translator->trans('sylius_systempay_plugin.ui.systempay_click_here', locale: $this->requestStack->getCurrentRequest()->get('admin_locale'))),
            'systempayPluginVersion' => SystempayTools::getDefault('PLUGIN_VERSION'),
            'systempayGatewayVersion' => SystempayTools::getDefault('GATEWAY_VERSION')
        ];
    }
}