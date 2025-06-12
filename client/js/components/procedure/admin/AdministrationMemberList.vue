
<license>
(c) 2010-present DEMOS plan GmbH.

This file is part of the package demosplan,
for more information see the license file.

All rights reserved
</license>

<template>
  <div>
    <!-- Bulk Actions Section -->
      <button
        class="btn--blank o-link--default u-mr-0_5"
        type="button"
        @click="deleteSelected"
        :data-form-actions-confirm="Translator.trans('check.invitable_institutions.marked.delete')"
      >
        <i class="fa fa-times-circle" aria-hidden="true"></i>
        {{ Translator.trans('remove') }}
      </button>

      <button
        class="btn--blank o-link--default u-mr-0_5"
        type="button"
        @click="writeEmail"
      >
        <i class="fa fa-envelope" aria-hidden="true"></i>
        {{ Translator.trans('email.invitation.write') }}
      </button>

      <button
        class="btn--blank o-link--default"
        type="button"
        @click="exportPdf"
      >
        <i class="fa fa-file" aria-hidden="true"></i>
        {{ Translator.trans('pdf.export') }}
      </button>

    <dp-button
      data-cy="addPublicAgency"
      :href="addMemberPath"
      :text="Translator.trans('invitable_institution.add')"
      variant="primary" />

  </div>

    <organisation-table
      :header-fields="headerFields"
      ref="organisationTable"
      resource-type="InvitedToeb"
      :procedure-id="procedureId"
      track-by-id="id"
      @selected-items="setSelectedItems" />

</template>

<script>
import { DpButton, dpRpc, checkResponse } from '@demos-europe/demosplan-ui'
import OrganisationTable from '@DpJs/components/procedure/admin/InstitutionTagManagement/OrganisationTable'

export default {
  name: 'AdministrationMemberList',

  components: {
    OrganisationTable,
    DpButton
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
          label: Translator.trans('copies.kind') }] : []),
        ...(hasPermission('field_organisation_paper_copy') ?
          [{
          field: 'paperCopy',
          label: Translator.trans('copies') }] : []),
        {
          field: 'originalStatementsCountInProcedure',
          label: Translator.trans('statement') },
        {
          field: 'hasReceivedInvitationMailInCurrentProcedurePhase',
          label: Translator.trans('invitation') }
      ]
    }
  },

  computed: {
    addMemberPath() {
      console.log('Generating addMemberPath for procedure:', this.procedureId)

      const baseRoute = 'DemosPlan_procedure_member_add'
      const masterRoute = 'DemosPlan_procedure_member_add_mastertoeblist'

      const routeName = hasPermission('area_use_mastertoeblist')
        ? masterRoute
        : baseRoute

      const path = Routing.generate(routeName, { procedure: this.procedureId })
      console.log('Generated path:', path)

      return path
    }
  },

  methods: {
    setSelectedItems(selectedItems) {
      this.selectedItems = selectedItems
    },

    deleteSelected() {
      if (this.selectedItems.length === 0) {
        dplan.notify.notify('warning', Translator.trans('organisation.select.first'))
        return
      }

      if (!confirm(Translator.trans('check.invitable_institutions.marked.delete'))) {
        return
      }
      // selectedItems from DpDataTable are strings in an array, so we need to map them to ids
      const organisationIds = this.selectedItems.map(item =>
        typeof item === 'string' ? item : item.id
      )

      dpRpc('invitedInstitutions.bulk.delete', {
        ids: organisationIds.map(id => ({ id }))
      })
        .then(checkResponse)
        .then(response => {
          this.$refs.organisationTable.getInstitutionsWithContacts()
          dplan.notify.notify('confirm', Translator.trans('confirm.invitable_institutions.deleted', { count: organisationIds.length }))
          this.selectedItems = []
        })
        .catch(error => {
          dplan.notify.notify('error', Translator.trans('error.invitable_institutions.delete'))
        })
    },

    writeEmail() {
      console.log('writeEmail called, selectedItems:', this.selectedItems)

      if (this.selectedItems.length === 0) {
        dplan.notify.notify('warning', Translator.trans('organisation.select.first'))
        return
      }

      // Erstelle verstecktes Form mit ausgewählten IDs
      const form = document.createElement('form')
      form.method = 'POST'
      form.action = Routing.generate('DemosPlan_admin_member_email', {
        procedureId: this.procedureId
      })
      form.style.display = 'none'

      // Füge selected organisation IDs hinzu
      this.selectedItems.forEach(orgId => {
        const input = document.createElement('input')
        input.type = 'hidden'
        input.name = 'orga_selected[]'  // Legacy-Format
        input.value = orgId
        form.appendChild(input)
      })

      // Email-Action Flag
      const actionInput = document.createElement('input')
      actionInput.type = 'hidden'
      actionInput.name = 'email_orga_action'
      actionInput.value = '1'
      form.appendChild(actionInput)

      // CSRF-Token
      const csrfInput = document.createElement('input')
      csrfInput.type = 'hidden'
      csrfInput.name = '_token'
      csrfInput.value = dplan.csrfToken
      form.appendChild(csrfInput)

      // Submit form
      document.body.appendChild(form)
      form.submit()
      document.body.removeChild(form)
    },


    exportPdf() {
      if (this.selectedItems.length === 0) {
        dplan.notify.notify('warning', Translator.trans('organisation.select.first'))

        return
      }

      document.getElementById('pdfExportButton')?.click()
    }
  }
}
</script>
