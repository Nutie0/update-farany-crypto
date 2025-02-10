// Fonction pour ajouter le token aux requêtes
function addAuthorizationHeader(request) {
    const token = localStorage.getItem('token');
    if (token) {
        request.headers.set('Authorization', `Bearer ${token}`);
    }
    return request;
}

// Intercepteur de requêtes fetch
const originalFetch = window.fetch;

window.fetch = async function(url, options = {}) {
    // Si c'est une requête API (commence par /api)
    if (url.toString().startsWith('/api')) {
        const token = localStorage.getItem('token');
        if (token) {
            // Initialiser les headers si nécessaire
            options.headers = options.headers || {};
            // Ajouter le token dans le header Authorization
            options.headers['Authorization'] = `Bearer ${token}`;
        }
    }

    try {
        const response = await originalFetch(url, options);
        
        // Si on reçoit une 401, rediriger vers la page de login
        if (response.status === 401) {
            localStorage.removeItem('token');
            window.location.href = '/login';
            return response;
        }
        
        return response;
    } catch (error) {
        console.error('Erreur fetch:', error);
        throw error;
    }
};
