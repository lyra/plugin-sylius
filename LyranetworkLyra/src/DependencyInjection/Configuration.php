<?php
/**
 * Copyright © Lyra Network.
 * This file is part of Lyra Collect plugin for Sylius. See COPYING.md for license details.
 *
 * @author    Lyra Network (https://www.lyra.com/)
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/mit-license.html The MIT License (MIT)
 */

declare(strict_types=1);

namespace Lyranetwork\Lyra\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Configuration definition for the Lyra Collect plugin.
 * Defines the semantic configuration tree for the bundle.
 */
final class Configuration implements ConfigurationInterface
{
    /**
     * Builds the configuration tree for the Lyra Collect plugin.
     *
     * @return TreeBuilder The configuration tree builder
     *
     * @psalm-suppress UnusedVariable
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        return new TreeBuilder('lyranetwork_lyra_plugin');
    }
}