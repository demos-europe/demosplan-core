import StatusBadge from '@DpJs/components/procedure/Shared/StatusBadge'
import shallowMountWithGlobalMocks from '@DpJs/VueConfigLocal'
describe('StatusBadge.vue', () => {
  it('renders correctly for each status', () => {
    const statuses = ['new', 'processing', 'completed']
    const colors = ['info', 'warning', 'confirm']

    statuses.forEach((status, index) => {
      const wrapper = shallowMountWithGlobalMocks(StatusBadge, {
        propsData: { status }
      })

      expect(wrapper.text()).toBe(status)
      expect(wrapper.classes()).toContain(colors[index])
    })
  })
})
