<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <dp-inline-notification
      class="mt-3 mb-2"
      :message="Translator.trans('excel.import.error', { count: errors.length, entities: Translator.trans(context) })"
      type="error" />

    <p
      v-if="errors.length > 1"
      class="u-mt">
      {{ Translator.trans('excel.import.errors.checklist.hint', { entities: Translator.trans(context) }) }}
    </p>

    <div
      v-for="(worksheet, i) in worksheets"
      :key="`worksheet:${i}`"
      class="u-mt-1_5">
      <h3 class="font-size-medium">
        {{ Translator.trans('worksheet') }}: {{ worksheet }}
      </h3>
      <ul class="u-mt-0_5 u-mb border-color--grey-light-1 rounded-lg">
        <li
          v-for="error in errorsByWorksheet(worksheet)"
          :key="`error:${error.id}`"
          class="u-p-0_5 u-mv-0_5 cursor-pointer"
          @click="toggle(error.id)"
          :class="itemClasses(error.id, worksheet)">
          <div v-if="errors.length > 1">
            <dp-checkbox
              :id="`error:${error.id}`"
              :checked="checkedItems[error.id]"
              class="inline-block"
              :label="{
                bold: true,
                text: lineTransKey(error.lineNumber)
              }" />
            <span>
              {{ error.message }}
            </span>
          </div>
          <div v-else>
            <span class="weight--bold">
              {{ lineTransKey(error.lineNumber) }}
            </span>
            <span>
              {{ error.message }}
            </span>
          </div>
        </li>
      </ul>
    </div>

    <dp-progress-bar
      v-if="errors.length > 1"
      :label="Translator.trans('done.capital') + ':'"
      :percentage="completedPercent" />

    <a
      :href="Routing.generate('DemosPlan_procedure_import', { procedureId: procedureId })"
      class="btn btn--primary u-mv">
      <i
        class="fa fa-angle-left u-pr-0_25"
        aria-hidden="true" />
      {{ Translator.trans('upload.again') }}
    </a>
  </div>
</template>

<script>
import { DpCheckbox, DpInlineNotification, DpProgressBar } from '@demos-europe/demosplan-ui'

export default {
  name: 'ExcelImportErrors',

  components: {
    DpCheckbox,
    DpInlineNotification,
    DpProgressBar
  },

  props: {
    // Possible values: 'statements' or 'segments'
    context: {
      type: String,
      required: true
    },

    errors: {
      type: Array,
      required: true
    },

    procedureId: {
      type: String,
      required: true
    }
  },

  data () {
    return {
      checkedItems: [],
      worksheets: []
    }
  },

  computed: {
    completedPercent () {
      const checked = this.checkedItems.filter(val => val === true)
      return checked.length / this.errors.length * 100
    },

    errorsByWorksheet () {
      return worksheetName => this.errors.filter(err => err.currentWorksheet === worksheetName)
    },

    itemClasses () {
      return (errorId, worksheetName) =>
        [
          this.checkedItems[errorId] === true
            ? 'color--grey-light'
            : '',
          this.errorsByWorksheet(worksheetName).findIndex(err => err.id === errorId) !== this.errorsByWorksheet(worksheetName).length - 1
            ? 'u-mb-0_5 border--bottom'
            : '',
          this.checkedItems.findIndex(item => item.id === errorId) !== 0
            ? 'u-mt-0_5'
            : ''
        ]
    }
  },

  methods: {
    lineTransKey (number) {
      return Translator.trans('line', { line: number })
    },

    toggle (id) {
      this.$set(this.checkedItems, id, !this.checkedItems[id])
    }
  },

  mounted () {
    this.errors.forEach(error => {
      this.$set(this.checkedItems, error.id, false)
      if (!this.worksheets.includes(error.currentWorksheet)) {
        this.worksheets.push(error.currentWorksheet)
      }
    })
  }
}
</script>
