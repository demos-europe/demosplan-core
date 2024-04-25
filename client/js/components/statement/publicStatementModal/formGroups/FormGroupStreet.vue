<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <dp-input
    id="r_street"
    autocomplete="street-address"
    data-cy="streetAddress"
    :class="prefixClass('layout__item')"
    :label="{
      text: Translator.trans('street')
    }"
    name="r_street"
    :required="required && statement.r_useName === '1'"
    :value="statement.r_street"
    width="u-1-of-2"
    @update:model-value="val => setStatementData({r_street: val})" />
</template>

<script>
import formGroupMixin from '../mixins/formGroupMixin'

export default {
  name: 'FormGroupStreet',

  mixins: [formGroupMixin]
}
</script>
