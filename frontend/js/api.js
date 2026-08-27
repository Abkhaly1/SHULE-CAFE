// frontend/js/api.js

const API_BASE_URL = 'http://localhost/shule-cafe/api';
const AUTH_TOKEN_KEY = 'shule_token';
const AUTH_ROLE_KEY = 'shule_role';

function getAuthToken() {
    return localStorage.getItem(AUTH_TOKEN_KEY) || sessionStorage.getItem(AUTH_TOKEN_KEY);
}

function setAuthToken(token) {
    if (token) {
        localStorage.setItem(AUTH_TOKEN_KEY, token);
        sessionStorage.setItem(AUTH_TOKEN_KEY, token);
    } else {
        localStorage.removeItem(AUTH_TOKEN_KEY);
        sessionStorage.removeItem(AUTH_TOKEN_KEY);
    }
}

function getCurrentUser() {
    const userStr = localStorage.getItem('shule_user');
    return userStr ? JSON.parse(userStr) : null;
}

async function apiCall(endpoint, method = 'GET', body = null) {
    const headers = {
        'Content-Type': 'application/json',
    };

    const token = getAuthToken();
    if (token) {
        headers['Authorization'] = `Bearer ${token}`;
    }

    const options = {
        method,
        headers,
        credentials: 'include',
    };

    if (body) {
        options.body = JSON.stringify(body);
    }

    try {
        const response = await fetch(`${API_BASE_URL}${endpoint}`, options);
        let data;
        const contentType = response.headers.get("content-type");
        if (contentType && contentType.indexOf("application/json") !== -1) {
            data = await response.json();
        } else {
            data = await response.text();
        }

        if (!response.ok) {
            throw new Error(data?.message || data || `Request failed with status ${response.status}`);
        }

        return data;
    } catch (error) {
        console.error('API Call Error:', error);
        throw error;
    }
}

async function login(username, password) {
    const data = await apiCall('/auth/login.php', 'POST', { identifier: username, password });
    
    if (data && data.accessToken) {
        setAuthToken(data.accessToken);
        localStorage.setItem('shule_user', JSON.stringify(data.user));
    }
    return data;
}

function logout() {
    setAuthToken(null);
    localStorage.removeItem('shule_user');
    const currentPath = window.location.pathname;
    const frontendIndex = currentPath.indexOf('/frontend/');
    if (frontendIndex !== -1) {
        const basePath = currentPath.substring(0, frontendIndex + '/frontend/'.length);
        window.location.href = basePath + 'login.html';
    } else {
        window.location.href = '/login.html';
    }
}

function getCurrentUser() {
    const userStr = localStorage.getItem('shule_user');
    return userStr ? JSON.parse(userStr) : null;
}
