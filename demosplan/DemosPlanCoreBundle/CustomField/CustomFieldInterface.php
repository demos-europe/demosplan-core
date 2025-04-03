<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\CustomField;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\CustomFieldJsonRepository")
 */
interface CustomFieldInterface
{
    /**
     * The format is a unique identifier which is used by
     * the object to json mapping in the database.
     */
    public function getFormat(): string;

    public function getType(): string;

    /**
     * Will be called during database fetch
     * to fill the query object with the stored data.
     */
    public function fromJson(array $json): void;

    /**
     * Will be called during the database store to
     * get the storable data from the query.
     *
     * This *MUST* return all data that is
     * required in `fromJson`.
     */
    public function toJson(): array;

    /**
     * Get the ID of the procedure the query was generated in.
     */
    public function getProcedureId(): string;

    public function getCustomFieldsList(): ?array;
}
