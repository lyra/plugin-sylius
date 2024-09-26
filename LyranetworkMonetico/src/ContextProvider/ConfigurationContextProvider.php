<?php
/**
 * Copyright © Lyra Network.
 * This file is part of Monetico Retail plugin for Sylius. See COPYING.md for license details.
 *
 * @author    Lyra Network (https://www.lyra.com/)
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/mit-license.html The MIT License (MIT)
 */

declare(strict_types=1);

namespace Lyranetwork\Monetico\ContextProvider;

use Sylius\Bundle\UiBundle\ContextProvider\ContextProviderInterface;
use Sylius\Bundle\UiBundle\Registry\TemplateBlock;
use Sylius\Component\Customer\Context\CustomerContextInterface;

use Lyranetwork\Monetico\Sdk\Form\Api as MoneticoApi;
use Lyranetwork\Monetico\Sdk\Tools as MoneticoTools;

final class ConfigurationContextProvider implements ContextProviderInterface
{
    public function __construct()
    {
    }

    public function provide(array $templateContext, TemplateBlock $templateBlock): array
    {
        $docsUrls = [];
        foreach (MoneticoApi::getOnlineDocUri() as $lang => $docUri) {
            $docsUrls[MoneticoTools::$doc_languages[$lang]] = $docUri . 'sylius/sitemap.html';
        }

        $templateContext['moneticoDocUrls'] = $docsUrls;
        $templateContext['moneticoSupport'] = MoneticoTools::getDefault('SUPPORT_EMAIL');
        $templateContext['moneticoPluginVersion'] = MoneticoTools::getDefault('PLUGIN_VERSION');
        $templateContext['moneticoGatewayVersion'] = MoneticoTools::getDefault('GATEWAY_VERSION');

        return $templateContext;
    }

    public function supports(TemplateBlock $templateBlock): bool
    {
        return $templateBlock->getEventName() === "sylius.admin.create" || $templateBlock->getEventName() === "sylius.admin.update";
    }
}