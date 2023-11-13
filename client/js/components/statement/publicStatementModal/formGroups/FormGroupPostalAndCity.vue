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
        id="r_postalCode"
        autocomplete="postal-code"
        data-cy="postalCode"
        :class="prefixClass('layout__item')"
        data-dp-validate-if="#r_getEvaluation_snailmail, #r_useName_1"
        :disabled="disabled"
        :label="{
          text: Translator.trans('postalcode')
        }"
        name="r_postalCode"
        pattern="^[0-9]{4,5}$"
        :required="required && statement.r_useName === '1'"
        :value="statement.r_postalCode"
        width="u-4-of-12"
        @input="val => setStatementData({r_postalCode: val})" /><!--
   --><dp-input
        id="r_city"
        autocomplete="address-level2"
        data-cy="city"
        :class="prefixClass('layout__item')"
        :disabled="disabled"
        :label="{
            text: Translator.trans('city')
        }"
        name="r_city"
        :required="required && statement.r_useName === '1'"
        :value="statement.r_city"
        width="u-8-of-12"
        @input="val => setStatementData({r_city: val})" />
    </div>
  </div>
</template>

<script>
import formGroupMixin from '../mixins/formGroupMixin'

export default {
  name: 'FormGroupPostalAndCity',

  mixins: [formGroupMixin]
}
</script>
