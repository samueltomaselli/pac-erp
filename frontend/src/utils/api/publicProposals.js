import httpClient, { ensureCsrfCookie } from './httpClient'

const resource = '/api/public/proposals'

export function getPublicProposal(pubId) {
  return httpClient.get(`${resource}/${pubId}`).then((response) => response.data.data)
}

export async function acceptPublicProposal(pubId, payload) {
  await ensureCsrfCookie()

  const response = await httpClient.post(`${resource}/${pubId}/accept`, payload)

  return response.data.data
}
