<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <p>
      {{ Translator.trans('explanation.list.of.submitters') }}
    </p>

    <div class="flex items-center u-pv-0_5">
      <a
        :href="exportSubmitterList">
        <i
          class="fa fa-download"
          aria-hidden="true" />
        {{ Translator.trans('export') }}
      </a>

      <dp-column-selector
        class="ml-auto"
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
        <template v-slot:street="rowData">
          <div class="o-hellip--nowrap">
            <span v-cleanhtml="rowData.street" />
          </div>
        </template>
        <template v-slot:postalCodeAndCity="rowData">
          <div class="o-hellip--nowrap">
            <span v-cleanhtml="rowData.postalCodeAndCity" />
          </div>
        </template>
        <template v-slot:internId="{ internId }">
          <div
            class="o-hellip__wrapper">
            <div
              v-text="internId"
              class="o-hellip--nowrap text-right"
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
import { CleanHtml, dpApi, DpColumnSelector, DpDataTable, DpLoading } from '@demos-europe/demosplan-ui'

export default {
  name: 'DpSubmitterList',

  components: {
    DpColumnSelector,
    DpDataTable,
    DpLoading
  },

  directives: { cleanhtml: CleanHtml },

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
        { field: 'street', label: Translator.trans('street') },
        { field: 'postalCodeAndCity', label: Translator.trans('postalcode') + ' / ' + Translator.trans('city') },
        { field: 'organisationAndDepartment', label: Translator.trans('organisation') + ' / ' + Translator.trans('department') },
        { field: 'memo', label: Translator.trans('memo') },
        { field: 'internId', label: Translator.trans('internId.shortened'), colClass: 'w-8' },
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
              'initialOrganisationCity',
              'initialOrganisationDepartmentName',
              'initialOrganisationName',
              'initialOrganisationPostalCode',
              'initialOrganisationHouseNumber',
              'initialOrganisationStreet',
              'isCitizen',
              'isSubmittedByCitizen',
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
     * @return {{internId: (*|string), isCitizen: *, organisationAndDepartment: (*|string), street: (string|*), name: (*|string), statement: *, memo: (*|string), id, postalCodeAndCity: (string|*), email: (*|string)}}
     */
    handleEmptyAttrs (resourceObj) {
      const {
        authorName,
        externId,
        internId,
        initialOrganisationCity: city,
        initialOrganisationDepartmentName: departmentName,
        initialOrganisationHouseNumber: houseNumber,
        initialOrganisationName: organisationName,
        initialOrganisationPostalCode: postalCode,
        initialOrganisationStreet: street,
        isCitizen,
        isSubmittedByCitizen,
        memo,
        submitterEmailAddress: email,
        submitName
      } = resourceObj.attributes

      return {
        email: email || '-',
        id: resourceObj.id,
        internId: internId || '',
        isCitizen,
        memo: memo || '-',
        name: authorName || submitName || '-',
        organisationAndDepartment: this.handleOrgaAndDepartment(departmentName, organisationName, isSubmittedByCitizen),
        postalCodeAndCity: this.handleOrgaPostalCodeAndOrgaCity(city, postalCode),
        statement: externId,
        street: this.handleOrgaStreet(street, houseNumber)
      }
    },

    handleOrgaAndDepartment (departmentName, organisationName, isSubmittedByCitizen) {
      if (organisationName) {
        if (isSubmittedByCitizen) {
          return organisationName
        }
        return departmentName ? organisationName + ', ' + departmentName : organisationName
      }
      return departmentName || '-'
    },

    handleOrgaPostalCodeAndOrgaCity (city, postalCode) {
      if (postalCode) {
        return city ? postalCode + ' ' + city : postalCode
      }
      return city || '-'
    },

    handleOrgaStreet (street, houseNumber) {
      if (street) {
        return houseNumber ? street + ' ' + houseNumber : street
      }
      return '-'
    },

    setCurrentSelection (selection) {
      this.currentSelection = selection
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
