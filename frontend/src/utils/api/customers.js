import httpClient from './httpClient'

const resource = '/api/admin/customers'

export function listCustomers(params = {}) {
  return httpClient.get(resource, { params }).then((response) => response.data)
}

export function getCustomer(id) {
  return httpClient.get(`${resource}/${id}`).then((response) => response.data.data)
}

export function createCustomer(payload) {
  return httpClient.post(resource, payload).then((response) => ({
    customer: response.data.data,
    generatedPassword: response.data.generated_password,
  }))
}

export function updateCustomer(id, payload) {
  return httpClient.put(`${resource}/${id}`, payload).then((response) => response.data.data)
}

export function deactivateCustomer(id) {
  return httpClient.delete(`${resource}/${id}`).then((response) => response.data)
}

export function restoreCustomer(id) {
  return httpClient.post(`${resource}/${id}/restore`).then((response) => response.data.data)
}
