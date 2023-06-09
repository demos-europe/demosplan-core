<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <dp-select
      class="u-1-of-2"
      :label="{
        text: Translator.trans('procedure.type')
      }"
      :options="procedureTypes"
      :selected="selectedId"
      @select="handleSelect" />
  </div>
</template>

<script>
import { DpSelect } from '@demos-europe/demosplan-ui/src'

export default {
  name: 'ProcedureTypeSelect',

  components: {
    DpSelect
  },

  props: {
    procedureTypes: {
      type: Array,
      required: false,
      default: () => ([])
    },

    selectedId: {
      type: String,
      required: false,
      default: ''
    }
  },

  data () {
    return {
      selectedProcedureTypeId: this.selectedId
    }
  },

  methods: {
    handleSelect (id) {
      this.selectedProcedureTypeId = id
      this.redirectToEditForm()
    },

    redirectToEditForm () {
      window.location.href = Routing.generate('DemosPlan_procedureType_duplicate', { procedureTypeId: this.selectedProcedureTypeId, isCreate: true })
    }
  }
}
</script>
