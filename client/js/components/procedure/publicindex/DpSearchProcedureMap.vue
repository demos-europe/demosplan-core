<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <div :class="prefixClass('c-proceduresearch__search-wrapper layout__item flex')">
      <dp-autocomplete
        v-if="dplan.settings.useOpenGeoDb"
        id="procedure_search"
        ref="autocomplete"
        v-model="currentAutocompleteSearch"
        :class="prefixClass('c-proceduresearch__search-field')"
        data-cy="searchProcedureMapForm:procedureSearch"
        height="34px"
        label="value"
        name="search"
        :options="autocompleteOptions"
        :placeholder="Translator.trans('procedure.public.search.placeholder')"
        :route-generator="(searchString) => {
          return Routing.generate('DemosPlan_procedure_public_suggest_procedure_location_json', {
            maxResults: 12,
            query: searchString
          })
        }"
        @search-changed="updateSuggestions"
        @searched="search => setValueAndSubmitForm({ target: { value: search } }, 'search')"
        @selected="search => setValueAndSubmitForm({ target: { value: search.value } }, 'search')" />

      <template v-else>
        <dp-input
          id="procedure_search_simple"
          v-model="currentAutocompleteSearch"
          :class="prefixClass('c-proceduresearch__search-field')"
          data-cy="searchProcedureMapForm:procedureSearch"
          :label="{
            hide: true,
            text: Translator.trans('procedure.public.search.placeholder')
          }"
          name="search"
          :placeholder="Translator.trans('procedure.public.search.placeholder')"
          width="auto"
          @enter="form.search = currentAutocompleteSearch; submitForm();" />
      </template>

      <button
        type="button"
        data-cy="searchProcedureMapForm:procedureSearchSubmit"
        :class="prefixClass('c-proceduresearch__search-btn btn btn--primary weight--bold')"
        @click.prevent="form.search = currentAutocompleteSearch; submitForm();">
        {{ Translator.trans('searching') }}
      </button>
    </div>

    <div :class="prefixClass('layout__item u-mb-0_75')">
      <button
        type="reset"
        data-cy="searchProcedureMapForm:resetToDefault"
        :disabled="form.search === '' && isDefaultFilter"
        :class="prefixClass('c-proceduresearch__reset-btn')"
        @click.prevent="resetAndSubmit">
        <i
          :class="prefixClass('fa fa-close u-mr-0_25')"
          aria-hidden="true" />
        {{ Translator.trans('reset.to.default') }}
      </button>
    </div>

    <!-- Trigger to show filters on mobile -->
    <div :class="prefixClass('layout__item u-1-of-1-palm hide-lap-up-ib')">
      <button
        type="button"
        :class="prefixClass('btn btn--primary weight--bold block u-1-of-1')"
        data-cy="searchProcedureMapForm:toggleFilter"
        @click.prevent="showFilter = !showFilter">
        Filter
      </button>
    </div>

    <!-- Sorting -->
    <div :class="prefixClass('u-pt-0_5-palm ' + (showFilter ? 'block' : 'hidden'))">
      <template v-if="sortOptions.length > 1">
        <label
          for="sort"
          :class="prefixClass('c-proceduresearch__filter-label layout__item u-1-of-1 u-mb-0_25')">
          {{ Translator.trans('sortation') }}
        </label><!--
     --><div :class="prefixClass('layout__item u-1-of-1 u-mb')">
          <select
            id="sort"
            data-cy="searchProcedureMapForm:sort"
            name="sort"
            :class="prefixClass('o-form__control-select')"
            @change="setValueAndSubmitForm($event, 'sort')"
            :value="form.sort">
            <option
              v-for="option in sortOptions"
              :key="'sort_' + option.value"
              :selected="option.selected"
              :value="option.value">
              {{ option.title }}
            </option>
          </select>
        </div>
      </template>

      <!-- Filter: Municipal code -->
      <label
        v-if="hasPermission('feature_procedures_show_municipal_filter')"
        :class="prefixClass('c-proceduresearch__filter-label layout__item u-1-of-1 u-mb-0_25')"
        for="municipalCode">
        Kreis
      </label><!--
   --><div
        v-if="hasPermission('feature_procedures_show_municipal_filter')"
        :class="prefixClass('layout__item u-1-of-1 u-mb')">
        <select
          id="municipalCode"
          :class="prefixClass('o-form__control-select')"
          data-cy="searchProcedureMapForm:municipalCode"
          name="municipalCode"
          @change="setValueAndSubmitForm($event, 'municipalCode')">
          <template
            v-for="municipalityGroup in municipalities"
            :key="`group_${municipalityGroup.label}`">
            <optgroup
              v-if="hasOwnProp(municipalityGroup,'options')"
              :label="municipalityGroup.label">
              <option
                v-for="(county, idx) in municipalityGroup.options"
                :key="`county:${idx}`"
                :selected="county.value === form.municipalCode"
                :value="county.value">
                {{ county.title }}
              </option>
            </optgroup>
            <option
              v-else
              :key="`opt_${municipalityGroup.value}`"
              :value="municipalityGroup.value">
              {{ municipalityGroup.label }}
            </option>
          </template>
        </select>
      </div>

      <!-- All other filters -->
      <template
        v-for="(filter, idx) in filters"
        :key="'label_' + idx">
        <label
          :for="filter.name"
          :class="prefixClass('c-proceduresearch__filter-label layout__item u-mb-0_25 u-1-of-1')">
          {{ filter.title }}
          <dp-contextual-help
            v-if="filter.contextHelp !== ''"
            class="u-ml-0_25"
            :text="filter.contextHelp" />
        </label><!--
     --><div :class="prefixClass('layout__item u-1-of-1 u-mb')">
          <select
            :id="filter.name"
            :ref="'filter_' + idx"
            :class="prefixClass('o-form__control-select')"
            :data-cy="'searchProcedureMapForm:' + filter.name"
            :name="filter.name"
            @change="setValueAndSubmitForm($event, filter.name)">
            <option value="">
              {{ Translator.trans('all') }}
            </option>
            <option
              v-for="(filterOption, index) in filter.options"
              :key="'filter_opt_' + index"
              :value="filterOption.value">
              {{ filterOption.label }}
            </option>
          </select>
        </div>
      </template>
    </div>

    <h2
      v-if="displayArsFilterHeader"
      :class="prefixClass('u-pl')"
      id="urlFilterResultsHeader">
      {{ searchResultsHeader }}
    </h2>
    <div v-else>
      <dp-loading
        v-if="isLoading"
        class="u-mt u-ml" />
      <div v-else>
        <h2
          v-if="isSearch"
          id="searchResultHeading"
          aria-live="assertive"
          :class="prefixClass('layout__item font-size-h2 u-pr u-mb c-proceduresearch__result')"
          role="status">
          Die Suche nach <span :class="prefixClass('c-proceduresearch__term weight--bold')">{{ currentSearch }}</span> hatte {{ resultCount }} Ergebnis
        </h2>
        <h2
          v-else-if="isNoSearchAndNoResult"
          id="noSearchResultHeading"
          :class="prefixClass('layout__item font-size-h2 u-pr u-mb c-proceduresearch__result')"
          role="status">
          {{ Translator.trans('search.results.none') }}.
        </h2>
        <template v-else-if="isDefaultFilter && resultCount === Translator.trans('none.neutral')">
          <h2 :class="prefixClass('layout__item font-size-h2 c-proceduresearch__result')">
            {{ Translator.trans('procedures.participation.none') }}
          </h2>
          <button
            type="button"
            :class="prefixClass('btn btn--primary u-ml u-mb')"
            data-cy="searchProcedureMapForm:showAllProcedures"
            @click.prevent="showAllProcedures">
            {{ Translator.trans('procedures.all.show') }}
          </button>
        </template>
      </div>
    </div>
  </div>
</template>
<script>
import {
  DpAutocomplete,
  DpContextualHelp,
  DpInput,
  DpLoading,
  hasOwnProp,
  makeFormPost,
  prefixClassMixin
} from '@demos-europe/demosplan-ui'
import proj4 from 'proj4'

export default {
  name: 'DpSearchProcedureMap',

  components: {
    DpAutocomplete,
    DpContextualHelp,
    DpInput,
    DpLoading
  },

  mixins: [prefixClassMixin],

  props: {
    countyCode: {
      type: String,
      default: ''
    },

    municipalCode: {
      type: String,
      default: ''
    },

    filters: {
      type: Array,
      default: () => []
    },

    initDisplayArsFilterHeader: {
      type: Boolean,
      default: false
    },

    initSearchTerm: {
      type: String,
      default: ''
    },

    municipalities: {
      type: Array,
      default: () => []
    },

    orgaSlug: {
      type: String,
      default: '',
      required: false
    },

    searchResultsHeader: {
      type: String,
      default: ''
    },

    sortOptions: {
      type: Array,
      default: () => []
    },

    useOpenGeoDb: {
      type: Boolean,
      default: false
    }
  },

  data () {
    return {
      resultCount: Translator.trans('following'),
      currentAutocompleteSearch: '',
      currentSearch: Translator.trans('entries.all.dative'),
      initialForm: {},
      isLoading: false,
      form: {
        orgaSlug: this.orgaSlug,
        search: '',
        sort: (this.sortOptions.length > 0) ? this.sortOptions.filter(opt => opt.selected)[0].value : '',
        municipalCode: '',
        ...this.filters.reduce((acc, filter) => {
          acc[filter.name] = ''
          return acc
        }, {})
      },
      autocompleteOptions: [],
      displayArsFilterHeader: this.initDisplayArsFilterHeader,
      showFilter: true
    }
  },

  computed: {
    isDefaultFilter () {
      return JSON.stringify(this.form) === JSON.stringify(this.initialForm)
    },

    isNoSearchAndNoResult () {
      return this.currentSearch === Translator.trans('entries.all.dative') && this.resultCount === Translator.trans('none.neutral') && this.isDefaultFilter === false
    },

    isSearch () {
      return this.currentSearch !== Translator.trans('entries.all.dative')
    }
  },

  methods: {
    fitToBounds () {
      setTimeout(() => {
        if (window.markersLayer.getLayers().length > 0) {
          const bounds = window.markersLayer.getBounds().pad(0.2)
          window.map.fitBounds(bounds)

          if (window.map.getZoom() > 16) {
            window.map.setZoom(12)
          }
        }
      }, 200)
    },

    hasOwnProp (obj, prop) {
      return hasOwnProp(obj, prop)
    },

    removeDefaultFilters () {
      if (this.form.phasePermissionset) {
        this.form.phasePermissionset = ''
        this.setFilterSelectValue('phasePermissionset', '')
      }
      if (this.form.publicParticipationPhasePermissionset) {
        this.form.publicParticipationPhasePermissionset = ''
        this.setFilterSelectValue('publicParticipationPhasePermissionset', '')
      }
    },

    resetAndSubmit () {
      // Reset needs to reset everything even on prefiltered by ars procedure list
      window.location.href = Routing.generate('core_home')
    },

    resetFilterSelects () {
      this.filters.forEach((_, idx) => {
        const filter = this.$refs[`filter_${idx}`]
        // As per the docs the result of $refs here will be an array because ref was used in a v-for https://vuejs.org/v2/api/#ref
        filter[0].selectedIndex = 0
      })

      this.setDefaultFilters()
    },

    resetFormData () {
      this.form = JSON.parse(JSON.stringify(this.initialForm))
    },

    resetSearch () {
      this.currentAutocompleteSearch = ''
    },

    resetURL () {
      const noFilterURL = window.location.href.split('?')[0]
      window.history.pushState({ html: noFilterURL, pageTitle: document.title }, document.title, noFilterURL)
    },

    setDefaultFilters () {
      if (hasPermission('feature_procedure_default_filter_intern')) {
        this.form.phasePermissionset = 'write'
        this.setFilterSelectValue('phasePermissionset', 'write')
      }
      if (hasPermission('feature_procedure_default_filter_extern')) {
        this.form.publicParticipationPhasePermissionset = 'write'
        this.setFilterSelectValue('publicParticipationPhasePermissionset', 'write')
      }
    },

    setFilterSelectValue (filterName, value) {
      const filterSelect = document.getElementById(filterName)
      filterSelect.value = value
    },

    setValueAndSubmitForm (e, key) {
      this.form[key] = e.target.value
      this.submitForm()
    },

    showAllProcedures () {
      this.resetSearch()
      this.resetFormData()
      this.resetFilterSelects()
      this.removeDefaultFilters()
      this.resetURL()

      this.submitForm()
      this.displayArsFilterHeader = false
    },

    submitForm () {
      return makeFormPost(this.form, Routing.generate('DemosPlan_procedure_public_list_json')).then(({ data }) => {
        const parsedData = JSON.parse(data)
        if (parsedData.code === 100 && parsedData.success === true) {
          // The response contains a html snippet to be directly rendered inside the procedure list container
          document.querySelector('[data-procedurelist-content]').innerHTML = parsedData.responseHtml
          this.resultCount = parsedData.procedureCount > 0 ? Translator.trans('following') : Translator.trans('none.neutral')
          this.currentSearch = this.form.search === '' ? Translator.trans('entries.all.dative') : this.form.search
          if (hasPermission('feature_public_index_map')) {
            this.updateMapFeatures(parsedData.mapVars)
          }
        }
        this.isLoading = false
      })
    },

    updateMapFeatures (mapVars) {
      // If there is no map, don't try to interact with it
      if (typeof map === 'undefined') {
        return
      }

      if (mapVars.length === 0) {
        // If there are no procedures found, don't show the info that there are no procedures in the shown bounding box - the hint, that there are no procedures for the filter/search is below the filters
        const noProcedureNotification = document.getElementById('noProcedureNotification')
        if (noProcedureNotification.classList.contains(this.prefixClass('hidden')) === false) {
          noProcedureNotification.classList.add(this.prefixClass('hidden'))
        }
      }

      const escapeRegExp = function (str) {
        return str.replace(/([.*+?^=!:${}()|[\]/\\])/g, '\\$1')
      }
      const replaceAll = function (str, find, replace) {
        return str.replace(new RegExp(escapeRegExp(find), 'g'), replace)
      }

      const markerTemplate = `<div class="${this.prefixClass('map_popup')}">` +
        `<h2 class="${this.prefixClass('font-size-small u-ml-0')}"><a href="___procedureUrl___">___title___</a></h2>` +
        `<p class="${this.prefixClass('font-size-smaller u-ml-0')}">` +
        `<strong>${Translator.trans('procedure.public.phase')}</strong>: ___phase___<br>` +
        `<strong>${Translator.trans('period')}</strong>: ___start___ - ___end___</p>` +
        `<p class="${this.prefixClass('font-size-smaller u-ml-0')}">___shortText___</p>` +
        `<p class="${this.prefixClass('font-size-smaller u-m-0')}">` +
        `<a href="___procedureUrl___" class="${this.prefixClass('btn btn--primary')}">${Translator.trans('detail.view')}</a>` +
        '</p>' + '</div>'
      window.markersLayer.clearLayers()
      let index

      proj4.defs(window.dplan.defaultProjectionLabel, window.dplan.defaultProjectionString)
      for (index = 0; index < mapVars.length; ++index) {
        // Test both coordinates for int or float, skip item if not
        const coordinateX = mapVars[index].coordinateX
        const coordinateY = mapVars[index].coordinateY
        if (isNaN(coordinateX) || isNaN(coordinateY)) {
          continue
        }
        const LMarker = window.L.marker
        const marker = new LMarker(proj4(window.dplan.defaultProjectionLabel, 'WGS84', [parseFloat(coordinateX), parseFloat(coordinateY)]).reverse())
        marker.bindPopup(
          replaceAll(markerTemplate
            .replace('___title___', mapVars[index].externalName)
            .replace('___start___', mapVars[index].publicParticipationStartDate)
            .replace('___end___', mapVars[index].publicParticipationEndDate)
            .replace('___phase___', mapVars[index].publicParticipationPhaseName)
            .replace('___shortText___', mapVars[index].externalDesc)
          , '___procedureUrl___', mapVars[index].procedureUrl
          ))
        marker.key = mapVars[index].procedureId
        window.markersLayer.addLayer(marker)
      }

      this.fitToBounds()
    },

    updateSuggestions ({ data }) {
      if (hasOwnProp(data, 'meta') && hasOwnProp(data, 'data') && data.meta.code < 400 && data.meta.success === true) {
        this.autocompleteOptions = data.data.suggestions
      } else {
        this.autocompleteOptions = []
      }
    }
  },

  mounted () {
    this.setDefaultFilters()
    this.initialForm = JSON.parse(JSON.stringify(this.form))

    if (this.displayArsFilterHeader) {
      this.form.ars = this.countyCode
      this.form.municipalCode = this.municipalCode
    }

    this.submitForm()
  }
}
</script>
