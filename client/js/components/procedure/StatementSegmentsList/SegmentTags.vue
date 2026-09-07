<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div class="bg-white rounded-lg border border-neutral p-3 mb-2">
    <div class="flex items-baseline gap-1 mb-2">
      <span class="font-bold">{{ Translator.trans('tags') }}</span>
      <span
        v-if="tags.length"
        class="text-neutral-dark-1"
      >({{ tags.length }})</span>
    </div>

    <div
      :id="`segmentTags_${segmentId}`"
      class="flex flex-wrap gap-1 items-center mb-2"
    >
      <span
        v-if="tags.length === 0"
        class="text-neutral-dark-1 text-sm"
        data-cy="segmentTags:empty"
      >
        {{ Translator.trans('segment.tags.none.assigned') }}
      </span>

      <addon-wrapper
        v-else-if="hasStyledTags"
        hook-name="tag.style.segment.recommendation"
        :addon-props="{ tags }"
        wrapper-classes="flex flex-wrap gap-1 items-center"
        @remove="removeTag"
      />

      <template v-else>
        <span
          v-for="tag in tags"
          :key="`tag_${tag.id}`"
          class="tag inline-flex items-center gap-1 shrink-0 max-w-full text-sm py-0.5 pl-2 pr-1 rounded-md bg-surface border border-neutral text-status-neutral"
          data-cy="segmentTags:chip"
        >
          <span class="overflow-hidden text-ellipsis whitespace-nowrap">
            {{ tag.attributes.title }}
          </span>
          <dp-button
            color="secondary"
            data-cy="segmentTags:removeTag"
            hide-text
            icon="close"
            icon-size="small"
            :text="Translator.trans('remove')"
            variant="transparent"
            @click="removeTag(tag.id)"
          />
        </span>
      </template>
    </div>

    <dp-multiselect
      :close-on-select="false"
      :clear-on-select="false"
      group-label="title"
      group-values="tags"
      :group-select="false"
      label="title"
      :options="groupedOptions"
      :placeholder="Translator.trans('segment.tags.add')"
      track-by="id"
      @input="onSelect"
    >
      <template v-slot:option="{ props }">
        <span
          v-if="props.option.$isLabel"
          class="font-semibold text-sm text-default block mb-1"
          :class="{ 'border-t border-neutral pt-2 mt-1': props.index !== 0 }"
        >
          {{ props.option.$groupLabel }}
        </span>
        <label
          v-else
          class="weight--normal text-sm mx-1 mt-0 mb-1 flex items-center gap-2 cursor-pointer"
          :for="`segmentTags_${segmentId}_option_${props.option.id}`"
          @click.prevent
        >
          <input
            :id="`segmentTags_${segmentId}_option_${props.option.id}`"
            type="checkbox"
            class="shrink-0 m-0"
            :checked="isAssigned(props.option.id)"
          >
          {{ props.option.title }}
        </label>
      </template>

      <template v-slot:noResult>
        <span
          v-cleanhtml="noMatchHint"
          class="text-sm"
        />
      </template>
    </dp-multiselect>
  </div>
</template>

<script setup lang="ts">
import { CleanHtml, dpApi, DpButton, DpMultiselect, sortAlphabetically } from '@demos-europe/demosplan-ui'
import { computed, inject, onMounted, type PropType, ref } from 'vue'
// eslint-disable-next-line import/extensions -- vue-tsc can't resolve an extensionless .vue import (see eslint.config.js:286-288)
import AddonWrapper from '@DpJs/components/addon/AddonWrapper.vue'
import { apiUrl } from '@DpJs/store/core/VuexApiRoutes'
import loadAddonComponents from '@DpJs/lib/addon/loadAddonComponents'
import { useStore } from 'vuex'

interface JsonApiRelationshipToOne {
  data: { id: string, type: string } | null
}

interface Tag {
  id: string
  type: 'Tag'
  attributes: {
    title: string
  }
  relationships?: {
    topic?: JsonApiRelationshipToOne
  }
}

interface TagTopic {
  id: string
  type: 'TagTopic'
  attributes: {
    title: string
  }
}

interface TagOption {
  id: string
  title: string
}

/*
 * The demosplan-ui `dpApi` export is untyped plain JS: `.get` is attached to the base function at
 * runtime, so TypeScript only sees the base signature. Cast locally rather than typing the library.
 */
type DpApiGet = (url: string, params?: Record<string, unknown>) => Promise<{ data: { data: Tag[] } }>

interface GroupedTagOption extends TagOption {
  tags: TagOption[]
}

/*
 * Shared across all SegmentTags instances on the page (one per segment) so the tag→topic
 * mapping is fetched once instead of once per segment row.
 */
let tagTopicByTagIdPromise: Promise<Record<string, string | null>> | null = null

function fetchTagTopicByTagId (): Promise<Record<string, string | null>> {
  if (tagTopicByTagIdPromise === null) {
    tagTopicByTagIdPromise = (dpApi as unknown as { get: DpApiGet }).get(apiUrl('Tag'), {
      include: 'topic',
      fields: { Tag: 'topic' },
    })
      .then(response => response.data.data.reduce((map: Record<string, string | null>, tag: Tag) => {
        map[tag.id] = tag.relationships?.topic?.data?.id ?? null

        return map
      }, {} as Record<string, string | null>))
      .catch(() => {
        tagTopicByTagIdPromise = null

        return {}
      })
  }

  return tagTopicByTagIdPromise
}

const { segmentId, tags } = defineProps({
  segmentId: {
    type: String,
    required: true,
  },

  tags: {
    type: Array as PropType<Tag[]>,
    required: true,
  },
})

const emit = defineEmits<{
  update: [tags: Tag[]]
}>()

const procedureId = inject<string>('procedureId')

const store = useStore()

const vCleanhtml = CleanHtml

const hasStyledTags = ref(false)
const tagTopicByTagId = ref<Record<string, string | null>>({})

const availableTags = computed<Tag[]>(() => Object.values(store.state.Tag.items))

const categorizedTags = computed<Tag[]>(() => availableTags.value.filter(tag => tagTopicByTagId.value[tag.id]))

const tagTopics = computed<TagTopic[]>(() => sortAlphabetically(Object.values(store.state.TagTopic.items), 'attributes.title'))

const uncategorizedTags = computed<Tag[]>(() => sortAlphabetically(
  availableTags.value.filter(tag => !tagTopicByTagId.value[tag.id]),
  'attributes.title',
))

const groupedOptions = computed<GroupedTagOption[]>(() => {
  const categorized = tagTopics.value.map(topic => ({
    title: topic.attributes.title,
    id: topic.id,
    tags: sortAlphabetically(
      categorizedTags.value.filter(tag => tagTopicByTagId.value[tag.id] === topic.id),
      'attributes.title',
    ).map((tag: Tag) => ({ title: tag.attributes.title, id: tag.id })),
  }))

  const uncategorized = {
    title: Translator.trans('category.none'),
    id: 'category.none',
    tags: uncategorizedTags.value.map(tag => ({ title: tag.attributes.title, id: tag.id })),
  }

  return [...categorized, uncategorized].filter(group => group.tags.length > 0)
})

const noMatchHint = computed<string>(() => {
  const url = Routing.generate('DemosPlan_statement_administration_tags', { procedure: procedureId ?? '' })

  return Translator.trans('segment.tags.no.match', { url })
})

const isAssigned = (id: string): boolean => tags.some(tag => tag.id === id)

const onSelect = (option: TagOption | null): void => {
  if (!option) {
    return
  }

  if (isAssigned(option.id)) {
    removeTag(option.id)

    return
  }

  const tag = availableTags.value.find(el => el.id === option.id)

  if (tag) {
    emit('update', [...tags, tag])
  }
}

const removeTag = (id: string): void => {
  emit('update', tags.filter(tag => tag.id !== id))
}

onMounted(async () => {
  const addons = await loadAddonComponents('tag.style.segment.recommendation')

  hasStyledTags.value = addons.length > 0

  /*
   * Fetched outside the shared Tag vuex module: any other fetch on this page that lists Tag
   * without requesting 'topic' overwrites this relationship for every Tag entry
   * (see reference_jsonapi_store_full_replace_race in project memory), so the topic mapping
   * used for grouping is kept as local state instead.
   */
  tagTopicByTagId.value = await fetchTagTopicByTagId()
})
</script>
