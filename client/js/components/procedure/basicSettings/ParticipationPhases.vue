<template>
  <div>
    <div class="flex gap-2">
      <div class="w-8-12">
        <dp-select
          :label="{
            text: labelText,
            tooltip: helpText
          }"
          :name="fieldName"
          :options="phaseOptions"
          required
          v-model="selectedPhase" />
      </div>

      <div
        v-if="true || hasPermission('field_phase_iterator')"
        class="w-4/12 inline-block relative">
        <dp-label
          for="r_public_participation_phase_iteration"
          :text="iterator.label"
          :tooltip="iterator.tooltip"
          required />
        <dp-input
          name="r_public_participation_phase_iteration"
          :value="iterator.value"
          pattern="^[1-9][0-9]*$" />
      </div>
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
  DpLabel,
  DpSelect
} from '@demos-europe/demosplan-ui'

export default {
  name: 'ParticipationPhases',

  components: {
    DpInlineNotification,
    DpInput,
    DpLabel,
    DpSelect
  },

  props: {
    autoswitchHint: {
      type: String,
      required: false,
      default: ''
    },

    labelText: {
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

    initSelectedPhase: {
      type: String,
      required: false,
      default: ''
    },

    iterator: {
      type: Object,
      required: false,
      default: () => ({})
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
      return this.permissionMessage + Translator.trans(`permissionset.${currentPhase.permissionset}`)
    },

    isInParticipation () {
      return this.participationPhases.includes(this.selectedPhase)
    }
  }
}
</script>
