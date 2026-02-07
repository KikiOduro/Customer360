<?php
/**
 * Customer 360 - Sign In Page
 * Clean, centered card design with icons
 */
session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';
if (isset($_GET['error'])) {
    $error = htmlspecialchars($_GET['error']);
}
if (isset($_GET['registered'])) {
    $success = 'Account created successfully! Please sign in.';
}
if (isset($_GET['logout'])) {
    $success = 'You have been logged out successfully.';
}

$currentYear = date('Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Sign In - Customer 360</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com" rel="preconnect"/>
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#0b203c",
                        "primary-hover": "#153055",
                        "background-light": "#f6f7f8",
                        "background-dark": "#121820",
                        "surface-light": "#ffffff",
                        "surface-dark": "#1a222e",
                        "text-main": "#0f141a",
                        "text-sub": "#536e93",
                        "border-color": "#d1dae5",
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
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark min-h-screen flex items-center justify-center p-4">
    <!-- Main Layout Container -->
    <div class="layout-container w-full max-w-md mx-auto">
        <!-- Login Card -->
        <div class="bg-surface-light dark:bg-surface-dark rounded-xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-border-color/50 overflow-hidden">
            <!-- Card Content -->
            <div class="p-8 sm:p-10 flex flex-col gap-6">
                <!-- Logo & Header Section -->
                <div class="flex flex-col items-center text-center gap-4">
                    <!-- Logo -->
                    <a href="landing.php" class="h-12 w-12 rounded-lg bg-primary flex items-center justify-center text-white mb-2 shadow-lg shadow-primary/20 hover:bg-primary-hover transition-colors">
                        <span class="material-symbols-outlined text-[28px]">analytics</span>
                    </a>
                    <div class="space-y-1">
                        <h1 class="text-text-main dark:text-white text-2xl font-bold tracking-tight">Welcome to Customer 360</h1>
                        <p class="text-text-sub text-sm">Please enter your details to sign in.</p>
                    </div>
                </div>

                <!-- Error/Success Messages -->
                <?php if (!empty($error)): ?>
                <div class="bg-red-50 text-red-600 px-4 py-3 rounded-lg text-sm flex items-center gap-2 border border-red-100">
                    <span class="material-symbols-outlined text-[20px]">error</span>
                    <span><?php echo $error; ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                <div class="bg-green-50 text-green-600 px-4 py-3 rounded-lg text-sm flex items-center gap-2 border border-green-100">
                    <span class="material-symbols-outlined text-[20px]">check_circle</span>
                    <span><?php echo $success; ?></span>
                </div>
                <?php endif; ?>

                <!-- Login Form -->
                <form class="flex flex-col gap-5" action="api/auth.php" method="POST" id="loginForm">
                    <input type="hidden" name="action" value="login">
                    
                    <!-- Email Field -->
                    <div class="space-y-1.5">
                        <label class="text-text-main dark:text-gray-200 text-sm font-medium" for="email">Email Address</label>
                        <div class="relative group">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-text-sub">
                                <span class="material-symbols-outlined text-[20px]">mail</span>
                            </div>
                            <input 
                                class="form-input block w-full pl-10 pr-3 py-2.5 rounded-lg border-border-color bg-background-light/50 focus:bg-white text-text-main placeholder:text-text-sub/70 focus:border-primary focus:ring-primary/20 transition-all text-sm shadow-sm" 
                                id="email" 
                                name="email"
                                placeholder="name@company.com" 
                                required 
                                type="email"
                            />
                        </div>
                    </div>

                    <!-- Password Field -->
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <label class="text-text-main dark:text-gray-200 text-sm font-medium" for="password">Password</label>
                        </div>
                        <div class="relative group">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-text-sub">
                                <span class="material-symbols-outlined text-[20px]">lock</span>
                            </div>
                            <input 
                                class="form-input block w-full pl-10 pr-10 py-2.5 rounded-lg border-border-color bg-background-light/50 focus:bg-white text-text-main placeholder:text-text-sub/70 focus:border-primary focus:ring-primary/20 transition-all text-sm shadow-sm" 
                                id="password" 
                                name="password"
                                placeholder="••••••••" 
                                required 
                                type="password"
                            />
                            <button 
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-text-sub hover:text-primary transition-colors cursor-pointer focus:outline-none" 
                                type="button"
                                onclick="togglePassword()"
                                id="togglePasswordBtn"
                            >
                                <span class="material-symbols-outlined text-[20px]" id="passwordIcon">visibility</span>
                            </button>
                        </div>
                        <div class="flex justify-end pt-1">
                            <a class="text-sm font-medium text-primary hover:text-primary-hover transition-colors" href="forgot-password.php">Forgot password?</a>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button 
                        class="mt-2 w-full bg-primary hover:bg-primary-hover text-white font-semibold py-2.5 px-4 rounded-lg shadow-lg shadow-primary/20 transition-all duration-200 flex items-center justify-center gap-2 group disabled:opacity-50 disabled:cursor-not-allowed" 
                        type="submit"
                        id="submitBtn"
                    >
                        <span id="btnText">Log in</span>
                        <span class="material-symbols-outlined text-[20px] group-hover:translate-x-0.5 transition-transform" id="btnIcon">arrow_forward</span>
                    </button>
                </form>

                <!-- Footer / Sign Up Link -->
                <div class="text-center pt-2 border-t border-border-color/30">
                    <p class="text-sm text-text-sub pt-4">
                        New to Customer 360? 
                        <a class="font-medium text-primary hover:text-primary-hover transition-colors ml-1" href="register.php">Create an account</a>
                    </p>
                </div>
            </div>
        </div>

        <!-- Footer Branding -->
        <div class="mt-8 text-center">
            <p class="text-xs text-text-sub/60">
                © <?php echo $currentYear; ?> Customer 360. All rights reserved.
            </p>
        </div>
    </div>

    <!-- Decorative Background Elements -->
    <div class="fixed top-0 left-0 w-full h-full pointer-events-none overflow-hidden -z-10">
        <!-- Abstract gradient blob top right -->
        <div class="absolute -top-[10%] -right-[10%] w-[50%] h-[50%] rounded-full bg-primary/5 blur-[100px]"></div>
        <!-- Abstract gradient blob bottom left -->
        <div class="absolute -bottom-[10%] -left-[10%] w-[40%] h-[40%] rounded-full bg-primary/5 blur-[100px]"></div>
    </div>

    <script>
        // Password visibility toggle
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const passwordIcon = document.getElementById('passwordIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                passwordIcon.textContent = 'visibility_off';
            } else {
                passwordInput.type = 'password';
                passwordIcon.textContent = 'visibility';
            }
        }

        // Form submission with loading state
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            const btnText = document.getElementById('btnText');
            const btnIcon = document.getElementById('btnIcon');
            
            // Show loading state
            submitBtn.disabled = true;
            btnText.textContent = 'Signing in...';
            btnIcon.textContent = 'hourglass_empty';
            btnIcon.classList.add('animate-spin');
        });

        // Auto-hide success messages after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const successAlert = document.querySelector('.bg-green-50');
            if (successAlert) {
                setTimeout(function() {
                    successAlert.style.transition = 'opacity 0.5s ease-out';
                    successAlert.style.opacity = '0';
                    setTimeout(function() {
                        successAlert.remove();
                    }, 500);
                }, 5000);
            }
        });
    </script>
</body>
</html>
