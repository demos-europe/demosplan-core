<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <fieldset class="u-pb-0 inline">
    <legend class="sr-only">
      {{ Translator.trans('filter') }}
    </legend>
    <select
      class="o-form__control-select w-10"
      id="districtFilter"
      @change="filterItems"
      v-model="selectedDistrict">
      <option
        value="all"
        selected>
        Alle Verfahrensträger
      </option>
      <option
        v-for="district in districtFilters"
        :key="district.field"
        :value="district.field">
        {{ district.value }}
      </option>
    </select><!--
 --><select
      class="o-form__control-select u-ml-0_5 w-10"
      id="documentFilter"
      @change="filterItems"
      v-model="selectedDocument">
      <option
        value="all"
        selected>
        Alle Dokumente
      </option>
      <option
        v-for="doc in documentFilters"
        :key="doc.field"
        :value="doc.field">
        {{ doc.value }}
      </option>
    </select>
  </fieldset>
</template>

<script>
export default {
  name: 'DpFilterMasterToeb',

  props: {
    items: {
      type: Array,
      required: true
    },

    fields: {
      type: Array,
      required: true
    }
  },

  data () {
    return {
      selectedDocument: 'all',
      selectedDistrict: 'all',
      districtFilters: this.getFieldsByPattern(this.fields, /^district/),
      documentFilters: this.getFieldsByPattern(this.fields, /^document/)
    }
  },

  methods: {
    filterItems () {
      const filteredItems = this.items.filter(item => {
        let filteredByDocuments = true
        let filteredByDistricts = true
        if (this.selectedDocument !== 'all') {
          filteredByDocuments = item[this.selectedDocument]
        }

        if (this.selectedDistrict !== 'all') {
          filteredByDistricts = item[this.selectedDistrict] > 0
        }

        return filteredByDistricts && filteredByDocuments
      })

      this.$emit('items-filtered', filteredItems)
    },

    getFieldsByPattern (fields, pattern) {
      return fields.reduce((acc, field) => {
        if (field.field.match(pattern) !== null) {
          acc.push(field)
        }
        return acc
      }, [])
    }
  }
}
</script>
