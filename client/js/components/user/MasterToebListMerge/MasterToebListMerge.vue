<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div class="layout">
    <div class="layout__item u-1-of-2">
      <div class="space-inset-s space-stack-s bg-color-light rounded-md">
        <dp-label
          class="u-mb-0_25"
          :text="Translator.trans('invitable_institution.master.organisations.new')"
          for="r_orga" />

        <dp-multiselect
          id="r_orga"
          v-model="selectedOrganisation"
          :allow-empty="false"
          label="name"
          :options="organisations"
          track-by="ident">
          <template v-slot:option="{ props }">
            <span class="weight--bold block">{{ props.option.name }}</span>
            <span class="font-size-small">{{ list(props.option.departmentNames) }}</span>
          </template>
        </dp-multiselect>

        <template v-if="selectedOrganisation">
          <input
            type="hidden"
            :value="selectedOrganisation.ident"
            name="r_orga">
          <dl class="description-list">
            <dt v-text="Translator.trans('name')" />
            <dd v-text="selectedOrganisation.name" />
            <dt v-text="Translator.trans('department')" />
            <dd v-text="list(selectedOrganisation.departmentNames)" />
            <dt v-text="Translator.trans('user')" />
            <dd v-text="list(selectedOrganisation.userNames)" />
          </dl>
        </template>
      </div>
    </div><!--

 --><div class="layout__item u-1-of-2">
      <div class="space-inset-s space-stack-s bg-color-light rounded-md">
        <dp-label
          class="u-mb-0_25"
          :text="Translator.trans('invitable_institutions.master.organisations.master_toeb_list')"
          for="r_orga_mastertoeb" />

        <dp-multiselect
          id="r_orga_mastertoeb"
          v-model="selectedOrganisationMasterToeb"
          :allow-empty="false"
          label="orgaName"
          :options="organisationsMasterToeb"
          track-by="ident">
          <template v-slot:option="{ props }">
            <span class="weight--bold block">{{ props.option.orgaName }}</span>
            <span class="font-size-small">{{ props.option.departmentName }}</span>
          </template>
        </dp-multiselect>

        <template v-if="selectedOrganisationMasterToeb">
          <input
            type="hidden"
            :value="selectedOrganisationMasterToeb.ident"
            name="r_orga_mastertoeb">
          <dl class="description-list">
            <dt v-text="Translator.trans('name')" />
            <dd v-text="selectedOrganisationMasterToeb.orgaName" />
            <dt v-text="Translator.trans('department')" />
            <dd v-text="selectedOrganisationMasterToeb.departmentName || '-'" />
            <dt v-text="Translator.trans('invitable_institution.master.gatewayGroup')" />
            <dd v-text="selectedOrganisationMasterToeb.gatewayGroup || '-'" />
          </dl>
        </template>
      </div>
    </div>
  </div>
</template>

<script>
import { DpLabel, DpMultiselect } from '@demos-europe/demosplan-ui'

export default {
  name: 'MasterToebListMerge',

  components: {
    DpLabel,
    DpMultiselect
  },

  props: {
    organisations: {
      type: Array,
      required: false,
      default: () => []
    },

    organisationsMasterToeb: {
      type: Array,
      required: false,
      default: () => []
    }
  },

  data () {
    return {
      selectedOrganisation: null,
      selectedOrganisationMasterToeb: null
    }
  },

  methods: {
    list (array) {
      if (array.length > 1) {
        return array.join(', ')
      } else {
        return array[0] || '-'
      }
    }
  }
}
</script>
