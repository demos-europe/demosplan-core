<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <label
    :class="prefixClass(['o-form__label flex', bold ? 'weight--bold' : 'weight--normal', hints.length > 0 ? 'has-hint' : ''])"
    :for="labelFor">
    <span>
      <span v-cleanhtml="text" /><span v-if="required">*</span>
      <span
        v-if="hints.length > 0"
        :class="prefixClass('display--block font-size-small weight--normal')">
        <span
          :class="prefixClass(['display--inline-block'])"
          :key="i"
          v-for="(h, i) in hints"
          v-cleanhtml="h" />
      </span>
    </span>
    <i
      v-if="tooltip !== ''"
      :class="prefixClass('fa fa-question-circle u-mt-0_125 flex-item-end')"
      :aria-label="ariaLabel"
      v-tooltip="tooltip" />
  </label>
</template>

<script>
import { CleanHtml } from 'demosplan-ui/directives'
import { de } from '../shared/translations'
import { prefixClassMixin } from '../../mixins'

export default {
  name: 'DpLabel',

  directives: {
    cleanhtml: CleanHtml
  },

  mixins: [prefixClassMixin],

  props: {
    bold: {
      type: Boolean,
      required: false,
      default: true
    },

    for: {
      type: String,
      required: true
    },

    // Can be string or array (the second element being the "maxlength" hint).
    hint: {
      type: [String, Array],
      required: false,
      default: () => []
    },

    text: {
      type: String,
      required: true
    },

    tooltip: {
      type: String,
      required: false,
      default: ''
    },

    required: {
      type: Boolean,
      required: false,
      default: false
    }
  },

  computed: {
    ariaLabel () {
      return de.contextualHelp
    },
    /**
     * List of Hints
     *
     * @return Array{String}
     */
    hints () {
      if (this.hint) {
        return this.wrapItemIntoArray(this.hint)
      }
      return []
    },

    labelFor () {
      return this.for
    }
  },

  methods: {
    wrapItemIntoArray (item) {
      return Array.isArray(item) ? item : [item]
    }
  }
}
</script>
