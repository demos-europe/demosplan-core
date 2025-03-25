<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <dp-modal
    ref="boilerPlateModal"
    content-classes="u-1-of-2">
    <h3>{{ Translator.trans('boilerplate.insert') }}</h3>
    <dp-boiler-plate
      :title="Translator.trans('boilerplates.category', { category: Translator.trans(boilerPlateType) })"
      :boiler-plates="displayedBoilerplates"
      ref="boilerplateDropdown"
      group-values="boilerplates"
      group-label="groupName"
      :group-select="false"
      @boilerplate-text-added="addBoilerplateText" />
    <div class="flex items-center u-mt">
      <a
        class="weight--bold font-size-small"
        :href="Routing.generate('DemosPlan_procedure_boilerplate_list', { procedure: procedureId })">
        {{ Translator.trans('boilerplates.edit') }} ({{ Translator.trans('view.leave.hint') }})
      </a>
      <dp-button-row
        class="ml-auto"
        :disabled="{ primary: textToBeAdded === '' }"
        primary
        :primary-text="Translator.trans('insert')"
        secondary
        @primary-action="insertBoilerPlate"
        @secondary-action="resetAndClose" />
    </div>
  </dp-modal>
</template>

<script>
import { DpButtonRow, DpModal } from '@demos-europe/demosplan-ui'
import { mapActions, mapGetters, mapState } from 'vuex'
import DpBoilerPlate from '@DpJs/components/statement/DpBoilerPlate'

export default {
  name: 'DpBoilerPlateModal',

  components: {
    DpBoilerPlate,
    DpButtonRow,
    DpModal
  },

  props: {
    // Needed to get boilerplates from BE via store
    procedureId: {
      required: true,
      type: String
    },

    /**
     * Defines which boilerplate types we want to see in modal. Possible are: consideration, email, news.notes
     */
    boilerPlateType: {
      required: false,
      type: String,
      default: ''
    },

    editorId: {
      required: false,
      type: String,
      default: ''
    }
  },

  data () {
    return {
      // Needed to make sure boilerplates are loaded from the BE before <dp-boiler-plate> component is mounted
      boilerPlatesLoaded: false,
      // The boilerplate text emitted from dp-boiler-plate, then emitted to TipTapTextEditor.vue on click of 'insert' button
      textToBeAdded: ''
    }
  },

  computed: {
    ...mapState('Boilerplates', ['getBoilerplatesRequestFired', 'moduleRegistered']),
    ...mapGetters('Boilerplates', ['getGroupedBoilerplates']),

    displayedBoilerplates () {
      const displayed = JSON.parse(JSON.stringify(this.getGroupedBoilerplates))
      displayed.forEach(group => {
        if (this.boilerPlateType !== '') {
          if (typeof this.boilerPlateType === 'string') {
            group.boilerplates = group.boilerplates.filter(bp => bp.category.includes(this.boilerPlateType))
          } else if (Array.isArray(this.boilerPlateType)) {
            group.boilerplates = group.boilerplates.filter(bp => this.boilerPlateType.some(el => bp.category.includes(el)))
          }
        }
      })

      return displayed
    },

    displayedBoilerplateType () {
      let boilerplateString = ''
      if (typeof this.boilerPlateType === 'string') {
        boilerplateString = Translator.trans(this.boilerPlateType)
      } else if (Array.isArray(this.boilerPlateType)) {
        boilerplateString = this.boilerPlateType.map(bp => Translator.trans(bp)).join(', ')
      }
      return boilerplateString
    }
  },

  methods: {
    ...mapActions('Boilerplates', ['getBoilerPlates']),

    addBoilerplateText (textFromTextArea) {
      this.textToBeAdded = textFromTextArea
    },

    insertBoilerPlate () {
      this.$emit('insert', this.textToBeAdded)
      this.resetAndClose()
    },

    resetAndClose () {
      this.$refs.boilerplateDropdown.resetBoilerPlateMultiSelect()
      this.textToBeAdded = ''
      this.toggleModal()
    },

    toggleModal () {
      if (hasPermission('area_admin_boilerplates')) {
        this.$refs.boilerPlateModal.toggle()
      }
    }
  },

  created () {
    if (this.getBoilerplatesRequestFired === false) {
      this.getBoilerPlates(this.procedureId)
    }
  }
}
</script>
