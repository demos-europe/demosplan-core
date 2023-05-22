<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject;

use ArrayIterator;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Used in Statements and Segments export document.
 * Stores information for the orga, to be used in the documents header. Exposes the info in an ordered sequential fashion.
 * This allows the exporter to show the available details with no gaps between them.
 */
class ExportOrgaInfoHeader
{
    /**
     * @var ArrayIterator<int, string>
     */
    private $orgaHeaders;

    public function __construct(Statement $statement, CurrentUserInterface $currentUser, TranslatorInterface $translator)
    {
        $this->orgaHeaders = new ArrayIterator();
        $authorName = $statement->getUserName();
        $authorName = $this->validInfoString($authorName)
            ? $authorName
            : $translator->trans('statement.name_source.unknown');
        $this->orgaHeaders->append($authorName);
        if ($this->validOrgaName($statement->getOName())) {
            $this->orgaHeaders->append($statement->getOName());
        }
        if ($this->validDepartmentName($statement->getDName())) {
            $this->orgaHeaders->append($statement->getDName());
        }
        if ($this->validInfoString($statement->getOrgaStreet())) {
            if ($currentUser->hasPermission('feature_statement_meta_house_number_export')) {
                $this->orgaHeaders->append($statement->getOrgaStreet().' '.$statement->getMeta()->getHouseNumber());
            } else {
                $this->orgaHeaders->append($statement->getOrgaStreet());
            }
        }
        if ($this->validInfoString($statement->getOrgaPostalCode())
            || $this->validInfoString($statement->getOrgaCity())) {
            $this->orgaHeaders->append($statement->getOrgaPostalCode().' '.$statement->getOrgaCity());
        }
    }

    public function getNextHeader(): string
    {
        $nextHeader = '';
        if ($this->orgaHeaders->valid()) {
            $nextHeader = $this->orgaHeaders->current();
            $this->orgaHeaders->next();
        }

        return $nextHeader;
    }

    private function validInfoString(?string $text): bool
    {
        return null !== $text && '' !== trim($text);
    }

    private function validOrgaName(?string $orgaName): bool
    {
        return $this->validInfoString($orgaName) && User::ANONYMOUS_USER_ORGA_NAME !== $orgaName;
    }

    private function validDepartmentName(?string $departmentName): bool
    {
        return $this->validInfoString($departmentName) && User::ANONYMOUS_USER_DEPARTMENT_NAME !== $departmentName;
    }
}
