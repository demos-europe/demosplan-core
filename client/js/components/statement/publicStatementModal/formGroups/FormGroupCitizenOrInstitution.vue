<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <div :class="prefixClass('layout')">
      <div :class="prefixClass('layout__item u-1-of-2 u-1-of-1-lap-down')">
        <div :class="prefixClass('layout')">
          <p
            :class="prefixClass('layout__item u-1-of-2 weight--bold u-mb-0')"
            id="submitterRoleLabel">
            {{ Translator.trans('submitter') }}
            <span
              v-if="required"
              aria-hidden="true">*</span>
          </p>
          <fieldset
            id="submitterTypeFieldset"
            :required="statement.r_useName === '1' && required"
            role="radiogroup"
            aria-labelledby="submitterRoleLabel"
            aria-required="true">
            <legend
              class="sr-only"
              v-text="Translator.trans('submitter')" />
            <div :class="prefixClass('layout__item u-1-of-2 u-1-of-1-lap-down')">
              <dp-radio
                id="citizen"
                :label="{
                  text: Translator.trans('citizen')
                }"
                name="r_submitter_role"
                :checked="statement.r_submitter_role === 'citizen'"
                @change="() => { setStatementData({r_submitter_role: 'citizen'}) }"
                value="citizen" />
            </div><!--
         --><div :class="prefixClass('layout__item u-1-of-2 u-1-of-1-lap-down')">
              <dp-radio
                id="publicagency"
                :label="{
                  text: Translator.trans('invitable_institution')
                }"
                name="r_submitter_role"
                :checked="statement.r_submitter_role === 'publicagency'"
                @change="() => { setStatementData({r_submitter_role: 'publicagency'}) }"
                value="publicagency" />
            </div>
          </fieldset>
        </div>
      </div><!--
   --><dp-input
        v-if="statement.r_submitter_role === 'publicagency'"
        id="r_userOrganisation"
        :class="prefixClass('layout__item')"
        :label="{
          text: Translator.trans('institution.name')
        }"
        required
        :value="statement.r_userOrganisation"
        width="u-1-of-2 u-1-of-1-lap-down"
        @input="val => setStatementData({ r_userOrganisation: val})" />
    </div>
  </div>
</template>
<script>
import { DpRadio } from '@demos-europe/demosplan-ui'
import formGroupMixin from '../mixins/formGroupMixin'

export default {
  name: 'FormGroupCitizenOrInstitution',

  components: {
    DpRadio
  },

  mixins: [formGroupMixin]
}
</script>
