<license>
(c) 2010-present DEMOS plan GmbH.

This file is part of the package demosplan,
for more information see the license file.

All rights reserved
</license>

<template>
  <ul
    :class="statements.length > 5 ? 'max-h-[255px] overflow-y-auto' : ''"
    class="border rounded-md pb-2 px-1"
  >
    <li
      v-for="stmt in statements"
      :key="stmt.id"
      class="py-2 border-b border-neutral-light-2"
    >
      <div class="flex items-center gap-2 px-1.5">
        <span>{{ stmt.attributes.externId }}</span>
        <span v-if="stmt.attributes.isSubmittedByCitizen">{{ stmt.attributes.authorName }}</span>
        <span v-else>{{ stmt.attributes.initialOrganisationName }}</span>
        <div class="ml-auto">
          <dp-button
            :data-cy="`statementGroupForm:removeStatement:${stmt.id}`"
            :text="Translator.trans('remove')"
            icon="close"
            icon-size="small"
            variant="subtle"
            hide-text
            @click="$emit('remove', stmt.id)"
          />
        </div>
      </div>
    </li>
  </ul>
</template>

<script setup>
import { DpButton } from '@demos-europe/demosplan-ui'

defineProps({
  procedureId: {
    type: String,
    default: '',
  },
  showDetailLink: {
    type: Boolean,
    default: false,
  },
  statements: {
    type: Array,
    required: true,
  },
})

defineEmits(['remove'])
</script>
