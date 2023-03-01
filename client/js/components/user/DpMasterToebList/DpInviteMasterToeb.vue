<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <button
      class="btn btn--primary"
      @click.prevent="inviteToeb">
      {{ Translator.trans('invitable_institution.add') }}
    </button>
  </div>
</template>

<script>
export default {
  name: 'DpInviteMasterToeb',

  props: {
    selectedToebIds: {
      type: Array,
      required: false,
      default: () => []
    },

    procedureId: {
      type: String,
      required: true
    }
  },

  methods: {
    inviteToeb () {
      const form = document.querySelector('form#orgalistForm')
      this.selectedToebIds.forEach(id => {
        const additionalParam = document.createElement('input')
        additionalParam.setAttribute('name', 'orga_add[]')
        additionalParam.setAttribute('value', id)
        additionalParam.setAttribute('type', 'hidden')
        form.appendChild(additionalParam)
      })
      form.submit()
    }
  }
}
</script>
