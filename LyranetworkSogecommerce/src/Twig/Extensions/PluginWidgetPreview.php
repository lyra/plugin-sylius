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
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Provides Twig functions for accessing Sogecommerce configuration preview.
 *
 * This extension exposes functions to retrieve configuration widget preview image.
 */
final class PluginWidgetPreview extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('getWidgetPreview', [self::class, 'getWidgetPreviewUrl']),
        ];
    }

    public static function getWidgetPreviewUrl(): string
    {
        return SogecommerceTools::getEpsilonUrl() . 'widget-preview.webp';
    }
}