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
      @enter="search" />
    <dp-button
      class="ml-0.5 mt-0.5"
      :text="Translator.trans('search')"
      @click="search" />
    <div class="u-mt-0_75">
      <fieldset class="u-pb-0 u-mb u-1-of-3 layout__item u-pl-0">
        <div class="u-mb-0_5">
          <dp-radio
            id="searchall"
            name="searchselection"
            value="all"
            v-model="searchIn"
            :label="{ text: Translator.trans('search.all.procedures') }" />
        </div>
        <dp-radio
          id="searchselected"
          name="searchselection"
          value="selected"
          v-model="searchIn"
          :label="{ text: Translator.trans('select.procedures.search') }" />
      </fieldset><!--
   --><div
        class="layout__item u-2-of-3 u-pl-0"
        v-if="searchIn === 'selected'">
        <label
          for="procedureselect"
          class="inline u-mr">
          {{ Translator.trans('select.procedures.search.chose') }}
        </label><!--
     --><dp-multiselect
          class="inline-block u-2-of-3 align-text-top"
          id="procedureselect"
          :options="searchableProcedures"
          track-by="id"
          :multiple="true"
          label="name"
          v-model="proceduresToSearch" />
      </div>
    </div>
    <ul class="o-list o-list--table u-mb">
      <li
        class="o-list__item"
        v-for="result in results"
        :key="result.id">
        <a :href="Routing.generate('dplan_assessmenttable_view_table', { procedureId: result.id })">{{ result.attributes.name }}</a>
      </li>
      <li
        class="o-list__item"
        v-if="noResults"
        v-cleanhtml="Translator.trans('search.no.results', {searchterm: lastSearchedTerm})" />
    </ul>
  </div>
</template>

<script>
import { CleanHtml, dpApi, DpButton, DpInput, DpMultiselect, DpRadio } from '@demos-europe/demosplan-ui'

export default {
  name: 'DpSearchProcedures',

  components: {
    DpButton,
    DpMultiselect,
    DpInput,
    DpRadio
  },

  directives: {
    cleanhtml: CleanHtml
  },

  props: {
    searchableProcedures: {
      type: Array,
      required: true
    }
  },

  data () {
    return {
      searchTerm: '',
      lastSearchedTerm: '',
      searchIn: 'all',
      proceduresToSearch: [],
      results: [],
      noResults: false
    }
  },

  methods: {
    search () {
      this.noResults = false

      if (this.searchIn === 'selected' && this.proceduresToSearch.length === 0) {
        dplan.notify.warning(Translator.trans('warning.no.selected.procedures'))
      } else if (this.searchTerm.length) {
        this.lastSearchedTerm = this.searchTerm
        const queryProcedures = ((this.searchIn === 'selected' && this.proceduresToSearch) || this.searchableProcedures)
          .map(procedure => procedure.id)
        const url = Routing.generate('api_resource_list', { resourceType: 'AdminProcedure' })
        const params = {
          filter: {
            authorOrSubmitter: {
              group: {
                conjunction: 'OR'
              }
            },
            withAuthor: {
              condition: {
                path: 'statements.authorName',
                value: this.searchTerm,
                operator: 'STRING_CONTAINS_CASE_INSENSITIVE',
                memberOf: 'authorOrSubmitter'
              }
            },
            withSubmitter: {
              condition: {
                path: 'statements.submitName',
                value: this.searchTerm,
                operator: 'STRING_CONTAINS_CASE_INSENSITIVE',
                memberOf: 'authorOrSubmitter'
              }
            }
          },
          sort: '-creationDate,name',
          fields: {
            AdminProcedure: [
              'id',
              'name'
            ].join()
          }
        }
        if (this.searchIn === 'selected') {
          params.filter.idIsOneOf = {
            condition: {
              path: 'id',
              value: queryProcedures,
              operator: 'IN'
            }
          }
        }
        dpApi.get(url, params)
          .then(response => {
            this.results = response.data.data
            if (this.results.length === 0) {
              this.noResults = true
            }
          })
          .catch(() => {
            dplan.notify.error(Translator.trans('error.api.generic'))
          })
      }
    }
  }
}
</script>
