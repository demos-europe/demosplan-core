<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

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
      name="r_replied"
      class="u-mb-0_5 u-1-of-2 display--inline-block u-valign--middle"
      :label="Translator.trans('statement.in.compass.was.answered')"
      v-model="checked"
      :disabled="readonly" /><!--
 --><div class="u-1-of-2 display--inline-block">
      <dp-label
        for="r_bthg_kompass_answer"
        :text="Translator.trans('paragraph')" />
      <dp-multiselect
        id="r_bthg_kompass_answer"
        :options="options"
        track-by="id"
        v-model="selected"
        :required="checked"
        :disabled="!checked || readonly"
        label="title">
        <template v-slot:option="{ option }">
          <span class="font-size-small">{{ option.breadcrumbTrail }}</span><br>
          {{ option.title }}
        </template>
      </dp-multiselect>
      <input
        type="hidden"
        :value="selected.id"
        name="r_bthg_kompass_answer">
    </div>
  </div>
</template>

<script>
import DpCheckbox from '@DpJs/components/core/form/DpCheckbox'
import { DpLabel } from 'demosplan-ui/components'
import DpMultiselect from '@DpJs/components/core/form/DpMultiselect'

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
    checked () {
      if (!this.checked) {
        this.setEmptyValue()
      }
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
