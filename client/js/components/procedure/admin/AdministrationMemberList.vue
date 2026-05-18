<license>
(c) 2010-present DEMOS plan GmbH.

This file is part of the package demosplan,
for more information see the license file.

All rights reserved
</license>

<template>
  <p class="u-mt-0_5">
    {{ Translator.trans('invitable_institution.add.explanation') }}
  </p>

  <organisation-table
    ref="organisationTable"
    :header-fields="headerFields"
    resource-type="InvitedToeb"
    :procedure-id="procedureId"
    @selected-items="setSelectedItems"
  >
    <template v-slot:bulkActions>
      <!-- Bulk Actions Section -->
      <div class="my-2">
        <button
          class="btn--blank o-link--default u-mr-0_5"
          :data-form-actions-confirm="Translator.trans('check.invitable_institutions.marked.delete')"
          data-cy="administrationMemberList:deleteSelected"
          type="button"
          @click="deleteSelected"
        >
          <i
            aria-hidden="true"
            class="fa fa-times-circle"
          />
          {{ Translator.trans('remove') }}
        </button>

        <button
          class="btn--blank o-link--default u-mr-0_5"
          data-cy="administrationMemberList:writeEmail"
          type="button"
          @click="writeEmail"
        >
          <i
            aria-hidden="true"
            class="fa fa-envelope"
          />
          {{ Translator.trans('email.invitation.write') }}
        </button>

        <button
          class="btn--blank o-link--default"
          data-cy="administrationMemberList:exportPdf"
          type="button"
          @click="exportPdf"
        >
          <i
            aria-hidden="true"
            class="fa fa-file"
          />
          {{ Translator.trans('pdf.export') }}
        </button>

        <dp-button
          :href="addMemberPath"
          :text="Translator.trans('invitable_institution.add')"
          class="btn btn--primary float-right"
          data-cy="addPublicAgency"
          rounded
        />
      </div>
    </template>
  </organisation-table>
</template>

<script>
import { DpButton, dpRpc } from '@demos-europe/demosplan-ui'
import OrganisationTable from '@DpJs/components/procedure/admin/InstitutionTagManagement/OrganisationTable'

export default {
  name: 'AdministrationMemberList',

  components: {
    OrganisationTable,
    DpButton,
  },

  props: {
    procedureId: {
      type: String,
      required: true,
    },
  },

  data () {
    return {
      selectedItems: [],
      headerFields: [
        {
          field: 'legalName',
          label: Translator.trans('invitable_institution'),
        },
        ...(hasPermission('field_organisation_paper_copy_spec') ?
          [{
            field: 'paperCopySpec',
            label: Translator.trans('copies.kind'),
          }] :
          []),
        ...(hasPermission('field_organisation_paper_copy') ?
          [{
            field: 'paperCopy',
            label: Translator.trans('copies'),
          }] :
          []),
        {
          field: 'originalStatementsCountInProcedure',
          label: Translator.trans('statement'),
        },
        {
          field: 'hasReceivedInvitationMailInCurrentProcedurePhase',
          label: Translator.trans('invitation'),
        },
      ],
    }
  },

  computed: {
    addMemberPath () {
      const routeName = hasPermission('area_use_mastertoeblist') ?
        'DemosPlan_procedure_member_add_mastertoeblist' :
        'DemosPlan_procedure_member_add'

      return Routing.generate(routeName, { procedure: this.procedureId })
    },
  },

  methods: {
    setSelectedItems (selectedItems) {
      this.selectedItems = selectedItems
    },

    deleteSelected () {
      if (this.selectedItems.length === 0) {
        dplan.notify.notify('warning', Translator.trans('organisation.select.first'))

        return
      }

      if (!confirm(Translator.trans('check.invitable_institutions.marked.delete'))) {
        return
      }
      // SelectedItems from DpDataTable are strings in an array, so we need to map them to ids
      const organisationIds = this.selectedItems.map(item =>
        typeof item === 'string' ? item : item.id,
      )

      dpRpc('invitedInstitutions.bulk.delete', {
        ids: organisationIds.map(id => ({ id })),
      })
        .then(response => {
          this.$refs.organisationTable.getInstitutionsWithContacts()
          this.$refs.organisationTable.clearSelections()
          dplan.notify.notify('confirm', Translator.trans('confirm.invitable_institutions.deleted', { count: organisationIds.length }))
          this.selectedItems = []
        })
        .catch(() => {
          dplan.notify.notify('error', Translator.trans('error.invitable_institutions.delete'))
        })
    },

    /**
     * Bulk action: Export selected organisations to PDF
     * Uses dedicated PDF route - no action flag needed
     */
    exportPdf () {
      this.submitBulkActionForm(
        'DemosPlan_procedure_member_index_pdf',
        { procedure: this.procedureId },
      )
    },

    /**
     * Generic method to submit bulk actions to legacy backend routes
     * Creates a form with selected organisation IDs and submits to specified route
     *
     * @param {string} routeName - Symfony route name to submit to
     * @param {Object} routeParams - Parameters for route generation
     * @param {string|null} actionFieldName - Optional action flag field name for legacy routes
     */
    submitBulkActionForm (routeName, routeParams, actionFieldName = null) {
      if (this.selectedItems.length === 0) {
        dplan.notify.notify('warning',
          Translator.trans('organisation.select.first'))

        return
      }
      // Create hidden form for legacy route compatibility
      const form = document.createElement('form')
      form.method = 'POST'
      form.action = Routing.generate(routeName, routeParams)
      form.style.display = 'none'

      // Add selected organisation IDs as form data
      this.selectedItems.forEach(orgId => {
        const input = document.createElement('input')
        input.type = 'hidden'
        input.name = 'orga_selected[]' // Legacy format expected by backend
        input.value = orgId
        form.appendChild(input)
      })

      // Add action-specific flag if required (legacy email route needs this)
      const actionInput = document.createElement('input')
      actionInput.type = 'hidden'
      actionInput.name = actionFieldName
      actionInput.value = '1'
      form.appendChild(actionInput)

      // CSRF protection
      const csrfInput = document.createElement('input')
      csrfInput.type = 'hidden'
      csrfInput.name = '_token'
      csrfInput.value = dplan.csrfToken
      form.appendChild(csrfInput)

      document.body.appendChild(form)
      form.submit()
      document.body.removeChild(form)
    },

    /**
     * Bulk action: Send email to selected organisations
     * Uses legacy email route that requires an action flag
     */
    writeEmail () {
      this.submitBulkActionForm(
        'DemosPlan_admin_member_email',
        { procedureId: this.procedureId },
        'email_orga_action',
      )
    },
  },
}
</script>
