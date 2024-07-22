<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <section class="u-mb">
    <template v-if="fragments.length">
      <dp-statement-fragment
        :csrf-token="csrfToken"
        :is-archive="isArchive"
        v-for="fragment in fragments"
        :key="missKeyValue(fragment.id, 0)"
        :fragment-id="missKeyValue(fragment.id, 0)"
        :statement-id="missKeyValue(fragment.statement.id, 0)"
        :current-user-id="currentUserId"
        :current-user-name="currentUserName"
        :advice-values="adviceValues" />
    </template>
    <article
      v-else
      class="c-at-item u-mb">
      {{ Translator.trans('fragments.none') }}
    </article>
  </section>
</template>

<script>
import AnimateById from '@DpJs/lib/shared/AnimateById'
import DpStatementFragment from './Fragment'
import { mapMutations } from 'vuex'

export default {
  name: 'DpFragmentList',

  components: {
    DpStatementFragment
  },

  props: {
    csrfToken: {
      type: String,
      required: true
    },

    isArchive: {
      type: Boolean,
      required: false,
      default: false
    },

    fragments: {
      type: Array,
      required: true
    },

    currentUserId: {
      type: String,
      required: false,
      default: ''
    },

    currentUserName: {
      type: String,
      required: false,
      default: ''
    },

    adviceValues: {
      type: Object,
      required: false,
      default: () => ({})
    }
  },

  methods: {
    ...mapMutations('AssessmentTable', ['setAssessmentBaseProperty']),

    missKeyValue (value, defaultValue) {
      if (typeof value === 'undefined' || value === null) {
        if (typeof defaultValue === 'undefined' || value === null) {
          defaultValue = ''
        }

        return defaultValue
      }

      return value
    }
  },

  created () {
    /*
     *  Cant use assessmentTable.getBaseData in the "Fachbehörde"-Views since this requires a procedureId
     *  which is not present here. So we have to work around this by manually setting the required values
     *  as props and passing it to the store with setAssessmentBaseProperty(). We still have to use the store since Status.vue
     *  expects this.$store.getters['assessmentTable/adviceValues'] to return something.
     */
    this.setAssessmentBaseProperty({ prop: 'adviceValues', val: this.adviceValues })

    const fragments = this.fragments
    let i = fragments ? fragments.length : 0

    /*
     * We have to do such weird stuff because the data-structure is not the same as n the a-table.
     * we can what for the JSON-API-Magic or think about refactoring the fragment-store, that we don't have the statement-Layer in between
     */
    while (i--) {
      this.$store.commit('fragment/addFragment', {
        statementId: this.fragments[i].statement.id,
        fragment: this.fragments[i]
      })
    }
  },

  mounted () {
    AnimateById()
  }
}
</script>
