<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <a
    class="flex overflow-word-break font-size-small"
    target="_blank"
    :href="path">
    <i
      class="fa fa-file-o u-mr-0_25"
      aria-hidden="true" />
    {{ name }} ({{ byteSize }})
  </a>
</template>

<script>
import { formatBytes } from '@demos-europe/demosplan-ui/src'

export default {
  name: 'FileInfo',

  props: {
    hash: {
      type: String,
      required: true
    },

    name: {
      type: String,
      required: true
    },

    size: {
      type: String,
      required: true
    }
  },

  computed: {
    path () {
      return Routing.generate('core_file_procedure', { procedureId: dplan.procedureId, hash: this.hash })
    },

    byteSize () {
      return formatBytes(this.size).replace(/\./g, ',')
    }
  }
}
</script>
