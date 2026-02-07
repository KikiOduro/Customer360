/**
 * Customer360 - API Client Module
 * Handles all HTTP requests to the backend API
 */

const API_BASE_URL = 'http://localhost:8000/api';

/**
 * API client with automatic token handling
 */
const api = {
    /**
     * Get the stored authentication token
     */
    getToken() {
        return localStorage.getItem('customer360_token');
    },

    /**
     * Set the authentication token
     */
    setToken(token) {
        localStorage.setItem('customer360_token', token);
    },

    /**
     * Clear the authentication token
     */
    clearToken() {
        localStorage.removeItem('customer360_token');
    },

    /**
     * Get headers for API requests
     */
    getHeaders(includeAuth = true, isFormData = false) {
        const headers = {};
        
        if (!isFormData) {
            headers['Content-Type'] = 'application/json';
        }
        
        if (includeAuth && this.getToken()) {
            headers['Authorization'] = `Bearer ${this.getToken()}`;
        }
        
        return headers;
    },

    /**
     * Handle API response
     */
    async handleResponse(response) {
        if (response.status === 401) {
            this.clearToken();
            window.location.hash = '#/login';
            throw new Error('Session expired. Please login again.');
        }

        const contentType = response.headers.get('content-type');
        
        if (contentType && contentType.includes('application/json')) {
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.detail || 'An error occurred');
            }
            
            return data;
        }
        
        if (!response.ok) {
            throw new Error('An error occurred');
        }
        
        return response;
    },

    /**
     * Make a GET request
     */
    async get(endpoint, requireAuth = true) {
        const response = await fetch(`${API_BASE_URL}${endpoint}`, {
            method: 'GET',
            headers: this.getHeaders(requireAuth)
        });
        return this.handleResponse(response);
    },

    /**
     * Make a POST request with JSON body
     */
    async post(endpoint, data, requireAuth = true) {
        const response = await fetch(`${API_BASE_URL}${endpoint}`, {
            method: 'POST',
            headers: this.getHeaders(requireAuth),
            body: JSON.stringify(data)
        });
        return this.handleResponse(response);
    },

    /**
     * Make a POST request with FormData
     */
    async postForm(endpoint, formData, requireAuth = true) {
        const response = await fetch(`${API_BASE_URL}${endpoint}`, {
            method: 'POST',
            headers: this.getHeaders(requireAuth, true),
            body: formData
        });
        return this.handleResponse(response);
    },

    /**
     * Make a DELETE request
     */
    async delete(endpoint, requireAuth = true) {
        const response = await fetch(`${API_BASE_URL}${endpoint}`, {
            method: 'DELETE',
            headers: this.getHeaders(requireAuth)
        });
        return this.handleResponse(response);
    },

    /**
     * Download a file
     */
    async downloadFile(endpoint, filename) {
        const response = await fetch(`${API_BASE_URL}${endpoint}`, {
            method: 'GET',
            headers: this.getHeaders(true)
        });

        if (!response.ok) {
            throw new Error('Failed to download file');
        }

        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
    }
};

// Freeze the api object to prevent modifications
Object.freeze(api);
