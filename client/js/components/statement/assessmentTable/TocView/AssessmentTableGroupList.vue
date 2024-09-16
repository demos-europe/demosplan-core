<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <ul class="o-list o-list--card">
    <assessment-table-group
      v-for="subgroup in subgroups"
      :csrf-token="csrfToken"
      :key="`subGroup:${subgroup.title}`"
      :count="1"
      :group="subgroup" />
  </ul>
</template>

<script>
import AssessmentTableGroup from './AssessmentTableGroup'
import { mapGetters } from 'vuex'

export default {
  name: 'AssessmentTableGroupList',

  components: {
    AssessmentTableGroup
  },

  props: {
    csrfToken: {
      type: String,
      required: true
    },

    formDefinitions: {
      type: Object,
      required: false,
      default: () => ({})
    }
  },

  computed: {
    ...mapGetters('Statement', {
      getToc: 'getToc'
    }),

    /**
     *
     * @return {Array<Object>}
     */
    subgroups () {
      return this.getToc.subgroups
    }
  }
}
</script>
