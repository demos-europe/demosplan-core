<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use Cocur\Slugify\Slugify;
use DemosEurope\DemosplanAddon\Contracts\Entities\EntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\Slug;
use demosplan\DemosPlanCoreBundle\Entity\SluggedEntity;
use demosplan\DemosPlanCoreBundle\Exception\DuplicateSlugException;

/**
 * @template TEntity of EntityInterface
 *
 * @template-extends CoreRepository<TEntity>
 */
abstract class SluggedRepository extends CoreRepository
{
    /**
     * If $sluggedEntity already had the slug, we set it to current slug.
     * If the slug exists for another Entity throws DuplicateSlugException.
     * Otherwise adds the slug to the Entity, setting it as current slug.
     * Slug will be automatically persisted when the Entity is persisted.
     */
    public function handleSlugUpdate(SluggedEntity $sluggedEntity, string $newSlug, string $oldSlug = '')
    {
        if ($oldSlug !== $newSlug) {
            /** @var SlugRepository $slugRepository */
            $slugRepository = $this->getEntityManager()->getRepository(Slug::class);
            $slugify = new Slugify();
            $newSlug = $slugify->slugify($newSlug);
            /** @var Slug $newSlugObj */
            $newSlugObj = $slugRepository->findOneBy(['name' => $newSlug]);
            if (null !== $newSlugObj) {
                if ($sluggedEntity->hasSlugString($newSlugObj)) {
                    $sluggedEntity->setCurrentSlug($newSlugObj);
                } else {
                    $e = new DuplicateSlugException("Slug/shortUrl: $newSlug is already in use");
                    $e->setDuplicatedSlug($newSlug);
                    throw $e;
                }
            } else {
                $newSlugObj = new Slug($newSlug);
                $sluggedEntity->addSlug($newSlugObj);
            }
        }
    }
}
