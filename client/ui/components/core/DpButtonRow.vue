<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentation>
  <!-- This component contains a button row, currently with save and abort buttons. -->
  <usage>
    <dp-button-row
      :busy="isLoading"
      primary
      secondary
      @primary-action="save"
      @secondary-action="reset" />
  </usage>

  <usage variant="with slot and third button">
    <dp-button-row
      :busy="isLoading"
      primary
      secondary
      @primary-action="save"
      @secondary-action="reset">
      <dp-button
        class="u-ml-0_5"
        color="secondary"
        :text="Translator.trans('close')"
        @click="closeAndCancel" />
    </dp-button-row>
  </usage>
</documentation>

<template>
  <div
    :class="[`text--${align}`, $attrs.class]"
    class="space-inline-s">
    <dp-button
      v-if="primary"
      :busy="busy"
      :disabled="disabled"
      :text="primaryText"
      :variant="variant"
      @click.prevent="$emit('primary-action')"
      data-cy="saveButton" />
    <dp-button
      v-if="secondary"
      color="secondary"
      :href="href"
      :text="secondaryText"
      :variant="variant"
      @click.prevent="$emit('secondary-action')" />
    <slot />
  </div>
</template>

<script>
import { DpButton } from 'demosplan-ui/components'

export default {
  name: 'DpButtonRow',

  components: {
    DpButton
  },

  props: {
    /**
     * Specifies if the buttons should align left or right inside their container.
     */
    align: {
      type: String,
      required: false,
      default: 'right'
    },

    /**
     * The primary button may have a "busy" state to indicate system progress.
     */
    busy: {
      type: Boolean,
      required: false,
      default: false
    },

    /**
     * The primary button may have a "disabled" state to prevent unwanted user interaction e.g if no data is changed yet.
     */
    disabled: {
      type: Boolean,
      required: false,
      default: false
    },

    /**
     * The secondary button may be a link under the hood, e.g. for jumping back to another view.
     * The default value corresponds with the respective value in the `DpButton` component.
     */
    href: {
      type: String,
      required: false,
      default: '#'
    },

    /**
     * Display a primary button intended to save changes.
     */
    primary: {
      type: Boolean,
      required: false,
      default: false
    },

    /**
     * Custom text for the primary button.
     */
    primaryText: {
      type: String,
      required: false,
      default: () => Translator.trans('save')
    },

    /**
     * Display a secondary button intended to cancel a process or reset input.
     */
    secondary: {
      type: Boolean,
      required: false,
      default: false
    },

    /**
     * Custom text for the secondary button.
     */
    secondaryText: {
      type: String,
      required: false,
      default: () => Translator.trans('abort')
    },

    /**
     * Variants of the button include `outline` (`text` to be implemented).
     * When not specified, the default style (white on colored background) is applied.
     */
    variant: {
      required: false,
      type: String,
      default: ''
    }
  }
}
</script>
