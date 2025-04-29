<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div
    v-if="element"
    class="flex space-inline-xs">
    <a
      class="weight--bold u-mr-auto"
      data-cy="documentCategoryName"
      :href="Routing.generate('DemosPlan_elements_administration_edit', {
        procedure: dplan.procedureId,
        elementId: elementId
      })">
      {{ element.attributes.title }}
    </a>
    <dp-contextual-help
      v-if="hasPermission('feature_auto_switch_element_state') && element.attributes.designatedSwitchDate !== null"
      icon="clock"
      large
      :text="designatedSwitchDate" />
    <dp-toggle
      v-if="element.attributes.category !== 'paragraph'"
      class="u-mt-0_125"
      data-cy="categoryStatusSwitcher"
      :disabled="element.attributes.designatedSwitchDate !== null"
      v-model="itemEnabled"
      v-tooltip="Translator.trans(itemEnabled ? 'published' : 'unpublished')" />
  </div>
</template>

<script>
import { DpContextualHelp, DpToggle, formatDate } from '@demos-europe/demosplan-ui'
import { mapActions, mapMutations, mapState } from 'vuex'

export default {
  name: 'ElementsAdminItem',

  components: {
    DpContextualHelp,
    DpToggle
  },

  props: {
    elementId: {
      type: String,
      required: true
    }
  },

  computed: {
    ...mapState('Elements', {
      elements: 'items'
    }),

    designatedSwitchDate () {
      return `${Translator.trans('phase.autoswitch.datetime')}: ${formatDate(this.element.attributes.designatedSwitchDate, 'long')}`
    },

    element () {
      return this.elements[this.elementId] || null
    },

    itemEnabled: {
      get () {
        return this.element.attributes.enabled
      },

      set (val) {
        if (val !== this.itemEnabled) {
          this.updateToggleElement({
            id: this.element.id,
            type: this.element.type,
            attributes: {
              ...this.element.attributes,
              enabled: val
            }
          })

          this.saveToggleElement(this.elementId)
            .then(() => {
              dplan.notify.confirm(Translator.trans('confirm.saved'))
            })
            .catch(() => {
              dplan.notify.error(Translator.trans('error.changes.not.saved'))
            })
        }
      }
    }
  },

  methods: {
    ...mapActions('Elements', {
      saveToggleElement: 'save'
    }),

    ...mapMutations('Elements', {
      updateToggleElement: 'setItem'
    })
  }
}
</script>
