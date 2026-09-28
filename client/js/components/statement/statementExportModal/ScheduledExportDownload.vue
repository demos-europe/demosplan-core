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
    <div
      v-if="!isFailed"
      class="flex"
    >
      <span class="inline-block mr-1">
        {{ `${Translator.trans('export.xlsx.scheduled.download.from_type')}:` }}
      </span>
      <span class="inline-block">
        {{ Translator.trans('export.xlsx.scheduled.download.type.xlsx') }}
      </span>
    </div>
    <div
      v-if="downloadUrl && !isFailed"
      class="flex">
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
import { computed, onMounted } from 'vue'
import { DpInlineNotification } from '@demos-europe/demosplan-ui'
import type { ExportJob, StatusTranslationMap, TranslationKeys } from '@DpJs/types/scheduledExport'

interface Props {
  job: ExportJob
  downloadUrl: string
}

const props = defineProps<Props>()

const isCompleted = computed((): boolean => props.job.status === 'completed')
const isFailed = computed((): boolean => props.job.status === 'failed')

const formattedDeleteAfter = computed((): string => {
  if (!props.job.deleteAfter) {
    return ''
  }

  return new Intl.DateTimeFormat('de-DE').format(
    new Date(props.job.deleteAfter)
  )
})

const statusTranslationKeys: StatusTranslationMap = {
  completed: {
    heading: 'export.xlsx.scheduled.download.heading',
    message: 'export.xlsx.scheduled.download.available_until',
  },
  failed: {
    heading: 'export.xlsx.scheduled.download.failed.heading',
    message: 'export.xlsx.scheduled.download.failed',
  },
  pending: {
    heading: 'export.xlsx.scheduled.download.not_ready.heading',
    message: 'export.xlsx.scheduled.download.not_ready',
  },
  default: {
    heading: 'export.xlsx.scheduled.download.not_ready.heading',
    message: 'export.xlsx.scheduled.download.not_ready',
  },
}

const currentTranslationKeys = computed((): TranslationKeys =>
  statusTranslationKeys[props.job.status] ?? statusTranslationKeys.default
)

const heading = computed((): string =>
  Translator.trans(currentTranslationKeys.value.heading)
)

const statusMessage = computed((): string => {
  const params: Record<string, string> | undefined = isCompleted.value
    ? { date: formattedDeleteAfter.value }
    : undefined

  return Translator.trans(currentTranslationKeys.value.message, params)
})

const actionLabel = computed((): string =>
  Translator.trans(
    isCompleted.value
      ? 'export.xlsx.scheduled.download.manual_trigger'
      : 'home.navigate'
  )
)

const actionUrl = computed((): string =>
  isCompleted.value
    ? props.downloadUrl
    : Routing.generate('core_home')
)

onMounted((): void => {
  // Auto-download when status is completed
  if (isCompleted.value && props.downloadUrl) {
    setTimeout((): void => {
      window.location.href = props.downloadUrl
    }, 1000)
  }
})
</script>
