import httpClient from './httpClient'

const resource = '/api/customer/tickets'

export function listCustomerTickets(params = {}) {
  return httpClient.get(resource, { params }).then((response) => response.data)
}

export function getCustomerTicket(id) {
  return httpClient.get(`${resource}/${id}`).then((response) => response.data.data)
}

export function createCustomerTicket(payload) {
  return httpClient.post(resource, payload).then((response) => response.data.data)
}

export function createCustomerTicketComment(id, body, attachments = []) {
  const formData = new FormData()
  formData.append('body', body)
  attachments.forEach((file) => formData.append('attachments[]', file))

  return httpClient
    .post(`${resource}/${id}/comments`, formData)
    .then((response) => response.data.data)
}

export function uploadCustomerTicketAttachments(id, attachments) {
  const formData = new FormData()
  attachments.forEach((file) => formData.append('attachments[]', file))

  return httpClient
    .post(`${resource}/${id}/attachments`, formData)
    .then((response) => response.data.data)
}

export function downloadCustomerTicketAttachment(id) {
  return httpClient.get(`/api/customer/ticket-attachments/${id}`, { responseType: 'blob' })
}
