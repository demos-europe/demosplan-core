<template>
  <div>
    <div class="flex gap-2">
      <dp-select
        v-model="selectedPhase"
        class="w-8/12"
        :data-cy="`${dataCy}:select`"
        :label="{
          text: labelText,
          tooltip: helpText
        }"
        :name="fieldName"
        :options="phaseOptions"
        required
        @select="$emit('phase:select', $event)" />

      <dp-input
        v-if="hasPermission('field_phase_iterator')"
        :id="iterator.name"
        :data-cy="`${dataCy}:iterator`"
        :label="{
          text: iterator.label,
          tooltip: iterator.tooltip
        }"
        :name="iterator.name"
        pattern="^[1-9][0-9]*$"
        required
        :value="iterator.value"
        width="w-4/12" />
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

    dataCy: {
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
      validator: val => {
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
      const currentPhase = this.phaseOptions.find(option => option.value === this.selectedPhase) || this.phaseOptions[0]
      /*
       * Generated Trans-Keys:
       *
       * 'permissionset.hidden'
       * 'permissionset.read'
       * 'permissionset.write'
       */
      const permissionsetMessage = Translator.trans(`permissionset.${currentPhase.permissionset}`)

      return `${this.permissionMessage} ${permissionsetMessage}`
    },

    isInParticipation () {
      return this.participationPhases.includes(this.selectedPhase)
    }
  }
}
</script>
