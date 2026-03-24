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

namespace Lyranetwork\Payzen\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Extension\Extension;

/**
 * Dependency injection extension for the PayZen plugin.
 * Loads service definitions and configures the Monolog logger for PayZen operations.
 */
final class LyranetworkPayzenExtension extends Extension
{
    /**
     * Loads the plugin's service definitions from XML configuration.
     *
     * @param array<int, mixed> $configs The plugin configurations
     * @param ContainerBuilder $container The container builder
     *
     * @return void
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../../Resources/config'));

        $loader->load('services.xml');
    }

    /**
     * Returns the configuration instance for this extension.
     *
     * @param array<int, mixed> $config The plugin configuration values
     * @param ContainerBuilder $container The container builder
     *
     * @return ConfigurationInterface The configuration instance
     */
    public function getConfiguration(array $config, ContainerBuilder $container): ConfigurationInterface
    {
        return new Configuration();
    }

    /**
     * Configures a dedicated Monolog channel and handler for PayZen logging.
     *
     * @param ContainerBuilder $container The container builder
     *
     * @return void
     */
    public function prepend(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('monolog', [
            'channels' => ['payzen'],
            'handlers' => [
                'payzen' => [
                    'type' => 'stream',
                    'path' => '%kernel.logs_dir%/payzen.log',
                    'level' => 'info',
                    'channels' => ['payzen'],
                    'bubble' => false
                ]
            ]
        ]);
    }
}