<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <form
    id="subscriptionForm"
    :action="Routing.generate('DemosPlan_procedure_list_subscriptions')"
    method="post">
    <input
      name="_token"
      type="hidden"
      :value="csrfToken">

    <h2 class="font-size-large u-mt-0_5">
      {{ Translator.trans('notification.create') }}
    </h2>

    <div class="flex space-inline-s">
      <dp-autocomplete
        v-if="dplan.settings.useOpenGeoDb"
        class="u-nojs-hide inline-block w-11 bg-color--white"
        height="32px"
        label="value"
        :options="postalCodeOptions"
        :placeholder="Translator.trans('autocomplete.label')"
        :route-generator="(searchString) => {
          return Routing.generate('core_suggest_location_json', {
            maxResults: 50,
            query: searchString
          })
        }"
        track-by="value"
        @search-changed="handleSearchChanged"
        @selected="handleSelected" />

      <input
        v-model="postalCode"
        type="hidden"
        name="r_postalCode">

      <input
        v-model="city"
        type="hidden"
        name="r_city">

      <dp-select
        id="r_radius"
        name="r_radius"
        :options="[5,10,50].map(i => {
          return { label: i + ' km', value: i }
        })"
        :show-placeholder="false" />

      <div>
        <dp-button
          name="newSubscription"
          :text="Translator.trans('save')"
          type="submit" />
      </div>
    </div>

    <h2 class="font-size-large u-mt-1_5">
      {{ Translator.trans('notifications.active') }}
    </h2>

    <template v-if="subscriptions.length > 0">
      <button
        class="btn--blank o-link--default weight--bold u-ml-0_25"
        name="deleteSubscription"
        :data-form-actions-confirm="Translator.trans('check.entries.marked.delete')"
        type="submit">
        <i
          class="fa fa-times-circle u-mr-0_25"
          aria-hidden="true" />
        {{ Translator.trans('items.marked.delete') }}
      </button>

      <dp-data-table
        :header-fields="headerFields"
        is-selectable
        is-selectable-name="region_selected[]"
        :items="subscriptions"
        track-by="id">
        <template v-slot:radius="rowData">
          {{ rowData.radius }} km
        </template>
        <template v-slot:created="rowData">
          {{ date(rowData.created) }}
        </template>
      </dp-data-table>
    </template>

    <dp-inline-notification
      v-else
      class="mt-3 mb-2"
      :message="Translator.trans('explanation.noentries')"
      type="info" />
  </form>
</template>

<script>
import { DpAutocomplete, DpButton, DpSelect, formatDate } from '@demos-europe/demosplan-ui'

export default {
  name: 'ListSubscriptions',

  components: {
    DpAutocomplete,
    DpButton,
    DpDataTable: async () => {
      const { DpDataTable } = await import('@demos-europe/demosplan-ui')
      return DpDataTable
    },
    DpInlineNotification: async () => {
      const { DpInlineNotification } = await import('@demos-europe/demosplan-ui')
      return DpInlineNotification
    },
    DpSelect
  },

  props: {
    csrfToken: {
      type: String,
      required: true
    },

    subscriptions: {
      type: Array,
      required: false,
      default: () => []
    }
  },

  data () {
    return {
      city: null,
      headerFields: [
        { field: 'postalcode', label: Translator.trans('postalcode') },
        { field: 'city', label: Translator.trans('city') },
        { field: 'radius', label: Translator.trans('radius') },
        { field: 'created', label: Translator.trans('date.created') }
      ],
      postalCode: null,
      postalCodeOptions: []
    }
  },

  methods: {
    date (date) {
      return formatDate(date)
    },

    handleSearchChanged (search) {
      /*
       * The `core_suggest_location_json` returns both suggestions with or without postalCode.
       * We only want the former, so to prevent some searches to return too less results on first
       * typing, maxResults is raised to 50 and then, after filtering out suggestions without postcode
       * or name, capped at 10 results.
       */
      this.postalCodeOptions = search.data.suggestions
        .filter(suggestion => suggestion.data.postcode && suggestion.data.name)
        .slice(0, 10)
    },

    handleSelected (suggestion) {
      this.postalCode = suggestion.data.postcode
      this.city = suggestion.data.name
    }
  }
}
</script>
