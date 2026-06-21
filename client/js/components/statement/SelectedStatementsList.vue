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
        <div class="ml-auto flex items-center gap-2">
          <a
            v-if="showDetailLink"
            :href="Routing.generate('dplan_statement_segments_list', { procedureId, statementId: stmt.id })"
            :title="Translator.trans('details.show')"
            class="o-link--default"
          >
            <dp-icon
              icon="arrow-square-out"
              size="small"
            />
          </a>
          <button
            :data-cy="`statementGroupForm:removeStatement:${stmt.id}`"
            class="btn--blank o-link--default"
            type="button"
            @click="$emit('remove', stmt.id)"
          >
            <dp-icon
              icon="close"
              size="small"
            />
          </button>
        </div>
      </div>
    </li>
  </ul>
</template>

<script setup>
import { DpIcon } from '@demos-europe/demosplan-ui'

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
