<?php
/**
 * Customer 360 - Create Account Page
 * Two-column layout with value proposition and registration form
 */
session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if (isset($_GET['error'])) {
    $error = htmlspecialchars($_GET['error']);
}

$currentYear = date('Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Create Account - Customer 360</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com" rel="preconnect"/>
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet"/>
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#0b203c",
                        "background-light": "#f6f7f8",
                        "background-dark": "#121820",
                        "primary-hover": "#08182d",
                        "primary-light": "#153055",
                    },
                    fontFamily: {
                        "display": ["Inter", "sans-serif"]
                    },
                    borderRadius: {"DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "full": "9999px"},
                },
            },
        }
    </script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f1f1; 
        }
        ::-webkit-scrollbar-thumb {
            background: #c1c1c1; 
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8; 
        }
    </style>
</head>
<body class="font-display bg-background-light dark:bg-background-dark text-slate-900 antialiased min-h-screen flex flex-col">
    <!-- Navbar -->
    <header class="w-full border-b border-slate-200 bg-white dark:bg-slate-900 dark:border-slate-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <!-- Logo -->
                <a href="landing.php" class="flex items-center gap-2">
                    <div class="flex items-center justify-center size-8 bg-primary rounded-lg text-white">
                        <span class="material-symbols-outlined text-xl">analytics</span>
                    </div>
                    <span class="text-xl font-bold tracking-tight text-primary dark:text-white">Customer 360</span>
                </a>
                <!-- Right Side Actions -->
                <div class="flex items-center gap-4">
                    <span class="text-sm text-slate-600 dark:text-slate-400 hidden sm:block">Already have an account?</span>
                    <a class="text-sm font-semibold text-primary hover:text-primary-light dark:text-white transition-colors" href="signin.php">Log In</a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="w-full max-w-5xl grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
            
            <!-- Left Column: Value Proposition (Desktop only) -->
            <div class="hidden lg:flex flex-col gap-8 pr-8">
                <div class="space-y-4">
                    <h1 class="text-4xl md:text-5xl font-black text-primary dark:text-white leading-tight tracking-tight">
                        Grow your business with data-driven insights.
                    </h1>
                    <p class="text-lg text-slate-600 dark:text-slate-400 max-w-md">
                        Join thousands of Ghanaian businesses transforming their customer relationships. Simple, secure, and built for growth.
                    </p>
                </div>
                
                <!-- Feature Cards -->
                <div class="grid grid-cols-2 gap-6 mt-4">
                    <div class="flex flex-col gap-2 p-4 bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-100 dark:border-slate-700">
                        <span class="material-symbols-outlined text-primary dark:text-blue-400 text-3xl">trending_up</span>
                        <h3 class="font-bold text-slate-900 dark:text-white">Real-time Analytics</h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Track sales and customer trends instantly.</p>
                    </div>
                    <div class="flex flex-col gap-2 p-4 bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-100 dark:border-slate-700">
                        <span class="material-symbols-outlined text-primary dark:text-blue-400 text-3xl">security</span>
                        <h3 class="font-bold text-slate-900 dark:text-white">Bank-Grade Security</h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Your business data is encrypted and safe.</p>
                    </div>
                </div>
                
                <!-- Social Proof -->
                <div class="flex items-center gap-4 mt-4">
                    <div class="flex -space-x-3">
                        <img alt="User 1" class="w-10 h-10 rounded-full border-2 border-white dark:border-slate-900 object-cover" src="https://lh3.googleusercontent.com/aida-public/AB6AXuDK5ikLGVf5qAYrTJ4wNhykH2NU6xlPRiaAic2YGrQiq8TafGz52ytiYQIE9xx-BkU4YRsPSR-eP13Ku-_EzY5LO3q1srQxuVBkgpNKoI0ce73URxfWzF81ND9VT46Z1rHEEZPO-8a8BfW7p2cpoJ60C552pDV4I_vil8jTam3WdOpcfvbWHzBlTJqYj9cWZ29kxkY7cLnrojBVl57424QT8raWRalJXhedWOZHsgh_PBUERTCIyArl8_EYMkbHAqVzUE2kSVpWnFs"/>
                        <img alt="User 2" class="w-10 h-10 rounded-full border-2 border-white dark:border-slate-900 object-cover" src="https://lh3.googleusercontent.com/aida-public/AB6AXuC6nNOmbAtVVDxjivivPP7rBS9HaCdix4BT9_7IjrF9EKVnGxut4Z_TYU4KmtvcmBKDpb4vPracHVDRQmSrBxIielHDP6uzJJ_Dosb3CO3M0vOHnOyGLum0FYD3DK4HAmkPnPbW32mZ9XYFsyFl_SVynkTAgnRkCwfPsxjvM75zlkWGlNe3fMTwcbeDOFeZpRFdz--ictkdcKDeBEAY2bmUIDfuluvbMj4Ew3WqOx8u1mBTXFYargPeVyrFRAKqCctcgxqhwq4EUQw"/>
                        <img alt="User 3" class="w-10 h-10 rounded-full border-2 border-white dark:border-slate-900 object-cover" src="https://lh3.googleusercontent.com/aida-public/AB6AXuBgp2B6gtL7p_p9elNqh9WmjovKgfS0IDw5tfCyQJumQB914DN9pq-MrKic94wTgxEXq7yPEwVdn7U45lJPetfvIWFYhYmeMH8bhhPwSPLUgR19QwCDcMmBgZ9BuN2ut8BsieDLmSFKTZGa1E5OVOruQZNukn4wp9EkzsCIdm_6qGePCxRwuC95TD_gWTTDYUWU72Iky2H0SRXtII-SuE1BrvUVTs5xOtBpeIgyKl7fib6VwApXedrMH2a-VEZKZwcq2AzNJ3EjqFc"/>
                        <div class="w-10 h-10 rounded-full border-2 border-white dark:border-slate-900 bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-xs font-bold text-slate-600 dark:text-slate-300">+2k</div>
                    </div>
                    <p class="text-sm font-medium text-slate-600 dark:text-slate-400">Trusted by SMEs across Ghana</p>
                </div>
            </div>

            <!-- Right Column: Registration Form -->
            <div class="w-full">
                <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl shadow-slate-200/50 dark:shadow-slate-900/50 border border-slate-100 dark:border-slate-700 p-8 sm:p-10">
                    
                    <!-- Mobile Header -->
                    <div class="mb-8 lg:hidden">
                        <h1 class="text-2xl font-bold text-primary dark:text-white mb-2">Create Account</h1>
                        <p class="text-slate-600 dark:text-slate-400">Join Ghanaian businesses growing with data.</p>
                    </div>
                    
                    <!-- Desktop Header -->
                    <div class="hidden lg:block mb-8">
                        <h2 class="text-2xl font-bold text-primary dark:text-white">Get Started</h2>
                        <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">Fill in the details below to create your account.</p>
                    </div>

                    <!-- Error Message -->
                    <?php if (!empty($error)): ?>
                    <div class="mb-6 bg-red-50 text-red-600 px-4 py-3 rounded-lg text-sm flex items-center gap-2 border border-red-100">
                        <span class="material-symbols-outlined text-[20px]">error</span>
                        <span><?php echo $error; ?></span>
                    </div>
                    <?php endif; ?>

                    <!-- Registration Form -->
                    <form action="api/auth.php" method="POST" class="space-y-5" id="registerForm">
                        <input type="hidden" name="action" value="register">
                        
                        <!-- Name & Business Name Row -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div class="space-y-1.5">
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300" for="fullname">Full Name</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                                        <span class="material-symbols-outlined text-[20px]">person</span>
                                    </div>
                                    <input 
                                        class="pl-10 block w-full rounded-lg border-slate-300 bg-slate-50 dark:bg-slate-900/50 dark:border-slate-600 text-slate-900 dark:text-white focus:ring-primary focus:border-primary sm:text-sm h-11 transition-shadow" 
                                        id="fullname" 
                                        name="full_name" 
                                        placeholder="Kwame Mensah" 
                                        required 
                                        type="text"
                                    />
                                </div>
                            </div>
                            <div class="space-y-1.5">
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300" for="business">Business Name</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                                        <span class="material-symbols-outlined text-[20px]">storefront</span>
                                    </div>
                                    <input 
                                        class="pl-10 block w-full rounded-lg border-slate-300 bg-slate-50 dark:bg-slate-900/50 dark:border-slate-600 text-slate-900 dark:text-white focus:ring-primary focus:border-primary sm:text-sm h-11 transition-shadow" 
                                        id="business" 
                                        name="company_name" 
                                        placeholder="Mensah Enterprise" 
                                        required 
                                        type="text"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Industry -->
                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300" for="industry">Industry</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                                    <span class="material-symbols-outlined text-[20px]">category</span>
                                </div>
                                <select 
                                    class="pl-10 block w-full rounded-lg border-slate-300 bg-slate-50 dark:bg-slate-900/50 dark:border-slate-600 text-slate-900 dark:text-white focus:ring-primary focus:border-primary sm:text-sm h-11 transition-shadow appearance-none" 
                                    id="industry" 
                                    name="industry"
                                >
                                    <option disabled selected value="">Select your industry type</option>
                                    <option value="retail">Retail &amp; Provisions</option>
                                    <option value="pharmacy">Pharmacy / Chemical Shop</option>
                                    <option value="salon">Salon / Barber Shop</option>
                                    <option value="fashion">Fashion &amp; Tailoring</option>
                                    <option value="electronics">Electronics &amp; Hardware</option>
                                    <option value="food">Food &amp; Restaurant</option>
                                    <option value="other">Other Service</option>
                                </select>
                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-slate-400">
                                    <span class="material-symbols-outlined text-[20px]">expand_more</span>
                                </div>
                            </div>
                        </div>

                        <!-- Email -->
                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300" for="email">Email Address</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                                    <span class="material-symbols-outlined text-[20px]">mail</span>
                                </div>
                                <input 
                                    class="pl-10 block w-full rounded-lg border-slate-300 bg-slate-50 dark:bg-slate-900/50 dark:border-slate-600 text-slate-900 dark:text-white focus:ring-primary focus:border-primary sm:text-sm h-11 transition-shadow" 
                                    id="email" 
                                    name="email" 
                                    placeholder="kwame@example.com" 
                                    required 
                                    type="email"
                                />
                            </div>
                        </div>

                        <!-- Passwords -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div class="space-y-1.5">
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300" for="password">Password</label>
                                <div class="relative group">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                                        <span class="material-symbols-outlined text-[20px]">lock</span>
                                    </div>
                                    <input 
                                        class="pl-10 pr-10 block w-full rounded-lg border-slate-300 bg-slate-50 dark:bg-slate-900/50 dark:border-slate-600 text-slate-900 dark:text-white focus:ring-primary focus:border-primary sm:text-sm h-11 transition-shadow" 
                                        id="password" 
                                        name="password" 
                                        placeholder="Min. 8 characters" 
                                        required 
                                        type="password"
                                        minlength="8"
                                    />
                                    <button 
                                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 focus:outline-none" 
                                        type="button"
                                        onclick="togglePassword('password', 'passwordIcon')"
                                    >
                                        <span class="material-symbols-outlined text-[20px]" id="passwordIcon">visibility</span>
                                    </button>
                                </div>
                            </div>
                            <div class="space-y-1.5">
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300" for="confirm_password">Confirm Password</label>
                                <div class="relative group">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                                        <span class="material-symbols-outlined text-[20px]">lock_reset</span>
                                    </div>
                                    <input 
                                        class="pl-10 pr-10 block w-full rounded-lg border-slate-300 bg-slate-50 dark:bg-slate-900/50 dark:border-slate-600 text-slate-900 dark:text-white focus:ring-primary focus:border-primary sm:text-sm h-11 transition-shadow" 
                                        id="confirm_password" 
                                        name="confirm_password" 
                                        placeholder="Repeat password" 
                                        required 
                                        type="password"
                                    />
                                    <button 
                                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 focus:outline-none" 
                                        type="button"
                                        onclick="togglePassword('confirm_password', 'confirmPasswordIcon')"
                                    >
                                        <span class="material-symbols-outlined text-[20px]" id="confirmPasswordIcon">visibility</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Terms Checkbox -->
                        <div class="flex items-start pt-2">
                            <div class="flex h-5 items-center">
                                <input 
                                    class="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary dark:border-slate-600 dark:bg-slate-800" 
                                    id="terms" 
                                    name="terms" 
                                    required 
                                    type="checkbox"
                                />
                            </div>
                            <div class="ml-3 text-sm">
                                <label class="font-medium text-slate-700 dark:text-slate-300" for="terms">
                                    I agree to the <a class="text-primary hover:text-primary-light underline dark:text-white" href="#">Terms and Conditions</a> and <a class="text-primary hover:text-primary-light underline dark:text-white" href="#">Privacy Policy</a>.
                                </label>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="pt-4">
                            <button 
                                class="group relative flex w-full justify-center rounded-lg bg-primary px-4 py-3 text-sm font-bold text-white shadow-lg shadow-primary/30 hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 dark:focus:ring-offset-slate-900 transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed" 
                                type="submit"
                                id="submitBtn"
                            >
                                <span id="btnText">Create Account</span>
                                <span class="absolute inset-y-0 right-0 flex items-center pr-3 opacity-0 group-hover:opacity-100 transition-opacity duration-200" id="btnArrow">
                                    <span class="material-symbols-outlined text-lg">arrow_forward</span>
                                </span>
                            </button>
                        </div>
                    </form>

                    <!-- Mobile Footer -->
                    <div class="mt-6 text-center lg:hidden">
                        <p class="text-sm text-slate-600 dark:text-slate-400">
                            Already have an account? 
                            <a class="font-semibold text-primary hover:text-primary-light dark:text-white" href="signin.php">Log in</a>
                        </p>
                    </div>
                </div>

                <!-- Trust Badges -->
                <div class="mt-6 flex justify-center gap-6 opacity-60 grayscale hover:grayscale-0 hover:opacity-100 transition-all duration-300">
                    <div class="flex items-center gap-1">
                        <span class="material-symbols-outlined text-slate-500 text-lg">verified_user</span>
                        <span class="text-xs font-semibold text-slate-500">SSL Secure</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <span class="material-symbols-outlined text-slate-500 text-lg">shield</span>
                        <span class="text-xs font-semibold text-slate-500">Data Privacy</span>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="py-6 text-center border-t border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 mt-auto">
        <p class="text-sm text-slate-500 dark:text-slate-400">
            © <?php echo $currentYear; ?> Customer 360 Ghana. All rights reserved.
        </p>
    </footer>

    <script>
        // Password visibility toggle
        function togglePassword(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.textContent = 'visibility_off';
            } else {
                input.type = 'password';
                icon.textContent = 'visibility';
            }
        }

        // Form validation and submission
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const submitBtn = document.getElementById('submitBtn');
            const btnText = document.getElementById('btnText');
            const btnArrow = document.getElementById('btnArrow');
            
            // Password match validation
            if (password !== confirmPassword) {
                e.preventDefault();
                showError('Passwords do not match!');
                return false;
            }
            
            // Password length validation
            if (password.length < 8) {
                e.preventDefault();
                showError('Password must be at least 8 characters long!');
                return false;
            }
            
            // Show loading state
            submitBtn.disabled = true;
            btnText.textContent = 'Creating account...';
            btnArrow.style.display = 'none';
        });

        // Show error message
        function showError(message) {
            let errorContainer = document.querySelector('.error-alert');
            
            if (!errorContainer) {
                errorContainer = document.createElement('div');
                errorContainer.className = 'error-alert mb-6 bg-red-50 text-red-600 px-4 py-3 rounded-lg text-sm flex items-center gap-2 border border-red-100';
                errorContainer.innerHTML = `
                    <span class="material-symbols-outlined text-[20px]">error</span>
                    <span class="error-message"></span>
                `;
                
                const form = document.getElementById('registerForm');
                form.parentNode.insertBefore(errorContainer, form);
            }
            
            errorContainer.querySelector('.error-message').textContent = message;
            errorContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
            
            // Auto-hide after 5 seconds
            setTimeout(function() {
                errorContainer.style.transition = 'opacity 0.5s ease-out';
                errorContainer.style.opacity = '0';
                setTimeout(function() {
                    errorContainer.remove();
                }, 500);
            }, 5000);
        }

        // Real-time password confirmation check
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword.length > 0 && password !== confirmPassword) {
                this.classList.add('border-red-300', 'focus:border-red-500', 'focus:ring-red-200');
                this.classList.remove('border-slate-300', 'focus:border-primary', 'focus:ring-primary');
            } else {
                this.classList.remove('border-red-300', 'focus:border-red-500', 'focus:ring-red-200');
                this.classList.add('border-slate-300', 'focus:border-primary', 'focus:ring-primary');
            }
        });
    </script>
</body>
</html>
