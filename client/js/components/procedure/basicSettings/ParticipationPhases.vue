<template>
  <div>
    <div class="flex gap-2">
      <dp-select
        class="w-8/12"
        :label="{
          text: labelText,
          tooltip: helpText
        }"
        :name="fieldName"
        :options="phaseOptions"
        required
        v-model="selectedPhase" />

      <dp-input
        v-if="hasPermission('field_phase_iterator')"
        width="w-4/12"
        :label="{
          text: iterator.label,
          tooltip: iterator.tooltip
        }"
        :name="iterator.name"
        :value="iterator.value"
        pattern="^[1-9][0-9]*$"
        required />
    </div>

    <dp-inline-notification
      :message="permissionMessageText"
      type="warning" />

    <div
      v-if="hasPermission('feature_auto_switch_to_procedure_end_phase') && !hasPermission('feature_auto_switch_procedure_phase') && isInParticipation"
      class="lbl__hint u-mt-0_25 u-mb-0">
      {{ autoswitchHint }}
    </div>
  </div>
</template>

<script>
import {
  DpInlineNotification,
  DpInput,
  DpSelect
} from '@demos-europe/demosplan-ui'

export default {
  name: 'ParticipationPhases',

  components: {
    DpInlineNotification,
    DpInput,
    DpSelect
  },

  props: {
    autoswitchHint: {
      type: String,
      required: false,
      default: ''
    },

    fieldName: {
      type: String,
      required: false,
      default: ''
    },

    helpText: {
      type: String,
      required: false,
      default: ''
    },

    labelText: {
      type: String,
      required: false,
      default: ''
    },

    initSelectedPhase: {
      type: String,
      required: false,
      default: ''
    },

    /**
     * Manually set iterator for the phase
     * The Value can only be a number larger than 0 ( > 0 )
     */
    iterator: {
      type: Object,
      required: false,
      default: () => ({}),
      validate: val => {
        const requiredKeys = ['label', 'name', 'tooltip', 'value']
        let keyCounter = 0

        Object.keys(val).forEach(key => {
          if (requiredKeys.includes(key)) {
            keyCounter++
          }
        })

        return keyCounter === requiredKeys.length
      }
    },

    participationPhases: {
      type: Array,
      required: false,
      default: () => []
    },

    permissionMessage: {
      type: String,
      required: false,
      default: ''
    },

    phaseOptions: {
      type: Array,
      required: false,
      default: () => []
    }
  },

  data () {
    return {
      selectedPhase: this.initSelectedPhase
    }
  },

  computed: {
    permissionMessageText () {
      const currentPhase = this.phaseOptions.find(option => option.value === this.selectedPhase)
       /*
       * Generated Trans-Keyes:
       *
       * 'permissionset.hidden'
       * 'permissionset.read'
       * 'permissionset.write'
       */
      const permissionsetMessage =  Translator.trans(`permissionset.${currentPhase.permissionset}`)
     
      return `${this.permissionMessage} ${permissionsetMessage}`
    },

    isInParticipation () {
      return this.participationPhases.includes(this.selectedPhase)
    }
  }
}
</script>
