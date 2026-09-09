/**
 * Groups statement resources of a JSON:API response by the procedure they belong to.
 *
 * The cross-procedure submitter search returns statements of several procedures in one flat list,
 * while the UI displays one accordion per procedure. The procedure names are only available in the
 * sideloaded `included` resources, so they are resolved by id here.
 *
 * Statement attributes are flattened into the group items because the data table expects flat rows.
 *
 * @param {Array} results - The `data` array of the JSON:API response.
 * @param {Array} included - The `included` array of the JSON:API response, holding the procedures.
 * @returns {Array} One entry per procedure, each holding `procedureId`, `procedureName` and `statements`.
 */
export default function buildProcedureGroups (results, included) {
  const nameById = included.reduce((acc, resource) => {
    acc[resource.id] = resource.attributes.name

    return acc
  }, {})

  const groups = results.reduce((acc, result) => {
    const procedureId = result.relationships.procedure.data.id

    if (!acc[procedureId]) {
      acc[procedureId] = {
        procedureId,
        procedureName: nameById[procedureId] || Translator.trans('procedure'),
        statements: [],
      }
    }

    acc[procedureId].statements.push({ id: result.id, ...result.attributes })

    return acc
  }, {})

  return Object.values(groups)
}
