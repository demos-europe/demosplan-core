<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <dp-editor
    hidden-input="r_send_body"
    :procedure-id="procedureId"
    :toolbar-items="toolbarItems"
    v-model="text" />
</template>

<script>
export default {
  name: 'AssessmentStatementDetailFinalEmail',

  components: {
    DpEditor: async () => {
      const { DpEditor } = await import('@demos-europe/demosplan-ui')
      return DpEditor
    }
  },

  props: {
    initText: {
      type: String,
      required: true
    },

    procedureId: {
      type: String,
      required: true
    }
  },

  data () {
    return {
      text: this.initText,
      toolbarItems: {
        boilerPlate: 'email',
        headings: [1, 2, 3],
        linkButton: true
      }
    }
  }
}
</script>
