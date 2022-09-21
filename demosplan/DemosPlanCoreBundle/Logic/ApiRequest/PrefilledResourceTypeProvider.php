<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\ApiRequest;

use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use EDT\JsonApi\ResourceTypes\ResourceTypeInterface;
use EDT\Wrapping\Contracts\Types\ReadableTypeInterface;
use EDT\Wrapping\Contracts\Types\TypeInterface;
use EDT\Wrapping\TypeProviders\PrefilledTypeProvider;
use Psr\Log\LoggerInterface;

class PrefilledResourceTypeProvider extends PrefilledTypeProvider
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(iterable $types, LoggerInterface $logger)
    {
        parent::__construct($types);
        $this->logger = $logger;
    }

    public function isTypeAvailable(string $typeIdentifier, string ...$implementations): bool
    {
        $typeIdentifier = $this->handleCasing($typeIdentifier);

        return parent::isTypeAvailable($typeIdentifier, ...$implementations);
    }

    public function getType(string $typeIdentifier, string ...$implementations): TypeInterface
    {
        $typeIdentifier = $this->handleCasing($typeIdentifier);

        return parent::getType($typeIdentifier, ...$implementations);
    }

    /**
     * @return ResourceTypeInterface
     */
    public function getReadableAvailableType(string $typeIdentifier): ReadableTypeInterface
    {
        return $this->getAvailableType($typeIdentifier, ResourceTypeInterface::class);
    }

    protected function getIdentifier(TypeInterface $type): string
    {
        if ($type instanceof ResourceTypeInterface) {
            return $type::getName();
        }

        throw new InvalidArgumentException('Expected ResourceTypeInterface, got '.get_class($type));
    }

    /**
     * Checks if the given identifier starts with an upper case character. If not the
     * first character is converted to an upper case and a deprecation warning is logged.
     *
     * @return string the upper-cased variant of the given identifier
     *
     * @deprecated This is only needed for invalid client requests. When frontend implementation is
     *             able to reliably send upper-cased types this method can be removed.
     */
    public function handleCasing(string $typeIdentifier): string
    {
        $typeUpperCased = ucfirst($typeIdentifier);
        if ($typeUpperCased !== $typeIdentifier) {
            $this->logger->warning(
                'Incoming type should have an uppercase first character',
                ['type' => $typeIdentifier]
            );
        }

        return $typeUpperCased;
    }
}
