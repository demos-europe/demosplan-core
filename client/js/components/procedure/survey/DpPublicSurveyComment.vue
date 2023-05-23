<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div :class="prefixClass('u-p u-pl-0 overflow-word-break')">
    <div :class="prefixClass('font-size-small color--grey u-mb-0_5')">
      <i
        :class="prefixClass('fa fa-calendar-check-o')"
        aria-hidden="true" />
      <span>{{ createdString }}: {{ commentCreatedDate }} </span>
    </div>
    {{ comment.text }}
  </div>
</template>

<script>
import { formatDate, prefixClassMixin } from '@demos-europe/demosplan-ui'

export default {
  name: 'DpPublicSurveyComment',

  mixins: [prefixClassMixin],

  props: {
    comment: {
      type: Object,
      required: true
    }
  },

  computed: {
    commentCreatedDate () {
      return formatDate(this.comment.createdDate)
    },

    createdString () {
      return Translator.trans('date.created').charAt(0).toUpperCase() + Translator.trans('date.created').slice(1)
    }
  }
}
</script>
