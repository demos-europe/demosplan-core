<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <div :class="prefixClass('layout')">
      <dp-input
        id="r_street"
        autocomplete="address-line1"
        data-cy="streetName"
        :class="prefixClass('layout__item')"
        :disabled="disabled"
        :label="{
          text: Translator.trans('street')
        }"
        name="r_street"
        :required="required && statement.r_useName === '1'"
        :model-value="statement.r_street"
        width="u-8-of-12"
        @update:model-value="val => setStatementData({r_street: val})" /><!--
   --><dp-input
        id="r_houseNumber"
        autocomplete="address-line2"
        data-cy="houseNumber"
        :class="prefixClass('layout__item')"
        :disabled="disabled"
        :label="{
          text: Translator.trans('street.number.short')
        }"
        name="r_houseNumber"
        :required="required && statement.r_useName === '1'"
        :model-value="statement.r_houseNumber"
        width="u-4-of-12"
        @update:model-value="val => setStatementData({r_houseNumber: val})" />
    </div>
  </div>
</template>

<script>
import formGroupMixin from '../mixins/formGroupMixin'

export default {
  name: 'FormGroupStreetAndNumber',

  mixins: [formGroupMixin]
}
</script>
