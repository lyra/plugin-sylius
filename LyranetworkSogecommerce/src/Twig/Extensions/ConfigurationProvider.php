<?php
/**
 * Copyright © Lyra Network.
 * This file is part of Sogecommerce plugin for Sylius. See COPYING.md for license details.
 *
 * @author    Lyra Network <https://www.lyra.com>
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/mit-license.html The MIT License (MIT)
 */

declare(strict_types=1);
namespace Lyranetwork\Sogecommerce\Twig\Extensions;

use Lyranetwork\Sogecommerce\Sdk\Tools as SogecommerceTools;
use Lyranetwork\Sogecommerce\Sdk\Form\Api as SogecommerceApi;

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
            new TwigFunction('sogecommerce_get_config_info', [$this, 'getConfigurationInformation'], ['is_safe' => ['html']]),
        ];
    }

    public function getConfigurationInformation(): array
    {
        $docsUrls = [];
        foreach (SogecommerceApi::getOnlineDocUri() as $lang => $docUri) {
            if (! isset(SogecommerceTools::$doc_languages[$lang])) {
                continue;
            }

            $docsUrls[SogecommerceTools::$doc_languages[$lang]] = $docUri . 'sylius2/sitemap.html';
        }

        $currentRequest = $this->requestStack->getCurrentRequest();
        $locale = $currentRequest ? $currentRequest->get('admin_locale') : null;

        return [
            'sogecommerceDocUrls' => $docsUrls,
            'sogecommerceSupport' => SogecommerceApi::formatSupportEmails(SogecommerceTools::getDefault('SUPPORT_EMAIL'), $this->translator->trans('sylius_sogecommerce_plugin.ui.sogecommerce_click_here', locale: $locale)),
            'sogecommercePluginVersion' => SogecommerceTools::getDefault('PLUGIN_VERSION'),
            'sogecommerceGatewayVersion' => SogecommerceTools::getDefault('GATEWAY_VERSION')
        ];
    }
}