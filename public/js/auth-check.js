/**
 * Auth Check for Email Signature Pages
 * Makes auth status available globally
 * Pages can use window.authStatus to check if user is logged in
 */

window.authStatus = {
    checked: false,
    authenticated: false,
    user: null
};

(async function() {
    try {
        const response = await fetch('https://apps.ironcrestsoftware.com/auth/api.php?action=check', {
            credentials: 'include'
        });
        const data = await response.json();
        
        window.authStatus = {
            checked: true,
            authenticated: data.success && data.authenticated || false,
            user: data.user || null
        };
        
        // Dispatch event so pages can react
        window.dispatchEvent(new CustomEvent('authStatusReady', { detail: window.authStatus }));
        
    } catch (error) {
        console.error('Auth check failed:', error);
        window.authStatus = {
            checked: true,
            authenticated: false,
            user: null,
            error: error
        };
        window.dispatchEvent(new CustomEvent('authStatusReady', { detail: window.authStatus }));
    }
})();
