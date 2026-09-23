import httpClient from './httpClient'

const resource = '/api/admin/proposal-templates'

export function listProposalTemplates(params = {}) {
  return httpClient.get(resource, { params }).then((response) => response.data)
}

export function createProposalTemplate(payload) {
  return httpClient.post(resource, payload).then((response) => response.data.data)
}

export function updateProposalTemplate(id, payload) {
  return httpClient.put(`${resource}/${id}`, payload).then((response) => response.data.data)
}

export function deleteProposalTemplate(id) {
  return httpClient.delete(`${resource}/${id}`).then((response) => response.data)
}