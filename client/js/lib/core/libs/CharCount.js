/**
 * Initializes an input field enhancement that counts and displays available characters when there is `maxlength` applied
 * on a given input field or textarea. It is used in conjunction with the generic translation key 'input.text.maxlength'.
 * @constructor
 */
export default function CharCount (target = null) {
  /**
   * Update element that displays how many characters are left.
   *
   * @param input - Reference to Dom Node of the input/textarea element
   * @param counter - Reference to the Dom Node that displays how many characters are left (located inside the trans key...)
   * @param maxlength - Maximum length the content must have
   * @return {boolean}
   */
  const handleKeyupEvent = (input, counter, maxlength) => {
    if (input.value.length > maxlength) {
      input.value = input.value.substring(0, maxlength)
      return false
    } else {
      counter.value = (maxlength - input.value.length)
    }
  }

  // Get a reference to all counters on the page
  let inputs = [target]
  if (target === null) {
    inputs = document.querySelectorAll('[data-counter]')
  }

  // Init found instances
  for (let i = 0; i < inputs.length; i++) {
    const input = inputs[i]
    const counterId = input.getAttribute('data-counter')
    const counter = document.getElementById(counterId)
    const maxlength = parseInt(input.getAttribute('maxlength'))

    // Set initial counter value
    counter.value = maxlength - input.value.length

    // Update counter on keyup
    input.addEventListener('keyup', function () { handleKeyupEvent(input, counter, maxlength) })
  }
}
