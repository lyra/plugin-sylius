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

use Lyranetwork\Lyra\Sdk\Tools as LyraTools;
use Lyranetwork\Lyra\Sdk\Form\Api as LyraApi;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RouterInterface;

/**
 * Provides Twig functions for accessing Lyra Collect plugin information.
 *
 * This extension exposes functions to retrieve plugin metadata, documentation URLs,
 * support information, and version details for display in Twig templates.
 */
final class PluginMetadataProvider extends AbstractExtension
{
    public function __construct(
        private TranslatorInterface $translator,
        private RequestStack $requestStack,
        private RouterInterface $router
    ) {
    }

    /**
     * Returns the list of Twig functions provided by this extension.
     *
     * @return array<TwigFunction> Array of TwigFunction instances
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('getPluginMetadata', [$this, 'getPluginMetadata'], ['is_safe' => ['html']]),
            new TwigFunction('getWidgetVars', [$this, 'getWidgetVars'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * Retrieves Lyra Collect plugin metadata for display.
     *
     * This method gathers documentation URLs for different languages,
     * contact support links or emails, and the current plugin version.
     *
     * @return array{
     *     docUrls: array<string, string>,
     *     contactSupport: string,
     *     pluginVersion: string
     * } Array containing plugin metadata:
     *         - docUrls: Array of documentation URLs indexed by language name
     *         - contactSupport: Contact support link or email with translation
     *         - pluginVersion: Current version of the Lyra plugin
     */
    public function getPluginMetadata(): array
    {
        $adminLocale = $this->requestStack->getCurrentRequest()->getLocale();
        $locale = substr($adminLocale, 0, 2);
        $allUris = LyraApi::getOnlineDocUri();

        $targetLang = isset($allUris[$locale]) ? $locale : LyraTools::getDefault('LANGUAGE');
        $docUrl = isset($allUris[$targetLang]) ? ($allUris[$targetLang] . 'sylius3/sitemap.html') : '';

        return [
            'docUrl' => $docUrl,
            'contactSupport' => LyraApi::formatSupportEmails(LyraTools::getDefault('SUPPORT_EMAIL'), $this->translator->trans('sylius_lyra_plugin.ui.lyra_support_client', locale: $adminLocale)),
            'pluginVersion' => LyraTools::getDefault('PLUGIN_VERSION')
        ];
    }

    /**
     * Retrieves all the vars needed to initialize the configuration widget.
     * @return array
     */
    public function getWidgetVars(): array
    {
        return [
            'epsilonUrl' => LyraTools::getEpsilonUrl(),
            'whitelabelAll' => LyraTools::$pluginFeatures['whitelabelall'],
            'widgetTokenUrl' => $this->router->generate('lyra_rest_configuration_widget_token', [], UrlGenerator::ABSOLUTE_URL),
            'defaultFormConfig' => $this->router->generate('lyra_rest_configuration_default_form_configuration', [], UrlGenerator::ABSOLUTE_URL)
        ];
    }
}