<?php

declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType;

use demosplan\DemosPlanCoreBundle\ResourceTypes\AdminProcedureResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\AdministratableUserResourceType;
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
	/** @var AdminProcedureResourceType */
	protected AdminProcedureResourceType $adminProcedureResourceType;

	/** @var AdministratableUserResourceType */
	protected AdministratableUserResourceType $administratableUserResourceType;

	/** @var AgencyEmailAddressResourceType */
	protected AgencyEmailAddressResourceType $agencyEmailAddressResourceType;

	/** @var AssignableUserResourceType */
	protected AssignableUserResourceType $assignableUserResourceType;

	/** @var BoilerplateGroupResourceType */
	protected BoilerplateGroupResourceType $boilerplateGroupResourceType;

	/** @var BoilerplateResourceType */
	protected BoilerplateResourceType $boilerplateResourceType;

	/** @var BrandingResourceType */
	protected BrandingResourceType $brandingResourceType;

	/** @var ClaimResourceType */
	protected ClaimResourceType $claimResourceType;

	/** @var ClusterStatementResourceType */
	protected ClusterStatementResourceType $clusterStatementResourceType;

	/** @var ConsultationTokenResourceType */
	protected ConsultationTokenResourceType $consultationTokenResourceType;

	/** @var ContextualHelpResourceType */
	protected ContextualHelpResourceType $contextualHelpResourceType;

	/** @var CountyResourceType */
	protected CountyResourceType $countyResourceType;

	/** @var CustomerContactResourceType */
	protected CustomerContactResourceType $customerContactResourceType;

	/** @var CustomerLoginSupportContactResourceType */
	protected CustomerLoginSupportContactResourceType $customerLoginSupportContactResourceType;

	/** @var CustomerResourceType */
	protected CustomerResourceType $customerResourceType;

	/** @var DepartmentResourceType */
	protected DepartmentResourceType $departmentResourceType;

	/** @var EmailAddressResourceType */
	protected EmailAddressResourceType $emailAddressResourceType;

	/** @var EmailResourceType */
	protected EmailResourceType $emailResourceType;

	/** @var FaqCategoryResourceType */
	protected FaqCategoryResourceType $faqCategoryResourceType;

	/** @var FaqResourceType */
	protected FaqResourceType $faqResourceType;

	/** @var FileResourceType */
	protected FileResourceType $fileResourceType;

	/** @var FinalMailReportEntryResourceType */
	protected FinalMailReportEntryResourceType $finalMailReportEntryResourceType;

	/** @var GeneralReportEntryResourceType */
	protected GeneralReportEntryResourceType $generalReportEntryResourceType;

	/** @var GisLayerCategoryResourceType */
	protected GisLayerCategoryResourceType $gisLayerCategoryResourceType;

	/** @var GisLayerResourceType */
	protected GisLayerResourceType $gisLayerResourceType;

	/** @var GlobalNewsCategoryResourceType */
	protected GlobalNewsCategoryResourceType $globalNewsCategoryResourceType;

	/** @var GlobalNewsResourceType */
	protected GlobalNewsResourceType $globalNewsResourceType;

	/** @var HashedQueryResourceType */
	protected HashedQueryResourceType $hashedQueryResourceType;

	/** @var HeadStatementResourceType */
	protected HeadStatementResourceType $headStatementResourceType;

	/** @var InstitutionLocationContactResourceType */
	protected InstitutionLocationContactResourceType $institutionLocationContactResourceType;

	/** @var InstitutionTagResourceType */
	protected InstitutionTagResourceType $institutionTagResourceType;

	/** @var InvitableInstitutionResourceType */
	protected InvitableInstitutionResourceType $invitableInstitutionResourceType;

	/** @var InvitablePublicAgencyResourceType */
	protected InvitablePublicAgencyResourceType $invitablePublicAgencyResourceType;

	/** @var InvitationReportEntryResourceType */
	protected InvitationReportEntryResourceType $invitationReportEntryResourceType;

	/** @var MasterToebResourceType */
	protected MasterToebResourceType $masterToebResourceType;

	/** @var MunicipalityResourceType */
	protected MunicipalityResourceType $municipalityResourceType;

	/** @var OrgaResourceType */
	protected OrgaResourceType $orgaResourceType;

	/** @var OrgaStatusInCustomerResourceType */
	protected OrgaStatusInCustomerResourceType $orgaStatusInCustomerResourceType;

	/** @var OrgaTypeResourceType */
	protected OrgaTypeResourceType $orgaTypeResourceType;

	/** @var OriginalStatementResourceType */
	protected OriginalStatementResourceType $originalStatementResourceType;

	/** @var ParagraphResourceType */
	protected ParagraphResourceType $paragraphResourceType;

	/** @var ParagraphVersionResourceType */
	protected ParagraphVersionResourceType $paragraphVersionResourceType;

	/** @var PlaceResourceType */
	protected PlaceResourceType $placeResourceType;

	/** @var PlanningDocumentCategoryResourceType */
	protected PlanningDocumentCategoryResourceType $planningDocumentCategoryResourceType;

	/** @var PriorityAreaResourceType */
	protected PriorityAreaResourceType $priorityAreaResourceType;

	/** @var ProcedureBehaviorDefinitionResourceType */
	protected ProcedureBehaviorDefinitionResourceType $procedureBehaviorDefinitionResourceType;

	/** @var ProcedureMapSettingResourceType */
	protected ProcedureMapSettingResourceType $procedureMapSettingResourceType;

	/** @var ProcedureNewsResourceType */
	protected ProcedureNewsResourceType $procedureNewsResourceType;

	/** @var ProcedurePhaseResourceType */
	protected ProcedurePhaseResourceType $procedurePhaseResourceType;

	/** @var ProcedureResourceType */
	protected ProcedureResourceType $procedureResourceType;

	/** @var ProcedureTemplateResourceType */
	protected ProcedureTemplateResourceType $procedureTemplateResourceType;

	/** @var ProcedureTypeResourceType */
	protected ProcedureTypeResourceType $procedureTypeResourceType;

	/** @var ProcedureUiDefinitionResourceType */
	protected ProcedureUiDefinitionResourceType $procedureUiDefinitionResourceType;

	/** @var PublicPhaseReportEntryResourceType */
	protected PublicPhaseReportEntryResourceType $publicPhaseReportEntryResourceType;

	/** @var RegisterInvitationReportEntryResourceType */
	protected RegisterInvitationReportEntryResourceType $registerInvitationReportEntryResourceType;

	/** @var ReportEntryResourceType */
	protected ReportEntryResourceType $reportEntryResourceType;

	/** @var RoleResourceType */
	protected RoleResourceType $roleResourceType;

	/** @var SegmentCommentResourceType */
	protected SegmentCommentResourceType $segmentCommentResourceType;

	/** @var SignLanguageOverviewVideoResourceType */
	protected SignLanguageOverviewVideoResourceType $signLanguageOverviewVideoResourceType;

	/** @var SimilarStatementSubmitterResourceType */
	protected SimilarStatementSubmitterResourceType $similarStatementSubmitterResourceType;

	/** @var SingleDocumentResourceType */
	protected SingleDocumentResourceType $singleDocumentResourceType;

	/** @var SlugResourceType */
	protected SlugResourceType $slugResourceType;

	/** @var StatementAttachmentResourceType */
	protected StatementAttachmentResourceType $statementAttachmentResourceType;

	/** @var StatementFieldDefinitionResourceType */
	protected StatementFieldDefinitionResourceType $statementFieldDefinitionResourceType;

	/** @var StatementFormDefinitionResourceType */
	protected StatementFormDefinitionResourceType $statementFormDefinitionResourceType;

	/** @var StatementFragmentResourceType */
	protected StatementFragmentResourceType $statementFragmentResourceType;

	/** @var StatementFragmentsElementsResourceType */
	protected StatementFragmentsElementsResourceType $statementFragmentsElementsResourceType;

	/** @var StatementMetaResourceType */
	protected StatementMetaResourceType $statementMetaResourceType;

	/** @var StatementReportEntryResourceType */
	protected StatementReportEntryResourceType $statementReportEntryResourceType;

	/** @var StatementResourceType */
	protected StatementResourceType $statementResourceType;

	/** @var StatementSegmentResourceType */
	protected StatementSegmentResourceType $statementSegmentResourceType;

	/** @var SurveyResourceType */
	protected SurveyResourceType $surveyResourceType;

	/** @var SurveyVoteResourceType */
	protected SurveyVoteResourceType $surveyVoteResourceType;

	/** @var TagResourceType */
	protected TagResourceType $tagResourceType;

	/** @var TagTopicResourceType */
	protected TagTopicResourceType $tagTopicResourceType;

	/** @var UserFilterSetResourceType */
	protected UserFilterSetResourceType $userFilterSetResourceType;

	/** @var UserResourceType */
	protected UserResourceType $userResourceType;

	/** @var UserRoleInCustomerResourceType */
	protected UserRoleInCustomerResourceType $userRoleInCustomerResourceType;


	/**
	 * @param AdminProcedureResourceType $adminProcedureResourceType
	 * @param AdministratableUserResourceType $administratableUserResourceType
	 * @param AgencyEmailAddressResourceType $agencyEmailAddressResourceType
	 * @param AssignableUserResourceType $assignableUserResourceType
	 * @param BoilerplateGroupResourceType $boilerplateGroupResourceType
	 * @param BoilerplateResourceType $boilerplateResourceType
	 * @param BrandingResourceType $brandingResourceType
	 * @param ClaimResourceType $claimResourceType
	 * @param ClusterStatementResourceType $clusterStatementResourceType
	 * @param ConsultationTokenResourceType $consultationTokenResourceType
	 * @param ContextualHelpResourceType $contextualHelpResourceType
	 * @param CountyResourceType $countyResourceType
	 * @param CustomerContactResourceType $customerContactResourceType
	 * @param CustomerLoginSupportContactResourceType $customerLoginSupportContactResourceType
	 * @param CustomerResourceType $customerResourceType
	 * @param DepartmentResourceType $departmentResourceType
	 * @param EmailAddressResourceType $emailAddressResourceType
	 * @param EmailResourceType $emailResourceType
	 * @param FaqCategoryResourceType $faqCategoryResourceType
	 * @param FaqResourceType $faqResourceType
	 * @param FileResourceType $fileResourceType
	 * @param FinalMailReportEntryResourceType $finalMailReportEntryResourceType
	 * @param GeneralReportEntryResourceType $generalReportEntryResourceType
	 * @param GisLayerCategoryResourceType $gisLayerCategoryResourceType
	 * @param GisLayerResourceType $gisLayerResourceType
	 * @param GlobalNewsCategoryResourceType $globalNewsCategoryResourceType
	 * @param GlobalNewsResourceType $globalNewsResourceType
	 * @param HashedQueryResourceType $hashedQueryResourceType
	 * @param HeadStatementResourceType $headStatementResourceType
	 * @param InstitutionLocationContactResourceType $institutionLocationContactResourceType
	 * @param InstitutionTagResourceType $institutionTagResourceType
	 * @param InvitableInstitutionResourceType $invitableInstitutionResourceType
	 * @param InvitablePublicAgencyResourceType $invitablePublicAgencyResourceType
	 * @param InvitationReportEntryResourceType $invitationReportEntryResourceType
	 * @param MasterToebResourceType $masterToebResourceType
	 * @param MunicipalityResourceType $municipalityResourceType
	 * @param OrgaResourceType $orgaResourceType
	 * @param OrgaStatusInCustomerResourceType $orgaStatusInCustomerResourceType
	 * @param OrgaTypeResourceType $orgaTypeResourceType
	 * @param OriginalStatementResourceType $originalStatementResourceType
	 * @param ParagraphResourceType $paragraphResourceType
	 * @param ParagraphVersionResourceType $paragraphVersionResourceType
	 * @param PlaceResourceType $placeResourceType
	 * @param PlanningDocumentCategoryResourceType $planningDocumentCategoryResourceType
	 * @param PriorityAreaResourceType $priorityAreaResourceType
	 * @param ProcedureBehaviorDefinitionResourceType $procedureBehaviorDefinitionResourceType
	 * @param ProcedureMapSettingResourceType $procedureMapSettingResourceType
	 * @param ProcedureNewsResourceType $procedureNewsResourceType
	 * @param ProcedurePhaseResourceType $procedurePhaseResourceType
	 * @param ProcedureResourceType $procedureResourceType
	 * @param ProcedureTemplateResourceType $procedureTemplateResourceType
	 * @param ProcedureTypeResourceType $procedureTypeResourceType
	 * @param ProcedureUiDefinitionResourceType $procedureUiDefinitionResourceType
	 * @param PublicPhaseReportEntryResourceType $publicPhaseReportEntryResourceType
	 * @param RegisterInvitationReportEntryResourceType $registerInvitationReportEntryResourceType
	 * @param ReportEntryResourceType $reportEntryResourceType
	 * @param RoleResourceType $roleResourceType
	 * @param SegmentCommentResourceType $segmentCommentResourceType
	 * @param SignLanguageOverviewVideoResourceType $signLanguageOverviewVideoResourceType
	 * @param SimilarStatementSubmitterResourceType $similarStatementSubmitterResourceType
	 * @param SingleDocumentResourceType $singleDocumentResourceType
	 * @param SlugResourceType $slugResourceType
	 * @param StatementAttachmentResourceType $statementAttachmentResourceType
	 * @param StatementFieldDefinitionResourceType $statementFieldDefinitionResourceType
	 * @param StatementFormDefinitionResourceType $statementFormDefinitionResourceType
	 * @param StatementFragmentResourceType $statementFragmentResourceType
	 * @param StatementFragmentsElementsResourceType $statementFragmentsElementsResourceType
	 * @param StatementMetaResourceType $statementMetaResourceType
	 * @param StatementReportEntryResourceType $statementReportEntryResourceType
	 * @param StatementResourceType $statementResourceType
	 * @param StatementSegmentResourceType $statementSegmentResourceType
	 * @param SurveyResourceType $surveyResourceType
	 * @param SurveyVoteResourceType $surveyVoteResourceType
	 * @param TagResourceType $tagResourceType
	 * @param TagTopicResourceType $tagTopicResourceType
	 * @param UserFilterSetResourceType $userFilterSetResourceType
	 * @param UserResourceType $userResourceType
	 * @param UserRoleInCustomerResourceType $userRoleInCustomerResourceType
	 */
	public function __construct(
		AdminProcedureResourceType $adminProcedureResourceType,
		AdministratableUserResourceType $administratableUserResourceType,
		AgencyEmailAddressResourceType $agencyEmailAddressResourceType,
		AssignableUserResourceType $assignableUserResourceType,
		BoilerplateGroupResourceType $boilerplateGroupResourceType,
		BoilerplateResourceType $boilerplateResourceType,
		BrandingResourceType $brandingResourceType,
		ClaimResourceType $claimResourceType,
		ClusterStatementResourceType $clusterStatementResourceType,
		ConsultationTokenResourceType $consultationTokenResourceType,
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
		StatementMetaResourceType $statementMetaResourceType,
		StatementReportEntryResourceType $statementReportEntryResourceType,
		StatementResourceType $statementResourceType,
		StatementSegmentResourceType $statementSegmentResourceType,
		SurveyResourceType $surveyResourceType,
		SurveyVoteResourceType $surveyVoteResourceType,
		TagResourceType $tagResourceType,
		TagTopicResourceType $tagTopicResourceType,
		UserFilterSetResourceType $userFilterSetResourceType,
		UserResourceType $userResourceType,
		UserRoleInCustomerResourceType $userRoleInCustomerResourceType,
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
		$this->surveyResourceType = $surveyResourceType;
		$this->surveyVoteResourceType = $surveyVoteResourceType;
		$this->tagResourceType = $tagResourceType;
		$this->tagTopicResourceType = $tagTopicResourceType;
		$this->userFilterSetResourceType = $userFilterSetResourceType;
		$this->userResourceType = $userResourceType;
		$this->userRoleInCustomerResourceType = $userRoleInCustomerResourceType;
	}


	/**
	 * @return AdminProcedureResourceType
	 */
	public function getAdminProcedureResourceType(): AdminProcedureResourceType
	{
		return $this->adminProcedureResourceType;
	}


	/**
	 * @return AdministratableUserResourceType
	 */
	public function getAdministratableUserResourceType(): AdministratableUserResourceType
	{
		return $this->administratableUserResourceType;
	}


	/**
	 * @return AgencyEmailAddressResourceType
	 */
	public function getAgencyEmailAddressResourceType(): AgencyEmailAddressResourceType
	{
		return $this->agencyEmailAddressResourceType;
	}


	/**
	 * @return AssignableUserResourceType
	 */
	public function getAssignableUserResourceType(): AssignableUserResourceType
	{
		return $this->assignableUserResourceType;
	}


	/**
	 * @return BoilerplateGroupResourceType
	 */
	public function getBoilerplateGroupResourceType(): BoilerplateGroupResourceType
	{
		return $this->boilerplateGroupResourceType;
	}


	/**
	 * @return BoilerplateResourceType
	 */
	public function getBoilerplateResourceType(): BoilerplateResourceType
	{
		return $this->boilerplateResourceType;
	}


	/**
	 * @return BrandingResourceType
	 */
	public function getBrandingResourceType(): BrandingResourceType
	{
		return $this->brandingResourceType;
	}


	/**
	 * @return ClaimResourceType
	 */
	public function getClaimResourceType(): ClaimResourceType
	{
		return $this->claimResourceType;
	}


	/**
	 * @return ClusterStatementResourceType
	 */
	public function getClusterStatementResourceType(): ClusterStatementResourceType
	{
		return $this->clusterStatementResourceType;
	}


	/**
	 * @return ConsultationTokenResourceType
	 */
	public function getConsultationTokenResourceType(): ConsultationTokenResourceType
	{
		return $this->consultationTokenResourceType;
	}


	/**
	 * @return ContextualHelpResourceType
	 */
	public function getContextualHelpResourceType(): ContextualHelpResourceType
	{
		return $this->contextualHelpResourceType;
	}


	/**
	 * @return CountyResourceType
	 */
	public function getCountyResourceType(): CountyResourceType
	{
		return $this->countyResourceType;
	}


	/**
	 * @return CustomerContactResourceType
	 */
	public function getCustomerContactResourceType(): CustomerContactResourceType
	{
		return $this->customerContactResourceType;
	}


	/**
	 * @return CustomerLoginSupportContactResourceType
	 */
	public function getCustomerLoginSupportContactResourceType(): CustomerLoginSupportContactResourceType
	{
		return $this->customerLoginSupportContactResourceType;
	}


	/**
	 * @return CustomerResourceType
	 */
	public function getCustomerResourceType(): CustomerResourceType
	{
		return $this->customerResourceType;
	}


	/**
	 * @return DepartmentResourceType
	 */
	public function getDepartmentResourceType(): DepartmentResourceType
	{
		return $this->departmentResourceType;
	}


	/**
	 * @return EmailAddressResourceType
	 */
	public function getEmailAddressResourceType(): EmailAddressResourceType
	{
		return $this->emailAddressResourceType;
	}


	/**
	 * @return EmailResourceType
	 */
	public function getEmailResourceType(): EmailResourceType
	{
		return $this->emailResourceType;
	}


	/**
	 * @return FaqCategoryResourceType
	 */
	public function getFaqCategoryResourceType(): FaqCategoryResourceType
	{
		return $this->faqCategoryResourceType;
	}


	/**
	 * @return FaqResourceType
	 */
	public function getFaqResourceType(): FaqResourceType
	{
		return $this->faqResourceType;
	}


	/**
	 * @return FileResourceType
	 */
	public function getFileResourceType(): FileResourceType
	{
		return $this->fileResourceType;
	}


	/**
	 * @return FinalMailReportEntryResourceType
	 */
	public function getFinalMailReportEntryResourceType(): FinalMailReportEntryResourceType
	{
		return $this->finalMailReportEntryResourceType;
	}


	/**
	 * @return GeneralReportEntryResourceType
	 */
	public function getGeneralReportEntryResourceType(): GeneralReportEntryResourceType
	{
		return $this->generalReportEntryResourceType;
	}


	/**
	 * @return GisLayerCategoryResourceType
	 */
	public function getGisLayerCategoryResourceType(): GisLayerCategoryResourceType
	{
		return $this->gisLayerCategoryResourceType;
	}


	/**
	 * @return GisLayerResourceType
	 */
	public function getGisLayerResourceType(): GisLayerResourceType
	{
		return $this->gisLayerResourceType;
	}


	/**
	 * @return GlobalNewsCategoryResourceType
	 */
	public function getGlobalNewsCategoryResourceType(): GlobalNewsCategoryResourceType
	{
		return $this->globalNewsCategoryResourceType;
	}


	/**
	 * @return GlobalNewsResourceType
	 */
	public function getGlobalNewsResourceType(): GlobalNewsResourceType
	{
		return $this->globalNewsResourceType;
	}


	/**
	 * @return HashedQueryResourceType
	 */
	public function getHashedQueryResourceType(): HashedQueryResourceType
	{
		return $this->hashedQueryResourceType;
	}


	/**
	 * @return HeadStatementResourceType
	 */
	public function getHeadStatementResourceType(): HeadStatementResourceType
	{
		return $this->headStatementResourceType;
	}


	/**
	 * @return InstitutionLocationContactResourceType
	 */
	public function getInstitutionLocationContactResourceType(): InstitutionLocationContactResourceType
	{
		return $this->institutionLocationContactResourceType;
	}


	/**
	 * @return InstitutionTagResourceType
	 */
	public function getInstitutionTagResourceType(): InstitutionTagResourceType
	{
		return $this->institutionTagResourceType;
	}


	/**
	 * @return InvitableInstitutionResourceType
	 */
	public function getInvitableInstitutionResourceType(): InvitableInstitutionResourceType
	{
		return $this->invitableInstitutionResourceType;
	}


	/**
	 * @return InvitablePublicAgencyResourceType
	 */
	public function getInvitablePublicAgencyResourceType(): InvitablePublicAgencyResourceType
	{
		return $this->invitablePublicAgencyResourceType;
	}


	/**
	 * @return InvitationReportEntryResourceType
	 */
	public function getInvitationReportEntryResourceType(): InvitationReportEntryResourceType
	{
		return $this->invitationReportEntryResourceType;
	}


	/**
	 * @return MasterToebResourceType
	 */
	public function getMasterToebResourceType(): MasterToebResourceType
	{
		return $this->masterToebResourceType;
	}


	/**
	 * @return MunicipalityResourceType
	 */
	public function getMunicipalityResourceType(): MunicipalityResourceType
	{
		return $this->municipalityResourceType;
	}


	/**
	 * @return OrgaResourceType
	 */
	public function getOrgaResourceType(): OrgaResourceType
	{
		return $this->orgaResourceType;
	}


	/**
	 * @return OrgaStatusInCustomerResourceType
	 */
	public function getOrgaStatusInCustomerResourceType(): OrgaStatusInCustomerResourceType
	{
		return $this->orgaStatusInCustomerResourceType;
	}


	/**
	 * @return OrgaTypeResourceType
	 */
	public function getOrgaTypeResourceType(): OrgaTypeResourceType
	{
		return $this->orgaTypeResourceType;
	}


	/**
	 * @return OriginalStatementResourceType
	 */
	public function getOriginalStatementResourceType(): OriginalStatementResourceType
	{
		return $this->originalStatementResourceType;
	}


	/**
	 * @return ParagraphResourceType
	 */
	public function getParagraphResourceType(): ParagraphResourceType
	{
		return $this->paragraphResourceType;
	}


	/**
	 * @return ParagraphVersionResourceType
	 */
	public function getParagraphVersionResourceType(): ParagraphVersionResourceType
	{
		return $this->paragraphVersionResourceType;
	}


	/**
	 * @return PlaceResourceType
	 */
	public function getPlaceResourceType(): PlaceResourceType
	{
		return $this->placeResourceType;
	}


	/**
	 * @return PlanningDocumentCategoryResourceType
	 */
	public function getPlanningDocumentCategoryResourceType(): PlanningDocumentCategoryResourceType
	{
		return $this->planningDocumentCategoryResourceType;
	}


	/**
	 * @return PriorityAreaResourceType
	 */
	public function getPriorityAreaResourceType(): PriorityAreaResourceType
	{
		return $this->priorityAreaResourceType;
	}


	/**
	 * @return ProcedureBehaviorDefinitionResourceType
	 */
	public function getProcedureBehaviorDefinitionResourceType(): ProcedureBehaviorDefinitionResourceType
	{
		return $this->procedureBehaviorDefinitionResourceType;
	}


	/**
	 * @return ProcedureMapSettingResourceType
	 */
	public function getProcedureMapSettingResourceType(): ProcedureMapSettingResourceType
	{
		return $this->procedureMapSettingResourceType;
	}


	/**
	 * @return ProcedureNewsResourceType
	 */
	public function getProcedureNewsResourceType(): ProcedureNewsResourceType
	{
		return $this->procedureNewsResourceType;
	}


	/**
	 * @return ProcedurePhaseResourceType
	 */
	public function getProcedurePhaseResourceType(): ProcedurePhaseResourceType
	{
		return $this->procedurePhaseResourceType;
	}


	/**
	 * @return ProcedureResourceType
	 */
	public function getProcedureResourceType(): ProcedureResourceType
	{
		return $this->procedureResourceType;
	}


	/**
	 * @return ProcedureTemplateResourceType
	 */
	public function getProcedureTemplateResourceType(): ProcedureTemplateResourceType
	{
		return $this->procedureTemplateResourceType;
	}


	/**
	 * @return ProcedureTypeResourceType
	 */
	public function getProcedureTypeResourceType(): ProcedureTypeResourceType
	{
		return $this->procedureTypeResourceType;
	}


	/**
	 * @return ProcedureUiDefinitionResourceType
	 */
	public function getProcedureUiDefinitionResourceType(): ProcedureUiDefinitionResourceType
	{
		return $this->procedureUiDefinitionResourceType;
	}


	/**
	 * @return PublicPhaseReportEntryResourceType
	 */
	public function getPublicPhaseReportEntryResourceType(): PublicPhaseReportEntryResourceType
	{
		return $this->publicPhaseReportEntryResourceType;
	}


	/**
	 * @return RegisterInvitationReportEntryResourceType
	 */
	public function getRegisterInvitationReportEntryResourceType(): RegisterInvitationReportEntryResourceType
	{
		return $this->registerInvitationReportEntryResourceType;
	}


	/**
	 * @return ReportEntryResourceType
	 */
	public function getReportEntryResourceType(): ReportEntryResourceType
	{
		return $this->reportEntryResourceType;
	}


	/**
	 * @return RoleResourceType
	 */
	public function getRoleResourceType(): RoleResourceType
	{
		return $this->roleResourceType;
	}


	/**
	 * @return SegmentCommentResourceType
	 */
	public function getSegmentCommentResourceType(): SegmentCommentResourceType
	{
		return $this->segmentCommentResourceType;
	}


	/**
	 * @return SignLanguageOverviewVideoResourceType
	 */
	public function getSignLanguageOverviewVideoResourceType(): SignLanguageOverviewVideoResourceType
	{
		return $this->signLanguageOverviewVideoResourceType;
	}


	/**
	 * @return SimilarStatementSubmitterResourceType
	 */
	public function getSimilarStatementSubmitterResourceType(): SimilarStatementSubmitterResourceType
	{
		return $this->similarStatementSubmitterResourceType;
	}


	/**
	 * @return SingleDocumentResourceType
	 */
	public function getSingleDocumentResourceType(): SingleDocumentResourceType
	{
		return $this->singleDocumentResourceType;
	}


	/**
	 * @return SlugResourceType
	 */
	public function getSlugResourceType(): SlugResourceType
	{
		return $this->slugResourceType;
	}


	/**
	 * @return StatementAttachmentResourceType
	 */
	public function getStatementAttachmentResourceType(): StatementAttachmentResourceType
	{
		return $this->statementAttachmentResourceType;
	}


	/**
	 * @return StatementFieldDefinitionResourceType
	 */
	public function getStatementFieldDefinitionResourceType(): StatementFieldDefinitionResourceType
	{
		return $this->statementFieldDefinitionResourceType;
	}


	/**
	 * @return StatementFormDefinitionResourceType
	 */
	public function getStatementFormDefinitionResourceType(): StatementFormDefinitionResourceType
	{
		return $this->statementFormDefinitionResourceType;
	}


	/**
	 * @return StatementFragmentResourceType
	 */
	public function getStatementFragmentResourceType(): StatementFragmentResourceType
	{
		return $this->statementFragmentResourceType;
	}


	/**
	 * @return StatementFragmentsElementsResourceType
	 */
	public function getStatementFragmentsElementsResourceType(): StatementFragmentsElementsResourceType
	{
		return $this->statementFragmentsElementsResourceType;
	}


	/**
	 * @return StatementMetaResourceType
	 */
	public function getStatementMetaResourceType(): StatementMetaResourceType
	{
		return $this->statementMetaResourceType;
	}


	/**
	 * @return StatementReportEntryResourceType
	 */
	public function getStatementReportEntryResourceType(): StatementReportEntryResourceType
	{
		return $this->statementReportEntryResourceType;
	}


	/**
	 * @return StatementResourceType
	 */
	public function getStatementResourceType(): StatementResourceType
	{
		return $this->statementResourceType;
	}


	/**
	 * @return StatementSegmentResourceType
	 */
	public function getStatementSegmentResourceType(): StatementSegmentResourceType
	{
		return $this->statementSegmentResourceType;
	}


	/**
	 * @return SurveyResourceType
	 */
	public function getSurveyResourceType(): SurveyResourceType
	{
		return $this->surveyResourceType;
	}


	/**
	 * @return SurveyVoteResourceType
	 */
	public function getSurveyVoteResourceType(): SurveyVoteResourceType
	{
		return $this->surveyVoteResourceType;
	}


	/**
	 * @return TagResourceType
	 */
	public function getTagResourceType(): TagResourceType
	{
		return $this->tagResourceType;
	}


	/**
	 * @return TagTopicResourceType
	 */
	public function getTagTopicResourceType(): TagTopicResourceType
	{
		return $this->tagTopicResourceType;
	}


	/**
	 * @return UserFilterSetResourceType
	 */
	public function getUserFilterSetResourceType(): UserFilterSetResourceType
	{
		return $this->userFilterSetResourceType;
	}


	/**
	 * @return UserResourceType
	 */
	public function getUserResourceType(): UserResourceType
	{
		return $this->userResourceType;
	}


	/**
	 * @return UserRoleInCustomerResourceType
	 */
	public function getUserRoleInCustomerResourceType(): UserRoleInCustomerResourceType
	{
		return $this->userRoleInCustomerResourceType;
	}
}
