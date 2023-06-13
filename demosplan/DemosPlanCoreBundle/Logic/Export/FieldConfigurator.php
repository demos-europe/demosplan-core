<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Export;

use demosplan\DemosPlanCoreBundle\Entity\ExportFieldsConfiguration;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Repository\ExportFieldsConfigurationRepository;
use Exception;

class FieldConfigurator
{
    /**
     * @var ExportFieldsConfigurationRepository
     */
    private $fieldsConfigRepo;

    /**
     * @var EntityPreparator
     */
    private $entityPreparator;

    public function __construct(EntityPreparator $entityPreparator, ExportFieldsConfigurationRepository $fieldsConfigRepo)
    {
        $this->entityPreparator = $entityPreparator;
        $this->fieldsConfigRepo = $fieldsConfigRepo;
    }

    public function add(ExportFieldsConfiguration $fieldsConfiguration): ExportFieldsConfiguration
    {
        return $this->fieldsConfigRepo->addObject($fieldsConfiguration);
    }

    public function update(ExportFieldsConfiguration $fieldsConfiguration): void
    {
        $this->fieldsConfigRepo->updateObject($fieldsConfiguration);
    }

    public function delete(ExportFieldsConfiguration $fieldsConfiguration): void
    {
        $this->fieldsConfigRepo->deleteObject($fieldsConfiguration);
    }

    public function get(string $exportFieldsConfigurationId): ?ExportFieldsConfiguration
    {
        return $this->fieldsConfigRepo->get($exportFieldsConfigurationId);
    }

    /**
     * @throws Exception
     */
    public function copy(string $blueprintId, Procedure $procedure)
    {
        $blueprintConfig = $this->fieldsConfigRepo->getEntityByProcedureId($blueprintId);
        $newProcedureConfig = new ExportFieldsConfiguration($procedure);
        $newProcedureConfig = $this->entityPreparator->copyProperties($blueprintConfig, $newProcedureConfig);

        $this->add($newProcedureConfig);
    }
}
