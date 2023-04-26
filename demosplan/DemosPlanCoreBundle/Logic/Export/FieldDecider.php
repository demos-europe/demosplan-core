<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Export;

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\ExportFieldsConfiguration;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\User\User;

class FieldDecider
{
    public const FIELD_ID = 'id';
    public const FIELD_STATEMENT_NAME = 'statementName';
    public const FIELD_CREATION_DATE = 'creationDate';
    public const FIELD_MOVED_TO_PROCEDURE = 'movedToProcedure';
    public const FIELD_PROCEDURE_NAME = 'procedureName';
    public const FIELD_PROCEDURE_PHASE = 'procedurePhase';
    public const FIELD_VOTES_NUM = 'votesNum';
    public const FIELD_USER_STATE = 'userState';
    public const FIELD_USER_GROUP = 'userGroup';
    public const FIELD_USER_ORGANISATION = 'userOrganisation';
    public const FIELD_USER_POSITION = 'userPosition';
    public const FIELD_ORGA_INFO = 'orgaInfo';
    public const FIELD_ORGA_NAME = 'orgaName';
    public const FIELD_ORGA_DEPARTMENT = 'orgaDepartment';
    public const FIELD_SUBMITTER_NAME = 'submitterName';
    public const FIELD_CITIZEN_INFO = 'citizenInfo';
    public const FIELD_ADDRESS = 'address';
    public const FIELD_ADDRESS_HOUSENUMBER = 'houseNumber';
    public const FIELD_ADDRESS_POSTALCODE = 'postalCode';
    public const FIELD_ADDRESS_CITY = 'city';
    public const FIELD_SHOW_IN_PUBLIC_AREA = 'showInPublicArea';
    public const FIELD_DOCUMENT = 'document';
    public const FIELD_PARAGRAPH = 'paragraph';
    public const FIELD_FILES = 'files';
    public const FIELD_ATTACHMENTS = 'attachments';
    public const FIELD_PRIORITY = 'priority';
    public const FIELD_EMAIL = 'email';
    public const FIELD_PHONE_NUMBER = 'phoneNumber';

    public const CITIZEN_ORGA_NAME = User::ANONYMOUS_USER_ORGA_NAME;

    /**
     * @var PermissionsInterface
     */
    private $permissions;

    public function __construct(PermissionsInterface $permissions)
    {
        $this->permissions = $permissions;
    }

    public function isExportable(
        string $field,
        ExportFieldsConfiguration $exportConfig,
        Statement $statement = null,
        array $data = [],
        bool $anonymous = false
    ): bool {
        if (self::FIELD_ID === $field) {
            return $exportConfig->isIdExportable();
        }
        if (self::FIELD_STATEMENT_NAME === $field) {
            return '' !== $statement->getName() && $exportConfig->isStatementNameExportable();
        }
        if (self::FIELD_CREATION_DATE === $field) {
            return $exportConfig->isCreationDateExportable();
        }
        if (self::FIELD_MOVED_TO_PROCEDURE === $field) {
            return null !== $statement->getMovedToProcedureName();
        }
        if (self::FIELD_PROCEDURE_NAME === $field) {
            return null !== $statement->getProcedure() && $exportConfig->isProcedureNameExportable();
        }
        if (self::FIELD_PROCEDURE_PHASE === $field) {
            return null !== $statement->getProcedure() && $exportConfig->isProcedurePhaseExportable();
        }
        if (self::FIELD_VOTES_NUM === $field) {
            return $statement->getVotesNum() > 0 && $exportConfig->isVotesNumExportable();
        }
        if (self::FIELD_USER_STATE === $field) {
            return $this->isValidString($statement->getMeta()->getUserState()) && $exportConfig->isUserStateExportable();
        }
        if (self::FIELD_USER_GROUP === $field) {
            return $this->isValidString($statement->getMeta()->getUserGroup()) && $exportConfig->isUserGroupExportable();
        }
        if (self::FIELD_USER_ORGANISATION === $field) {
            return $this->isValidString($statement->getMeta()->getUserOrganisation())
                && $exportConfig->isUserOrganisationExportable();
        }
        if (self::FIELD_USER_POSITION === $field) {
            return $this->isValidString($statement->getMeta()->getUserPosition())
            && $exportConfig->isUserPositionExportable();
        }
        if (self::FIELD_ORGA_INFO === $field) {
            return !$statement->isSubmittedByCitizen();
        }
        if (self::FIELD_ORGA_NAME === $field) {
            return $exportConfig->isOrgaNameExportable();
        }
        if (self::FIELD_ORGA_DEPARTMENT === $field) {
            return $this->hasArrayKeyInfo($data, 'orgaDepartment') && $exportConfig->isDepartmentNameExportable();
        }
        if (self::FIELD_SUBMITTER_NAME === $field) {
            return false === $anonymous && $exportConfig->isSubmitterNameExportable();
        }
        if (self::FIELD_CITIZEN_INFO === $field) {
            return $statement->isSubmittedByCitizen();
        }
        if (self::FIELD_ADDRESS === $field) {
            return false === $anonymous && $exportConfig->isStreetExportable();
        }
        if (self::FIELD_ADDRESS_HOUSENUMBER === $field) {
            return false === $anonymous && $exportConfig->isStreetNumberExportable();
        }
        if (self::FIELD_ADDRESS_POSTALCODE === $field) {
            return false === $anonymous && $exportConfig->isPostalCodeExportable();
        }
        if (self::FIELD_ADDRESS_CITY === $field) {
            return false === $anonymous && $exportConfig->isCityExportable();
        }
        if (self::FIELD_EMAIL === $field) {
            return false === $anonymous && $this->permissions->hasPermission('field_statement_submitter_email_address')
                && $this->isValidString($statement->getOrgaEmail()) && $exportConfig->isEmailExportable();
        }
        if (self::FIELD_PHONE_NUMBER === $field) {
            return false === $anonymous && $this->hasArrayKeyInfo($data, 'phoneNumber')
                && $exportConfig->isPhoneNumberExportable();
        }
        if (self::FIELD_SHOW_IN_PUBLIC_AREA === $field) {
            return $this->permissions->hasPermission('field_statement_public_allowed')
                && $exportConfig->isShowInPublicAreaExportable();
        }
        if (self::FIELD_DOCUMENT === $field) {
            return null !== $statement->getElement() && $exportConfig->isDocumentExportable();
        }
        if (self::FIELD_PARAGRAPH === $field) {
            return null !== $statement->getParagraph() && $exportConfig->isParagraphExportable();
        }
        if (self::FIELD_FILES === $field) {
            return !empty($statement->getFiles()) && $exportConfig->isFilesExportable();
        }
        if (self::FIELD_ATTACHMENTS === $field) {
            return $exportConfig->isAttachmentsExportable();
        }
        if (self::FIELD_PRIORITY === $field) {
            return !empty($statement->getPriority()) && $exportConfig->isPriorityExportable();
        }

        return false;
    }

    private function isValidString(?string $input): bool
    {
        return null !== $input && '' !== trim($input);
    }

    private function hasArrayKeyInfo(array $data, string $key): bool
    {
        return isset($data[$key]) && $this->isValidString($data[$key]);
    }
}
