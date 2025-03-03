<license>
(c) 2010-present DEMOS plan GmbH.

This file is part of the package demosplan,
for more information see the license file.

All rights reserved
</license>

<template>
  <fieldset data-dp-validate="statementFinalEmail">
    <legend
      id="finalEmail"
      class="mb-3 color-text-muted font-normal">
      {{ Translator.trans('statement.final.send') }}
    </legend>
  </fieldset>
</template>
<script>
export default {
  name: 'StatementMetaFinalEmail',
}
</script>
