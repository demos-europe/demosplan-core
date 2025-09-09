<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <button
    class="o-link--default text-left btn--blank weight--bold block u-1-of-1"
    data-cy="backToResultsList"
    type="button"
    @click="$emit('input', 'DpList')">
    {{ Translator.trans('back.to.results.list') }}
  </button>
</template>

<script>
export default {
  name: 'DpContentToggle',

  emits: [
    'input'
  ],

  data () {
    return {
      view: ''
    }
  }
}
</script>
