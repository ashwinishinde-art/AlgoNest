// Authentication Helper Utilities
const Auth = {
    getToken() {
        return sessionStorage.getItem('token');
    },

    setToken(token) {
        sessionStorage.setItem('token', token);
    },

    clearToken() {
        sessionStorage.removeItem('token');
    },

    isAuthenticated() {
        const token = this.getToken();
        if (!token) return false;
        
        // Basic JWT expiry check (client-side decoding)
        try {
            const payload = JSON.parse(atob(token.split('.')[1]));
            return payload.exp > (Date.now() / 1000);
        } catch (e) {
            return false;
        }
    },

    getUser() {
        const token = this.getToken();
        if (!token) return null;
        try {
            return JSON.parse(atob(token.split('.')[1])).user;
        } catch (e) {
            return null;
        }
    },

    getAuthHeader() {
        const token = this.getToken();
        return token ? { 'Authorization': `Bearer ${token}` } : {};
    },

    logout() {
        this.clearToken();
        window.location.href = 'login.html';
    },

    checkAuthGuard() {
        if (!this.isAuthenticated()) {
            this.logout();
        }
    }
};
