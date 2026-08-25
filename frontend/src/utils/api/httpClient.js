import axios from 'axios'

const baseURL = import.meta.env.VITE_API_URL || 'http://localhost:8000'

const httpClient = axios.create({
  baseURL,
  withCredentials: true,
  withXSRFToken: true,
})

export function ensureCsrfCookie() {
  return axios.get('/sanctum/csrf-cookie', {
    baseURL,
    withCredentials: true,
  })
}

export default httpClient
