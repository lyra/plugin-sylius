<?php
/**
 * Copyright © Lyra Network.
 * This file is part of Sogecommerce plugin for Sylius. See COPYING.md for license details.
 *
 * @author    Lyra Network (https://www.lyra.com/)
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/mit-license.html The MIT License (MIT)
 */

declare(strict_types=1);

namespace Lyranetwork\Sogecommerce\ContextProvider;

use Sylius\Bundle\UiBundle\ContextProvider\ContextProviderInterface;
use Sylius\Bundle\UiBundle\Registry\TemplateBlock;
use Sylius\Component\Customer\Context\CustomerContextInterface;

use Lyranetwork\Sogecommerce\Sdk\Form\Api as SogecommerceApi;
use Lyranetwork\Sogecommerce\Sdk\Tools as SogecommerceTools;

final class ConfigurationContextProvider implements ContextProviderInterface
{
    public function __construct()
    {
    }

    public function provide(array $templateContext, TemplateBlock $templateBlock): array
    {
        $docsUrls = [];
        foreach (SogecommerceApi::getOnlineDocUri() as $lang => $docUri) {
            $docsUrls[SogecommerceTools::$doc_languages[$lang]] = $docUri . 'sylius/sitemap.html';
        }

        $templateContext['sogecommerceDocUrls'] = $docsUrls;
        $templateContext['sogecommerceSupport'] = SogecommerceTools::getDefault('SUPPORT_EMAIL');
        $templateContext['sogecommercePluginVersion'] = SogecommerceTools::getDefault('PLUGIN_VERSION');
        $templateContext['sogecommerceGatewayVersion'] = SogecommerceTools::getDefault('GATEWAY_VERSION');

        return $templateContext;
    }

    public function supports(TemplateBlock $templateBlock): bool
    {
        return $templateBlock->getEventName() === "sylius.admin.create" || $templateBlock->getEventName() === "sylius.admin.update";
    }
}