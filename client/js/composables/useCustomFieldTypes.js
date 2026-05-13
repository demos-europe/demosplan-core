import MultiselectCustomField from '@DpJs/components/customFields/MultiselectCustomField'
import SingleselectCustomField from '@DpJs/components/customFields/SingleselectCustomField'
import TextCustomField from '@DpJs/components/customFields/TextCustomField'

function enrichSelectValue (rawValue, options) {
  const optionIds = rawValue ? [rawValue].flat() : []

  return {
    selectedOptions: optionIds
      .map(id => options.find(opt => opt?.id === id))
      .filter(Boolean),
  }
}

/*
 * Central registry for custom field type capabilities.
 * Add a new entry here when a new field type is introduced.
 */
const FIELD_TYPE_REGISTRY = {
  multiSelect: {
    component: MultiselectCustomField,
    enrichValue: enrichSelectValue,
    supportsOptions: true,
    translationKey: 'custom.field.type.multiSelect',
  },

  singleSelect: {
    component: SingleselectCustomField,
    enrichValue: enrichSelectValue,
    supportsOptions: true,
    translationKey: 'custom.field.type.singleSelect',
  },

  text: {
    component: TextCustomField,
    enrichValue: null,
    supportsOptions: false,
    translationKey: 'custom.field.type.text',
  },
}

/*
 * Maps targetEntity → the default fieldType pre-selected in the create form.
 */
const TARGET_ENTITY_DEFAULT_TYPE = {
  ORGA: 'text',
  SEGMENT: 'singleSelect',
  STATEMENT: 'multiSelect',
}

function enrichFieldValue (fieldType, rawValue, options) {
  const enrich = FIELD_TYPE_REGISTRY[fieldType]?.enrichValue

  return enrich ? enrich(rawValue, options) : {}
}

function fieldTypeSupportsOptions (fieldType) {
  return FIELD_TYPE_REGISTRY[fieldType]?.supportsOptions ?? false
}

function getComponentForFieldType (fieldType) {
  return FIELD_TYPE_REGISTRY[fieldType]?.component ?? SingleselectCustomField
}

function getDefaultFieldTypeForTarget (targetEntity) {
  return TARGET_ENTITY_DEFAULT_TYPE[targetEntity] ?? ''
}

function getFieldTypeLabel (fieldType) {
  const translationKey = FIELD_TYPE_REGISTRY[fieldType]?.translationKey

  return translationKey ? Translator.trans(translationKey) : fieldType
}

export function useCustomFieldTypes () {
  return {
    enrichFieldValue,
    fieldTypeSupportsOptions,
    getComponentForFieldType,
    getDefaultFieldTypeForTarget,
    getFieldTypeLabel,
  }
}
