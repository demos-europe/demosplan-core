<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <action-stepper
    class="u-mv"
    :busy="busy"
    :valid="hasElements"
    :return-link="Routing.generate('DemosPlan_element_administration', { procedure: dplan.procedureId })"
    :selected-elements="elements.length"
    :step="step"
    @apply="apply"
    @confirm="confirm"
    @edit="step = 1">
    <!-- Step 1 - Chose action -->
    <template v-slot:step-1>
      <div
        data-dp-validate="autoSwitchForm"
        v-if="hasPermission('feature_auto_switch_element_state')"
        class="border--bottom u-pt u-pb-0_5">
        <dp-checkbox
          id="autoSwitchAction"
          v-model="actions.setEnabled.checked"
          class="inline-block"
          disabled
          :label="{
            bold: actions.setEnabled.checked,
            text: Translator.trans('change.state.at.date')
          }" />
        <div
          v-if="actions.setEnabled.checked"
          class="u-mv-0_5 flex space-inline-m">
          <dp-datetime-picker
            id="autoSwitchActionEnabledDatetime"
            :label="Translator.trans('phase.autoswitch.datetime')"
            :min-date="now"
            required
            v-model="actions.setEnabled.datetime" />
          <dp-select
            v-model="actions.setEnabled.state"
            :label="{
              text: Translator.trans('status.new')
            }"
            :options="stateOptions"
            required />
        </div>
      </div>
    </template>

    <!-- Step 2 - Confirm -->
    <template v-slot:step-2>
      <div
        v-if="hasPermission('feature_auto_switch_element_state')"
        class="border--bottom u-mt u-pb-0_5">
        <p v-html="confirmStateChangeMessage" />
        <dp-inline-notification
          class="mt-3 mb-2"
          :message="Translator.trans('elements.bulk.edit.change.state.hint')"
          type="warning" />
      </div>
    </template>

    <!-- Step 3 - System feedback -->
    <template v-slot:step-3>
      <action-stepper-response
        v-if="hasPermission('feature_auto_switch_element_state')"
        :success="actions.setEnabled.success"
        :description-error="Translator.trans('elements.bulk.edit.change.state.error')"
        :description-success="Translator.trans('elements.bulk.edit.change.state.success', { changed: actions.setEnabled.elementsCount, total: elements.length })" />
    </template>
  </action-stepper>
</template>

<script>
import {
  checkResponse,
  DpCheckbox,
  DpDatetimePicker,
  DpInlineNotification,
  dpRpc,
  DpSelect,
  dpValidateMixin,
  formatDate,
  hasOwnProp
} from '@demos-europe/demosplan-ui'
import ActionStepper from '@DpJs/components/procedure/SegmentsBulkEdit/ActionStepper/ActionStepper'
import ActionStepperResponse from '@DpJs/components/procedure/SegmentsBulkEdit/ActionStepper/ActionStepperResponse'
import lscache from 'lscache'

export default {
  name: 'ElementsAdminBulkEdit',

  components: {
    ActionStepper,
    ActionStepperResponse,
    DpCheckbox,
    DpDatetimePicker,
    DpInlineNotification,
    DpSelect
  },

  mixins: [dpValidateMixin],

  data () {
    return {
      actions: {
        setEnabled: {
          /*
           * Whether or not this action shall be performed. Since atm it is the only action available in this
           * bulk edit flow, it is set to true, and the checkbox that toggles it is disabled.
           */
          checked: true,

          // The iso date at which the state shall be changed to the new state.
          datetime: '',

          // How many elements actually got set up to automatically switch
          elementsCount: null,

          /*
           * The new state which should be applied to all selected elements.
           * Default is the empty string (which makes DpSelect "unselected").
           */
          state: '',

          // Whether or not this action failed or not - used in step 3.
          success: false
        }
      },
      busy: false,
      step: 1,
      elements: []
    }
  },

  computed: {
    confirmStateChangeMessage () {
      return Translator.trans('elements.bulk.edit.change.state.confirmation', {
        datetime: formatDate(this.actions.setEnabled.datetime, 'long'),
        state: Translator.trans(this.currentStateOption.label)
      })
    },

    currentStateOption () {
      return this.stateOptions.find(option => option.value === this.actions.setEnabled.state)
    },

    hasElements () {
      return this.elements.length > 0
    },

    now () {
      return formatDate()
    }
  },

  methods: {
    /**
     * Apply selected actions.
     */
    apply () {
      this.busy = true

      const params = {
        state: Boolean(parseInt(this.actions.setEnabled.state)),
        datetime: this.actions.setEnabled.datetime,
        elementIds: this.elements
      }

      dpRpc('planning.document.category.bulk.edit', params)
        .then(checkResponse)
        .then((response) => {
          this.actions.setEnabled.success = (hasOwnProp(response, 0) && hasOwnProp(response[0], 'result'))
          this.actions.setEnabled.elementsCount = (hasOwnProp(response, 0) && response[0]?.result)
        })
        .catch(() => {
          this.actions.setEnabled.success = false
        })
        .finally(() => {
          // Always delete saved selection to ensure that no action is processed more than one time
          lscache.remove(`${dplan.procedureId}:selectedElements`)
          this.step = 3
          this.busy = false
        })
    },

    confirm () {
      this.dpValidateAction('autoSwitchForm', () => {
        this.step = 2
      }, false)
    }
  },

  created () {
    // Get elements ids from localStorage.
    this.elements = lscache.get(`${dplan.procedureId}:selectedElements`) || []

    /*
     * These are used both in the select element and confirm message.
     * Since DpSelect returns only string values we have to stick with that.
     */
    this.stateOptions = [
      {
        value: '1',
        label: Translator.trans('published')
      },
      {
        value: '0',
        label: Translator.trans('unpublished')
      }
    ]
  }
}
</script>
