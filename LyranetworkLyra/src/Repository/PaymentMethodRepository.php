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

namespace Lyranetwork\Lyra\Repository;

use Sylius\Bundle\CoreBundle\Doctrine\ORM\PaymentMethodRepository as BasePaymentMethodRepository;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;

/**
 * Repository for managing Lyra Collect payment method entities.
 * Extends the base Sylius payment method repository with custom query methods.
 */
final class PaymentMethodRepository extends BasePaymentMethodRepository implements PaymentMethodRepositoryInterface
{
    /**
     * Finds a payment method by gateway factory name and payment method code.
     *
     * @param string $gatewayFactoryName The gateway factory name (e.g., 'lyra_sylius_payment')
     * @param string $code The payment method code
     *
     * @return PaymentMethodInterface|null The payment method if found, null otherwise
     */
    public function findByGatewayNameAndCode(string $gatewayFactoryName, string $code): ?PaymentMethodInterface
    {
        $result = $this->createQueryBuilder('o')
            ->innerJoin('o.gatewayConfig', 'gatewayConfig')
            ->where('gatewayConfig.factoryName = :gatewayFactoryName')
            ->andWhere('o.code = :code')
            ->setParameter('gatewayFactoryName', $gatewayFactoryName)
            ->setParameter('code', $code)
            ->getQuery()
            ->setMaxResults(1)
            ->getResult();

        return $result[0] ?? null;
    }

    /**
     * Finds all payment methods for a specific gateway factory.
     *
     * @param mixed $gatewayName The gateway factory name to search for
     *
     * @return array<int, PaymentMethodInterface> Array of payment methods matching the gateway name
     */
    public function findAllByGatewayName(mixed $gatewayName): array
    {
        return $this->createQueryBuilder('o')
            ->innerJoin('o.gatewayConfig', 'gatewayConfig')
            ->where('gatewayConfig.factoryName = :gatewayFactoryName')
            ->setParameter('gatewayFactoryName', $gatewayName)
            ->getQuery()
            ->getResult();
    }
}