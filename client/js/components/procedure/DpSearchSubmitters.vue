<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <dp-input
      id="searchinput"
      v-model="searchTerm"
      class="inline-block align-top u-1-of-3"
      :label="{
        hide: true,
        text: Translator.trans('search.submitter')
      }"
      @enter="search"
    />
    <dp-button
      class="ml-1"
      :text="Translator.trans('search')"
      @click="search"
    />
    <div class="u-mt-0_75">
      <fieldset class="u-pb-0 u-mb u-1-of-3 layout__item u-pl-0">
        <div class="u-mb-0_5">
          <dp-radio
            id="searchall"
            v-model="searchIn"
            name="searchselection"
            value="all"
            :label="{ text: Translator.trans('search.all.procedures') }"
          />
        </div>
        <dp-radio
          id="searchselected"
          v-model="searchIn"
          name="searchselection"
          value="selected"
          :label="{ text: Translator.trans('select.procedures.search') }"
        />
      </fieldset><!--
   --><div
        v-if="searchIn === 'selected'"
        class="layout__item u-2-of-3 u-pl-0"
      >
        <label
          for="procedureselect"
          class="inline u-mr"
        >
          {{ Translator.trans('select.procedures.search.chose') }}
        </label><!--
     --><dp-multiselect
          id="procedureselect"
          v-model="proceduresToSearch"
          class="inline-block u-2-of-3 align-text-top"
          :options="searchableProcedures"
          track-by="id"
          :multiple="true"
          label="name"
        />
      </div>
    </div>
    <ul class="o-list o-list--table u-mb">
      <li
        v-for="result in results"
        :key="result.id"
        class="o-list__item flex items-center justify-between"
      >
        <span>
          <strong>{{ result.attributes.externId }}</strong>
          — {{ result.attributes.status }}
          — {{ result.attributes.authorName || result.attributes.submitName }}
          ({{ procedureNameById[result.relationships.procedure.data.id] }})
        </span>
        <dp-button
          :text="Translator.trans('remove')"
          icon="trash"
          variant="subtle"
          hide-text
          @click="deleteStatement(result.id)"
        />
      </li>
      <li
        v-if="noResults"
        v-cleanhtml="Translator.trans('search.no.results', {searchterm: lastSearchedTerm})"
        class="o-list__item"
      />
    </ul>
  </div>
</template>

<script>
import { CleanHtml, dpApi, DpButton, DpInput, DpMultiselect, DpRadio } from '@demos-europe/demosplan-ui'

export default {
  name: 'DpSearchSubmitters',

  components: {
    DpButton,
    DpMultiselect,
    DpInput,
    DpRadio,
  },

  directives: {
    cleanhtml: CleanHtml,
  },

  props: {
    searchableProcedures: {
      type: Array,
      required: true,
    },
  },

  data () {
    return {
      searchTerm: '',
      lastSearchedTerm: '',
      searchIn: 'all',
      proceduresToSearch: [],
      results: [],
      included: [],
      noResults: false,
    }
  },

  computed: {
    procedureNameById () {
      return this.included.reduce((acc, resource) => {
        acc[resource.id] = resource.attributes.name

        return acc
      }, {})
    },
  },

  methods: {
    search () {
      this.noResults = false

      if (this.searchIn === 'selected' && this.proceduresToSearch.length === 0) {
        dplan.notify.warning(Translator.trans('warning.no.selected.procedures'))

        return
      }

      if (this.searchTerm.length === 0) {
        return
      }

      this.lastSearchedTerm = this.searchTerm
      const url = Routing.generate('api_resource_list', { resourceType: 'AdminStatementCrossProcedureSearch' })
      const params = {
        filter: {
          authorOrSubmitter: {
            group: {
              conjunction: 'OR',
            },
          },
          withAuthor: {
            condition: {
              path: 'authorName',
              value: this.searchTerm,
              operator: 'STRING_CONTAINS_CASE_INSENSITIVE',
              memberOf: 'authorOrSubmitter',
            },
          },
          withSubmitter: {
            condition: {
              path: 'submitName',
              value: this.searchTerm,
              operator: 'STRING_CONTAINS_CASE_INSENSITIVE',
              memberOf: 'authorOrSubmitter',
            },
          },
        },
        include: 'procedure',
        fields: {
          AdminStatementCrossProcedureSearch: [
            'externId',
            'status',
            'authorName',
            'submitName',
            'initialOrganisationName',
            'procedure',
          ].join(),
          AdminProcedure: ['name'].join(),
        },
      }

      if (this.searchIn === 'selected') {
        params.filter.idIsOneOf = {
          condition: {
            path: 'procedure.id',
            value: this.proceduresToSearch.map(procedure => procedure.id),
            operator: 'IN',
          },
        }
      }

      dpApi.get(url, params)
        .then(response => {
          this.results = response.data.data
          this.included = response.data.included || []
          this.noResults = this.results.length === 0
        })
        .catch(() => {
          dplan.notify.error(Translator.trans('error.api.generic'))
        })
    },

    deleteStatement (statementId) {
      const url = Routing.generate('api_resource_delete', {
        resourceType: 'AdminStatementCrossProcedureSearch',
        resourceId: statementId,
      })

      dpApi.delete(url)
        .then(() => {
          this.results = this.results.filter(result => result.id !== statementId)
        })
        .catch(() => {
          dplan.notify.error(Translator.trans('error.api.generic'))
        })
    },
  },
}
</script>
