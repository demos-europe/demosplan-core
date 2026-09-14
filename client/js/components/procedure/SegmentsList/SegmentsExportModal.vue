<license>
(c) 2010-present DEMOS plan GmbH.

This file is part of the package demosplan,
for more information see the license file.

All rights reserved
</license>

<template>
  <div>
    <dp-button
      class="mr-0 h-fit"
      data-cy="exportModal:open"
      icon="export"
      icon-size="medium"
      :text="Translator.trans('export.verb')"
      variant="subtle"
    />
  </div>
</template>

<script>
import { DpButton } from '@demos-europe/demosplan-ui'
export default {
  name: 'SegmentsExportModal',

  components: {
    DpButton,
  }
}
</script>
