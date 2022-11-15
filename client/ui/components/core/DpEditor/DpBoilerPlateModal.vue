<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentation>
  <!-- This component contains the UI for inserting boilerplates into a textarea

    If used in combination with DpEditor.vue and its wrapper TipTapEditText.vue (as is currently the case), the
    button to open the modal is located in TipTapEditText.vue; otherwise, you need to define it.

   -->
</documentation>

<template>
  <dp-modal
    ref="boilerPlateModal"
    content-classes="u-1-of-2">
    <template>
      <h3>{{ Translator.trans('boilerplate.insert') }}</h3>
      <dp-boiler-plate
        :title="Translator.trans('boilerplates.category', { category: Translator.trans(boilerPlateType) })"
        :boiler-plates="displayedBoilerplates"
        ref="boilerplateDropdown"
        group-values="boilerplates"
        group-label="groupName"
        :group-select="false"
        @boilerplate-text-added="addBoilerplateText" />
      <div class="flex flex-items-center u-mt">
        <a
          class="weight--bold font-size-small"
          :href="Routing.generate('DemosPlan_procedure_boilerplate_list', { procedure: procedureId })">
          {{ Translator.trans('boilerplates.edit') }} ({{ Translator.trans('view.leave.hint') }})
        </a>
        <dp-button-row
          class="flex-item-end"
          primary
          :primary-text="Translator.trans('insert')"
          secondary
          @primary-action="insertBoilerPlate"
          @secondary-action="resetAndClose" />
      </div>
    </template>
  </dp-modal>
</template>

<script>
import { mapActions, mapGetters, mapState } from 'vuex'
import BoilerplatesStore from '@DpJs/store/procedure/Boilerplates'
import DpBoilerPlate from './DpBoilerPlate'
import DpButtonRow from '../DpButtonRow'
import DpModal from '../DpModal'
import { hasOwnProp } from 'demosplan-utils'

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
    ...mapState('boilerplates', ['getBoilerplatesRequestFired', 'moduleRegistered']),
    ...mapGetters('boilerplates', ['getGroupedBoilerplates']),

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
    ...mapActions('boilerplates', ['getBoilerPlates']),

    addBoilerplateText (textFromTextArea) {
      this.textToBeAdded = textFromTextArea
    },

    insertBoilerPlate () {
      this.$emit('insertBoilerPlate', this.textToBeAdded)
      this.resetAndClose()
    },

    resetAndClose () {
      this.$refs.boilerplateDropdown.resetBoilerPlateMultiSelect()
      this.toggleModal()
    },

    toggleModal () {
      this.$refs.boilerPlateModal.toggle()
    }
  },

  created () {
    const isRegistered = this.$store && hasOwnProp(this.$store.state, 'boilerplates')

    if (isRegistered === false) {
      this.$store.registerModule('boilerplates', BoilerplatesStore)
    }

    if (this.getBoilerplatesRequestFired === false) {
      this.getBoilerPlates(this.procedureId)
    }
  },

  beforeDestroy () {
    this.$store.unregisterModule('boilerplates')
  }

}
</script>
