<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <div class="layout">
      <dp-input
        id="r_firstname"
        autocomplete="given-name"
        data-cy="firstName"
        :class="prefixClass('layout__item')"
        :label="{
          text: Translator.trans('name.first')
        }"
        name="r_firstname"
        :required="required && statement.r_useName === '1'"
        :model-value="statement.r_firstname"
        width="u-1-of-2"
        @update:modelValue="val => setStatementData({r_firstname: val})" /><!--
   --><dp-input
        id="r_lastname"
        autocomplete="family-name"
        data-cy="familyName"
        :class="prefixClass('layout__item')"
        :label="{
          text: Translator.trans('name.last')
        }"
        name="r_lastname"
        :required="statement.r_useName === '1' && required"
        :model-value="statement.r_lastname"
        width="u-1-of-2"
        @update:modelValue="val => setStatementData({r_lastname: val})" />
    </div>
  </div>
</template>

<script>
import formGroupMixin from '../mixins/formGroupMixin'

export default {
  name: 'FormGroupName',

  mixins: [formGroupMixin]
}
</script>
