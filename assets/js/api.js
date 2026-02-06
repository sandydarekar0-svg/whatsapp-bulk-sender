// assets/js/api.js

const API_BASE_URL = 'http://localhost/whatsapp-bulk-sender/backend';

async function apiCall(endpoint, method = 'GET', data = null, token = null) {
    const options = {
        method: method,
        headers: {
            'Content-Type': 'application/json'
        }
    };

    if (token) {
        options.headers['Authorization'] = `Bearer ${token}`;
    }

    if (data && method !== 'GET') {
        if (data instanceof FormData) {
            delete options.headers['Content-Type'];
            options.body = data;
        } else {
            options.body = JSON.stringify(data);
        }
    }

    try {
        const response = await fetch(API_BASE_URL + endpoint, options);
        
        if (response.status === 401) {
            localStorage.removeItem('token');
            window.location.href = '/index.html';
            throw new Error('Unauthorized');
        }

        const result = await response.json();
        
        if (!response.ok) {
            throw new Error(result.message || `HTTP ${response.status}`);
        }

        return result;
    } catch (error) {
        throw error;
    }
}

function showAlert(message, type = 'info') {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;

    // Insert at top of page
    const container = document.querySelector('main') || document.querySelector('.main-content') || document.body;
    container.insertAdjacentHTML('afterbegin', alertHtml);

    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        const alert = container.querySelector('.alert');
        if (alert) alert.remove();
    }, 5000);
}

// Utility: Format date
function formatDate(dateString) {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

// Utility: Format time
function formatTime(dateString) {
    return new Date(dateString).toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit'
    });
}
