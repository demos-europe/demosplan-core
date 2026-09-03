import { enableAutoUnmount } from '@vue/test-utils'
import ServerBanner from '@DpJs/components/shared/ServerBanner'
import shallowMountWithGlobalMocks from '@DpJs/VueConfigLocal'

describe('ServerBanner', () => {
  const message = '<p>Downtime des Systems heute zwischen 12:00 und 12:15 Uhr</p>'
  const storageKey = 'serverBannerDismissed'

  let wrapper

  beforeEach(() => {
    sessionStorage.clear()
    wrapper = shallowMountWithGlobalMocks(ServerBanner, {
      props: { message },
    })
  })

  enableAutoUnmount(afterEach)

  it('renders the given message', () => {
    expect(wrapper.html()).toContain(message)
  })

  it('hides the banner and marks it as dismissed in sessionStorage when the close button is clicked', async () => {
    await wrapper.findComponent({ name: 'DpButton' }).vm.$emit('click')

    expect(wrapper.find('div').exists()).toBe(false)
    expect(sessionStorage.getItem(storageKey)).toBe('1')
  })

  it('does not render if the banner was already dismissed earlier in this session', () => {
    sessionStorage.setItem(storageKey, '1')

    const dismissedWrapper = shallowMountWithGlobalMocks(ServerBanner, {
      props: { message },
    })

    expect(dismissedWrapper.find('div').exists()).toBe(false)
  })
})
