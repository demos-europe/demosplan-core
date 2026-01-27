<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType;

use demosplan\DemosPlanCoreBundle\ApiResources\Transformers\ApiPlatformRelationshipConfig;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\ResourceTypes\AdministratableUserResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\AdminProcedureResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\AgencyEmailAddressResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\AssignableUserResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\BoilerplateGroupResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\BoilerplateResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\BrandingResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\ClusterStatementResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\ConsultationTokenResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\ContextualHelpResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\CountyResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\CustomerContactResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\CustomerLoginSupportContactResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\CustomerResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\CustomFieldResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\DepartmentResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\EmailAddressResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\EmailResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\FaqCategoryResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\FaqResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\FileResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\FinalMailReportEntryResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\GeneralReportEntryResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\GenericStatementAttachmentResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\GisLayerCategoryResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\GisLayerResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\GlobalNewsCategoryResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\GlobalNewsResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\HashedQueryResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\HeadStatementResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\InstitutionLocationContactResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\InstitutionTagCategoryResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\InstitutionTagResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\InvitableInstitutionResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\InvitablePublicAgencyResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\InvitationReportEntryResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\MasterToebResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\MunicipalityResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\OrgaResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\OrgaStatusInCustomerResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\OrgaTypeResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\OriginalStatementResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\ParagraphResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\ParagraphVersionResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\PlaceResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\PlanningDocumentCategoryDetailsResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\PlanningDocumentCategoryResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\PriorityAreaResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\ProcedureBehaviorDefinitionResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\ProcedureMapSettingResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\ProcedureNewsResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\ProcedurePhaseResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\ProcedureResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\ProcedureTemplateResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\ProcedureTypeResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\ProcedureUiDefinitionResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\PublicPhaseReportEntryResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\RegisterInvitationReportEntryResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\ReportEntryResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\RoleResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\SegmentCommentResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\SignLanguageOverviewVideoResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\SimilarStatementSubmitterResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\SingleDocumentResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\SlugResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\SourceStatementAttachmentResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementFieldDefinitionResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementFormDefinitionResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementFragmentResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementFragmentsElementsResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementMetaResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementReportEntryResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementSegmentResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementVoteResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\TagResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\TagTopicResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\UserFilterSetResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\UserResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\UserRoleInCustomerResourceType;

/**
 * WARNING: THIS CLASS IS AUTOGENERATED.
 * MANUAL CHANGES WILL BE LOST ON RE-GENERATION.
 */
class ResourceTypeStore
{
    public function __construct(protected AdminProcedureResourceType $adminProcedureResourceType, protected AdministratableUserResourceType $administratableUserResourceType, protected AgencyEmailAddressResourceType $agencyEmailAddressResourceType, protected AssignableUserResourceType $assignableUserResourceType, protected BoilerplateGroupResourceType $boilerplateGroupResourceType, protected BoilerplateResourceType $boilerplateResourceType, protected BrandingResourceType $brandingResourceType, protected ClusterStatementResourceType $clusterStatementResourceType, protected ConsultationTokenResourceType $consultationTokenResourceType, protected ContextualHelpResourceType $contextualHelpResourceType, protected CountyResourceType $countyResourceType, protected CustomerContactResourceType $customerContactResourceType, protected CustomerLoginSupportContactResourceType $customerLoginSupportContactResourceType, protected CustomerResourceType $customerResourceType, protected DepartmentResourceType $departmentResourceType, protected EmailAddressResourceType $emailAddressResourceType, protected EmailResourceType $emailResourceType, protected FaqCategoryResourceType $faqCategoryResourceType, protected FaqResourceType $faqResourceType, protected FileResourceType $fileResourceType, protected FinalMailReportEntryResourceType $finalMailReportEntryResourceType, protected GeneralReportEntryResourceType $generalReportEntryResourceType, protected GenericStatementAttachmentResourceType $genericStatementAttachmentResourceType, protected GisLayerCategoryResourceType $gisLayerCategoryResourceType, protected GisLayerResourceType $gisLayerResourceType, protected GlobalNewsCategoryResourceType $globalNewsCategoryResourceType, protected GlobalNewsResourceType $globalNewsResourceType, protected HashedQueryResourceType $hashedQueryResourceType, protected HeadStatementResourceType $headStatementResourceType, protected InstitutionLocationContactResourceType $institutionLocationContactResourceType, protected InstitutionTagCategoryResourceType $institutionTagCategoryResourceType, protected InstitutionTagResourceType $institutionTagResourceType, protected InvitableInstitutionResourceType $invitableInstitutionResourceType, protected InvitablePublicAgencyResourceType $invitablePublicAgencyResourceType, protected InvitationReportEntryResourceType $invitationReportEntryResourceType, protected MasterToebResourceType $masterToebResourceType, protected MunicipalityResourceType $municipalityResourceType, protected OrgaResourceType $orgaResourceType, protected OrgaStatusInCustomerResourceType $orgaStatusInCustomerResourceType, protected OrgaTypeResourceType $orgaTypeResourceType, protected OriginalStatementResourceType $originalStatementResourceType, protected ParagraphResourceType $paragraphResourceType, protected ParagraphVersionResourceType $paragraphVersionResourceType, protected PlaceResourceType $placeResourceType, protected PlanningDocumentCategoryResourceType $planningDocumentCategoryResourceType, protected PlanningDocumentCategoryDetailsResourceType $planningDocumentCategoryDetailsResourceType, protected PriorityAreaResourceType $priorityAreaResourceType, protected ProcedureBehaviorDefinitionResourceType $procedureBehaviorDefinitionResourceType, protected ProcedureMapSettingResourceType $procedureMapSettingResourceType, protected ProcedureNewsResourceType $procedureNewsResourceType, protected ProcedurePhaseResourceType $procedurePhaseResourceType, protected ProcedureResourceType $procedureResourceType, protected ProcedureTemplateResourceType $procedureTemplateResourceType, protected ProcedureTypeResourceType $procedureTypeResourceType, protected ProcedureUiDefinitionResourceType $procedureUiDefinitionResourceType, protected PublicPhaseReportEntryResourceType $publicPhaseReportEntryResourceType, protected RegisterInvitationReportEntryResourceType $registerInvitationReportEntryResourceType, protected ReportEntryResourceType $reportEntryResourceType, protected RoleResourceType $roleResourceType, protected SegmentCommentResourceType $segmentCommentResourceType, protected SignLanguageOverviewVideoResourceType $signLanguageOverviewVideoResourceType, protected SimilarStatementSubmitterResourceType $similarStatementSubmitterResourceType, protected SingleDocumentResourceType $singleDocumentResourceType, protected SlugResourceType $slugResourceType, protected SourceStatementAttachmentResourceType $sourceStatementAttachmentResourceType, protected StatementFieldDefinitionResourceType $statementFieldDefinitionResourceType, protected StatementFormDefinitionResourceType $statementFormDefinitionResourceType, protected StatementFragmentResourceType $statementFragmentResourceType, protected StatementFragmentsElementsResourceType $statementFragmentsElementsResourceType, protected StatementMetaResourceType $statementMetaResourceType, protected StatementReportEntryResourceType $statementReportEntryResourceType, protected StatementResourceType $statementResourceType, protected StatementSegmentResourceType $statementSegmentResourceType, protected StatementVoteResourceType $statementVoteResourceType, protected TagResourceType $tagResourceType, protected TagTopicResourceType $tagTopicResourceType, protected UserFilterSetResourceType $userFilterSetResourceType, protected UserResourceType $userResourceType, protected UserRoleInCustomerResourceType $userRoleInCustomerResourceType, protected CustomFieldResourceType $customFieldResourceType)
    {
    }

    public function getAdminProcedureResourceType(): AdminProcedureResourceType
    {
        return $this->adminProcedureResourceType;
    }

    public function getAdministratableUserResourceType(): AdministratableUserResourceType
    {
        return $this->administratableUserResourceType;
    }

    public function getAgencyEmailAddressResourceType(): AgencyEmailAddressResourceType
    {
        return $this->agencyEmailAddressResourceType;
    }

    public function getAssignableUserResourceType(): AssignableUserResourceType
    {
        return $this->assignableUserResourceType;
    }

    public function getBoilerplateGroupResourceType(): BoilerplateGroupResourceType
    {
        return $this->boilerplateGroupResourceType;
    }

    public function getBoilerplateResourceType(): BoilerplateResourceType
    {
        return $this->boilerplateResourceType;
    }

    public function getBrandingResourceType(): BrandingResourceType
    {
        return $this->brandingResourceType;
    }

    /**
     * Get Claim relationship configuration for API Platform.
     *
     * Returns ApiPlatformRelationshipConfig instead of ClaimResourceType
     * to enable gradual migration to API Platform.
     *
     * This config satisfies EDT's type requirements while allowing
     * ExtendedDynamicTransformer to use API Platform ClaimResource
     * for actual transformation.
     *
     * @return ApiPlatformRelationshipConfig Configuration for Claim relationship
     */
    public function getClaimResourceType(): ApiPlatformRelationshipConfig
    {
        return new ApiPlatformRelationshipConfig('Claim', User::class);
    }

    public function getClusterStatementResourceType(): ClusterStatementResourceType
    {
        return $this->clusterStatementResourceType;
    }

    public function getConsultationTokenResourceType(): ConsultationTokenResourceType
    {
        return $this->consultationTokenResourceType;
    }

    public function getContextualHelpResourceType(): ContextualHelpResourceType
    {
        return $this->contextualHelpResourceType;
    }

    public function getCountyResourceType(): CountyResourceType
    {
        return $this->countyResourceType;
    }

    public function getCustomerContactResourceType(): CustomerContactResourceType
    {
        return $this->customerContactResourceType;
    }

    public function getCustomerLoginSupportContactResourceType(): CustomerLoginSupportContactResourceType
    {
        return $this->customerLoginSupportContactResourceType;
    }

    public function getCustomerResourceType(): CustomerResourceType
    {
        return $this->customerResourceType;
    }

    public function getDepartmentResourceType(): DepartmentResourceType
    {
        return $this->departmentResourceType;
    }

    public function getEmailAddressResourceType(): EmailAddressResourceType
    {
        return $this->emailAddressResourceType;
    }

    public function getEmailResourceType(): EmailResourceType
    {
        return $this->emailResourceType;
    }

    public function getFaqCategoryResourceType(): FaqCategoryResourceType
    {
        return $this->faqCategoryResourceType;
    }

    public function getFaqResourceType(): FaqResourceType
    {
        return $this->faqResourceType;
    }

    public function getFileResourceType(): FileResourceType
    {
        return $this->fileResourceType;
    }

    public function getFinalMailReportEntryResourceType(): FinalMailReportEntryResourceType
    {
        return $this->finalMailReportEntryResourceType;
    }

    public function getGeneralReportEntryResourceType(): GeneralReportEntryResourceType
    {
        return $this->generalReportEntryResourceType;
    }

    public function getGenericStatementAttachmentResourceType(): GenericStatementAttachmentResourceType
    {
        return $this->genericStatementAttachmentResourceType;
    }

    public function getGisLayerCategoryResourceType(): GisLayerCategoryResourceType
    {
        return $this->gisLayerCategoryResourceType;
    }

    public function getGisLayerResourceType(): GisLayerResourceType
    {
        return $this->gisLayerResourceType;
    }

    public function getGlobalNewsCategoryResourceType(): GlobalNewsCategoryResourceType
    {
        return $this->globalNewsCategoryResourceType;
    }

    public function getGlobalNewsResourceType(): GlobalNewsResourceType
    {
        return $this->globalNewsResourceType;
    }

    public function getHashedQueryResourceType(): HashedQueryResourceType
    {
        return $this->hashedQueryResourceType;
    }

    public function getHeadStatementResourceType(): HeadStatementResourceType
    {
        return $this->headStatementResourceType;
    }

    public function getInstitutionLocationContactResourceType(): InstitutionLocationContactResourceType
    {
        return $this->institutionLocationContactResourceType;
    }

    public function getInstitutionTagCategoryResourceType(): InstitutionTagCategoryResourceType
    {
        return $this->institutionTagCategoryResourceType;
    }

    public function getInstitutionTagResourceType(): InstitutionTagResourceType
    {
        return $this->institutionTagResourceType;
    }

    public function getInvitableInstitutionResourceType(): InvitableInstitutionResourceType
    {
        return $this->invitableInstitutionResourceType;
    }

    public function getInvitablePublicAgencyResourceType(): InvitablePublicAgencyResourceType
    {
        return $this->invitablePublicAgencyResourceType;
    }

    public function getInvitationReportEntryResourceType(): InvitationReportEntryResourceType
    {
        return $this->invitationReportEntryResourceType;
    }

    public function getMasterToebResourceType(): MasterToebResourceType
    {
        return $this->masterToebResourceType;
    }

    public function getMunicipalityResourceType(): MunicipalityResourceType
    {
        return $this->municipalityResourceType;
    }

    public function getOrgaResourceType(): OrgaResourceType
    {
        return $this->orgaResourceType;
    }

    public function getOrgaStatusInCustomerResourceType(): OrgaStatusInCustomerResourceType
    {
        return $this->orgaStatusInCustomerResourceType;
    }

    public function getOrgaTypeResourceType(): OrgaTypeResourceType
    {
        return $this->orgaTypeResourceType;
    }

    public function getOriginalStatementResourceType(): OriginalStatementResourceType
    {
        return $this->originalStatementResourceType;
    }

    public function getParagraphResourceType(): ParagraphResourceType
    {
        return $this->paragraphResourceType;
    }

    public function getParagraphVersionResourceType(): ParagraphVersionResourceType
    {
        return $this->paragraphVersionResourceType;
    }

    public function getPlaceResourceType(): PlaceResourceType
    {
        return $this->placeResourceType;
    }

    public function getPlanningDocumentCategoryResourceType(): PlanningDocumentCategoryResourceType
    {
        return $this->planningDocumentCategoryResourceType;
    }

    public function getPlanningDocumentCategoryDetailsResourceType(): PlanningDocumentCategoryDetailsResourceType
    {
        return $this->planningDocumentCategoryDetailsResourceType;
    }

    public function getPriorityAreaResourceType(): PriorityAreaResourceType
    {
        return $this->priorityAreaResourceType;
    }

    public function getProcedureBehaviorDefinitionResourceType(): ProcedureBehaviorDefinitionResourceType
    {
        return $this->procedureBehaviorDefinitionResourceType;
    }

    public function getProcedureMapSettingResourceType(): ProcedureMapSettingResourceType
    {
        return $this->procedureMapSettingResourceType;
    }

    public function getProcedureNewsResourceType(): ProcedureNewsResourceType
    {
        return $this->procedureNewsResourceType;
    }

    public function getProcedurePhaseResourceType(): ProcedurePhaseResourceType
    {
        return $this->procedurePhaseResourceType;
    }

    public function getProcedureResourceType(): ProcedureResourceType
    {
        return $this->procedureResourceType;
    }

    public function getProcedureTemplateResourceType(): ProcedureTemplateResourceType
    {
        return $this->procedureTemplateResourceType;
    }

    public function getProcedureTypeResourceType(): ProcedureTypeResourceType
    {
        return $this->procedureTypeResourceType;
    }

    public function getProcedureUiDefinitionResourceType(): ProcedureUiDefinitionResourceType
    {
        return $this->procedureUiDefinitionResourceType;
    }

    public function getPublicPhaseReportEntryResourceType(): PublicPhaseReportEntryResourceType
    {
        return $this->publicPhaseReportEntryResourceType;
    }

    public function getRegisterInvitationReportEntryResourceType(): RegisterInvitationReportEntryResourceType
    {
        return $this->registerInvitationReportEntryResourceType;
    }

    public function getReportEntryResourceType(): ReportEntryResourceType
    {
        return $this->reportEntryResourceType;
    }

    public function getRoleResourceType(): RoleResourceType
    {
        return $this->roleResourceType;
    }

    public function getSegmentCommentResourceType(): SegmentCommentResourceType
    {
        return $this->segmentCommentResourceType;
    }

    public function getSignLanguageOverviewVideoResourceType(): SignLanguageOverviewVideoResourceType
    {
        return $this->signLanguageOverviewVideoResourceType;
    }

    public function getSimilarStatementSubmitterResourceType(): SimilarStatementSubmitterResourceType
    {
        return $this->similarStatementSubmitterResourceType;
    }

    public function getSingleDocumentResourceType(): SingleDocumentResourceType
    {
        return $this->singleDocumentResourceType;
    }

    public function getSlugResourceType(): SlugResourceType
    {
        return $this->slugResourceType;
    }

    public function getSourceStatementAttachmentResourceType(): SourceStatementAttachmentResourceType
    {
        return $this->sourceStatementAttachmentResourceType;
    }

    public function getStatementFieldDefinitionResourceType(): StatementFieldDefinitionResourceType
    {
        return $this->statementFieldDefinitionResourceType;
    }

    public function getStatementFormDefinitionResourceType(): StatementFormDefinitionResourceType
    {
        return $this->statementFormDefinitionResourceType;
    }

    public function getStatementFragmentResourceType(): StatementFragmentResourceType
    {
        return $this->statementFragmentResourceType;
    }

    public function getStatementFragmentsElementsResourceType(): StatementFragmentsElementsResourceType
    {
        return $this->statementFragmentsElementsResourceType;
    }

    public function getStatementMetaResourceType(): StatementMetaResourceType
    {
        return $this->statementMetaResourceType;
    }

    public function getStatementReportEntryResourceType(): StatementReportEntryResourceType
    {
        return $this->statementReportEntryResourceType;
    }

    public function getStatementResourceType(): StatementResourceType
    {
        return $this->statementResourceType;
    }

    public function getStatementSegmentResourceType(): StatementSegmentResourceType
    {
        return $this->statementSegmentResourceType;
    }

    public function getStatementVoteResourceType(): StatementVoteResourceType
    {
        return $this->statementVoteResourceType;
    }

    public function getTagResourceType(): TagResourceType
    {
        return $this->tagResourceType;
    }

    public function getTagTopicResourceType(): TagTopicResourceType
    {
        return $this->tagTopicResourceType;
    }

    public function getUserFilterSetResourceType(): UserFilterSetResourceType
    {
        return $this->userFilterSetResourceType;
    }

    public function getUserResourceType(): UserResourceType
    {
        return $this->userResourceType;
    }

    public function getUserRoleInCustomerResourceType(): UserRoleInCustomerResourceType
    {
        return $this->userRoleInCustomerResourceType;
    }

    public function getCustomFieldResourceType(): CustomFieldResourceType
    {
        return $this->customFieldResourceType;
    }
}
