<license>
(c) 2010-present DEMOS plan GmbH.

This file is part of the package demosplan,
for more information see the license file.

All rights reserved
</license>

<template>
  <div class="w-14 mx-auto bg-white border border-neutral rounded-lg p-5">
    <h3 class="font-semibold text-center pb-2">
      {{ heading }}
    </h3>
    <h4 class="font-semibold text-center mb-4">
      {{ job.fileName }}
    </h4>
    <div class="flex">
      <span class="inline-block mr-1">
        {{ `${Translator.trans('export.xlsx.scheduled.download.from_type')}:` }}
      </span>
      <span class="inline-block">
        {{ Translator.trans('export.xlsx.scheduled.download.type.xlsx') }}
      </span>
    </div>
    <div class="flex">
      <span class="inline-block mr-1">
        {{ `${Translator.trans('export.xlsx.scheduled.download.from')}:` }}
      </span>
      <a
        :href="downloadUrl"
        class="text-interactive hover:underline"
      >
        {{ downloadUrl }}
      </a>
    </div>
    <dp-inline-notification
      v-if="job.deleteAfter"
      class="my-4"
      :message="statusMessage"
      type="info"
    />
    <p
      v-if="isCompleted"
      class="font-semibold text-sm text-neutral-dark mb-4">
      {{ Translator.trans('export.xlsx.scheduled.download.manual_start_text') }}
    </p>
    <div class="text-right">
      <a
        :href="actionUrl"
        class="btn btn--primary"
      >
        {{ actionLabel }}
      </a>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { DpInlineNotification } from '@demos-europe/demosplan-ui'

type JobStatus = 'completed' | 'failed' | string

interface ExportJob {
  status: JobStatus
  fileName?: string
  deleteAfter?: string | null
}

const props = defineProps<{
  job: ExportJob
  downloadUrl?: string
}>()

const actionLabel = computed(() =>
  Translator.trans(
    isCompleted.value
      ? 'export.xlsx.scheduled.download.manual_trigger'
      : 'home.navigate'
  )
)

const actionUrl = computed(() =>
  isCompleted.value
    ? props.downloadUrl
    : Routing.generate('core_home')
)

const formattedDeleteAfter = computed(() => {
  if (!props.job.deleteAfter) {
    return ''
  }

  return new Intl.DateTimeFormat('de-DE').format(
    new Date(props.job.deleteAfter)
  )
})

const isCompleted = computed(() => props.job.status === 'completed')

const statusTranslationKeys = {
  completed: {
    heading: 'export.xlsx.scheduled.download.heading',
    message: 'export.xlsx.scheduled.download.available_until',
  },
  failed: {
    heading: 'export.xlsx.scheduled.download.failed.heading',
    message: 'export.xlsx.scheduled.download.failed',
  },
  default: {
    heading: 'export.xlsx.scheduled.download.not_ready.heading',
    message: 'export.xlsx.scheduled.download.not_ready',
  },
}

const currentTranslationKeys = computed(() =>
  statusTranslationKeys[props.job.status] ?? statusTranslationKeys.default
)

const heading = computed(() => Translator.trans(currentTranslationKeys.value.heading))

const statusMessage = computed(() => {
  const params = isCompleted.value
    ? { date: formattedDeleteAfter.value }
    : undefined

  return Translator.trans(currentTranslationKeys.value.message, params)
})
</script>
