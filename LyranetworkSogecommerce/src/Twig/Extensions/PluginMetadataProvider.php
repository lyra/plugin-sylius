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

/**
 * Provides Twig functions for accessing Sogecommerce plugin information.
 *
 * This extension exposes functions to retrieve plugin metadata, documentation URLs,
 * support information, and version details for display in Twig templates.
 */
final class PluginMetadataProvider extends AbstractExtension
{
    public function __construct(
        private TranslatorInterface $translator,
        private RequestStack $requestStack
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
        ];
    }

    /**
     * Retrieves Sogecommerce plugin metadata for display.
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
     *         - pluginVersion: Current version of the Sogecommerce plugin
     */
    public function getPluginMetadata(): array
    {
        $docsUrls = [];
        foreach (SogecommerceApi::getOnlineDocUri() as $lang => $docUri) {
            $docsUrls[SogecommerceTools::$docLanguages[$lang]] = $docUri . 'sylius3/sitemap.html';
        }

        return [
            'docUrls' => $docsUrls,
            'contactSupport' => SogecommerceApi::formatSupportEmails(SogecommerceTools::getDefault('SUPPORT_EMAIL'), $this->translator->trans('sylius_sogecommerce_plugin.ui.sogecommerce_click_here', locale: $this->requestStack->getCurrentRequest()->get('admin_locale'))),
            'pluginVersion' => SogecommerceTools::getDefault('PLUGIN_VERSION')
        ];
    }
}