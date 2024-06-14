export const UserFilterSetResource = {
  data: [{
    attributes: {
      name: 'my Name'
    },
    relationships: {
      filterSet: {
        data: { id: '1' }
      }
    }
  }],
  included: [
    { id: '1', attributes: { hash: 'hash1' } },
    { id: '2', attributes: { hash: 'hash2' } }
  ]
}
