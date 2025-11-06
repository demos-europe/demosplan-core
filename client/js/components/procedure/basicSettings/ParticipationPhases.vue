<template>
  <div>
    <div class="layout">
      <div class="layout__item u-2-of-3">
        <dp-select
          v-model="selectedPhase"
          :data-cy="`${dataCy}:select`"
          :label="phaseLabel"
          :name="fieldName"
          :options="phaseOptions"
          required
          @select="$emit('phase:select', $event)"
        />
        <span
          v-if="isWizardMode && helpText"
          class="inline-block font-size-small u-mb-0_5">
          {{ helpText }}
        </span>
      </div>

      <div
        v-if="hasPermission('field_phase_iterator')"
        class="layout__item u-1-of-3">
        <dp-input
          :id="iterator.name"
          :data-cy="`${dataCy}:iterator`"
          :label="iteratorLabel"
          :model-value="iterator.value"
          :name="iterator.name"
          pattern="^[1-9][0-9]*$"
          required
        />
        <span
          v-if="isWizardMode && iterator.hint"
          class="inline-block font-size-small u-mb-0_5">
          {{ iterator.hint }}
        </span>
      </div>
    </div>

    <dp-inline-notification
      :message="permissionMessageText"
      class="mt-3 mb-2"
      type="warning"
    />

    <div
      v-if="hasPermission('feature_auto_switch_to_procedure_end_phase') && !hasPermission('feature_auto_switch_procedure_phase') && isInParticipation"
      class="lbl__hint u-mt-0_25 u-mb-0"
    >
      {{ autoswitchHint }}
    </div>
  </div>
</template>

<script>
import {
  DpInlineNotification,
  DpInput,
  DpSelect,
} from '@demos-europe/demosplan-ui'

export default {
  name: 'ParticipationPhases',

  components: {
    DpInlineNotification,
    DpInput,
    DpSelect,
  },

  props: {
    autoswitchHint: {
      type: String,
      required: false,
      default: '',
    },

    dataCy: {
      type: String,
      required: false,
      default: '',
    },

    fieldName: {
      type: String,
      required: false,
      default: '',
    },

    helpText: {
      type: String,
      required: false,
      default: '',
    },

    labelText: {
      type: String,
      required: false,
      default: '',
    },

    initSelectedPhase: {
      type: String,
      required: false,
      default: '',
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
        const requiredKeys = ['label', 'name', 'hint', 'value']
        let keyCounter = 0

        Object.keys(val).forEach(key => {
          if (requiredKeys.includes(key)) {
            keyCounter++
          }
        })

        return keyCounter === requiredKeys.length
      },
    },

    participationPhases: {
      type: Array,
      required: false,
      default: () => [],
    },

    permissionMessage: {
      type: String,
      required: false,
      default: '',
    },

    phaseOptions: {
      type: Array,
      required: false,
      default: () => [],
    },
  },

  emits: [
    'phase:select',
  ],

  data () {
    return {
      selectedPhase: this.initSelectedPhase,
      isWizardMode: false,
      wizardObserver: null,
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
    },

    phaseLabel () {
      return {
        text: this.labelText,
        ...(!this.isWizardMode && { tooltip: this.helpText }),
      }
    },

    iteratorLabel () {
      return {
        text: this.iterator.label,
        ...(!this.isWizardMode && { tooltip: this.iterator.hint }),
      }
    },
  },

  mounted () {
    this.checkWizardMode()
    this.observeWizardMode()
  },

  beforeUnmount () {
    if (this.wizardObserver) {
      this.wizardObserver.disconnect()
    }
  },

  methods: {
    checkWizardMode () {
      const form = document.querySelector('form[name="configForm"]')
      this.isWizardMode = form ? form.classList.contains('o-wizard-mode') : false
    },

    observeWizardMode () {
      const form = document.querySelector('form[name="configForm"]')
      if (!form) return

      // Watch for class changes on the form element
      this.wizardObserver = new MutationObserver(() => {
        this.checkWizardMode()
      })

      this.wizardObserver.observe(form, {
        attributes: true,
        attributeFilter: ['class'],
      })
    },
  },
}
</script>
