import httpClient from './httpClient'

const resource = '/api/admin/proposal-catalog-items'

export function listProposalCatalog(params = {}) {
  return httpClient.get(resource, { params }).then((response) => response.data)
}

export function createProposalCatalog(payload) {
  return httpClient.post(resource, payload).then((response) => response.data.data)
}

export function updateProposalCatalog(id, payload) {
  return httpClient.put(`${resource}/${id}`, payload).then((response) => response.data.data)
}

export function deleteProposalCatalog(id) {
  return httpClient.delete(`${resource}/${id}`).then((response) => response.data)
}