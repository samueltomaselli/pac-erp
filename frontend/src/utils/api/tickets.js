import httpClient from './httpClient'

const resource = '/api/admin/tickets'

export function listTickets(params = {}) {
  return httpClient.get(resource, { params }).then((response) => response.data)
}

export function getTicket(id) {
  return httpClient.get(`${resource}/${id}`).then((response) => response.data.data)
}

export function createTicket(payload) {
  return httpClient.post(resource, payload).then((response) => response.data.data)
}

export function updateTicket(id, payload) {
  return httpClient.put(`${resource}/${id}`, payload).then((response) => response.data.data)
}

export function transitionTicket(id, status) {
  return httpClient.post(`${resource}/${id}/status`, { status }).then((response) => response.data.data)
}

export function updateTicketAssignee(id, assignedTo) {
  return httpClient.put(`${resource}/${id}/assignee`, { assigned_to: assignedTo }).then((response) => response.data.data)
}

export function listStaff() {
  return httpClient.get('/api/admin/staff').then((response) => response.data.data)
}

export function createTicketComment(id, body, attachments = []) {
  const formData = new FormData()
  formData.append('body', body)
  attachments.forEach((file) => formData.append('attachments[]', file))

  return httpClient
    .post(`${resource}/${id}/comments`, formData)
    .then((response) => response.data.data)
}

export function uploadTicketAttachments(id, attachments) {
  const formData = new FormData()
  attachments.forEach((file) => formData.append('attachments[]', file))

  return httpClient
    .post(`${resource}/${id}/attachments`, formData)
    .then((response) => response.data.data)
}

export function downloadTicketAttachment(id) {
  return httpClient.get(`/api/admin/ticket-attachments/${id}`, { responseType: 'blob' })
}
