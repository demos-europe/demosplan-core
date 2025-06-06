<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <!-- modal to add a new organisation -->
    <dp-new-orga-modal
      ref="newOrgaModal"
      @save="addOrga"
      :fields="fields" />
    <!-- button to trigger the "new orga modal" -->
    <button
      class="btn btn--primary"
      type="button"
      @click="() => { $refs.newOrgaModal.toggleModal() }"
      aria-haspopup="true"
      aria-role="navigation"
      :aria-label="Translator.trans('organisation.add')"
      aria-expanded="false">
      {{ Translator.trans('organisation.add') }}
    </button>
  </div>
</template>

<script>
import DpNewOrgaModal from './DpNewOrgaModal'
import { makeFormPost } from '@demos-europe/demosplan-ui'

export default {
  name: 'DpNewMasterToeb',

  components: {
    DpNewOrgaModal
  },

  props: {
    fields: {
      type: Array,
      required: true
    },

    boolToStringFields: {
      type: Array,
      required: true
    }
  },

  emits: [
    'orga:added'
  ],

  methods: {
    addOrga (newOrga) {
      const newOrgaCpy = { ...newOrga }
      this.boolToStringFields.forEach(field => {
        // Set true if boolToStringFields where set with any string.
        newOrgaCpy[field] = typeof newOrgaCpy[field] !== 'undefined'
      })
      const initialPayload = {
        oId: '',
        field: 'orgaName',
        value: newOrgaCpy.orgaName
      }

      makeFormPost(initialPayload, Routing.generate('DemosPlan_user_mastertoeblist_add_ajax')).then((response) => {
        delete newOrgaCpy.orgaName
        newOrgaCpy.ident = response.data.ident
        this.batchRequest(newOrgaCpy).then(() => {
          newOrgaCpy.orgaName = newOrga.orgaName
          this.$emit('orga:added', newOrgaCpy)
        })
      })
    },

    batchRequest (orga) {
      return Promise.all(
        Object.keys(orga).filter(key => key !== 'ident').map((key) => {
          return makeFormPost(
            {
              oId: orga.ident,
              field: key,
              value: orga[key]
            },
            Routing.generate('DemosPlan_user_mastertoeblist_update_ajax')
          )
        })
      )
    }
  }
}
</script>
