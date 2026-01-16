<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div class="flow-root">
    <dp-checkbox
      :id="checkboxId"
      v-model="autoSwitchPhase"
      :data-cy="`autoSwitchPhase:${checkboxId}`"
      :disabled="hasPermission('feature_auto_switch_to_procedure_end_phase') && isParticipationPhaseSelected"
      :label="{
        text: Translator.trans('procedure.public.phase.autoswitch')
      }"
      :name="checkboxId"
    />

    <transition
      name="slide-fade"
      mode="out-in"
    >
      <div
        v-if="!hasPermission('feature_auto_switch_to_procedure_end_phase') || (hasPermission('feature_auto_switch_to_procedure_end_phase') && !isParticipationPhaseSelected)"
        class="layout u-mt-0_25 u-pl"
      >
        <dp-select
          v-model="selectedPhase"
          :data-cy="`selectedPhase:${checkboxId}`"
          :disabled="!autoSwitchPhase"
          :label="{
            text: Translator.trans('procedure.phase.autoswitch.targetphase')
          }"
          :name="phaseSelectId"
          :options="phaseOptions"
          class="layout__item u-1-of-3 u-1-of-1-lap-down"
        /><!--

     --><div class="layout__item u-1-of-3 u-1-of-1-lap-down">
          <div class="layout">
            <div class="layout__item w-2/3 pr-2">
              <dp-label
                :for="switchDateId"
                :text="Translator.trans('phase.autoswitch.datetime')"
                class="mb-0.5"
                required
              />
              <dp-datepicker
                :id="switchDateId"
                v-model="switchDateOnly"
                :data-cy="`autoSwitchProcedurePhaseForm:${switchDateId}`"
                :disabled="!autoSwitchPhase"
                hidden-input
                :max-date="switchDateMax"
                :min-date="minSwitchDate"
                :name="`${switchDateId}_date_only`"
                required
              />
              <!-- Hidden input with combined datetime for backend -->
              <input
                type="hidden"
                :name="switchDateId"
                :value="switchDate"
              >
            </div><!--
         --><div class="layout__item w-1/3 pl-2">
              <dp-label
                :for="`${switchDateId}_time`"
                :text="Translator.trans('time')"
                class="mb-0.5"
                required
              />
              <dp-input
                :id="`${switchDateId}_time`"
                v-model="switchTime"
                :data-cy="`autoSwitchProcedurePhaseForm:${switchDateId}_time`"
                :disabled="!autoSwitchPhase"
                :name="`${switchDateId}_time`"
                placeholder="09:00"
                @blur="parseAndUpdateTime"
              />
            </div>
          </div>
        </div><!--

     --><div class="layout__item u-1-of-3">
          <dp-label
            :text="Translator.trans('period.new')"
            class="mb-0.5"
            for="procedurePhasePeriod"
            required
          />
          <dp-date-range-picker
            id="procedurePhasePeriod"
            :data-cy="dataCyPhasePeriod"
            :end-disabled="!autoSwitchPhase"
            :end-id="endDateId"
            :end-name="endDateId"
            :end-value="endDate"
            :min-date="minSwitchDate"
            :start-id="startDateId"
            :start-name="startDateId"
            :start-value="startDate"
            enforce-plausible-dates
            required
            start-disabled
            @input:end-date="handleInputEndDate"
          />
        </div>

        <transition
          name="slide-fade"
          mode="out-in"
        >
          <dp-inline-notification
            v-if="showAutoSwitchToAnalysisHint"
            :message="Translator.trans('period.autoswitch.hint', { phase: Translator.trans(isInternal ? 'procedure.phases.internal.analysis' : 'procedure.phases.external.evaluating')})"
            class="mt-3 mb-0"
            type="warning"
          />
        </transition>
      </div>

      <dp-inline-notification
        v-else-if="hasPermission('feature_auto_switch_to_procedure_end_phase') && isParticipationPhaseSelected"
        :message="Translator.trans('period.autoswitch.hint', { phase: Translator.trans(isInternal ? 'procedure.phases.internal.analysis' : 'procedure.phases.external.evaluating')})"
        class="mt-3 mb-0"
        type="warning"
      />
    </transition>
  </div>
</template>

<script>
import {
  DpCheckbox,
  DpDatepicker,
  DpDateRangePicker,
  DpInput,
  DpLabel,
  DpSelect,
} from '@demos-europe/demosplan-ui'
import { defineAsyncComponent } from 'vue'

export default {
  name: 'AutoSwitchProcedurePhaseForm',

  components: {
    DpCheckbox,
    DpDatepicker,
    DpDateRangePicker,
    DpInput,
    DpInlineNotification: defineAsyncComponent(async () => {
      const { DpInlineNotification } = await import('@demos-europe/demosplan-ui')
      return DpInlineNotification
    }),
    DpLabel,
    DpSelect,
  },

  props: {
    availableProcedurePhases: {
      type: Object,
      default: () => ({}),
    },

    dataCyPhasePeriod: {
      type: String,
      required: false,
      default: '',
    },

    /**
     * The date that is used as new "endDate" when a procedure phase is switched.
     * Expects a date string in dplanDate format ('DD.MM.YYYY').
     */
    endDate: {
      type: String,
      required: true,
    },

    initSelectedPhase: {
      type: String,
      default: '',
    },

    /**
     * The date and time on which the specified new values should be applied to the procedure.
     * Expects a datetime string in ISO 8601 (eg. "2021-08-31T00:00:00+02:00").
     */
    initSwitchDate: {
      type: String,
      default: '',
    },

    // Is used to determine whether to use data for internal or external procedure phases
    isInternal: {
      type: Boolean,
      default: false,
    },

    minSwitchDate: {
      type: String,
      default: '',
    },

    selectedCurrentPhase: {
      type: String,
      default: '',
    },
  },

  data () {
    return {
      autoSwitchPhase: false,
      selectedPhase: '',
      startDate: '',
      switchDate: '',
      switchDateOnly: '', // Date part in DD.MM.YYYY format for datepicker
      switchTime: '00:00', // Time part in HH:mm format
      switchDateMax: '',
    }
  },

  computed: {
    checkboxId () {
      return this.isInternal ? 'r_autoSwitch' : 'r_autoSwitchPublic'
    },

    /**
     * Returns true if the currently selected phase in the select for the current procedure phase (not the phase to be
     * switched to) is a participation phase
     * @return {boolean}
     */
    isParticipationPhaseSelected () {
      return Object.values(this.availableProcedurePhases)
        .filter(phase => phase.permission === 'write')
        .map(phase => phase.value)
        .includes(this.selectedCurrentPhase)
    },

    endDateId () {
      return this.isInternal ? 'r_designatedEndDate' : 'r_designatedPublicEndDate'
    },

    phaseOptions () {
      return Object.values(this.availableProcedurePhases).filter(phase => phase.value !== this.selectedCurrentPhase)
    },

    phaseSelectId () {
      return this.isInternal ? 'r_designatedPhase' : 'r_designatedPublicPhase'
    },

    showAutoSwitchToAnalysisHint () {
      const isInParticipation = this.phaseOptions.find(option => option.value === this.selectedPhase)?.permission === 'write'

      return hasPermission('feature_auto_switch_to_procedure_end_phase') && this.autoSwitchPhase && isInParticipation
    },

    startDateId () {
      return this.isInternal ? 'r_designatedStartDate' : 'r_designatedPublicStartDate'
    },

    switchDateId () {
      return this.isInternal ? 'r_designatedSwitchDate' : 'r_designatedPublicSwitchDate'
    },
  },

  watch: {
    autoSwitchPhase (newVal) {
      if (newVal && !this.switchDateOnly) {
        this.switchDateOnly = this.minSwitchDate
        this.switchTime = '00:00'
        this.updateSwitchDate()
      }

      // Needed for the addon-modal on form-submit
      this.$emit('phase-selected', {
        phase: this.selectedPhase,
        enabled: newVal,
        isInternal: this.isInternal
      })
    },

    selectedCurrentPhase: {
      handler () {
        this.setSelectedPhase()

        if (hasPermission('feature_auto_switch_to_procedure_end_phase')) {
          this.autoSwitchPhase = this.isParticipationPhaseSelected
        }
      },
      deep: false, // Set default for migrating purpose. To know this occurrence is checked
    },

    switchDateOnly () {
      this.updateSwitchDate()
    },

    selectedPhase: {
      handler (newVal) {
        this.$emit('phase-selected', {
          phase: newVal,
          enabled: this.autoSwitchPhase,
          isInternal: this.isInternal
        })
      },
      immediate: true
    },

    switchDate: {
      handler (newVal) {
        if (newVal) {
          const date = new Date(newVal)

          if (!isNaN(date.getTime())) {
            this.startDate = this.formatDateToGerman(date)
          }
        }
      },
      deep: true,
    },
  },

  methods: {
    // Helper function to convert German date format (DD.MM.YYYY) to ISO format (YYYY-MM-DD)
    convertGermanToIsoDate (germanDate) {
      if (!germanDate) {
        return ''
      }

      if (germanDate.includes('.')) {
        const [day, month, year] = germanDate.split('.')

        return `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`
      }

      return germanDate // Already in ISO format
    },

    // Helper function to convert date object to German format (DD.MM.YYYY)
    formatDateToGerman (date) {
      const day = date.getDate().toString().padStart(2, '0')
      const month = (date.getMonth() + 1).toString().padStart(2, '0')
      const year = date.getFullYear()

      return `${day}.${month}.${year}`
    },

    parseAndUpdateTime () {
      this.switchTime = this.parseTimeInput(this.switchTime)
      this.updateSwitchDate()
    },

    parseTimeInput (input) {
      if (!input) {
        return '00:00'
      }

      // Remove any non-digits and colons
      const cleaned = input.replace(/[^\d:]/g, '')

      // Handle different formats
      if (cleaned.includes(':')) {
        // 9:00 or 09:00 format
        const [hour, minute] = cleaned.split(':')
        const h = parseInt(hour) || 0
        const m = parseInt(minute) || 0

        return `${Math.min(h, 23).toString().padStart(2, '0')}:${Math.min(m, 59).toString().padStart(2, '0')}`
      } else {
        // 900, 0900, 9 formats
        const digits = cleaned.padStart(1, '0') // Don't force pad here

        if (digits.length === 1) {
          // Single digit (e.g., "9") -> 09:00
          return `${digits.padStart(2, '0')}:00`
        } else if (digits.length === 2) {
          // Two digits (e.g., "09") -> 09:00
          return `${digits}:00`
        } else if (digits.length === 3) {
          // Three digits (e.g., "900") -> 09:00
          return `0${digits[0]}:${digits.slice(1)}`
        } else if (digits.length === 4) {
          // Four digits (e.g., "0900") -> 09:00
          const h = Math.min(parseInt(digits.slice(0, 2)) || 0, 23)
          const m = Math.min(parseInt(digits.slice(2)) || 0, 59)

          return `${h.toString().padStart(2, '0')}:${m.toString().padStart(2, '0')}`
        } else {
          // Fallback for other cases
          return '00:00'
        }
      }
    },

    updateSwitchDate () {
      if (this.switchDateOnly && this.switchTime) {
        const isoDate = this.convertGermanToIsoDate(this.switchDateOnly)
        const dateObj = new Date(`${isoDate}T${this.switchTime}:00`)

        if (!isNaN(dateObj.getTime())) {
          this.switchDate = dateObj.toISOString()
        }
      }
    },

    handleInputEndDate (date) {
      this.switchDateMax = date
    },

    /**
     * Set internal state from initial values saved in the procedure settings.
     * Converts saved ISO datetime to separate date and time fields for the UI.
     */
    setDesignatedDates () {
      if (this.initSwitchDate) {
        this.autoSwitchPhase = true
        const date = new Date(this.initSwitchDate)

        this.switchDateOnly = this.formatDateToGerman(date)
        this.switchTime = date.toTimeString().substring(0, 5)
        this.switchDate = this.initSwitchDate
      } else {
        this.switchDateOnly = this.minSwitchDate
        this.switchTime = '00:00'
      }

      this.switchDateMax = this.endDate

      if (this.switchDate) {
        const date = new Date(this.switchDate)

        if (!isNaN(date.getTime())) {
          this.startDate = this.formatDateToGerman(date)
        }
      }
    },

    setSelectedPhase () {
      const evaluationPhase = 'evaluating'

      if (this.isParticipationPhaseSelected) {
        this.selectedPhase = evaluationPhase
      } else {
        this.selectedPhase = this.phaseOptions[0].value
      }
    },
  },

  mounted () {
    this.setDesignatedDates()

    if (this.initSelectedPhase !== '') {
      this.selectedPhase = this.initSelectedPhase
    } else {
      this.setSelectedPhase()
    }

    if (hasPermission('feature_auto_switch_to_procedure_end_phase') && this.isParticipationPhaseSelected) {
      this.autoSwitchPhase = true
    }
  },
}
</script>
