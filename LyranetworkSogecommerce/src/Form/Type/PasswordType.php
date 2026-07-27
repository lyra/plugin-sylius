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

namespace Lyranetwork\Sogecommerce\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Custom password form type that extends TextType.
 * Ensures password fields are always rendered empty and do not trim whitespace.
 */
final class PasswordType extends AbstractType
{
    /**
     * Configures the default options for this form type.
     * Sets the field to always render empty and preserve whitespace.
     *
     * @param OptionsResolver $resolver The options resolver
     *
     * @return void
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'always_empty' => true,
            'trim' => false,
        ]);
    }

    /**
     * Returns the parent form type class.
     *
     * @return string The fully qualified class name of the parent type (TextType)
     */
    public function getParent(): string
    {
        return TextType::class;
    }

    /**
     * Returns the block prefix for template rendering.
     *
     * @return string The block prefix used in form themes
     */
    public function getBlockPrefix(): string
    {
        return 'password';
    }
}