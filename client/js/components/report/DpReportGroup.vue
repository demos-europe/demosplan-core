<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div :id="'report__' + group">
    <h2>{{ Translator.trans(groupLabel) }}</h2>

    <template v-if="hasItems">
      <dp-data-table
        :header-fields="fields"
        :items="Object.values(items)"
        track-by="id">
        <template v-slot:date="rowData">
          <span
            v-text="createdDateItem(rowData)"
            v-tooltip="createdDateTimeItem(rowData)" />
        </template>
        <template v-slot:content="rowData">
          <div
            class="break-words"
            v-cleanhtml="rowData.attributes.message" />
        </template>
        <template v-slot:user="rowData">
          {{ rowData.attributes.createdByDataInputOrga ? rowData.attributes.orgaName : rowData.attributes.userName }}
        </template>
      </dp-data-table>

      <div class="layout u-mv-0_5">
        <div
          v-if="totalPages > 1"
          class="layout__item u-1-of-2">
          <dp-sliding-pagination
            :current="currentPage"
            :total="totalPages"
            :non-sliding-size="10"
            :aria-label="paginationLabel"
            @page-change="handlePageChange" />
        </div><!--
     --><div class="layout__item u-1-of-2">
        <dp-loading
          v-if="isLoading"
          class="u-mt-0_5 float-right" />
        </div>
      </div>
    </template>

    <p v-else>
      {{ Translator.trans('text.protocol.no.entries') }}
    </p>
  </div>
</template>

<script>
import { CleanHtml, DpDataTable, DpLoading, DpSlidingPagination, formatDate } from '@demos-europe/demosplan-ui'

export default {
  name: 'DpReportGroup',

  components: {
    DpDataTable,
    DpLoading,
    DpSlidingPagination
  },

  directives: {
    cleanhtml: CleanHtml
  },

  props: {
    contentLabel: {
      type: String,
      required: true
    },

    groupLabel: {
      type: String,
      required: true
    },

    group: {
      type: String,
      required: true
    },

    items: {
      type: Object,
      required: true
    },

    currentPage: {
      type: Number,
      required: true
    },

    totalPages: {
      type: Number,
      required: true
    },

    isLoading: {
      required: true,
      type: Boolean
    }
  },

  data () {
    return {
      fields: [
        {
          colClass: 'u-1-of-5',
          field: 'date',
          label: Translator.trans('date')
        },
        {
          colClass: 'u-3-of-5',
          field: 'content',
          label: Translator.trans(this.contentLabel)
        },
        {
          colClass: 'u-1-of-5',
          field: 'user',
          label: Translator.trans('user')
        }
      ]
    }
  },

  computed: {
    hasItems () {
      return Object.keys(this.items).length > 0
    },

    paginationLabel () {
      return 'Seitennavigation für die Protokoll-Gruppe "' + this.groupLabel + '"'
    }
  },

  methods: {
    createdDateItem (item) {
      return formatDate(item.attributes.created)
    },

    createdDateTimeItem (item) {
      return `${formatDate(item.attributes.created, 'long')}`
    },

    handlePageChange (requestedPage) {
      this.$emit('page-change', requestedPage)
    }
  }
}
</script>
