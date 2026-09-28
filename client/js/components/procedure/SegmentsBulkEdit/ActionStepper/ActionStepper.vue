<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <p class="mb-1">
      {{ Translator.trans('bulk.edit.stepper.progress', { step, total: totalSteps }) }}
    </p>
    <h3
      class="mb-2"
      v-text="stepTitle"
    />

    <template v-if="step === 1">
      <slot name="step-1" />

      <div class="u-mt flow-root">
        <dp-button
          :href="sanitizedReturnLink"
          :text="mergedTranslations.back"
          color="secondary"
          icon="arrow-left"
        />
        <dp-button
          :disabled="!valid"
          :text="mergedTranslations.confirm"
          class="float-right"
          icon-after="arrow-right"
          @click="$emit('confirm')"
        />
      </div>
    </template>

    <template v-if="step === 2">
      <slot name="step-2" />

      <div class="u-mt flow-root">
        <dp-button
          :text="mergedTranslations.edit"
          color="secondary"
          icon="arrow-left"
          @click="$emit('edit')"
        />
        <dp-button
          :busy="busy"
          :text="mergedTranslations.apply"
          class="float-right"
          icon-after="arrow-right"
          @click="$emit('apply')"
        />
      </div>
    </template>

    <template v-if="step === 3">
      <slot name="step-3" />

      <div class="u-mt flow-root">
        <dp-button
          :href="sanitizedReturnLink"
          :text="mergedTranslations.backToList"
          class="float-right"
          icon-after="arrow-right"
        />
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
    DpButton,
  },

  props: {
    busy: {
      type: Boolean,
      required: true,
    },

    returnLink: {
      required: true,
      type: String,
    },

    selectedElements: {
      required: true,
      type: Number,
    },

    step: {
      required: false,
      type: Number,
      default: 1,
    },

    totalSteps: {
      type: Number,
      required: false,
      default: 3,
    },

    translations: {
      type: Object,
      required: false,
      default: () => ({}),
    },

    valid: {
      type: Boolean,
      required: true,
    },
  },

  emits: [
    'apply',
    'confirm',
    'edit',
  ],

  computed: {
    defaultTranslations () {
      return {
        back: Translator.trans('back.to.segments.list'),
        backToList: Translator.trans('back.to.segments.list'),
        confirm: Translator.trans('continue.confirm'),
        apply: Translator.trans('bulk.edit.actions.apply'),
        edit: Translator.trans('bulk.edit.actions.edit'),
        stepTitles: [
          Translator.trans('bulk.edit.title.actions.choose', { count: this.selectedElements }),
          Translator.trans('bulk.edit.title.actions.apply', { count: this.selectedElements }),
          Translator.trans('confirm.saved.plural'),
        ],
      }
    },

    mergedTranslations () {
      return { ...this.defaultTranslations, ...this.translations }
    },

    sanitizedReturnLink () {
      return sanitizeUrl(this.returnLink)
    },

    stepTitle () {
      if (this.selectedElements === 0) {
        return Translator.trans('warning.entries.no.selected')
      } else {
        return this.mergedTranslations.stepTitles[this.step - 1] || ''
      }
    },
  },
}
</script>
