<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <time
    :datetime="date"
    v-text="dateShort"
    v-tooltip="dateLong" />
</template>

<script>
import { DATE_FORMAT_LONG, formatDate } from '@DpJs/lib/utils/date'

export default {
  name: 'EmailImportDate',

  props: {
    date: {
      required: true,
      type: String
    }
  },

  computed: {
    dateShort () {
      return formatDate(this.date)
    },

    dateLong () {
      return formatDate(this.date, DATE_FORMAT_LONG)
    }
  }
}
</script>
