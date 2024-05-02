<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div
    v-if="hasPermission('field_statement_user_state') ||
      hasPermission('field_statement_user_group') ||
      hasPermission('field_statement_user_organisation') ||
      hasPermission('field_statement_user_position')">
    <div :class="prefixClass('layout')">
      <!-- @improve may or may not use dp-select for following selects. it would result in style changes -->
      <label
        v-if="hasPermission('field_statement_user_state') && formOptions.userState"
        for="r_userState"
        :class="prefixClass('layout__item u-1-of-2 u-1-of-4-lap-up u-mb-0 u-mt-0_5')"
        :title="required ? Translator.trans('statements.required.field') : false">
        <span
          :class="prefixClass('block')"
          id="statement-detail-state">
          {{ Translator.trans('state') }}
          <span
            v-if="required"
            aria-hidden="true">
            *
          </span>
        </span>
        <select
          id="r_userState"
          name="r_userState"
          :class="prefixClass('o-form__control-select')"
          @change="val => setStatementData({r_userState: val.target.value})"
          :required="required && statement.r_useName === '1'"
          :value="statement.r_userState">
          <option
            v-for="userState in formOptions.userState"
            :selected="userState === statement.r_userState"
            :value="userState"
            :key="userState">
            {{ userState }}
          </option>
        </select>
      </label><!--

  --><label
      v-if="hasPermission('field_statement_user_group') && formOptions.userGroup"
      for="r_userGroup"
      :class="prefixClass('layout__item u-1-of-2 u-1-of-4-lap-up u-mb-0 u-mt-0_5')"
      :title="required ? Translator.trans('statements.required.field') : false">
      <span
        :class="prefixClass('block')"
        id="statement-detail-organisation">
        {{ Translator.trans('organisation') }}
        <span
          v-if="required"
          aria-hidden="true">
          *
        </span>
      </span>
      <select
        id="r_userGroup"
        name="r_userGroup"
        :class="prefixClass('o-form__control-select')"
        @change="val => setStatementData({r_userGroup: val.target.value})"
        :required="required && statement.r_useName === '1'"
        :value="statement.r_userGroup">
        <option
          v-for="userGroup in formOptions.userGroup"
          :selected="userGroup === statement.r_userGroup"
          :value="userGroup"
          :key="userGroup">
          {{ userGroup }}
        </option>
      </select>
      </label><!--

   --><dp-input
        id="r_userOrganisation"
        aria-labelledby="statement-detail-organisation-name"
        :class="prefixClass('layout__item u-1-of-2 u-1-of-4-lap-up u-mb-0 u-mt-0_5')"
        :label="{
          text: Translator.trans('organisation.name')
        }"
        name="r_userOrganisation"
        :required="required && statement.r_useName === '1'"
        :model-value="statement.r_userOrganisation"
      @update:modelValue="val => setStatementData({r_userOrganisation: val})" /><!--

   --><label
        v-if="hasPermission('field_statement_user_position') && formOptions.userPosition"
        for="r_userPosition"
        :class="prefixClass('layout__item u-1-of-2 u-1-of-4-lap-up u-mb-0 u-mt-0_5')">
        <span
          :class="prefixClass('block')"
          id="statement-detail-position">
          {{ Translator.trans('position') }}
        </span>
        <select
          id="r_userPosition"
          name="r_userPosition"
          :class="prefixClass('o-form__control-select')"
          @change="val => setStatementData({r_userPosition: val.target.value})"
          :value="statement.r_userPosition">
          <option
            v-for="userPosition in formOptions.userPosition"
            :selected="userPosition === statement.r_userPosition"
            :value="userPosition"
            :key="userPosition">
            {{ userPosition }}
          </option>
        </select>
      </label>
    </div>
  </div>
</template>

<script>
import formGroupMixin from '../mixins/formGroupMixin'

export default {
  name: 'FormGroupStateAndGroupAndOrgaNameAndPosition',

  mixins: [formGroupMixin],

  props: {
    formOptions: {
      type: [Object, Array],
      required: false,
      default: () => ({})
    }
  }
}
</script>
