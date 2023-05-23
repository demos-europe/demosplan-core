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
  <div>
    <slot
      :formatted-authored-date="formattedAuthoredDate"
      :formatted-submit-date="formattedSubmitDate"
      :initial-organisation-department-name="statement.attributes.initialOrganisationDepartmentName || '-'"
      :initial-organisation-name="statement.attributes.initialOrganisationName || '-'"
      :intern-id="statement.attributes.internId || '-'"
      :memo="statement.attributes.memo || '-'"
      :submit-name="submitName"
      :submit-type="submitType"
      :location="location" />
  </div>
</template>

<script>
const convertDate = (dateString) => {
  const date = dateString.split('T')[0].split('-')
  if (date.length > 1) {
    return date[2] + '.' + date[1] + '.' + date[0]
  }
  return date[0]
}

export default {
  name: 'StatementMetaData',

  props: {
    statement: {
      type: Object,
      required: true
    },

    submitTypeOptions: {
      type: Array,
      required: false,
      default: () => []
    }
  },

  computed: {
    formattedAuthoredDate () {
      return this.statement.attributes.authoredDate ? convertDate(this.statement.attributes.authoredDate) : '-'
    },

    formattedSubmitDate () {
      return this.statement.attributes.submitDate ? convertDate(this.statement.attributes.submitDate) : '-'
    },

    location () {
      let locationString = ''
      if (this.statement.attributes.initialOrganisationStreet) {
        locationString += this.statement.attributes.initialOrganisationStreet
        if (this.statement.attributes.initialOrganisationHouseNumber) {
          locationString += ' ' + this.statement.attributes.initialOrganisationHouseNumber
        }
        locationString += ', '
      }
      if (this.statement.attributes.initialOrganisationPostalCode) {
        locationString += this.statement.attributes.initialOrganisationPostalCode + ' '
      }
      if (this.statement.attributes.initialOrganisationCity) {
        locationString += this.statement.attributes.initialOrganisationCity
      }
      return locationString !== '' ? locationString : '-'
    },

    submitName () {
      return this.statement.attributes.authorName || '-'
    },

    submitType () {
      if (!this.statement.attributes.submitType) {
        return '-'
      }
      const option = this.submitTypeOptions.find(option => option.value === this.statement.attributes.submitType)
      return option ? Translator.trans(option.label) : ''
    }
  }
}
</script>
