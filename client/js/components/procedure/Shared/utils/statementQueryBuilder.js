/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * Builds query parameters for fetching detailed statement data via vuex-json-api
 *
 * @param {string} statementId - The ID of the statement to fetch
 * @param {Object} options - Additional query options
 * @param {boolean} [options.isSourceAndCoupledProcedure=false] - Whether the procedure is a source and coupled procedure
 * @returns {Object} Query parameters object with id, include, and fields properties
 *
 * @example
 * // Basic usage
 * const params = buildDetailedStatementQuery('statement-123')
 * store.dispatch('Statement/get', params)
 *
 * @example
 * // With options
 * const params = buildDetailedStatementQuery('statement-123', {
 *   isSourceAndCoupledProcedure: true
 * })
 */
export function buildDetailedStatementQuery (statementId, options = {}) {
  const { isSourceAndCoupledProcedure = false } = options

  // Base statement fields that are always included
  const statementFields = [
    'assignee',
    'authoredDate',
    'authorName',
    'counties',
    'document',
    'elements',
    'fullText',
    'genericAttachments',
    'initialOrganisationCity',
    'initialOrganisationDepartmentName',
    'initialOrganisationHouseNumber',
    'initialOrganisationName',
    'initialOrganisationPostalCode',
    'initialOrganisationStreet',
    'internId',
    'isManual',
    'isSubmittedByCitizen',
    'memo',
    'municipalities',
    'numberOfAnonymVotes',
    'paragraph',
    'paragraphParentId',
    'paragraphVersion',
    'polygon',
    'priorityAreas',
    'procedurePhase',
    'publicVerified',
    'publicVerifiedTranslation',
    'recommendation',
    'segmentDraftList',
    'sourceAttachment',
    'submitDate',
    'submitName',
    'submitterEmailAddress',
    'submitType',
    'status',
  ]

  // Add synchronized field for source and coupled procedures
  if (isSourceAndCoupledProcedure) {
    statementFields.push('synchronized')
  }

  // Add permission-based fields
  if (hasPermission('field_statement_phase')) {
    statementFields.push('availableProcedurePhases')
  }

  if (hasPermission('area_statement_segmentation')) {
    statementFields.push('segmentDraftList')
  }

  if (hasPermission('feature_similar_statement_submitter')) {
    statementFields.push('similarStatementSubmitters')
  }

  if (hasPermission('field_send_final_email')) {
    statementFields.push('authorFeedback', 'feedback', 'initialOrganisationEmail', 'publicStatement', 'sentAssessment', 'sentAssessmentDate', 'user')
  }

  if (hasPermission('feature_statement_gdpr_consent')) {
    statementFields.push('consentRevoked')
  }

  if (hasPermission('feature_statements_vote')) {
    statementFields.push('votes')
  }

  // Build fields object for related entities
  const allFields = {
    ElementsDetails: [
      'documents',
      'paragraphs',
      'title',
    ].join(),
    File: [
      'hash',
      'filename',
    ].join(),
    GenericStatementAttachment: [
      'file',
    ].join(),
    ParagraphVersion: [
      'title',
    ].join(),
    SingleDocument: [
      'title',
    ].join(),
    SourceStatementAttachment: [
      'file',
    ].join(),
    Statement: statementFields.join(),
  }

  // Add permission-based entity fields
  if (hasPermission('feature_statements_vote')) {
    allFields.StatementVote = [
      'city',
      'createdByCitizen',
      'departmentName',
      'email',
      'name',
      'organisationName',
      'postcode',
    ].join()
  }

  if (hasPermission('feature_similar_statement_submitter')) {
    allFields.SimilarStatementSubmitter = [
      'city',
      'emailAddress',
      'fullName',
      'postalCode',
      'streetName',
      'streetNumber',
    ].join()
  }

  if (hasPermission('field_send_final_email')) {
    allFields.User = [
      'orga',
    ].join()
  }

  // Build include array for related entities
  const include = [
    'assignee',
    'document',
    'elements',
    'genericAttachments',
    'genericAttachments.file',
    'paragraph',
    'paragraphVersion.paragraph',
    'sourceAttachment',
    'sourceAttachment.file',
  ]

  // Add permission-based includes
  if (hasPermission('feature_statements_vote')) {
    include.push('votes')
  }

  if (hasPermission('feature_similar_statement_submitter')) {
    include.push('similarStatementSubmitters')
  }

  if (hasPermission('field_send_final_email')) {
    include.push('user', 'user.orga')
  }

  return {
    id: statementId,
    include: include.join(),
    fields: allFields,
  }
}
