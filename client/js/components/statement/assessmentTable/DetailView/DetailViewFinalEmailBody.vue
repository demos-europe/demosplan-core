<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <dp-editor
    :data-cy="dataCy"
    hidden-input="r_send_body"
    :toolbar-items="toolbarItems"
    v-model="text"
    @input="$emit('emailBody:input', $event)">
    <template v-slot:modal="modalProps">
      <dp-boiler-plate-modal
        v-if="hasPermission('area_admin_boilerplates')"
        ref="boilerPlateModal"
        boiler-plate-type="email"
        :procedure-id="procedureId"
        @insert="text => modalProps.handleInsertText(text)" />
    </template>
    <template v-slot:button>
      <button
        v-if="hasPermission('area_admin_boilerplates')"
        :class="prefixClass('menubar__button')"
        type="button"
        v-tooltip="Translator.trans('boilerplate.insert')"
        @click.stop="openBoilerPlate">
        <i :class="prefixClass('fa fa-puzzle-piece')" />
      </button>
    </template>
  </dp-editor>
</template>

<script>
import DpBoilerPlateModal from '@DpJs/components/statement/DpBoilerPlateModal'
import { prefixClassMixin } from '@demos-europe/demosplan-ui'

export default {
  name: 'AssessmentStatementDetailFinalEmail',

  components: {
    DpBoilerPlateModal,
    DpEditor: async () => {
      const { DpEditor } = await import('@demos-europe/demosplan-ui')
      return DpEditor
    }
  },

  mixins: [prefixClassMixin],

  props: {
    dataCy: {
      type: String,
      required: false,
      default: 'statementDetailFinalEmailBody'
    },

    initText: {
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
      text: this.initText,
      toolbarItems: {
        headings: [1, 2, 3],
        linkButton: true
      }
    }
  },

  watch: {
    initText (newVal) {
      this.text = newVal
    }
  },

  methods: {
    openBoilerPlate () {
      if (hasPermission('area_admin_boilerplates')) {
        this.$refs.boilerPlateModal.toggleModal()
      }
    }
  }
}
</script>
