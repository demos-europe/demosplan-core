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
      :name="checkboxId" />

    <transition
      name="slide-fade"
      mode="out-in">
      <div
        v-if="!hasPermission('feature_auto_switch_to_procedure_end_phase') || (hasPermission('feature_auto_switch_to_procedure_end_phase') && !isParticipationPhaseSelected)"
        class="layout u-mt-0_25 u-pl">
        <dp-select
          class="layout__item u-1-of-3 u-1-of-1-lap-down"
          v-model="selectedPhase"
          :data-cy="`selectedPhase:${checkboxId}`"
          :disabled="!autoSwitchPhase"
          :label="{
            text: Translator.trans('procedure.phase.autoswitch.targetphase')
          }"
          :name="phaseSelectId"
          :options="phaseOptions" /><!--

     --><div class="layout__item u-1-of-3 u-1-of-1-lap-down">
          <dp-datetime-picker
            :data-cy="`autoSwitchProcedurePhaseForm:${switchDateId}`"
            :disabled="!autoSwitchPhase"
            hidden-input
            :id="switchDateId"
            :label="Translator.trans('phase.autoswitch.datetime')"
            :max-date="switchDateMax"
            :min-date="minSwitchDate"
            :name="switchDateId"
            required
            v-model="switchDate" />
        </div><!--

     --><div class="layout__item u-1-of-3">
          <dp-label
            for="procedurePhasePeriod"
            :text="Translator.trans('period.new')"
            required />
          <dp-date-range-picker
            id="procedurePhasePeriod"
            :end-disabled="!autoSwitchPhase"
            :end-id="endDateId"
            :end-name="endDateId"
            :end-value="endDate"
            enforce-plausible-dates
            :min-date="startDate"
            required
            :data-cy="dataCyPhasePeriod"
            start-disabled
            :start-id="startDateId"
            :start-name="startDateId"
            :start-value="startDate"
            @input:end-date="handleInputEndDate" />
        </div>

        <transition
          name="slide-fade"
          mode="out-in">
          <dp-inline-notification
            v-if="showAutoSwitchToAnalysisHint"
            class="u-mb-0"
            :message="Translator.trans('period.autoswitch.hint', { phase: Translator.trans(isInternal ? 'procedure.phases.internal.analysis' : 'procedure.phases.external.evaluating')})"
            type="warning" />
        </transition>
      </div>

      <dp-inline-notification
        v-else-if="hasPermission('feature_auto_switch_to_procedure_end_phase') && isParticipationPhaseSelected"
        class="u-mb-0"
        :message="Translator.trans('period.autoswitch.hint', { phase: Translator.trans(isInternal ? 'procedure.phases.internal.analysis' : 'procedure.phases.external.evaluating')})"
        type="warning" />
    </transition>
  </div>
</template>

<script>
import {
  DpCheckbox,
  DpDateRangePicker,
  DpDatetimePicker,
  DpLabel,
  DpSelect,
  formatDate
} from '@demos-europe/demosplan-ui'
import { defineAsyncComponent } from 'vue'

export default {
  name: 'AutoSwitchProcedurePhaseForm',

  components: {
    DpCheckbox,
    DpDateRangePicker,
    DpDatetimePicker,
    DpInlineNotification: defineAsyncComponent(async () => {
      const { DpInlineNotification } = await import('@demos-europe/demosplan-ui')
      return DpInlineNotification
    }),
    DpLabel,
    DpSelect
  },

  props: {
    availableProcedurePhases: {
      type: Object,
      default: () => ({})
    },

    dataCyPhasePeriod: {
      type: String,
      required: false,
      default: ''
    },

    /**
     * The date that is used as new "endDate" when a procedure phase is switched.
     * Expects a date string in dplanDate format ('DD.MM.YYYY').
     */
    endDate: {
      type: String,
      required: true
    },

    initSelectedPhase: {
      type: String,
      default: ''
    },

    /**
     * The date and time on which the specified new values should be applied to the procedure.
     * Expects a datetime string in ISO 8601 (eg. "2021-08-31T00:00:00+02:00").
     */
    initSwitchDate: {
      type: String,
      default: ''
    },

    // Is used to determine whether to use data for internal or external procedure phases
    isInternal: {
      type: Boolean,
      default: false
    },

    minSwitchDate: {
      type: String,
      default: ''
    },

    selectedCurrentPhase: {
      type: String,
      default: ''
    }
  },

  data () {
    return {
      autoSwitchPhase: false,
      selectedPhase: '',
      startDate: '',
      switchDate: '',
      switchDateMax: ''
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
    }
  },

  watch: {
    selectedCurrentPhase: {
      handler () {
        this.setSelectedPhase()

        if (hasPermission('feature_auto_switch_to_procedure_end_phase')) {
          this.autoSwitchPhase = this.isParticipationPhaseSelected
        }
      },
      deep: false // Set default for migrating purpose. To know this occurrence is checked
    },

    switchDate: {
      handler (newVal) {
        this.startDate = formatDate(newVal)
      },
      deep: true
    }
  },

  methods: {
    handleInputEndDate (date) {
      this.switchDateMax = date
    },

    /**
     * Set internal state from initial values saved in the procedure settings.
     *
     * Currently, `startDate` is not saved separately from `switchDate`, but derived from it in the backend.
     * The transformation with formatDate() is necessary because DpDatepicker passes 'dd.mm.yyyy'
     * to a11y-datepicker as the format to be used for date strings. On the other hand, DpDateTimePicker
     * (which handles `switchDate`) uses the ISO date format internally.
     */
    setDesignatedDates () {
      if (this.initSwitchDate) {
        this.autoSwitchPhase = true
      }
      this.switchDateMax = this.endDate
      this.switchDate = this.initSwitchDate
      this.startDate = formatDate(this.initSwitchDate)
    },

    setSelectedPhase () {
      const evaluationPhase = 'evaluating'

      if (this.isParticipationPhaseSelected) {
        this.selectedPhase = evaluationPhase
      } else {
        this.selectedPhase = this.phaseOptions[0].value
      }
    }
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
  }
}
</script>
