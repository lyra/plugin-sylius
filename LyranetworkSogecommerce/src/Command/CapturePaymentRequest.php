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

namespace Lyranetwork\Sogecommerce\Command;

use Sylius\Bundle\PaymentBundle\Command\PaymentRequestHashAwareInterface;
use Sylius\Bundle\PaymentBundle\Command\PaymentRequestHashAwareTrait;

/**
 * Command for capturing a payment through the Sogecommerce gateway.
 * Represents a request to capture funds for an authorized payment.
 */
final class CapturePaymentRequest implements PaymentRequestHashAwareInterface
{
    use PaymentRequestHashAwareTrait;

    /**
     * @param string|null $hash The payment request hash identifier
     */
    public function __construct(protected ?string $hash)
    {
    }
}