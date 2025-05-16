<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentation>
  <!-- This component is a child of DpVersionHistory.vue and a wrapper around DpVersionHistoryItem.vue, which contains
    the changes for a specific time -->
</documentation>

<template>
  <tbody class="o-accordion align-top">
    <tr>
      <td colspan="4">
        <h3 class="border--bottom u-pb-0_5 u-mb-0_5 u-mt">
          {{ formattedDate }}
        </h3>
      </td>
    </tr>

    <tr class="color--grey">
      <td
        class="u-pb-0_5 u-pl-0_5"
        style="width: 15%;">
        {{ Translator.trans('time') }}
      </td>
      <td
        class="u-pb-0_5 u-pl-0_5"
        style="width: 40%;">
        {{ Translator.trans('user') }}
      </td>
      <td
        class="u-pb-0_5"
        style="width: 40%;">
        {{ Translator.trans('fields') }}
      </td>
      <td
        class="u-pb-0_5"
        style="width: 5%" />
    </tr>

    <dp-version-history-item
      v-for="(time, idx) in filteredItems"
      :key="idx"
      :procedure-id="procedureId"
      :day="day"
      :time="time.attributes"
      :entity="entity" />
  </tbody>
</template>

<script>
import DpVersionHistoryItem from './DpVersionHistoryItem'
import { formatDate } from '@demos-europe/demosplan-ui'

export default {
  name: 'DpVersionHistoryDay',

  components: {
    DpVersionHistoryItem
  },

  props: {
    allTimes: {
      type: Array,
      required: false,
      default: () => []
    },

    date: {
      type: String,
      required: false,
      default: ''
    },

    day: {
      type: Object,
      required: false,
      default: () => ({})
    },

    entity: {
      type: String,
      required: true
    },

    procedureId: {
      type: String,
      required: true
    }
  },

  data () {
    return {
      formattedDate: formatDate(this.date)
    }
  },

  computed: {
    filteredItems () {
      const dayTimes = this.day.relationships.historyTimes.data
      const filteredTimes = []
      this.allTimes.forEach(time => {
        dayTimes.forEach(dayTime => {
          if (time.id === dayTime.id) {
            filteredTimes.push(time)
          }
        })
      })
      return filteredTimes
    }
  }
}
</script>
