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
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Regex;

use Lyranetwork\Sogecommerce\Repository\PaymentMethodRepositoryInterface;

/**
 * Form type for configuring Sogecommerce payment gateway settings in Sylius.
 * Provides configuration fields for REST API credentials, payment options, and advanced settings.
 */
final class SyliusGatewayConfigurationType extends AbstractType
{
    /** @var string Translation key prefix for form labels and help texts */
    private string $PREFIX = 'sylius_sogecommerce_plugin.';

    /** @var string Field prefix for REST API configuration fields */
    public static string $REST_FIELDS = 'sogecommerce_rest_api_';

    /** @var string Field prefix for advanced configuration options */
    public static string $ADVANCED_FIELDS = 'sogecommerce_advanced_options_';

    /**
     * @param PaymentMethodRepositoryInterface $paymentMethodRepository Repository for retrieving payment methods
     * @param RouterInterface $router Router for generating notification URLs
     * @param RequestStack $requestStack Request stack for accessing current request data
     */
    public function __construct(
        private PaymentMethodRepositoryInterface $paymentMethodRepository,
        private RouterInterface $router,
        private RequestStack $requestStack
    ) {
    }

    /**
     * Builds the payment gateway configuration form.
     * Creates form fields for REST API credentials, payment mode, advanced options,
     * and payment-specific settings. Pre-populates fields with existing configuration.
     *
     * @param FormBuilderInterface $builder The form builder
     * @param array<string, mixed> $options Form options (not used)
     *
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $restCheckUrl = "";

        $methodId = $this->requestStack->getCurrentRequest()->get('id');
        if ($methodId) {
            $paymentMethod = $this->paymentMethodRepository->find($methodId);
            if ($paymentMethod) {
                $restCheckUrl = $this->router->generate('sylius_payment_method_notify', ["code" => $paymentMethod->getCode()], UrlGenerator::ABSOLUTE_URL);
            }
        }

        $builder
            ->add(self::$REST_FIELDS . 'widget', HiddenType::class, [])
            ->add(self::$REST_FIELDS . 'check_url', TextType::class, [
                'label' => $this->PREFIX . 'ui.sogecommerce_rest_check_url.label',
                'disabled' => true,
                'required' => false,
                'data' => $restCheckUrl
            ])
        ;
    }
}