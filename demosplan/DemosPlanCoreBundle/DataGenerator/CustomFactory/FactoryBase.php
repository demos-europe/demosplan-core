<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataGenerator\CustomFactory;

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\AnonymousUser;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\DataProviderException;
use demosplan\DemosPlanCoreBundle\Permissions\Permissions;
use Doctrine\Persistence\ManagerRegistry;
use Faker\Factory;
use Faker\Generator;
use ReflectionException;
use ReflectionFunction;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Session\Session;

abstract class FactoryBase implements FactoryInterface
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var Permissions
     */
    protected $permissions;

    /**
     * @var ManagerRegistry
     */
    protected $doctrine;

    /**
     * @var Generator
     */
    protected $faker;

    /**
     * @var callable Progress callback
     */
    protected $progressCallback;

    /**
     * @var User
     */
    protected $user;

    /**
     * @var Session
     */
    protected $session;

    public function __construct(ManagerRegistry $registry, PermissionsInterface $permissions)
    {
        $this->permissions = $permissions;
        $this->doctrine = $registry;

        $this->setupFaker();
    }

    protected function setupFaker(): Generator
    {
        $this->faker = Factory::create('de_DE');

        return $this->faker;
    }

    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    public function setContainer(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getFaker(): Generator
    {
        return $this->faker;
    }

    /**
     * This is currently just an alias of make since not all entities can be created without
     * the necessity of persisting them or requirements to create tehm.
     *
     * @param int $amount
     */
    public function create($amount = 1)
    {
        return $this->make($amount);
    }

    public function getProgressCallback(): callable
    {
        if (is_callable($this->progressCallback)) {
            return $this->progressCallback;
        }

        return static function ($offset, $current) {
        };
    }

    /**
     * @throws DataProviderException
     * @throws ReflectionException
     */
    public function setProgressCallback(callable $progressCallback): self
    {
        $reflection = new ReflectionFunction($progressCallback);
        $parameters = $reflection->getParameters();

        // NOTE (SG): This could be vastly improved on PHP7 by using ReflectionParameter->hasType/getType-checking
        if (2 === count($parameters)) {
            $this->progressCallback = $progressCallback;

            return $this;
        }

        throw new DataProviderException('Invalid progress callback, expected signature: function($offset, Model $latest)');
    }

    /**
     * Process passed options in a concrete factory.
     *
     * @param array $options
     *
     * @throws DataProviderException
     */
    public function configure(...$options): void
    {
        $this->user = new AnonymousUser();

        $this->permissions->initPermissions($this->user);

        $this->session = new Session();
        $this->session->set('permissions', $this->permissions->getPermissions());
        $this->session->set('userId', $this->user->getId());
    }

    protected function clearEntityManager($offset, $batchSize)
    {
        // regularily clear em,
        // see: http://docs.doctrine-project.org/projects/doctrine-orm/en/latest/reference/batch-processing.html#bulk-inserts
        if (0 === ($offset - 1) % $batchSize) {
            $this->doctrine->getManager()->flush();
            $this->doctrine->getManager()->clear();
        }
    }

    abstract protected function parseOptions(array $options);
}
