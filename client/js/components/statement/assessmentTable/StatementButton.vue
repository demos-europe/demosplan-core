<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <a
    class="c-at-item__tab-trigger o-link--icon inline-block u-pv-0_5 u-ph u-ml-0_5"
    :class="{'is-active-toggle':active}"
    :href="'#' + statementId + '_statement'"
    @click="showStatement(statementId)">
    <i
      class="fa fa-file-o"
      aria-hidden="true" />
    {{ Translator.trans('statement') }}
  </a>
</template>

<script>
export default {
  name: 'DpStatementsButton',

  props: {
    active: {
      required: true,
      type: Boolean
    },

    statementId: {
      required: true,
      type: String
    }
  },

  emits: [
    'click'
  ],

  methods: {
    showStatement () {
      this.$emit('click')
    }
  }
}
</script>
