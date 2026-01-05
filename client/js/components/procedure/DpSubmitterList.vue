<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <p>{{ Translator.trans('explanation.list.of.submitters') }}</p>

    <div class="flex items-center u-pv-0_5">
      <a
        :href="exportSubmitterList"
        download
      >
        <i
          class="fa fa-download"
          aria-hidden="true"
        />
        {{ Translator.trans('export') }}
      </a>

      <dp-column-selector
        class="ml-auto"
        data-cy="submitterList:selectableColumns"
        :initial-selection="currentSelection"
        :selectable-columns="selectableColumns"
        use-local-storage
        local-storage-key="submitterList"
        @selection-changed="setCurrentSelection"
      />
    </div>

    <dp-loading v-if="isLoading" />

    <template v-else>
      <dp-data-table
        v-if="items.length"
        class="overflow-x-auto"
        :header-fields="headerFields"
        :items="items"
        track-by="id"
      >
        <template v-slot:statement="rowData">
          <a
            :href="getSegmentsListItemUrl(rowData)"
            :aria-label="Translator.trans('aria.navigate.statement.details', { name: rowData.statement })"
            data-cy="submitterList:navigate:segmentsListItem"
          >
            {{ rowData.statement }}
          </a>
        </template>

        <template v-slot:internId="{ internId }">
          <div class="o-hellip__wrapper">
            <div
              v-tooltip="internId"
              class="o-hellip--nowrap text-right"
              dir="rtl"
              v-cleanhtml="internId"
            />
          </div>
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

        <template v-slot:similarSubmitters="rowData">
          <span v-if="rowData.similarSubmittersCount === '-'">
            {{ rowData.similarSubmittersCount }}
          </span>
          <a
            v-else
            :href="getSimilarSubmittersUrl(rowData)"
            :aria-label="Translator.trans('aria.navigate.statement.details', { name: rowData.statement })"
            data-cy="submitterList:navigate:similarSubmitters"
          >
            {{ rowData.similarSubmittersCount }}
          </a>
        </template>
      </dp-data-table>

      <div v-else>
        <p class="flash flash-info">
          {{ Translator.trans('statements.submitted.none') }}
        </p>
      </div>
    </template>
  </div>
</template>

<script>
import { CleanHtml, DpColumnSelector, DpDataTable, DpLoading } from '@demos-europe/demosplan-ui'
import { mapActions, mapState } from 'vuex'

export default {
  name: 'DpSubmitterList',

  components: {
    DpColumnSelector,
    DpDataTable,
    DpLoading,
  },

  directives: { cleanhtml: CleanHtml },

  props: {
    procedureId: {
      type: String,
      required: true,
    },
  },

  data () {
    return {
      headerFieldsAvailable: [
        { field: 'name', label: Translator.trans('name') },
        { field: 'statement', label: Translator.trans('id'), tooltip: Translator.trans('id.statement.long') },
        { field: 'internId', label: Translator.trans('internId.shortened'), colClass: 'w-8' },
        { field: 'email', label: Translator.trans('email') },
        { field: 'street', label: Translator.trans('street') },
        { field: 'postalCodeAndCity', label: Translator.trans('postalcode') + ' / ' + Translator.trans('city') },
        { field: 'organisationAndDepartment', label: Translator.trans('organisation') + ' / ' + Translator.trans('department') },
        { field: 'similarSubmitters', label: Translator.trans('statement.similarSubmitters') },
        { field: 'memo', label: Translator.trans('memo') },
      ],
      isLoading: false,
      currentSelection: ['name', 'organisationAndDepartment', 'statement'],
    }
  },

  computed: {
    ...mapState('Statement', {
      statements: 'items',
    }),

    exportSubmitterList () {
      return Routing.generate('dplan_admin_procedure_submitter_export', {
        procedureId: this.procedureId,
      })
    },

    headerFields () {
      return this.headerFieldsAvailable.filter(headerField => this.currentSelection.includes(headerField.field))
    },

    items () {
      return Object.values(this.statements)
        .map(statement => this.handleEmptyAttrs(statement))
        .sort((a, b) => Number(a.isCitizen) - Number(b.isCitizen))
    },

    selectableColumns () {
      return this.headerFieldsAvailable.map(headerField => ([headerField.field, headerField.label]))
    },
  },

  methods: {
    ...mapActions('Statement', {
      statementList: 'list',
    }),

    fetchStatements () {
      this.isLoading = true

      const statementFields = [
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
        'similarStatementSubmitters',
        'submitName',
        'submitterEmailAddress',
      ]

      const hasSimilarSubmitterFeature = hasPermission('feature_similar_statement_submitter')

      if (hasSimilarSubmitterFeature) {
        statementFields.push('similarStatementSubmitters')
      }

      const params = {
        filter: {
          procedureId: {
            condition: {
              path: 'procedure.id',
              value: this.procedureId,
            },
          },
        },
        fields: {
          Statement: statementFields.join(),
        },
        ...(hasSimilarSubmitterFeature && { include: 'similarStatementSubmitters' })
      }

      this.statementList(params)
        .finally(() => {
          this.isLoading = false
      })
    },

    getSegmentsListItemUrl (rowData) {
      return Routing.generate('dplan_statement_segments_list', { statementId: rowData.id, procedureId: this.procedureId })
    },

    getSimilarSubmittersUrl (rowData) {
      const submittersHash = `#submitter`

      const url = Routing.generate('dplan_statement_segments_list', {
        statementId: rowData.id,
        procedureId: this.procedureId,
        action: 'editText',
      })

      return `${url}${submittersHash}`
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
        submitName,
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
        similarSubmittersCount: resourceObj.relationships.similarStatementSubmitters.data.length || '-',
        statement: externId,
        street: this.handleOrgaStreet(street, houseNumber),
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
  },
  mounted () {
    this.fetchStatements()
  },
}
</script>
