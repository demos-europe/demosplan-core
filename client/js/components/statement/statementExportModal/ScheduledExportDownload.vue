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
      v-if="isCompleted || isExpired"
      class="flex"
    >
      <span class="mr-1">
        {{ `${Translator.trans('export.xlsx.scheduled.download.from_type')}:` }}
      </span>
      <span>
        {{ Translator.trans('export.xlsx.scheduled.download.type.xlsx') }}
      </span>
    </div>
    <div
      v-if="(isCompleted || isExpired) && downloadUrl"
      class="flex"
    >
      <span class="mr-1">
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
      class="my-4"
      :message="statusMessage"
      :type="isFailed ? 'warning' : 'info'"
    />
    <p
      v-if="isCompleted"
      class="font-semibold text-sm text-neutral-dark mb-4"
    >
      {{ Translator.trans('export.xlsx.scheduled.download.manual_start_text') }}
    </p>
    <p v-if="isFailed">
      {{ Translator.trans('export.xlsx.scheduled.download.failed.hint') }}
    </p>
    <div class="text-right mt-4">
      <dp-button
        data-cy="scheduledExportDownload:manualTrigger"
        :href="actionUrl"
        :text="actionLabel"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import type { DisplayStatus, ExportJob, StatusTranslationMap, TranslationKeys } from '@DpJs/types/scheduledExport'
import { computed, onMounted } from 'vue'
import { DpButton, DpInlineNotification } from '@demos-europe/demosplan-ui'

interface Props {
  job: ExportJob
  downloadUrl: string
}

const props = defineProps<Props>()

const isExpired = computed((): boolean => {
  if (!props.job.deleteAfter) {
    return false
  }

  return new Date(props.job.deleteAfter) < new Date()
})

const isCompleted = computed((): boolean => props.job.status === 'completed' && !isExpired.value)
const isFailed = computed((): boolean => props.job.status === 'failed')

const formattedDeleteAfter = computed((): string => {
  if (!props.job.deleteAfter) {
    return ''
  }

  return new Intl.DateTimeFormat('de-DE').format(
    new Date(props.job.deleteAfter),
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
  expired: {
    heading: 'export.xlsx.scheduled.download.expired.heading',
    message: 'export.xlsx.scheduled.download.expired',
  },
}

const displayStatus = computed((): DisplayStatus => {
  if (isExpired.value) {
    return 'expired'
  }
  return props.job.status
})

const currentTranslationKeys = computed((): TranslationKeys =>
  statusTranslationKeys[displayStatus.value],
)

const heading = computed((): string =>
  Translator.trans(currentTranslationKeys.value.heading),
)

const statusMessage = computed((): string => {
  const params: Record<string, string> | undefined = isCompleted.value ?
    { date: formattedDeleteAfter.value } :
    undefined

  return Translator.trans(currentTranslationKeys.value.message, params)
})

const actionLabel = computed((): string =>
  Translator.trans(
    isCompleted.value ?
      'export.xlsx.scheduled.download.manual_trigger' :
      'home.navigate',
  ),
)

const actionUrl = computed((): string =>
  isCompleted.value ?
    props.downloadUrl :
    Routing.generate('core_home'),
)

onMounted((): void => {
  // Auto-download only when status is completed and not expired
  if (isCompleted.value && props.downloadUrl && !isExpired.value) {
    setTimeout((): void => {
      window.location.href = props.downloadUrl
    }, 1000)
  }
})
</script>
