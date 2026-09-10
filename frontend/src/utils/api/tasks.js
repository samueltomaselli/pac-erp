import httpClient from './httpClient'

const resource = '/api/admin/tasks'

export function listTasks(params = {}) {
  return httpClient.get(resource, { params }).then((response) => response.data)
}

export function createTask(payload) {
  return httpClient.post(resource, payload).then((response) => response.data.data)
}

export function updateTask(id, payload) {
  return httpClient.put(`${resource}/${id}`, payload).then((response) => response.data.data)
}

export function completeTask(id) {
  return httpClient.post(`${resource}/${id}/complete`).then((response) => response.data.data)
}

export function reopenTask(id) {
  return httpClient.post(`${resource}/${id}/reopen`).then((response) => response.data.data)
}

export function deleteTask(id) {
  return httpClient.delete(`${resource}/${id}`).then((response) => response.data)
}
