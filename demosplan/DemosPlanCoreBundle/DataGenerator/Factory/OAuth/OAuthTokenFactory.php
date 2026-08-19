<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\OAuth;

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\UserFactory;
use demosplan\DemosPlanCoreBundle\Entity\User\OAuthToken;
use demosplan\DemosPlanCoreBundle\Repository\OAuthTokenRepository;
use Doctrine\ORM\EntityRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<OAuthToken>
 *
 * @method        OAuthToken|Proxy                              create(array|callable $attributes = [])
 * @method static OAuthToken|Proxy                              createOne(array $attributes = [])
 * @method static OAuthToken|Proxy                              find(object|array|mixed $criteria)
 * @method static OAuthToken|Proxy                              findOrCreate(array $attributes)
 * @method static OAuthToken|Proxy                              first(string $sortedField = 'id')
 * @method static OAuthToken|Proxy                              last(string $sortedField = 'id')
 * @method static OAuthToken|Proxy                              random(array $attributes = [])
 * @method static OAuthToken|Proxy                              randomOrCreate(array $attributes = [])
 * @method static OAuthTokenRepository|ProxyRepositoryDecorator repository()
 * @method static OAuthToken[]|Proxy[]                          all()
 * @method static OAuthToken[]|Proxy[]                          createMany(int $number, array|callable $attributes = [])
 * @method static OAuthToken[]|Proxy[]                          createSequence(iterable|callable $sequence)
 * @method static OAuthToken[]|Proxy[]                          findBy(array $attributes)
 * @method static OAuthToken[]|Proxy[]                          randomRange(int $min, int $max, array $attributes = [])
 * @method static OAuthToken[]|Proxy[]                          randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        OAuthToken&Proxy<OAuthToken> create(array|callable $attributes = [])
 * @phpstan-method static OAuthToken&Proxy<OAuthToken> createOne(array $attributes = [])
 * @phpstan-method static OAuthToken&Proxy<OAuthToken> find(object|array|mixed $criteria)
 * @phpstan-method static OAuthToken&Proxy<OAuthToken> findOrCreate(array $attributes)
 * @phpstan-method static OAuthToken&Proxy<OAuthToken> first(string $sortedField = 'id')
 * @phpstan-method static OAuthToken&Proxy<OAuthToken> last(string $sortedField = 'id')
 * @phpstan-method static OAuthToken&Proxy<OAuthToken> random(array $attributes = [])
 * @phpstan-method static OAuthToken&Proxy<OAuthToken> randomOrCreate(array $attributes = [])
 * @phpstan-method static ProxyRepositoryDecorator<OAuthToken, EntityRepository> repository()
 * @phpstan-method static list<OAuthToken&Proxy<OAuthToken>> all()
 * @phpstan-method static list<OAuthToken&Proxy<OAuthToken>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<OAuthToken&Proxy<OAuthToken>> createSequence(iterable|callable $sequence)
 * @phpstan-method static list<OAuthToken&Proxy<OAuthToken>> findBy(array $attributes)
 * @phpstan-method static list<OAuthToken&Proxy<OAuthToken>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<OAuthToken&Proxy<OAuthToken>> randomSet(int $number, array $attributes = [])
 */
final class OAuthTokenFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return OAuthToken::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'user'     => UserFactory::new(),
            'provider' => 'keycloak_ozg',
        ];
    }
}
