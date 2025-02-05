<template>
  <div>
    <header class="border-b mb-4">
      <h1>
        {{ Translator.trans('tag.administration') }}
      </h1>
    </header>

    <p class="mb-2">
      {{ Translator.trans('text.tags.administration') }}
    </p>
  </div>
</template>

<script>
export default {
  name: 'TagsListHeader'
}
</script>
