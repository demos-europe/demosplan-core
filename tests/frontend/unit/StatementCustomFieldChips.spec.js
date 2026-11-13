import { mount } from '@vue/test-utils'
import StatementCustomFieldChips from '@DpJs/components/statement/listStatements/StatementCustomFieldChips.vue'

describe('StatementCustomFieldChips', () => {
  const selectDefinition = (id, name, options, extra = {}) => ({
    id,
    attributes: { name, fieldType: 'singleSelect', options, ...extra },
  })

  const definitions = [
    selectDefinition('region', 'Region', [{ id: 'o1', label: 'North' }, { id: 'o2', label: 'South' }]),
    selectDefinition('topic', 'Topic', [{ id: 'o3', label: 'Traffic' }]),
    selectDefinition('own', 'Own column', [{ id: 'o4', label: 'Separate' }], { isSeparateColumn: true }),
  ]

  const mountChips = (props = {}) => mount(StatementCustomFieldChips, {
    props: { definitions, externId: '42', ...props },
    global: { stubs: { DpPopover: true, DpTooltip: { template: '<span><slot /></span>' } } },
  })

  const badgeTexts = wrapper => wrapper.findAllComponents({ name: 'DpBadge' }).map(badge => badge.props('text'))

  it('shows the values that were handed over, without requesting them', () => {
    const wrapper = mountChips({
      customFields: [{ id: 'region', value: ['o1', 'o2'] }, { id: 'topic', value: 'o3' }],
    })

    expect(badgeTexts(wrapper)).toEqual(['North', 'South', 'Traffic'])
  })

  it('provides the field name of each chip as persistent text for assistive technology', () => {
    const wrapper = mountChips({ customFields: [{ id: 'region', value: 'o1' }] })

    expect(wrapper.find('.sr-only').text()).toBe('Region:')
  })

  it('does not show fields that have their own column', () => {
    const wrapper = mountChips({ customFields: [{ id: 'own', value: 'o4' }] })

    expect(wrapper.find('div').exists()).toBe(false)
  })

  it('renders nothing if the statement has no values', () => {
    expect(mountChips({ customFields: null }).find('div').exists()).toBe(false)
    expect(mountChips({ customFields: [] }).find('div').exists()).toBe(false)
  })

  it('updates when the handed over values change', async () => {
    const wrapper = mountChips({ customFields: [{ id: 'topic', value: 'o3' }] })

    expect(badgeTexts(wrapper)).toEqual(['Traffic'])

    await wrapper.setProps({ customFields: [{ id: 'region', value: 'o1' }] })

    expect(badgeTexts(wrapper)).toEqual(['North'])
  })

  it('shows an overflow control for more than four chips', () => {
    const options = ['a', 'b', 'c', 'd', 'e', 'f'].map(letter => ({ id: letter, label: letter }))
    const wrapper = mountChips({
      customFields: [{ id: 'many', value: options.map(option => option.id) }],
      definitions: [selectDefinition('many', 'Many', options)],
    })

    expect(wrapper.findComponent({ name: 'DpPopover' }).exists()).toBe(true)
    // The accessible name has to contain the visible trigger text "+2"
    expect(Translator.trans).toHaveBeenCalledWith('custom.fields.chips.show.more', { count: 2 })
  })
})
