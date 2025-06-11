
<license>
(c) 2010-present DEMOS plan GmbH.

This file is part of the package demosplan,
for more information see the license file.

All rights reserved
</license>

<template>
  <organisation-table
    :header-fields="headerFields"
    ref="organisationTable"
    resource-type="InvitedToeb"
    :procedure-id="procedureId"
    track-by-id="id"
    @selected-items="setSelectedItems" />

</template>

<script>
import OrganisationTable from '@DpJs/components/procedure/admin/InstitutionTagManagement/OrganisationTable'
export default {
  name: 'AdministrationMemberList',

  components: {
    OrganisationTable
  },

  props: {
    procedureId: {
      type: String,
      required: true
    }
  },

  data() {
    return {
      selectedItems: [],
      headerFields: [
        {
          field: 'legalName',
          label: Translator.trans('invitable_institution')
        },
        ...(hasPermission('field_organisation_paper_copy_spec') ?
          [{
          field: 'paperCopySpec',
            label: Translator.trans('copies.kind') }]
          : []),
        ...(hasPermission('field_organisation_paper_copy') ?
          [{
          field: 'paperCopy',
            label: Translator.trans('copies') }]
          : []),
        {
          field: 'statementCount',
          label: Translator.trans('statement') },
        {
          field: 'invitationDate',
          label: Translator.trans('invitation') }
      ]
    }
  },

  methods: {
    setSelectedItems(selectedItems) {
      this.selectedItems = selectedItems
    }
  }

}
</script>
