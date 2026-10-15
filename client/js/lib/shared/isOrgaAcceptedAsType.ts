/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

interface RegistrationStatus {
  status: string,
  type: string
}

/**
 * Whether any of the given registration statuses is accepted for one of the given orga types.
 * Deliberately excludes "pending" - the underlying grant only takes effect once actually accepted,
 * so offering it earlier would be misleading.
 */
export function isOrgaAcceptedAsType (registrationStatuses: RegistrationStatus[], types: string[]): boolean {
  return registrationStatuses.some(registration => registration.status === 'accepted' && types.includes(registration.type))
}
