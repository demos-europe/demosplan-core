<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use DemosEurope\DemosplanAddon\Logic\ApiRequest\FluentRepository;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\FileContainer;
use demosplan\DemosPlanCoreBundle\Entity\Statement\DraftStatementFile;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ObjectInterface;
use Doctrine\ORM\NoResultException;
use Exception;

use function collect;

/**
 * @template-extends FluentRepository<DraftStatementFile>
 */
class DraftStatementFileRepository extends FluentRepository
{
    public function getDraftStatementFilesByFile(File $file): array
    {
        try {
            $query = $this->getEntityManager()
                ->createQueryBuilder()
                ->select('draftStatementFile')
                ->from(DraftStatementFile::class, 'draftStatementFile')
                ->where('draftStatementFile.file = :file')
                ->setParameter('file', $file);

            return $query->getQuery()->getResult();
        } catch (NoResultException $e) {
            $this->logger->error('Get DraftStatementFile failed.', [$e]);

            return [];
        }
    }
}
