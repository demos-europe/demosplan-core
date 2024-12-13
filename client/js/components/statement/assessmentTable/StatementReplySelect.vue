<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <h4 class="font-size-large u-mt u-mb-0 u-pt-0_5 border--top">
      {{ Translator.trans('statement.recommendation') }}
    </h4>

    <dp-checkbox
      id="r_replied"
      v-model="checked"
      class="u-mb-0_5 u-1-of-2 inline-block align-middle"
      :disabled="readonly"
      :label="{
        text: Translator.trans('statement.in.compass.was.answered')
      }"
      name="r_replied" />
  </div>
</template>

<script>
import { DpCheckbox, DpLabel, DpMultiselect } from '@demos-europe/demosplan-ui'

export default {
  name: 'StatementReplySelect',

  components: {
    DpCheckbox,
    DpLabel,
    DpMultiselect
  },

  props: {
    initialAnswer: {
      type: Object,
      default: () => ({
        id: '',
        title: '',
        url: ''
      })
    },

    isStatementReplied: {
      type: Boolean,
      default: false
    },

    readonly: {
      type: Boolean,
      default: false
    },

    replyOptions: {
      type: Array,
      required: true
    }
  },

  data () {
    return {
      checked: true,
      selected: {}
    }
  },

  computed: {
    options () {
      return [...this.replyOptions]
        .sort((a, b) => a.title.localeCompare(b.title, 'de', { sensitivity: 'base' }))
    }
  },

  watch: {
    checked: {
      handler (val) {
        if (!val) {
          this.setEmptyValue()
        }
      },
      deep: false // Set default for migrating purpose. To know this occurrence is checked
    }
  },

  methods: {
    setEmptyValue () {
      this.selected = { id: '', title: '' }
    },

    setInitialValue () {
      this.selected = { ...this.initialAnswer }
    }
  },

  mounted () {
    this.checked = this.isStatementReplied
    this.setInitialValue()
  }
}
</script>
