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

    <dp-loading v-if="isLoading" />
    <template v-else>
      <dp-data-table
        v-if="items.length"
        :header-fields="headerFields"
        is-resizable
        is-truncatable
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
              class="o-hellip--nowrap text--right"
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
import { dpApi } from '@DemosPlanCoreBundle/plugins/DpApi'
import DpDataTable from '@DpJs/components/core/DpDataTable/DpDataTable'
import { DpLoading } from 'demosplan-ui/components'

export default {
  name: 'DpSubmitterList',

  components: {
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
      headerFields: [
        { field: 'name', label: Translator.trans('name'), initialMaxWidth: 250 },
        { field: 'email', label: Translator.trans('email'), initialMaxWidth: 200 },
        { field: 'postalCodeAndCity', label: Translator.trans('postalcode') + ' / ' + Translator.trans('city'), initialMaxWidth: 120 },
        { field: 'organisationAndDepartment', label: Translator.trans('organisation') + ' / ' + Translator.trans('department'), initialMaxWidth: 200 },
        { field: 'memo', label: Translator.trans('memo'), initialMinWidth: 100, initialMaxWidth: 200 },
        { field: 'internId', label: Translator.trans('internId.shortened'), initialWidth: 80 },
        { field: 'statement', label: Translator.trans('id'), tooltip: Translator.trans('id.statement.long'), initialWidth: 40 }
      ],
      isLoading: false,
      items: []
    }
  },

  computed: {
    exportSubmitterList () {
      return Routing.generate('dplan_admin_procedure_submitter_export', {
        procedureId: this.procedureId
      })
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
              'submitterEmailAddress'
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
     * @return {{organisationAndDepartment: *, name: *, postalCodeAndCity: (string|string), email: (*|string)}}
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
        submitterEmailAddress
      } = resourceObj.attributes

      return {
        email: submitterEmailAddress || '-',
        id: resourceObj.id,
        internId: internId || '',
        isCitizen,
        memo: memo || '-',
        name: authorName || submitName || '-',
        organisationAndDepartment: this.handleOrgaAndDepartment(initialOrganisationDepartmentName, initialOrganisationName, isSubmittedByCitizen),
        postalCodeAndCity: this.handleOrgaPostalCodeAndOrgaCity(initialOrganisationCity, initialOrganisationPostalCode),
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

    handleOrgaPostalCodeAndOrgaCity (initialOrganisationCity, initialOrganisationPostalCode) {
      if (initialOrganisationPostalCode) {
        return initialOrganisationCity ? initialOrganisationPostalCode + ' ' + initialOrganisationCity : initialOrganisationPostalCode
      }
      return initialOrganisationCity || '-'
    },

    SubmitterListItem (rowData) {
      return Routing.generate('dplan_statement_segments_list', { statementId: rowData.id, procedureId: this.procedureId })
    }
  },
  mounted () {
    this.fetchStatements()
  }
}
</script>
