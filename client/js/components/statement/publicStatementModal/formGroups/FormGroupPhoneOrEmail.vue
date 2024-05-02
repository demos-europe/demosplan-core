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
        id="r_phone"
        autocomplete="tel"
        :class="prefixClass('u-4-of-12 layout__item')"
        data-dp-validate-if="#r_useName_1"
        :label="{
          text: Translator.trans('phone')
        }"
        name="r_phone"
        pattern="^(\+?)(-| |[0-9]|\(|\))*$"
        :required="phoneIsRequired"
        type="tel"
        :model-value="statement.r_phone"
        @update:modelValue="val => setStatementData({r_phone: val})" /><!--
   --><dp-input
        id="r_email"
        autocomplete="email"
        :class="prefixClass('u-8-of-12 layout__item')"
        data-dp-validate-if="#r_useName_1"
        :label="{
          text: Translator.trans('email')
        }"
        name="r_email"
        :required="mailIsRequired"
        type="email"
        :model-value="statement.r_email"
        @update:modelValue="val => setStatementData({r_email: val})" />
    </div>
  </div>
</template>

<script>
import formGroupMixin from '../mixins/formGroupMixin'

export default {
  name: 'FormGroupPhoneOrEmail',

  mixins: [formGroupMixin],

  computed: {
    mailIsRequired () {
      return this.required && this.statement.r_useName === '1' && this.statement.r_phone === ''
    },

    phoneIsRequired () {
      return this.required && this.statement.r_useName === '1' && this.statement.r_email === ''
    }
  }
}
</script>
