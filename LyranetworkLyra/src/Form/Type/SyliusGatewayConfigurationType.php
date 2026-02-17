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

namespace Lyranetwork\Lyra\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Regex;

use Lyranetwork\Lyra\Sdk\Tools as LyraTools;
use Lyranetwork\Lyra\Repository\PaymentMethodRepositoryInterface;
use Lyranetwork\Lyra\Form\Type\PasswordType;

/**
 * Form type for configuring Lyra Collect payment gateway settings in Sylius.
 * Provides configuration fields for REST API credentials, payment options, and advanced settings.
 */
final class SyliusGatewayConfigurationType extends AbstractType
{
    /** @var string Translation key prefix for form labels and help texts */
    private $PREFIX = 'sylius_lyra_plugin.';

    /** @var string Field prefix for REST API configuration fields */
    public static $REST_FIELDS = 'lyra_rest_api_';

    /** @var string Field prefix for advanced configuration options */
    public static $ADVANCED_FIELDS = 'lyra_advanced_options_';

    /** @var string Field prefix for payment-specific options */
    public static $PAYMENT_OPTIONS = 'lyra_payment_options_';

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
        $config = [];

        $methodId = $this->requestStack->getCurrentRequest()->get('id');
        if ($methodId) {
            $paymentMethod = $this->paymentMethodRepository->find($methodId);
            if ($paymentMethod) {
                $restCheckUrl = $this->router->generate('sylius_payment_method_notify', ["code" => $paymentMethod->getCode()], UrlGenerator::ABSOLUTE_URL);
                $gatewayConfig = $paymentMethod->getGatewayConfig();
                if ($gatewayConfig) {
                    $config = $gatewayConfig->getConfig();
                }
            }
        }

        $builder
            ->add(self::$REST_FIELDS . 'site_id', TextType::class, [
                'label' => $this->PREFIX . 'ui.lyra_site_id.label',
                'data' => $config[self::$REST_FIELDS . 'site_id'] ?? LyraTools::getDefault('SITE_ID'),
                'help' => $this->PREFIX . 'ui.lyra_site_id.helptext',
                'required' => true
            ])
            ->add(self::$REST_FIELDS . 'context_mode', ChoiceType::class, [
                'label' => $this->PREFIX . 'ui.lyra_mode.label',
                'choices' => [
                    $this->PREFIX . 'config.test' => 'TEST',
                    $this->PREFIX . 'config.production' => 'PRODUCTION'
                ],
                'help' => $this->PREFIX . 'ui.lyra_mode.helptext',
                'data' => $config[self::$REST_FIELDS . 'context_mode'] ?? LyraTools::getDefault('CTX_MODE'),
                'required' => false
            ])
            ->add(self::$REST_FIELDS . 'rest_check_url', TextType::class, [
                'label' => $this->PREFIX . 'ui.lyra_rest_check_url.label',
                'disabled' => true,
                'help' => $this->PREFIX . 'ui.lyra_rest_check_url.helptext',
                'required' => false,
                'data' => $restCheckUrl
            ])
            ->add(self::$REST_FIELDS . 'private_test_key', PasswordType::class, [
                'label' => $this->PREFIX . 'ui.lyra_private_test_key.label',
                'required' => false
            ])
            ->add(self::$REST_FIELDS . 'private_prod_key', PasswordType::class, [
                'label' => $this->PREFIX . 'ui.lyra_private_prod_key.label',
                'required' => false
            ])
            ->add(self::$REST_FIELDS . 'public_test_key', TextType::class, [
                'label' => $this->PREFIX . 'ui.lyra_public_test_key.label',
                'required' => false
            ])
            ->add(self::$REST_FIELDS . 'public_prod_key', TextType::class, [
                'label' => $this->PREFIX . 'ui.lyra_public_prod_key.label',
                'required' => false
            ])
            ->add(self::$REST_FIELDS . 'hmac_test_key', PasswordType::class, [
                'label' => $this->PREFIX . 'ui.lyra_hmac_test_key.label',
                'required' => false
            ])
            ->add(self::$REST_FIELDS . 'hmac_prod_key', PasswordType::class, [
                'label' => $this->PREFIX . 'ui.lyra_hmac_prod_key.label',
                'required' => false
            ])
            ->add(self::$ADVANCED_FIELDS . 'payment_data_entry_mode', ChoiceType::class, [
                'label' => $this->PREFIX . 'ui.lyra_payment_data_entry_mode.label',
                'choices' => LyraTools::getPaymentDataEntryModeChoices($this->PREFIX),
                'help' => $this->PREFIX . 'ui.lyra_payment_data_entry_mode.helptext',
                'data' => $config[self::$ADVANCED_FIELDS . 'payment_data_entry_mode'] ?? LyraTools::getDefault('EMBEDDED_MODE'),
                'required' => false
            ])
            ->add(self::$ADVANCED_FIELDS . 'rest_popin_mode', CheckboxType::class, [
                'label' => $this->PREFIX . 'ui.lyra_rest_popin_mode.label',
                'help' => $this->PREFIX . 'ui.lyra_rest_popin_mode.helptext',
                'required' => false
            ])
            ->add(self::$ADVANCED_FIELDS . 'rest_theme', ChoiceType::class, [
                'label' => $this->PREFIX . 'ui.lyra_rest_theme.label',
                'choices' => LyraTools::getThemeChoices($this->PREFIX),
                'help' => $this->PREFIX . 'ui.lyra_rest_theme.helptext',
                'data' => $config[self::$ADVANCED_FIELDS . 'rest_theme'] ?? LyraTools::getDefault('THEME'),
                'required' => false
            ])
            ->add(self::$ADVANCED_FIELDS . 'rest_compact_mode', CheckboxType::class, [
                'label' => $this->PREFIX . 'ui.lyra_rest_compact_mode.label',
                'help' => $this->PREFIX . 'ui.lyra_rest_compact_mode.helptext',
                'required' => false
            ])
            ->add(self::$ADVANCED_FIELDS . 'rest_attempts', NumberType::class, [
                'label' => $this->PREFIX . 'ui.lyra_rest_attempts.label',
                'help' => $this->PREFIX . 'ui.lyra_rest_attempts.helptext',
                'required' => false,
                'constraints' => [
                    new Range([
                        'min' => 0,
                        'max' => 2,
                        'groups' => ['sylius'],
                    ])
                ]
            ])
            ->add(self::$ADVANCED_FIELDS . 'oneclick_payment', CheckboxType::class, [
                'label' => $this->PREFIX . 'ui.lyra_oneclick_payment.label',
                'help' => $this->PREFIX . 'ui.lyra_oneclick_payment.helptext',
                'required' => false
            ])
        ;
    }
}