<?php
/**
 * Copyright © Lyra Network.
 * This file is part of PayZen plugin for Sylius. See COPYING.md for license details.
 *
 * @author    Lyra Network (https://www.lyra.com/)
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/mit-license.html The MIT License (MIT)
 */

declare(strict_types=1);

namespace Lyranetwork\Payzen\ContextProvider;

use Sylius\Bundle\UiBundle\ContextProvider\ContextProviderInterface;
use Sylius\Bundle\UiBundle\Registry\TemplateBlock;
use Sylius\Component\Customer\Context\CustomerContextInterface;

use Lyranetwork\Payzen\Sdk\Form\Api as PayzenApi;
use Lyranetwork\Payzen\Sdk\Tools as PayzenTools;

use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final class ConfigurationContextProvider implements ContextProviderInterface
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

    public function provide(array $templateContext, TemplateBlock $templateBlock): array
    {
        $docsUrls = [];
        foreach (PayzenApi::getOnlineDocUri() as $lang => $docUri) {
            $docsUrls[PayzenTools::$doc_languages[$lang]] = $docUri . 'sylius/sitemap.html';
        }

        $templateContext['payzenDocUrls'] = $docsUrls;
        $templateContext['payzenSupport'] = PayzenApi::formatSupportEmails(PayzenTools::getDefault('SUPPORT_EMAIL'), $this->translator->trans('sylius_payzen_plugin.ui.payzen_click_here', locale: $this->requestStack->getCurrentRequest()->get('admin_locale')));
        $templateContext['payzenPluginVersion'] = PayzenTools::getDefault('PLUGIN_VERSION');
        $templateContext['payzenGatewayVersion'] = PayzenTools::getDefault('GATEWAY_VERSION');

        return $templateContext;
    }

    public function supports(TemplateBlock $templateBlock): bool
    {
        return $templateBlock->getEventName() === "sylius.admin.create" || $templateBlock->getEventName() === "sylius.admin.update";
    }
}