<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <a
    data-cy="statementMetaAttachmentsLink"
    :href="Routing.generate('core_file_procedure', { hash: attachment.hash, procedureId: procedureId})"
    rel="noopener"
    target="_blank">
    {{ attachment.filename }}
  </a>
</template>

<script>
export default {
  name: 'StatementMetaAttachmentsLink',

  props: {
    procedureId: {
      type: String,
      required: true
    },

    attachment: {
      type: Object,
      required: true
    }
  }
}
</script>
