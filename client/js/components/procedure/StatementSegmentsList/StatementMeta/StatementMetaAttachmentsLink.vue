<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <a
    :href="Routing.generate('core_file', { hash: attachment.hash })"
    rel="noopener"
    target="_blank">
    {{ attachment.filename }}
  </a>
</template>

<script>
export default {
  name: 'StatementMetaAttachmentsLink',

  props: {
    attachment: {
      type: Object,
      required: true
    }
  }
}
</script>
