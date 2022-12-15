<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <h3
      class="u-mb-0_5"
      v-text="stepTitle" />

    <template v-if="step === 1">
      <slot name="step-1" />

      <div class="u-mt cf">
        <dp-button
          color="secondary"
          :href="sanitizedReturnLink"
          :text="Translator.trans('bulk.edit.actions.back.to.list')" />
        <dp-button
          class="float--right"
          :disabled="!valid"
          @click="$emit('confirm')">
          {{ Translator.trans('continue.confirm') }}
          <i class="fa fa-angle-right u-pl-0_25" />
        </dp-button>
      </div>
    </template>

    <template v-if="step === 2">
      <slot name="step-2" />

      <div class="u-mt cf">
        <dp-button
          color="secondary"
          @click="$emit('edit')">
          <i class="fa fa-angle-left u-pr-0_25" />
          {{ Translator.trans('bulk.edit.actions.edit') }}
        </dp-button>
        <dp-button
          class="float--right"
          :busy="busy"
          @click="$emit('apply')">
          {{ Translator.trans('bulk.edit.actions.apply') }}
          <i class="fa fa-angle-right u-pl-0_25" />
        </dp-button>
      </div>
    </template>

    <template v-if="step === 3">
      <slot name="step-3" />

      <div class="u-mt">
        <dp-button
          :href="sanitizedReturnLink"
          :text="Translator.trans('bulk.edit.actions.back.to.list')" />
      </div>
    </template>
  </div>
</template>

<script>
import { DpButton } from '@demos-europe/demosplan-ui'
import { sanitizeUrl } from '@braintree/sanitize-url'

export default {
  name: 'ActionStepper',

  components: {
    DpButton
  },

  props: {
    busy: {
      type: Boolean,
      required: true
    },

    valid: {
      type: Boolean,
      required: true
    },

    returnLink: {
      required: true,
      type: String
    },

    selectedElements: {
      required: true,
      type: Number
    },

    step: {
      required: false,
      type: Number,
      default: 1
    }
  },

  computed: {
    sanitizedReturnLink () {
      return sanitizeUrl(this.returnLink)
    },

    stepTitle () {
      if (this.selectedElements === 0) {
        return Translator.trans('warning.entries.no.selected')
      } else {
        return Translator.trans([
          'bulk.edit.title.actions.choose',
          'bulk.edit.title.actions.apply',
          'confirm.saved.plural'
        ][this.step - 1], { count: this.selectedElements })
      }
    }
  }
}
</script>
