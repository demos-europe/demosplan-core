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

use demosplan\DemosPlanCoreBundle\ResourceTypes\AdministratableUserResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\AdminProcedureResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\AgencyEmailAddressResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\AssignableUserResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\BoilerplateGroupResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\BoilerplateResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\BrandingResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\ClaimResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\ClusterStatementResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\ConsultationTokenResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\ContextualHelpResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\CountyResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\CustomerContactResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\CustomerLoginSupportContactResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\CustomerResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\DepartmentResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\EmailAddressResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\EmailResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\FaqCategoryResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\FaqResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\FileResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\FinalMailReportEntryResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\GeneralReportEntryResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\GisLayerCategoryResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\GisLayerResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\GlobalNewsCategoryResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\GlobalNewsResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\HashedQueryResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\HeadStatementResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\InstitutionLocationContactResourceType;
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
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementAttachmentResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementFieldDefinitionResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementFormDefinitionResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementFragmentResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementFragmentsElementsResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementMetaResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementReportEntryResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementSegmentResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementVoteResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\SurveyResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\SurveyVoteResourceType;
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
    protected AdminProcedureResourceType $adminProcedureResourceType;

    protected AdministratableUserResourceType $administratableUserResourceType;

    protected AgencyEmailAddressResourceType $agencyEmailAddressResourceType;

    protected AssignableUserResourceType $assignableUserResourceType;

    protected BoilerplateGroupResourceType $boilerplateGroupResourceType;

    protected BoilerplateResourceType $boilerplateResourceType;

    protected BrandingResourceType $brandingResourceType;

    protected ClaimResourceType $claimResourceType;

    protected ClusterStatementResourceType $clusterStatementResourceType;

    protected ConsultationTokenResourceType $consultationTokenResourceType;

    protected ContextualHelpResourceType $contextualHelpResourceType;

    protected CountyResourceType $countyResourceType;

    protected CustomerContactResourceType $customerContactResourceType;

    protected CustomerLoginSupportContactResourceType $customerLoginSupportContactResourceType;

    protected CustomerResourceType $customerResourceType;

    protected DepartmentResourceType $departmentResourceType;

    protected EmailAddressResourceType $emailAddressResourceType;

    protected EmailResourceType $emailResourceType;

    protected FaqCategoryResourceType $faqCategoryResourceType;

    protected FaqResourceType $faqResourceType;

    protected FileResourceType $fileResourceType;

    protected FinalMailReportEntryResourceType $finalMailReportEntryResourceType;

    protected GeneralReportEntryResourceType $generalReportEntryResourceType;

    protected GisLayerCategoryResourceType $gisLayerCategoryResourceType;

    protected GisLayerResourceType $gisLayerResourceType;

    protected GlobalNewsCategoryResourceType $globalNewsCategoryResourceType;

    protected GlobalNewsResourceType $globalNewsResourceType;

    protected HashedQueryResourceType $hashedQueryResourceType;

    protected HeadStatementResourceType $headStatementResourceType;

    protected InstitutionLocationContactResourceType $institutionLocationContactResourceType;

    protected InstitutionTagResourceType $institutionTagResourceType;

    protected InvitableInstitutionResourceType $invitableInstitutionResourceType;

    protected InvitablePublicAgencyResourceType $invitablePublicAgencyResourceType;

    protected InvitationReportEntryResourceType $invitationReportEntryResourceType;

    protected MasterToebResourceType $masterToebResourceType;

    protected MunicipalityResourceType $municipalityResourceType;

    protected OrgaResourceType $orgaResourceType;

    protected OrgaStatusInCustomerResourceType $orgaStatusInCustomerResourceType;

    protected OrgaTypeResourceType $orgaTypeResourceType;

    protected OriginalStatementResourceType $originalStatementResourceType;

    protected ParagraphResourceType $paragraphResourceType;

    protected ParagraphVersionResourceType $paragraphVersionResourceType;

    protected PlaceResourceType $placeResourceType;

    protected PlanningDocumentCategoryResourceType $planningDocumentCategoryResourceType;

    protected PlanningDocumentCategoryDetailsResourceType $planningDocumentCategoryDetailsResourceType;

    protected PriorityAreaResourceType $priorityAreaResourceType;

    protected ProcedureBehaviorDefinitionResourceType $procedureBehaviorDefinitionResourceType;

    protected ProcedureMapSettingResourceType $procedureMapSettingResourceType;

    protected ProcedureNewsResourceType $procedureNewsResourceType;

    protected ProcedurePhaseResourceType $procedurePhaseResourceType;

    protected ProcedureResourceType $procedureResourceType;

    protected ProcedureTemplateResourceType $procedureTemplateResourceType;

    protected ProcedureTypeResourceType $procedureTypeResourceType;

    protected ProcedureUiDefinitionResourceType $procedureUiDefinitionResourceType;

    protected PublicPhaseReportEntryResourceType $publicPhaseReportEntryResourceType;

    protected RegisterInvitationReportEntryResourceType $registerInvitationReportEntryResourceType;

    protected ReportEntryResourceType $reportEntryResourceType;

    protected RoleResourceType $roleResourceType;

    protected SegmentCommentResourceType $segmentCommentResourceType;

    protected SignLanguageOverviewVideoResourceType $signLanguageOverviewVideoResourceType;

    protected SimilarStatementSubmitterResourceType $similarStatementSubmitterResourceType;

    protected SingleDocumentResourceType $singleDocumentResourceType;

    protected SlugResourceType $slugResourceType;

    protected StatementAttachmentResourceType $statementAttachmentResourceType;

    protected StatementFieldDefinitionResourceType $statementFieldDefinitionResourceType;

    protected StatementFormDefinitionResourceType $statementFormDefinitionResourceType;

    protected StatementFragmentResourceType $statementFragmentResourceType;

    protected StatementFragmentsElementsResourceType $statementFragmentsElementsResourceType;

    protected StatementMetaResourceType $statementMetaResourceType;

    protected StatementReportEntryResourceType $statementReportEntryResourceType;

    protected StatementResourceType $statementResourceType;

    protected StatementSegmentResourceType $statementSegmentResourceType;

    protected StatementVoteResourceType $statementVoteResourceType;

    protected SurveyResourceType $surveyResourceType;

    protected SurveyVoteResourceType $surveyVoteResourceType;

    protected TagResourceType $tagResourceType;

    protected TagTopicResourceType $tagTopicResourceType;

    protected UserFilterSetResourceType $userFilterSetResourceType;

    protected UserResourceType $userResourceType;

    protected UserRoleInCustomerResourceType $userRoleInCustomerResourceType;

    public function __construct(
        AdminProcedureResourceType       $adminProcedureResourceType,
        AdministratableUserResourceType  $administratableUserResourceType,
        AgencyEmailAddressResourceType   $agencyEmailAddressResourceType,
        AssignableUserResourceType       $assignableUserResourceType,
        BoilerplateGroupResourceType     $boilerplateGroupResourceType,
        BoilerplateResourceType          $boilerplateResourceType,
        BrandingResourceType             $brandingResourceType,
        ClaimResourceType                $claimResourceType,
        ClusterStatementResourceType     $clusterStatementResourceType,
        ConsultationTokenResourceType    $consultationTokenResourceType,
        ContextualHelpResourceType $contextualHelpResourceType,
        CountyResourceType $countyResourceType,
        CustomerContactResourceType $customerContactResourceType,
        CustomerLoginSupportContactResourceType $customerLoginSupportContactResourceType,
        CustomerResourceType $customerResourceType,
        DepartmentResourceType $departmentResourceType,
        EmailAddressResourceType $emailAddressResourceType,
        EmailResourceType $emailResourceType,
        FaqCategoryResourceType $faqCategoryResourceType,
        FaqResourceType $faqResourceType,
        FileResourceType $fileResourceType,
        FinalMailReportEntryResourceType $finalMailReportEntryResourceType,
        GeneralReportEntryResourceType $generalReportEntryResourceType,
        GisLayerCategoryResourceType $gisLayerCategoryResourceType,
        GisLayerResourceType $gisLayerResourceType,
        GlobalNewsCategoryResourceType $globalNewsCategoryResourceType,
        GlobalNewsResourceType $globalNewsResourceType,
        HashedQueryResourceType $hashedQueryResourceType,
        HeadStatementResourceType $headStatementResourceType,
        InstitutionLocationContactResourceType $institutionLocationContactResourceType,
        InstitutionTagResourceType $institutionTagResourceType,
        InvitableInstitutionResourceType $invitableInstitutionResourceType,
        InvitablePublicAgencyResourceType $invitablePublicAgencyResourceType,
        InvitationReportEntryResourceType $invitationReportEntryResourceType,
        MasterToebResourceType $masterToebResourceType,
        MunicipalityResourceType $municipalityResourceType,
        OrgaResourceType $orgaResourceType,
        OrgaStatusInCustomerResourceType $orgaStatusInCustomerResourceType,
        OrgaTypeResourceType $orgaTypeResourceType,
        OriginalStatementResourceType $originalStatementResourceType,
        ParagraphResourceType $paragraphResourceType,
        ParagraphVersionResourceType $paragraphVersionResourceType,
        PlaceResourceType $placeResourceType,
        PlanningDocumentCategoryResourceType $planningDocumentCategoryResourceType,
        PlanningDocumentCategoryDetailsResourceType $planningDocumentCategoryDetailsResourceType,
        PriorityAreaResourceType $priorityAreaResourceType,
        ProcedureBehaviorDefinitionResourceType $procedureBehaviorDefinitionResourceType,
        ProcedureMapSettingResourceType $procedureMapSettingResourceType,
        ProcedureNewsResourceType $procedureNewsResourceType,
        ProcedurePhaseResourceType $procedurePhaseResourceType,
        ProcedureResourceType $procedureResourceType,
        ProcedureTemplateResourceType $procedureTemplateResourceType,
        ProcedureTypeResourceType $procedureTypeResourceType,
        ProcedureUiDefinitionResourceType $procedureUiDefinitionResourceType,
        PublicPhaseReportEntryResourceType $publicPhaseReportEntryResourceType,
        RegisterInvitationReportEntryResourceType $registerInvitationReportEntryResourceType,
        ReportEntryResourceType $reportEntryResourceType,
        RoleResourceType $roleResourceType,
        SegmentCommentResourceType $segmentCommentResourceType,
        SignLanguageOverviewVideoResourceType $signLanguageOverviewVideoResourceType,
        SimilarStatementSubmitterResourceType $similarStatementSubmitterResourceType,
        SingleDocumentResourceType $singleDocumentResourceType,
        SlugResourceType $slugResourceType,
        StatementAttachmentResourceType $statementAttachmentResourceType,
        StatementFieldDefinitionResourceType $statementFieldDefinitionResourceType,
        StatementFormDefinitionResourceType $statementFormDefinitionResourceType,
        StatementFragmentResourceType $statementFragmentResourceType,
        StatementFragmentsElementsResourceType $statementFragmentsElementsResourceType,
        StatementMetaResourceType        $statementMetaResourceType,
        StatementReportEntryResourceType $statementReportEntryResourceType,
        StatementResourceType            $statementResourceType,
        StatementSegmentResourceType     $statementSegmentResourceType,
        StatementVoteResourceType        $statementVoteResourceType,
        SurveyResourceType               $surveyResourceType,
        SurveyVoteResourceType           $surveyVoteResourceType,
        TagResourceType                  $tagResourceType,
        TagTopicResourceType             $tagTopicResourceType,
        UserFilterSetResourceType        $userFilterSetResourceType,
        UserResourceType                 $userResourceType,
        UserRoleInCustomerResourceType   $userRoleInCustomerResourceType
    ) {
        $this->adminProcedureResourceType = $adminProcedureResourceType;
        $this->administratableUserResourceType = $administratableUserResourceType;
        $this->agencyEmailAddressResourceType = $agencyEmailAddressResourceType;
        $this->assignableUserResourceType = $assignableUserResourceType;
        $this->boilerplateGroupResourceType = $boilerplateGroupResourceType;
        $this->boilerplateResourceType = $boilerplateResourceType;
        $this->brandingResourceType = $brandingResourceType;
        $this->claimResourceType = $claimResourceType;
        $this->clusterStatementResourceType = $clusterStatementResourceType;
        $this->consultationTokenResourceType = $consultationTokenResourceType;
        $this->contextualHelpResourceType = $contextualHelpResourceType;
        $this->countyResourceType = $countyResourceType;
        $this->customerContactResourceType = $customerContactResourceType;
        $this->customerLoginSupportContactResourceType = $customerLoginSupportContactResourceType;
        $this->customerResourceType = $customerResourceType;
        $this->departmentResourceType = $departmentResourceType;
        $this->emailAddressResourceType = $emailAddressResourceType;
        $this->emailResourceType = $emailResourceType;
        $this->faqCategoryResourceType = $faqCategoryResourceType;
        $this->faqResourceType = $faqResourceType;
        $this->fileResourceType = $fileResourceType;
        $this->finalMailReportEntryResourceType = $finalMailReportEntryResourceType;
        $this->generalReportEntryResourceType = $generalReportEntryResourceType;
        $this->gisLayerCategoryResourceType = $gisLayerCategoryResourceType;
        $this->gisLayerResourceType = $gisLayerResourceType;
        $this->globalNewsCategoryResourceType = $globalNewsCategoryResourceType;
        $this->globalNewsResourceType = $globalNewsResourceType;
        $this->hashedQueryResourceType = $hashedQueryResourceType;
        $this->headStatementResourceType = $headStatementResourceType;
        $this->institutionLocationContactResourceType = $institutionLocationContactResourceType;
        $this->institutionTagResourceType = $institutionTagResourceType;
        $this->invitableInstitutionResourceType = $invitableInstitutionResourceType;
        $this->invitablePublicAgencyResourceType = $invitablePublicAgencyResourceType;
        $this->invitationReportEntryResourceType = $invitationReportEntryResourceType;
        $this->masterToebResourceType = $masterToebResourceType;
        $this->municipalityResourceType = $municipalityResourceType;
        $this->orgaResourceType = $orgaResourceType;
        $this->orgaStatusInCustomerResourceType = $orgaStatusInCustomerResourceType;
        $this->orgaTypeResourceType = $orgaTypeResourceType;
        $this->originalStatementResourceType = $originalStatementResourceType;
        $this->paragraphResourceType = $paragraphResourceType;
        $this->paragraphVersionResourceType = $paragraphVersionResourceType;
        $this->placeResourceType = $placeResourceType;
        $this->planningDocumentCategoryResourceType = $planningDocumentCategoryResourceType;
        $this->planningDocumentCategoryDetailsResourceType = $planningDocumentCategoryDetailsResourceType;
        $this->priorityAreaResourceType = $priorityAreaResourceType;
        $this->procedureBehaviorDefinitionResourceType = $procedureBehaviorDefinitionResourceType;
        $this->procedureMapSettingResourceType = $procedureMapSettingResourceType;
        $this->procedureNewsResourceType = $procedureNewsResourceType;
        $this->procedurePhaseResourceType = $procedurePhaseResourceType;
        $this->procedureResourceType = $procedureResourceType;
        $this->procedureTemplateResourceType = $procedureTemplateResourceType;
        $this->procedureTypeResourceType = $procedureTypeResourceType;
        $this->procedureUiDefinitionResourceType = $procedureUiDefinitionResourceType;
        $this->publicPhaseReportEntryResourceType = $publicPhaseReportEntryResourceType;
        $this->registerInvitationReportEntryResourceType = $registerInvitationReportEntryResourceType;
        $this->reportEntryResourceType = $reportEntryResourceType;
        $this->roleResourceType = $roleResourceType;
        $this->segmentCommentResourceType = $segmentCommentResourceType;
        $this->signLanguageOverviewVideoResourceType = $signLanguageOverviewVideoResourceType;
        $this->similarStatementSubmitterResourceType = $similarStatementSubmitterResourceType;
        $this->singleDocumentResourceType = $singleDocumentResourceType;
        $this->slugResourceType = $slugResourceType;
        $this->statementAttachmentResourceType = $statementAttachmentResourceType;
        $this->statementFieldDefinitionResourceType = $statementFieldDefinitionResourceType;
        $this->statementFormDefinitionResourceType = $statementFormDefinitionResourceType;
        $this->statementFragmentResourceType = $statementFragmentResourceType;
        $this->statementFragmentsElementsResourceType = $statementFragmentsElementsResourceType;
        $this->statementMetaResourceType = $statementMetaResourceType;
        $this->statementReportEntryResourceType = $statementReportEntryResourceType;
        $this->statementResourceType = $statementResourceType;
        $this->statementSegmentResourceType = $statementSegmentResourceType;
        $this->statementVoteResourceType =$statementVoteResourceType;
        $this->surveyResourceType = $surveyResourceType;
        $this->surveyVoteResourceType = $surveyVoteResourceType;
        $this->tagResourceType = $tagResourceType;
        $this->tagTopicResourceType = $tagTopicResourceType;
        $this->userFilterSetResourceType = $userFilterSetResourceType;
        $this->userResourceType = $userResourceType;
        $this->userRoleInCustomerResourceType = $userRoleInCustomerResourceType;
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

    public function getClaimResourceType(): ClaimResourceType
    {
        return $this->claimResourceType;
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

    public function getStatementAttachmentResourceType(): StatementAttachmentResourceType
    {
        return $this->statementAttachmentResourceType;
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

    public function getSurveyResourceType(): SurveyResourceType
    {
        return $this->surveyResourceType;
    }

    public function getSurveyVoteResourceType(): SurveyVoteResourceType
    {
        return $this->surveyVoteResourceType;
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
}
