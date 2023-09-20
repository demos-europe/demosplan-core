<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject\AssessmentTable;

use demosplan\DemosPlanCoreBundle\ValueObject\ValueObject;

/**
 * Class SubmitterValueObject.
 *
 * @method getEntityId()
 * @method setEntityId(string $id)
 * @method getEntityType()
 * @method setEntityType(string $type)
 * @method getList()
 * @method setList(string $list)
 * @method getSubmitter()
 * @method getLastStatementCountyIds()
 * @method setLastStatementCountyIds(array $ids)
 * @method getLastStatementMunicipalityIds()
 * @method setLastStatementMunicipalityIds(array $ids)
 */
class SubmitterValueObject extends ValueObject
{
    final public const LIST_CITIZEN = 'citizen';
    final public const LIST_INSTITUTION = 'institution';

    /** @var string */
    protected $entityId;

    /** @var string */
    protected $entityType;

    /** @var string */
    protected $list;

    /** @var array */
    protected $submitter;

    /**
     * Holds the countyIds of the latest submitted statement.
     *
     * @var array
     */
    protected $lastStatementCountyIds;

    /**
     * Holds the countyIds of the latest submitted statement.
     *
     * @var array
     */
    protected $lastStatementMunicipalityIds;

    /**
     * @param string $organisation
     * @param string $department
     * @param string $author
     * @param string $postalcode
     * @param string $city
     */
    public function setSubmitter($organisation, $department, $author = '', $postalcode = '', $city = '')
    {
        $this->submitter = [
           'organisation' => $organisation,
           'department'   => $department,
           'name'         => $author,
           'postalCode'   => $postalcode,
           'city'         => $city,
       ];
    }

    public function jsonSerialize(): array
    {
        return [
            'entityId'       => $this->getEntityId(),
            'entityType'     => $this->getEntityType(),
            'list'           => $this->getList(),
            'submitter'      => $this->getSubmitter(),
            'counties'       => $this->getLastStatementCountyIds() ?? [],
            'municipalities' => $this->getLastStatementMunicipalityIds() ?? [],
        ];
    }
}
