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
        :class="prefixClass('layout__item')"
        :label="{
          text: Translator.trans('name.first')
        }"
        :model-value="statement.r_firstname"
        :required="required && statement.r_useName === '1'"
        autocomplete="given-name"
        data-cy="firstName"
        name="r_firstname"
        width="u-1-of-2"
        @update:model-value="val => setStatementData({r_firstname: val})"
      /><!--
   --><dp-input
        id="r_lastname"
        :class="prefixClass('layout__item')"
        :label="{
          text: Translator.trans('name.last')
        }"
        :model-value="statement.r_lastname"
        :required="statement.r_useName === '1' && required"
        autocomplete="family-name"
        data-cy="familyName"
        name="r_lastname"
        width="u-1-of-2"
        @update:model-value="val => setStatementData({r_lastname: val})"
    />
    </div>
  </div>
</template>

<script>
import formGroupMixin from '../mixins/formGroupMixin'

export default {
  name: 'FormGroupName',

  mixins: [formGroupMixin],
}
</script>
