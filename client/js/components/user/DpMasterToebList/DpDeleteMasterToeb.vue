<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <button
      @click.prevent="deleteOrga"
      class="btn-icns u-m-0 u-pl-0">
      <i
        class="fa fa-trash"
        aria-hidden="true" />
    </button>
  </div>
</template>

<script>
import { makeFormPost } from '@demos-europe/demosplan-ui'

export default {
  name: 'DpDeleteMasterToeb',

  props: {
    orgaId: {
      type: String,
      required: true
    }
  },

  emits: [
    'orga-deleted'
  ],

  methods: {
    deleteOrga () {
      if (confirm(Translator.trans('check.mastertoeb.delete'))) {
        makeFormPost({ oId: this.orgaId }, Routing.generate('DemosPlan_user_mastertoeblist_delete_ajax'))
          .then(() => this.$emit('orga-deleted', this.orgaId))
      }
    }
  }
}
</script>
