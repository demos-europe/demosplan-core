<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\Location;
use demosplan\DemosPlanCoreBundle\Exception\NotYetImplementedException;
use demosplan\DemosPlanCoreBundle\Logic\Map\MapService;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ImmutableObjectInterface;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\Query\ResultSetMapping;
use proj4php\Point;
use proj4php\Proj;
use proj4php\Proj4php;

/**
 * @template-extends CoreRepository<Location>
 */
class LocationRepository extends CoreRepository implements ImmutableObjectInterface
{
    /**
     * Suche eine Stadt an Hand eines Suchstrings.
     *
     * @param string $searchString
     * @param int    $limit
     * @param array  $maxExtent
     *
     * @return Location[]|null
     */
    public function searchCity($searchString, $limit, $maxExtent = null)
    {
        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('l.name', 'l.postcode', 'l.municipalCode', 'l.lat', 'l.lon')
            ->from(Location::class, 'l')
            ->where('l.name LIKE :searchString')
            ->orWhere('l.postcode LIKE :searchString');
        if (null !== $maxExtent && 4 === count($maxExtent)) {
            $point1 = new Point($maxExtent[0], $maxExtent[1]);
            $point2 = new Point($maxExtent[2], $maxExtent[3]);
            $daiumLib = new Proj4php();
            $point1->setProjection(new Proj(MapService::PSEUDO_MERCATOR_PROJECTION_LABEL, $daiumLib));
            $point2->setProjection(new Proj(MapService::PSEUDO_MERCATOR_PROJECTION_LABEL, $daiumLib));

            $targetProjection = new Proj(MapService::WGS84_PROJECTION_LABEL, $daiumLib);
            $point1Transformed = $daiumLib->transform($targetProjection, $point1)->toArray();
            $point2Transformed = $daiumLib->transform($targetProjection, $point2)->toArray();
            $query->andWhere('l.lon > :lonMin')
                ->andWhere('l.lon < :lonMax')
                ->andWhere('l.lat > :latMin')
                ->andWhere('l.lat < :latMax')
                ->setParameter('lonMin', $point1Transformed[0])
                ->setParameter('lonMax', $point2Transformed[0])
                ->setParameter('latMin', $point1Transformed[1])
                ->setParameter('latMax', $point2Transformed[1]);
        }
        $query->setParameter('searchString', $searchString.'%')
            ->addOrderBy('l.postcode', 'asc')
            ->setFirstResult(0)
            ->setMaxResults((int) $limit)
            ->getQuery();
        try {
            return $query->getQuery()->getResult();
        } catch (NoResultException) {
            return null;
        }
    }

    /**
     * Suche eine Stadt an Hand eines Suchstrings.
     *
     * @param int $id
     * @param int $radius
     */
    public function getPostalCodesByRadius($id, $radius): ?array
    {
        // Nutze eine Native Query, weil Doctrine nicht ohne weiteres einen Cross Join ausführen kann
        $rsm = new ResultSetMapping();
        $rsm->addEntityResult(Location::class, 'l');
        $rsm->addScalarResult('postcode', 'postcode');
        $em = $this->getEntityManager();
        $query = $em->createNativeQuery('
            SELECT DISTINCT dest.postcode, dest.name, ACOS( SIN(RADIANS(src.lat)) * SIN(RADIANS(dest.lat)) + COS(RADIANS(src.lat)) * COS(RADIANS(dest.lat))
                    * COS(RADIANS(src.lon) - RADIANS(dest.lon)) ) * 6380 AS distance
                FROM location dest CROSS JOIN location src
                WHERE src.id = :id HAVING distance <= :radius ORDER BY distance', $rsm)
            ->setParameter('id', $id)
            ->setParameter('radius', $radius);
        try {
            return $query->getResult();
        } catch (NoResultException) {
            return null;
        }
    }

    /**
     * @return int|mixed|string
     */
    public function deleteAll()
    {
        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->delete()
            ->from(Location::class, 'l')
            ->getQuery();

        return $query->execute();
    }

    public function get($entityId): never
    {
        throw new NotYetImplementedException('Method not yet implemented.');
    }

    /**
     * @param Location[] $locations
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function addObjects($locations): void
    {
        foreach ($locations as $location) {
            $this->getEntityManager()->persist($location);
        }
        $this->getEntityManager()->flush();
    }

    /**
     * @param CoreEntity $location
     *
     * @return CoreEntity|void
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function addObject($location)
    {
        $this->getEntityManager()->persist($location);
        $this->getEntityManager()->flush();
    }

    /**
     * @param string $ars an "allgemeiner regional-schlüssel"
     *
     * @return array<int,string> list of location names matching this ars
     */
    public function findByArs(string $ars): array
    {
        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('l')
            ->from(Location::class, 'l')
            ->where('l.ars=:ars')
            ->setParameter('ars', $ars)
            ->getQuery();

        return $query->getResult();
    }

    public function findByMunicipalCode(string $municipalCode): array
    {
        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('l')
            ->from(Location::class, 'l')
            ->where('l.municipalCode=:municipalCode')
            ->setParameter('municipalCode', $municipalCode)
            ->getQuery();

        return $query->getResult();
    }
}
