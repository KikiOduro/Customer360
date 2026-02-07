/**
 * Customer360 - Main Application Module
 * Handles routing, navigation, and global UI functions
 */

const app = {
    /**
     * Initialize the application
     */
    init() {
        this.setupRouter();
        this.handleRoute();
        
        // Listen for hash changes
        window.addEventListener('hashchange', () => this.handleRoute());
    },

    /**
     * Setup router
     */
    setupRouter() {
        this.routes = {
            'login': {
                render: () => auth.renderLoginPage(),
                init: () => auth.initLoginForm(),
                requiresAuth: false
            },
            'register': {
                render: () => auth.renderRegisterPage(),
                init: () => auth.initRegisterForm(),
                requiresAuth: false
            },
            'dashboard': {
                render: async () => await dashboard.renderDashboard(),
                init: () => {},
                requiresAuth: true
            },
            'upload': {
                render: () => upload.renderUploadPage(),
                init: () => upload.initUploadPage(),
                requiresAuth: true
            },
            'results': {
                render: async (params) => await dashboard.renderResultsPage(params.id),
                init: () => {},
                requiresAuth: true
            }
        };
    },

    /**
     * Handle current route
     */
    async handleRoute() {
        const hash = window.location.hash.slice(2) || 'dashboard'; // Remove '#/'
        const [route, ...params] = hash.split('/');
        
        const routeConfig = this.routes[route];
        
        // Check authentication
        const isAuthenticated = auth.isAuthenticated();
        
        if (!routeConfig) {
            // Unknown route, redirect
            window.location.hash = isAuthenticated ? '#/dashboard' : '#/login';
            return;
        }

        if (routeConfig.requiresAuth && !isAuthenticated) {
            window.location.hash = '#/login';
            return;
        }

        if (!routeConfig.requiresAuth && isAuthenticated && (route === 'login' || route === 'register')) {
            window.location.hash = '#/dashboard';
            return;
        }

        // Update navigation
        this.updateNavigation(isAuthenticated);

        // Render page
        try {
            this.showLoading('Loading...');
            
            const content = await routeConfig.render({ id: params[0] });
            document.getElementById('main-content').innerHTML = content;
            
            // Initialize page-specific handlers
            routeConfig.init();
            
        } catch (error) {
            console.error('Route error:', error);
            document.getElementById('main-content').innerHTML = `
                <div class="card">
                    <div class="card-body">
                        <div class="empty-state">
                            <div class="empty-icon">❌</div>
                            <h3>Error</h3>
                            <p>${error.message}</p>
                        </div>
                    </div>
                </div>
            `;
        } finally {
            this.hideLoading();
        }
    },

    /**
     * Navigate to a route
     */
    navigate(route) {
        window.location.hash = `#/${route}`;
    },

    /**
     * Update navigation based on auth state
     */
    updateNavigation(isAuthenticated) {
        const navLinks = document.getElementById('nav-links');
        
        if (isAuthenticated) {
            navLinks.innerHTML = `
                <a href="#/dashboard" class="${this.isActiveRoute('dashboard') ? 'active' : ''}">Dashboard</a>
                <a href="#/upload" class="${this.isActiveRoute('upload') ? 'active' : ''}">Upload</a>
                <button class="btn btn-secondary" onclick="auth.logout()">Logout</button>
            `;
        } else {
            navLinks.innerHTML = `
                <a href="#/login" class="btn btn-secondary">Sign In</a>
                <a href="#/register" class="btn btn-primary">Get Started</a>
            `;
        }
    },

    /**
     * Check if current route matches
     */
    isActiveRoute(route) {
        const hash = window.location.hash.slice(2) || 'dashboard';
        return hash.startsWith(route);
    },

    /**
     * Show loading overlay
     */
    showLoading(text = 'Loading...') {
        document.getElementById('loading-text').textContent = text;
        document.getElementById('loading-overlay').classList.add('active');
    },

    /**
     * Hide loading overlay
     */
    hideLoading() {
        document.getElementById('loading-overlay').classList.remove('active');
    },

    /**
     * Show toast notification
     */
    showToast(message, type = 'info') {
        const container = document.getElementById('toast-container');
        
        const icons = {
            success: '✅',
            error: '❌',
            warning: '⚠️',
            info: 'ℹ️'
        };

        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <span class="toast-icon">${icons[type]}</span>
            <span class="toast-message">${message}</span>
            <button class="toast-close" onclick="this.parentElement.remove()">×</button>
        `;

        container.appendChild(toast);

        // Auto remove after 5 seconds
        setTimeout(() => {
            if (toast.parentElement) {
                toast.remove();
            }
        }, 5000);
    }
};

// Initialize app when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    app.init();
});
