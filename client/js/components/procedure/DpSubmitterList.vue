<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <p>
      {{ Translator.trans('explanation.list.of.submitters') }}
    </p>

    <a
      class="u-mb"
      :href="exportSubmitterList">
      <i
        class="fa fa-download"
        aria-hidden="true" />
      {{ Translator.trans('export') }}
    </a>

    <div class="u-mb text--right">
      <dp-column-selector
        :initial-selection="currentSelection"
        :selectable-columns="selectableColumns"
        @selection-changed="setCurrentSelection"
        use-local-storage
        local-storage-key="submitterList" />
    </div>

    <dp-loading v-if="isLoading" />
    <template v-else>
      <dp-data-table
        class="overflow-x-auto"
        v-if="items.length"
        :header-fields="headerFields"
        :items="items"
        track-by="id">
        <template v-slot:statement="rowData">
          <a
            :href="SubmitterListItem(rowData)"
            data-cy="SubmitterListItem">
            {{ rowData.statement }}
          </a>
        </template>
        <template v-slot:internId="{ internId }">
          <div class="o-hellip__wrapper">
            <div
              v-text="internId"
              class="o-hellip--nowrap"
              v-tooltip="internId"
              dir="rtl" />
          </div>
        </template>
      </dp-data-table>

      <div v-else-if="items.length === 0">
        <p class="flash flash-info">
          {{ Translator.trans('statements.submitted.none') }}
        </p>
      </div>
    </template>
  </div>
</template>

<script>
import { DpColumnSelector, DpDataTable, DpLoading } from '@demos-europe/demosplan-ui'
import { dpApi } from '@demos-europe/demosplan-utils'

export default {
  name: 'DpSubmitterList',

  components: {
    DpColumnSelector,
    DpDataTable,
    DpLoading
  },

  props: {
    procedureId: {
      type: String,
      required: true
    }
  },

  data () {
    return {
      headerFieldsAvailable: [
        { field: 'name', label: Translator.trans('name') },
        { field: 'email', label: Translator.trans('email') },
        { field: 'address', label: Translator.trans('city') },
        { field: 'organisationAndDepartment', label: Translator.trans('organisation') + ' / ' + Translator.trans('department') },
        { field: 'memo', label: Translator.trans('memo') },
        { field: 'internId', label: Translator.trans('internId.shortened') },
        { field: 'statement', label: Translator.trans('id'), tooltip: Translator.trans('id.statement.long') }
      ],
      isLoading: false,
      items: [],
      currentSelection: ['name', 'organisationAndDepartment', 'statement']
    }
  },

  computed: {
    exportSubmitterList () {
      return Routing.generate('dplan_admin_procedure_submitter_export', {
        procedureId: this.procedureId
      })
    },
    selectableColumns () {
      return this.headerFieldsAvailable.map(headerField => ([headerField.field, headerField.label]))
    },
    headerFields () {
      return this.headerFieldsAvailable.filter(headerField => this.currentSelection.includes(headerField.field))
    }
  },

  methods: {
    async fetchStatements () {
      this.isLoading = true
      const response = await dpApi.get(Routing.generate('api_resource_list', { resourceType: 'Statement' }),
        {
          filter: {
            procedureId: {
              condition: {
                path: 'procedure.id',
                value: this.procedureId
              }
            }
          },
          fields: {
            Statement: [
              'authorName',
              'externId',
              'internId',
              'isSubmittedByCitizen',
              'initialOrganisationCity',
              'initialOrganisationDepartmentName',
              'initialOrganisationName',
              'initialOrganisationPostalCode',
              'isCitizen',
              'memo',
              'submitName',
              'submitterEmailAddress',
              'initialOrganisationHouseNumber',
              'initialOrganisationStreet'
            ].join()
          }
        },
        { serialize: true }
      )

      this.items = [...response.data.data]
        .map(statement => {
          return this.handleEmptyAttrs(statement)
        })
        .sort((a, b) => {
          return (a.isCitizen === b.isCitizen) ? 0 : a.isCitizen ? 1 : -1
        })
      this.isLoading = false
    },

    /**
     * If an attribute is empty, replace it with '-' or don't display it
     * @param resourceObj
     * @return {{organisationAndDepartment: *, name: *, address: (string|string), email: (*|string)}}
     */
    handleEmptyAttrs (resourceObj) {
      const {
        authorName,
        externId,
        internId,
        isSubmittedByCitizen,
        initialOrganisationCity,
        initialOrganisationDepartmentName,
        initialOrganisationName,
        initialOrganisationPostalCode,
        isCitizen,
        memo,
        submitName,
        submitterEmailAddress,
        initialOrganisationHouseNumber,
        initialOrganisationStreet
      } = resourceObj.attributes

      return {
        email: submitterEmailAddress || '-',
        id: resourceObj.id,
        internId: internId || '',
        isCitizen,
        memo: memo || '-',
        name: authorName || submitName || '-',
        organisationAndDepartment: this.handleOrgaAndDepartment(initialOrganisationDepartmentName, initialOrganisationName, isSubmittedByCitizen),
        address: this.handleOrgaAddress(initialOrganisationCity, initialOrganisationPostalCode, initialOrganisationHouseNumber, initialOrganisationStreet),
        statement: externId
      }
    },

    handleOrgaAndDepartment (initialOrganisationDepartmentName, initialOrganisationName, isSubmittedByCitizen) {
      if (initialOrganisationName) {
        if (isSubmittedByCitizen) {
          return initialOrganisationName
        }
        return initialOrganisationDepartmentName ? initialOrganisationName + ', ' + initialOrganisationDepartmentName : initialOrganisationName
      }
      return initialOrganisationDepartmentName || '-'
    },

    handleOrgaAddress (initialOrganisationCity, initialOrganisationPostalCode, initialOrganisationStreet, initialOrganisationHouseNumber) {
      let fullAddress = ''
      if (initialOrganisationStreet) {
        fullAddress = initialOrganisationHouseNumber ? initialOrganisationHouseNumber + ' ' + initialOrganisationStreet : initialOrganisationStreet
      }
      if (initialOrganisationPostalCode) {
        fullAddress += initialOrganisationStreet ? ', ' : ''
        fullAddress += initialOrganisationCity ? initialOrganisationPostalCode + ' ' + initialOrganisationCity : initialOrganisationPostalCode
      }
      return fullAddress || '-'
    },

    SubmitterListItem (rowData) {
      return Routing.generate('dplan_statement_segments_list', { statementId: rowData.id, procedureId: this.procedureId })
    },

    setCurrentSelection (selection) {
      this.currentSelection = selection
    }
  },
  mounted () {
    this.fetchStatements()
  }
}
</script>
