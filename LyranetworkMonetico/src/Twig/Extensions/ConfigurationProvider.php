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
namespace Lyranetwork\Monetico\Twig\Extensions;

use Lyranetwork\Monetico\Sdk\Tools as MoneticoTools;
use Lyranetwork\Monetico\Sdk\Form\Api as MoneticoApi;

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
            new TwigFunction('monetico_get_config_info', [$this, 'getConfigurationInformation'], ['is_safe' => ['html']]),
        ];
    }

    public function getConfigurationInformation(): array
    {
        $docsUrls = [];
        foreach (MoneticoApi::getOnlineDocUri() as $lang => $docUri) {
            $docsUrls[MoneticoTools::$doc_languages[$lang]] = $docUri . 'sylius2/sitemap.html';
        }

        return [
            'moneticoDocUrls' => $docsUrls,
            'moneticoSupport' => MoneticoApi::formatSupportEmails(MoneticoTools::getDefault('SUPPORT_EMAIL'), $this->translator->trans('sylius_monetico_plugin.ui.monetico_click_here', locale: $this->requestStack->getCurrentRequest()->get('admin_locale'))),
            'moneticoPluginVersion' => MoneticoTools::getDefault('PLUGIN_VERSION'),
            'moneticoGatewayVersion' => MoneticoTools::getDefault('GATEWAY_VERSION')
        ];
    }
}