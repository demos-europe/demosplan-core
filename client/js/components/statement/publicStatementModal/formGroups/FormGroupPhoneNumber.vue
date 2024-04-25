<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <dp-input
    id="r_phone"
    autocomplete="tel"
    data-dp-validate-if="#r_useName_1"
    :label="{
      text: Translator.trans('phone')
    }"
    name="r_phone"
    pattern="^(\+?)(-| |[0-9]|\(|\))*$"
    :required="required && statement.r_useName === '1'"
    type="tel"
    :value="statement.r_phone"
    @update:model-value="val => setStatementData({r_phone: val})" />
</template>

<script>
import formGroupMixin from '../mixins/formGroupMixin'

export default {
  name: 'FormGroupPhoneNumber',

  mixins: [formGroupMixin]
}
</script>
