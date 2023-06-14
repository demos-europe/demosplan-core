<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <dp-badge
    :color="color"
    size="small"
    :text="text" />
</template>

<script>
import DpBadge from '@DpJs/components/procedure/AdministrationImport/EmailImport/DpBadge'

export default {
  name: 'EmailImportStatusBadge',

  components: {
    DpBadge
  },

  props: {
    imported: {
      type: Boolean,
      required: false,
      default: false
    }
  },

  computed: {
    color () {
      return this.imported ? 'confirm' : 'default'
    },

    text () {
      return Translator.trans(this.imported ? 'imported.verb' : 'new')
    }
  }
}
</script>
