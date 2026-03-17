<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <dp-radio
      v-for="organisation in organisations"
      :id="`organisation-${organisation.id}`"
      :key="organisation.id"
      :checked="organisation.id === currentOrganisationId"
      :label="{
        text: organisation.name
      }"
      :value="organisation.id"
      class="mb-2"
      name="organisation_id"
    />
    <dp-button
      :text="Translator.trans('organisation.select.confirm')"
      class="mt-4"
      color="primary"
      type="submit"
    />
  </div>
</template>

<script>
import {
  DpButton,
  DpRadio,
} from '@demos-europe/demosplan-ui'

export default {
  name: 'OrganisationSwitcher',

  components: {
    DpButton,
    DpRadio,
  },

  props: {
    currentOrganisationId: {
      type: String,
      required: true,
    },

    organisations: {
      type: Array,
      required: true,
    },
  },
}
</script>
