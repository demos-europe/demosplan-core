<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <dp-input
    id="r_email"
    :class="prefixClass('layout__item')"
    :label="{
      text: Translator.trans('email')
    }"
    :model-value="statement.r_email"
    :required="required && statement.r_useName === '1'"
    autocomplete="email"
    data-cy="emailAddress"
    data-dp-validate-if="#r_useName_1"
    name="r_email"
    type="email"
    width="u-1-of-2"
    @update:model-value="val => setStatementData({r_email: val})"
  />
</template>

<script>
import formGroupMixin from '../mixins/formGroupMixin'

export default {
  name: 'FormGroupEmailAddress',

  mixins: [formGroupMixin],
}
</script>
