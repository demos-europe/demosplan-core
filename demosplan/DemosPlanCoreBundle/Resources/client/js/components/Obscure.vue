<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <span
    :class="obscureClass"
    :title="Translator.trans('obscure.title')">
    <slot />
  </span>
</template>

<script>
export default {
  name: 'DpObscure',

  computed: {
    obscureClass () {
      return (hasPermission('feature_obscure_text')) ? 'u-obscure' : ''
    }
  }

}
</script>
