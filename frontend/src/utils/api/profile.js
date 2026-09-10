import httpClient from './httpClient'

export function getCustomerProfile() {
  return httpClient.get('/api/customer/profile').then((response) => response.data.data)
}
