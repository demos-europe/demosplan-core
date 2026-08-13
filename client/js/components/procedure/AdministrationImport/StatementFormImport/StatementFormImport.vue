<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <dp-simplified-new-statement-form
      allow-file-upload
      :csrf-token="csrfToken"
      :current-external-phase-definition-id="currentExternalPhaseDefinitionId"
      :current-internal-phase-definition-id="currentInternalPhaseDefinitionId"
      expand-all
      :newest-intern-id="newestInternId"
      :procedure-id="procedureId"
      :statement-import-email-id="null"
      :submit-type-options="submitTypeOptions"
      :tags="tags"
      :used-intern-ids="usedInternIds"
    />
  </div>
</template>

<script>
import DpSimplifiedNewStatementForm from '@DpJs/components/procedure/DpSimplifiedNewStatementForm'

export default {
  name: 'StatementFormImport',

  inject: ['currentExternalPhaseDefinitionId', 'currentInternalPhaseDefinitionId', 'currentUserId', 'newestInternId', 'procedureId', 'submitTypeOptions', 'tags', 'usedInternIds'],

  components: {
    DpSimplifiedNewStatementForm,
  },

  props: {
    csrfToken: {
      type: String,
      required: true,
    },
  },
}
</script>
