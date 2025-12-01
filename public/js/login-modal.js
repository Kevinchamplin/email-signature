/**
 * Login Modal
 * For returning users to access their saved preferences
 * Note: showLoginModal and closeLoginModal are defined in layout.js
 */

// Switch from login to signup
function switchToSignup() {
    if (typeof closeLoginModal === 'function') {
        closeLoginModal();
    }
    if (typeof showQuickSignup === 'function') {
        showQuickSignup();
    }
}

// Handle login form submission
async function handleLogin(event) {
    event.preventDefault();
    
    const emailInput = document.getElementById('login-email');
    const passwordInput = document.getElementById('login-password');
    const submitBtn = document.getElementById('login-submit');
    const errorDiv = document.getElementById('login-error');
    
    if (!emailInput || !passwordInput) {
        console.error('Login form inputs not found!');
        return;
    }
    
    const email = emailInput.value.trim();
    const password = passwordInput.value;
    
    // Validate
    if (!email || !password) {
        errorDiv.textContent = 'Email and password are required';
        errorDiv.classList.remove('hidden');
        return;
    }
    
    // Show loading
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing In...';
    errorDiv.classList.add('hidden');
    
    try {
        // Login user
        const response = await fetch(`${API_BASE}/auth.php?action=login`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, password })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Login successful - store session token
            if (data.session_token) {
                // Store session token in cookie (30 days)
                const expiryDate = new Date();
                expiryDate.setDate(expiryDate.getDate() + 30);
                document.cookie = `session_token=${data.session_token}; expires=${expiryDate.toUTCString()}; path=/; SameSite=Strict`;
            }
            
            submitBtn.innerHTML = '<i class="fas fa-check"></i> Success! Loading...';
            
            // Close modal
            closeLoginModal();
            
            // Show success message
            showToast('✓ Welcome back! Loading your preferences...', 'success');
            
            // Reload to update UI with logged-in state and load preferences
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            // Login failed
            errorDiv.textContent = data.error || 'Invalid email or password';
            errorDiv.classList.remove('hidden');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Sign In';
        }
    } catch (error) {
        console.error('Login error:', error);
        errorDiv.textContent = 'Network error. Please try again.';
        errorDiv.classList.remove('hidden');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Sign In';
    }
}

// Initialize login modal
document.addEventListener('DOMContentLoaded', () => {
    // Close modal when clicking outside
    const modal = document.getElementById('login-modal');
    if (modal) {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeLoginModal();
            }
        });
    }
    
    // Handle form submission
    const form = document.getElementById('login-form');
    if (form) {
        form.addEventListener('submit', handleLogin);
    }
    
    // Close button
    const closeBtn = document.getElementById('login-close');
    if (closeBtn) {
        closeBtn.addEventListener('click', closeLoginModal);
    }
});
