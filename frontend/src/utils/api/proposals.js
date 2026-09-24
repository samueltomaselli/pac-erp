import httpClient from './httpClient'

const resource = '/api/admin/proposals'

export function listProposals(params = {}) {
  return httpClient.get(resource, { params }).then((response) => response.data)
}

export function getProposal(id) {
  return httpClient.get(`${resource}/${id}`).then((response) => response.data.data)
}

export function createProposal(payload) {
  return httpClient.post(resource, payload).then((response) => response.data.data)
}

export function updateProposal(id, payload) {
  return httpClient.put(`${resource}/${id}`, payload).then((response) => response.data.data)
}

export function deleteProposal(id) {
  return httpClient.delete(`${resource}/${id}`).then((response) => response.data)
}

export function addProposalItem(id, payload) {
  return httpClient.post(`${resource}/${id}/items`, payload).then((response) => response.data.data)
}

export function updateProposalItem(proposalId, itemId, payload) {
  return httpClient.put(`${resource}/${proposalId}/items/${itemId}`, payload).then((response) => response.data.data)
}

export function removeProposalItem(proposalId, itemId) {
  return httpClient.delete(`${resource}/${proposalId}/items/${itemId}`).then((response) => response.data.data)
}

export function sendProposal(id) {
  return httpClient.post(`${resource}/${id}/send`).then((response) => response.data.data)
}

export function acceptProposal(id) {
  return httpClient.post(`${resource}/${id}/accept`).then((response) => response.data.data)
}

export function rejectProposal(id) {
  return httpClient.post(`${resource}/${id}/reject`).then((response) => response.data.data)
}