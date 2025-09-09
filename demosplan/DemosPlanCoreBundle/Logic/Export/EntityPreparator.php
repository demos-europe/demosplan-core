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
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * Takes care of updating ExportFieldsConfiguration by enabling or disabling the corresponding fields.
 */
class EntityPreparator
{
    /**
     * @param array<string, bool> $exportableFields
     */
    public function prepareEntity(array $exportableFields, ExportFieldsConfiguration $exportConfig): void
    {
        // Set all properties to false, because we only get `true` values from FE
        $exportConfig->initializeAllProperties(false);

        $selectFields = array_keys($exportableFields);
        foreach ($selectFields as $fieldName) {
            switch ($fieldName) {
                case 'r_extern_id':
                    $exportConfig->setIdExportable(true);
                    break;
                case 'r_statement_name':
                    $exportConfig->setStatementNameExportable(true);
                    break;
                case 'r_submitted_date':
                    $exportConfig->setCreationDateExportable(true);
                    break;
                case 'r_orga_name':
                    $exportConfig->setOrgaNameExportable(true);
                    break;
                case 'r_procedure_name':
                    $exportConfig->setProcedureNameExportable(true);
                    break;
                case 'r_phase':
                    $exportConfig->setProcedurePhaseExportable(true);
                    break;
                case 'r_votes':
                    $exportConfig->setVotesNumExportable(true);
                    break;
                case 'r_userState':
                    $exportConfig->setUserStateExportable(true);
                    break;
                case 'r_userGroup':
                    $exportConfig->setUserGroupExportable(true);
                    break;
                case 'r_userOrganisation':
                    $exportConfig->setUserOrganisationExportable(true);
                    break;
                case 'r_userPosition':
                    $exportConfig->setUserPositionExportable(true);
                    break;
                case 'institution':
                    $exportConfig->setInstitutionExportable(true);
                    break;
                case 'r_public_participation':
                    $exportConfig->setPublicParticipationExportable(true);
                    break;
                case 'r_departmentName':
                    $exportConfig->setDepartmentNameExportable(true);
                    break;
                case 'r_author_name':
                    $exportConfig->setSubmitterNameExportable(true);
                    break;
                case 'r_public_show':
                    $exportConfig->setShowInPublicAreaExportable(true);
                    break;
                case 'r_element':
                    $exportConfig->setDocumentExportable(true);
                    break;
                case 'r_paragraph':
                    $exportConfig->setParagraphExportable(true);
                    break;
                case 'r_document':
                    $exportConfig->setFilesExportable(true);
                    break;
                case 'r_attachment':
                    $exportConfig->setAttachmentsExportable(true);
                    break;
                case 'r_priority':
                    $exportConfig->setPriorityExportable(true);
                    break;
                case 'r_submitterEmailAddress':
                    $exportConfig->setEmailExportable(true);
                    break;
                case 'r_phone':
                    $exportConfig->setPhoneNumberExportable(true);
                    break;
                case 'r_orga_street':
                    $exportConfig->setStreetExportable(true);
                    break;
                case 'r_houseNumber':
                    $exportConfig->setStreetNumberExportable(true);
                    break;
                case 'r_orga_postalcode':
                    $exportConfig->setPostalCodeExportable(true);
                    break;
                case 'r_orga_city':
                    $exportConfig->setCityExportable(true);
                    break;
                case 'r_institutionOrCitizen':
                    $exportConfig->setInstitutionOrCitizenExportable(true);
                    break;
            }
        }
    }

    public function copyProperties(ExportFieldsConfiguration $sourceConfig, ExportFieldsConfiguration $targetConfig): ExportFieldsConfiguration
    {
        $targetConfig->setIdExportable($sourceConfig->isIdExportable());
        $targetConfig->setStatementNameExportable($sourceConfig->isStatementNameExportable());
        $targetConfig->setCreationDateExportable($sourceConfig->isCreationDateExportable());
        $targetConfig->setProcedureNameExportable($sourceConfig->isProcedureNameExportable());
        $targetConfig->setProcedurePhaseExportable($sourceConfig->isProcedurePhaseExportable());
        $targetConfig->setVotesNumExportable($sourceConfig->isVotesNumExportable());
        $targetConfig->setUserGroupExportable($sourceConfig->isUserGroupExportable());
        $targetConfig->setUserOrganisationExportable($sourceConfig->isUserOrganisationExportable());
        $targetConfig->setUserPositionExportable($sourceConfig->isUserPositionExportable());
        $targetConfig->setUserStateExportable($sourceConfig->isUserStateExportable());
        $targetConfig->setInstitutionExportable($sourceConfig->isInstitutionExportable());
        $targetConfig->setPublicParticipationExportable($sourceConfig->isPublicParticipationExportable());
        $targetConfig->setOrgaNameExportable($sourceConfig->isOrgaNameExportable());
        $targetConfig->setDepartmentNameExportable($sourceConfig->isDepartmentNameExportable());
        $targetConfig->setSubmitterNameExportable($sourceConfig->isSubmitterNameExportable());
        $targetConfig->setShowInPublicAreaExportable($sourceConfig->isShowInPublicAreaExportable());
        $targetConfig->setDocumentExportable($sourceConfig->isDocumentExportable());
        $targetConfig->setParagraphExportable($sourceConfig->isParagraphExportable());
        $targetConfig->setFilesExportable($sourceConfig->isFilesExportable());
        $targetConfig->setAttachmentsExportable($sourceConfig->isAttachmentsExportable());
        $targetConfig->setPriorityExportable($sourceConfig->isPriorityExportable());
        $targetConfig->setEmailExportable($sourceConfig->isEmailExportable());
        $targetConfig->setPhoneNumberExportable($sourceConfig->isPhoneNumberExportable());
        $targetConfig->setStreetExportable($sourceConfig->isStreetExportable());
        $targetConfig->setStreetNumberExportable($sourceConfig->isStreetNumberExportable());
        $targetConfig->setPostalCodeExportable($sourceConfig->isPostalCodeExportable());
        $targetConfig->setCityExportable($sourceConfig->isCityExportable());
        $targetConfig->setInstitutionOrCitizenExportable($sourceConfig->isInstitutionOrCitizenExportable());

        return $targetConfig;
    }

    /**
     * @param string|null $encode Can be 'json' or 'xml' or be left out to get the unencoded array
     *
     * @throws ExceptionInterface
     */
    public function convert(ExportFieldsConfiguration $exportConfig, string $encode = null): array
    {
        $encoders = [new XmlEncoder(), new JsonEncoder()];
        $normalizers = [new ObjectNormalizer()];

        $serializer = new Serializer($normalizers, $encoders);

        return $serializer->normalize(
            $exportConfig,
            $encode,
            ['ignored_attributes' => ['procedure', 'creationDate', 'modificationDate']]);
    }
}
