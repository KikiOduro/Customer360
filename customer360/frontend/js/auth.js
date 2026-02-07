/**
 * Customer360 - Authentication Module
 * Handles user registration, login, and logout
 */

const auth = {
    /**
     * Check if user is authenticated
     */
    isAuthenticated() {
        return !!api.getToken();
    },

    /**
     * Get current user info
     */
    async getCurrentUser() {
        if (!this.isAuthenticated()) {
            return null;
        }
        try {
            return await api.get('/auth/me');
        } catch (error) {
            return null;
        }
    },

    /**
     * Register a new user
     */
    async register(email, password, companyName) {
        const data = await api.post('/auth/register', {
            email,
            password,
            company_name: companyName
        }, false);
        return data;
    },

    /**
     * Login user
     */
    async login(email, password) {
        const data = await api.post('/auth/login/json', {
            email,
            password
        }, false);
        
        if (data.access_token) {
            api.setToken(data.access_token);
        }
        
        return data;
    },

    /**
     * Logout user
     */
    logout() {
        api.clearToken();
        window.location.hash = '#/login';
    },

    /**
     * Render login page
     */
    renderLoginPage() {
        return `
            <div class="auth-container">
                <div class="auth-card card">
                    <div class="card-body">
                        <div class="auth-header">
                            <h1>Welcome Back</h1>
                            <p>Sign in to your Customer360 account</p>
                        </div>
                        
                        <form id="login-form">
                            <div class="form-group">
                                <label class="form-label" for="email">Email Address</label>
                                <input 
                                    type="email" 
                                    id="email" 
                                    class="form-input" 
                                    placeholder="you@company.com"
                                    required
                                >
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="password">Password</label>
                                <input 
                                    type="password" 
                                    id="password" 
                                    class="form-input" 
                                    placeholder="••••••••"
                                    required
                                    minlength="6"
                                >
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
                                Sign In
                            </button>
                        </form>
                        
                        <div class="auth-footer">
                            <p>Don't have an account? <a href="#/register">Create one</a></p>
                        </div>
                    </div>
                </div>
            </div>
        `;
    },

    /**
     * Render register page
     */
    renderRegisterPage() {
        return `
            <div class="auth-container">
                <div class="auth-card card">
                    <div class="card-body">
                        <div class="auth-header">
                            <h1>Create Account</h1>
                            <p>Start analyzing your customer data</p>
                        </div>
                        
                        <form id="register-form">
                            <div class="form-group">
                                <label class="form-label" for="email">Email Address</label>
                                <input 
                                    type="email" 
                                    id="email" 
                                    class="form-input" 
                                    placeholder="you@company.com"
                                    required
                                >
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="company">Company Name (Optional)</label>
                                <input 
                                    type="text" 
                                    id="company" 
                                    class="form-input" 
                                    placeholder="Your Business Ltd."
                                >
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="password">Password</label>
                                <input 
                                    type="password" 
                                    id="password" 
                                    class="form-input" 
                                    placeholder="••••••••"
                                    required
                                    minlength="6"
                                >
                                <p class="form-hint">Minimum 6 characters</p>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="confirm-password">Confirm Password</label>
                                <input 
                                    type="password" 
                                    id="confirm-password" 
                                    class="form-input" 
                                    placeholder="••••••••"
                                    required
                                    minlength="6"
                                >
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
                                Create Account
                            </button>
                        </form>
                        
                        <div class="auth-footer">
                            <p>Already have an account? <a href="#/login">Sign in</a></p>
                        </div>
                    </div>
                </div>
            </div>
        `;
    },

    /**
     * Initialize login form handlers
     */
    initLoginForm() {
        const form = document.getElementById('login-form');
        if (!form) return;

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            
            try {
                app.showLoading('Signing in...');
                await this.login(email, password);
                app.showToast('Welcome back!', 'success');
                window.location.hash = '#/dashboard';
            } catch (error) {
                app.showToast(error.message, 'error');
            } finally {
                app.hideLoading();
            }
        });
    },

    /**
     * Initialize register form handlers
     */
    initRegisterForm() {
        const form = document.getElementById('register-form');
        if (!form) return;

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const email = document.getElementById('email').value;
            const company = document.getElementById('company').value;
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm-password').value;
            
            if (password !== confirmPassword) {
                app.showToast('Passwords do not match', 'error');
                return;
            }
            
            try {
                app.showLoading('Creating account...');
                await this.register(email, password, company);
                app.showToast('Account created! Please sign in.', 'success');
                window.location.hash = '#/login';
            } catch (error) {
                app.showToast(error.message, 'error');
            } finally {
                app.hideLoading();
            }
        });
    }
};

// Freeze the auth object
Object.freeze(auth);
