<?php
/**
 * Customer 360 - Dashboard Page
 * Main dashboard with sidebar navigation and recent analysis runs
 */
require_once __DIR__ . '/api/config.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: signin.php');
    exit;
}

// Get user info from session
$rawUserName = trim((string) ($_SESSION['user_name'] ?? ''));
$rawCompanyName = trim((string) ($_SESSION['company_name'] ?? ''));
$rawUserEmail = trim((string) ($_SESSION['user_email'] ?? ''));

$userName = htmlspecialchars($rawUserName !== '' ? $rawUserName : ($rawUserEmail !== '' ? explode('@', $rawUserEmail)[0] : ''));
$companyName = htmlspecialchars($rawCompanyName);
$userEmail = htmlspecialchars($rawUserEmail);
$authToken = $_SESSION['auth_token'] ?? null;

$currentYear = date('Y');

function dashboardApiGet(string $endpoint, ?string $token): ?array {
    // Small wrapper for dashboard-only reads from the FastAPI backend.
    if (!$token) {
        return null;
    }

    $result = apiRequest($endpoint, 'GET', null, $token);
    if (!$result['success'] || !is_array($result['data'])) {
        return null;
    }

    return $result['data'];
}

function formatDashboardDate(?string $value): string {
    if (!$value) {
        return 'Not available';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return 'Not available';
    }

    return date('M j, Y g:i A', $timestamp);
}

function formatCompactNumber($value): string {
    if ($value === null || $value === '') {
        return 'Not available';
    }

    return number_format((float) $value);
}

function formatCurrencyValue($value): string {
    if ($value === null || $value === '') {
        return 'Not available';
    }

    return 'GH₵ ' . number_format((float) $value, 2);
}

function getRunVisuals(string $status): array {
    $map = [
        'completed' => [
            'icon' => 'task_alt',
            'container' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
        ],
        'processing' => [
            'icon' => 'sync',
            'container' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        ],
        'pending' => [
            'icon' => 'schedule',
            'container' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
        ],
        'failed' => [
            'icon' => 'warning',
            'container' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
        ],
        'cancelled' => [
            'icon' => 'block',
            'container' => 'bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
        ],
    ];

    return $map[$status] ?? [
        'icon' => 'description',
        'container' => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
    ];
}

function getStatusBadgeClass(string $status): string {
    $map = [
        'completed' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400',
        'processing' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
        'pending' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
        'failed' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
        'cancelled' => 'bg-slate-200 text-slate-800 dark:bg-slate-800 dark:text-slate-300',
    ];

    return $map[$status] ?? 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-300';
}

function getStatusLabel(string $status): string {
    $map = [
        'completed' => 'Completed',
        'processing' => 'Processing',
        'pending' => 'Pending',
        'failed' => 'Failed',
        'cancelled' => 'Cancelled',
    ];

    return $map[$status] ?? ucfirst($status ?: 'Unknown');
}

function buildActionConfig(array $run): array {
    $jobId = urlencode($run['job_id']);

    if ($run['status'] === 'completed') {
        return [
            'href' => "analysis.php?job_id={$jobId}",
            'label' => 'View results',
            'icon' => 'visibility',
            'classes' => 'text-primary hover:text-primary-hover dark:text-blue-400 dark:hover:text-blue-300',
        ];
    }

    if ($run['status'] === 'failed') {
        return [
            'href' => 'analysis.php',
            'label' => 'Retry upload',
            'icon' => 'refresh',
            'classes' => 'text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300',
        ];
    }

    if ($run['status'] === 'cancelled') {
        return [
            'href' => 'analysis.php',
            'label' => 'Start over',
            'icon' => 'upload_file',
            'classes' => 'text-slate-500 hover:text-primary dark:text-slate-400 dark:hover:text-white',
        ];
    }

    return [
        'href' => "processing.php?job_id={$jobId}",
        'label' => $run['status'] === 'pending' ? 'Track job' : 'Track progress',
        'icon' => $run['status'] === 'pending' ? 'schedule' : 'hourglass_top',
        'classes' => 'text-slate-500 hover:text-primary dark:text-slate-400 dark:hover:text-white',
    ];
}

$recentRuns = [];
$jobsLoadError = null;

if ($authToken) {
    // Pull the latest jobs for the signed-in user so the dashboard reflects server state,
    // not only what was stored in the browser session.
    $jobList = dashboardApiGet('/jobs/', $authToken);

    if ($jobList === null) {
        $jobsLoadError = 'We could not load your recent analysis runs right now.';
    } else {
        foreach (array_slice($jobList, 0, 5) as $job) {
            // Store only display-safe fields for the recent-runs list.
            $run = [
                'job_id' => $job['job_id'] ?? '',
                'original_filename' => $job['original_filename'] ?? 'Untitled upload',
                'status' => $job['status'] ?? 'pending',
                'num_customers' => $job['num_customers'] ?? null,
                'num_transactions' => $job['num_transactions'] ?? null,
                'total_revenue' => $job['total_revenue'] ?? null,
                'created_at' => $job['created_at'] ?? null,
                'completed_at' => null,
                'clustering_method' => null,
                'num_clusters' => null,
                'silhouette_score' => null,
                'error_message' => null,
            ];

            $statusData = dashboardApiGet('/jobs/status/' . rawurlencode($run['job_id']), $authToken);
            if ($statusData) {
                $run['completed_at'] = $statusData['completed_at'] ?? null;
                $run['error_message'] = $statusData['error_message'] ?? null;
                $run['status'] = $statusData['status'] ?? $run['status'];
            }

            if ($run['status'] === 'completed') {
                // Completed jobs can show analytics totals without forcing a rerun.
                $resultsData = dashboardApiGet('/jobs/results/' . rawurlencode($run['job_id']), $authToken);
                if ($resultsData) {
                    $meta = is_array($resultsData['meta'] ?? null) ? $resultsData['meta'] : $resultsData;
                    $run['clustering_method'] = $meta['clustering_method'] ?? null;
                    $run['num_clusters'] = $meta['num_clusters'] ?? null;
                    $run['silhouette_score'] = $meta['silhouette_score'] ?? null;
                    $run['num_customers'] = $meta['num_customers'] ?? $run['num_customers'];
                    $run['num_transactions'] = $meta['num_transactions'] ?? $run['num_transactions'];
                    $run['total_revenue'] = $meta['total_revenue'] ?? $run['total_revenue'];
                }
            }

            $run['visuals'] = getRunVisuals($run['status']);
            $run['status_label'] = getStatusLabel($run['status']);
            $run['status_badge_class'] = getStatusBadgeClass($run['status']);
            $run['action'] = buildActionConfig($run);
            $recentRuns[] = $run;
        }
    }
}

$showUploadMessage = empty($recentRuns);
$welcomeTarget = $companyName !== '' ? $companyName : ($userName !== '' ? $userName : 'your workspace');
$welcomeMessage = $showUploadMessage
    ? 'Upload your first customer dataset to begin building your segmentation history.'
    : 'Track your latest segmentation runs, statuses, and customer insights in one place.';
$profileLabel = $userName !== '' ? $userName : $userEmail;
$profileSubLabel = $companyName !== '' ? $companyName : 'Signed in account';
$userInitial = strtoupper(substr($profileLabel, 0, 1));

// Current page for navigation highlighting
$currentPage = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Dashboard - Customer 360</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
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
                        "accent": "#e8b031",
                    },
                    fontFamily: {
                        "display": ["Inter", "sans-serif"],
                        "body": ["Inter", "sans-serif"]
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
        .upload-spinner {
            width: 1rem;
            height: 1rem;
            border-radius: 9999px;
            border: 2px solid rgba(255,255,255,.35);
            border-top-color: #fff;
            animation: upload-spin .8s linear infinite;
        }
        .upload-progress-shimmer {
            position: relative;
            overflow: hidden;
        }
        .upload-progress-shimmer::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,.45), transparent);
            transform: translateX(-100%);
            animation: upload-shimmer 1.4s ease-in-out infinite;
        }
        .upload-float-in {
            animation: upload-float-in .35s ease-out both;
        }
        @keyframes upload-spin {
            to { transform: rotate(360deg); }
        }
        @keyframes upload-shimmer {
            100% { transform: translateX(100%); }
        }
        @keyframes upload-float-in {
            from { opacity: 0; transform: translateY(12px) scale(.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
        }
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
        /* Sidebar scrollbar */
        aside::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.2);
        }
        aside::-webkit-scrollbar-thumb:hover {
            background: rgba(255,255,255,0.3);
        }
    </style>
</head>
<body class="font-display bg-background-light dark:bg-background-dark text-slate-900 dark:text-white antialiased">
    <div class="flex h-screen w-full overflow-hidden">
        
        <!-- Sidebar -->
        <aside class="flex w-72 flex-col bg-primary text-white h-full border-r border-slate-800 hidden md:flex">
            <!-- Logo Area -->
            <div class="flex h-20 items-center gap-3 px-6 border-b border-slate-700/50">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-white/10 text-white">
                    <span class="material-symbols-outlined">analytics</span>
                </div>
                <div>
                    <h1 class="text-lg font-bold leading-tight tracking-tight">Customer 360</h1>
                    <p class="text-slate-400 text-xs font-normal">SME Intelligence</p>
                </div>
            </div>
            
            <!-- Navigation -->
            <nav class="flex-1 overflow-y-auto px-4 py-6 space-y-2">
                <a class="flex items-center gap-3 rounded-lg <?php echo $currentPage === 'dashboard' ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white'; ?> px-4 py-3 text-sm font-medium transition-colors" href="dashboard.php">
                    <span class="material-symbols-outlined">dashboard</span>
                    Dashboard
                </a>
                <a class="flex items-center gap-3 rounded-lg <?php echo $currentPage === 'upload' ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white'; ?> px-4 py-3 text-sm font-medium transition-colors" href="upload.php">
                    <span class="material-symbols-outlined">upload_file</span>
                    Upload Data
                </a>
                <a class="flex items-center gap-3 rounded-lg <?php echo $currentPage === 'analytics' ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white'; ?> px-4 py-3 text-sm font-medium transition-colors" href="analytics.php">
                    <span class="material-symbols-outlined">analytics</span>
                    Analytics
                </a>
                <a class="flex items-center gap-3 rounded-lg <?php echo $currentPage === 'reports' ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white'; ?> px-4 py-3 text-sm font-medium transition-colors" href="reports.php">
                    <span class="material-symbols-outlined">bar_chart</span>
                    Reports
                </a>
                
                <div class="my-4 border-t border-slate-700/50"></div>
                
                <a class="flex items-center gap-3 rounded-lg <?php echo $currentPage === 'help' ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white'; ?> px-4 py-3 text-sm font-medium transition-colors" href="help.php">
                    <span class="material-symbols-outlined">help</span>
                    Help & Support
                </a>
            </nav>
            
            <!-- User Profile -->
            <div class="border-t border-slate-700/50 p-4">
                <div class="flex items-center gap-3 rounded-lg p-2 hover:bg-white/5 cursor-pointer transition-colors group" onclick="toggleUserMenu()">
                    <div class="h-10 w-10 rounded-full bg-slate-600 flex items-center justify-center text-white font-semibold text-sm">
                        <?php echo htmlspecialchars($userInitial); ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-white truncate"><?php echo $profileLabel; ?></p>
                        <p class="text-xs text-slate-400 truncate"><?php echo $profileSubLabel; ?></p>
                    </div>
                    <span class="material-symbols-outlined text-slate-400 group-hover:text-white transition-colors text-[20px]">expand_more</span>
                </div>
                
                <!-- User Dropdown Menu -->
                <div id="userMenu" class="hidden mt-2 py-2 bg-slate-800 rounded-lg shadow-lg">
                    <a href="help.php" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-300 hover:bg-slate-700 hover:text-white transition-colors">
                        <span class="material-symbols-outlined text-[18px]">person</span>
                        My Profile
                    </a>
                    <a href="api/auth.php?action=logout" class="flex items-center gap-2 px-4 py-2 text-sm text-red-400 hover:bg-slate-700 hover:text-red-300 transition-colors">
                        <span class="material-symbols-outlined text-[18px]">logout</span>
                        Sign Out
                    </a>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex flex-1 flex-col overflow-y-auto bg-background-light dark:bg-background-dark relative">
            
            <!-- Header -->
            <header class="sticky top-0 z-10 flex h-20 items-center justify-between border-b border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-6 sm:px-10">
                <div class="flex items-center gap-4">
                    <!-- Mobile Menu Button -->
                    <button class="md:hidden text-slate-500 hover:text-slate-700" onclick="toggleMobileMenu()">
                        <span class="material-symbols-outlined">menu</span>
                    </button>
                    
                    <!-- Breadcrumb -->
                    <div class="hidden sm:flex items-center text-sm text-slate-500">
                        <a class="hover:text-primary transition-colors" href="dashboard.php">Home</a>
                        <span class="mx-2 text-slate-300">/</span>
                        <span class="font-medium text-primary dark:text-white">Dashboard</span>
                    </div>
                </div>
                
                <div class="flex items-center gap-4">
                    <!-- Search -->
                    <div class="relative hidden sm:block">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[20px]">search</span>
                        <input 
                            class="h-10 w-64 rounded-full border border-slate-200 bg-slate-50 pl-10 pr-4 text-sm text-slate-700 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-slate-700 dark:bg-slate-800 dark:text-white" 
                            placeholder="Search data..." 
                            type="text"
                            id="searchInput"
                        />
                    </div>
                    
                    <!-- Notifications -->
                    <button class="relative rounded-full p-2 text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800 transition-colors">
                        <span class="material-symbols-outlined">notifications</span>
                        <span class="absolute right-2 top-2 h-2 w-2 rounded-full bg-red-500 ring-2 ring-white dark:ring-slate-900"></span>
                    </button>
                </div>
            </header>

            <!-- Content Body -->
            <div class="p-6 sm:p-10 max-w-7xl mx-auto w-full">
                
                <!-- Dashboard Title & Welcome -->
                <div class="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Dashboard</h2>
                        <p class="mt-1 text-slate-500 dark:text-slate-400"><?php echo htmlspecialchars("Overview for {$welcomeTarget}. {$welcomeMessage}"); ?></p>
                    </div>
                    <div>
                        <button 
                            class="flex items-center justify-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 transition-all shadow-sm"
                            onclick="openUploadModal()"
                        >
                            <span class="material-symbols-outlined text-[20px]">add</span>
                            New Segmentation
                        </button>
                    </div>
                </div>

                <!-- Action Cards -->
                <div class="grid gap-6 md:grid-cols-2 mb-10">
                    
                    <!-- Primary Action Card - Upload -->
                    <div class="group relative overflow-hidden rounded-xl border border-slate-200 bg-white p-6 shadow-sm transition-all hover:shadow-md dark:border-slate-800 dark:bg-slate-900">
                        <div class="absolute right-0 top-0 h-24 w-24 translate-x-8 -translate-y-8 rounded-full bg-primary/5 dark:bg-primary/20 blur-2xl transition-all group-hover:bg-primary/10"></div>
                        <div class="flex items-start gap-5">
                            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary dark:bg-primary/30 dark:text-white">
                                <span class="material-symbols-outlined text-3xl">cloud_upload</span>
                            </div>
                            <div class="flex flex-1 flex-col">
                                <h3 class="text-lg font-bold text-slate-900 dark:text-white">Start New Segmentation</h3>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Upload your customer CSV to begin a new analysis run. We'll automatically clean and sort your data.</p>
                                <div class="mt-5">
                                    <button 
                                        class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-primary-hover"
                                        onclick="openUploadModal()"
                                        type="button"
                                    >
                                        Upload File
                                        <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Secondary Action Card - Template Download -->
                    <div class="group relative overflow-hidden rounded-xl border border-slate-200 bg-white p-6 shadow-sm transition-all hover:shadow-md dark:border-slate-800 dark:bg-slate-900">
                        <div class="absolute right-0 top-0 h-24 w-24 translate-x-8 -translate-y-8 rounded-full bg-emerald-500/5 dark:bg-emerald-500/20 blur-2xl transition-all group-hover:bg-emerald-500/10"></div>
                        <div class="flex items-start gap-5">
                            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                <span class="material-symbols-outlined text-3xl">download</span>
                            </div>
                            <div class="flex flex-1 flex-col">
                                <h3 class="text-lg font-bold text-slate-900 dark:text-white">Get Data Template</h3>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Ensure your data is formatted correctly before uploading. Download our standard CSV template.</p>
                                <div class="mt-5">
                                    <a 
                                        href="templates/customer_template.csv" 
                                        download
                                        class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700 transition-colors"
                                    >
                                        Download CSV
                                        <span class="material-symbols-outlined text-[18px]">file_download</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900 overflow-hidden">
                    <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50/50 px-6 py-4 dark:border-slate-800 dark:bg-slate-900">
                        <h3 class="text-base font-semibold text-slate-900 dark:text-white">Recent Analysis Runs</h3>
                        <a class="text-sm font-medium text-primary hover:text-primary-hover dark:text-blue-400 dark:hover:text-blue-300 transition-colors" href="reports.php">View All</a>
                    </div>

                    <?php if (!$showUploadMessage): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                                <tr>
                                    <th class="px-6 py-4 font-medium">File & Run Details</th>
                                    <th class="px-6 py-4 font-medium">Date Run</th>
                                    <th class="px-6 py-4 font-medium">Job Summary</th>
                                    <th class="px-6 py-4 font-medium">Status</th>
                                    <th class="px-6 py-4 font-medium text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-slate-800" id="runsTableBody">
                                <?php foreach ($recentRuns as $run): ?>
                                <tr class="group hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="flex h-9 w-9 items-center justify-center rounded-lg <?php echo $run['visuals']['container']; ?>">
                                                <span class="material-symbols-outlined text-[18px] <?php echo $run['status'] === 'processing' ? 'animate-spin' : ''; ?>"><?php echo $run['visuals']['icon']; ?></span>
                                            </div>
                                            <div class="min-w-0">
                                                <p class="font-medium text-slate-900 dark:text-white truncate"><?php echo htmlspecialchars($run['original_filename']); ?></p>
                                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400 truncate">Job ID: <?php echo htmlspecialchars($run['job_id']); ?></p>
                                                <div class="mt-2 flex flex-wrap gap-2 text-xs text-slate-500 dark:text-slate-400">
                                                    <span class="rounded-full bg-slate-100 px-2 py-1 dark:bg-slate-800">Analysis type: Customer grouping</span>
                                                    <span class="rounded-full bg-slate-100 px-2 py-1 dark:bg-slate-800">Customer groups: <?php echo $run['num_clusters'] !== null ? number_format((int) $run['num_clusters']) : 'Not available'; ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-slate-500 dark:text-slate-400">
                                        <div class="space-y-1">
                                            <p class="text-slate-900 dark:text-white">Created: <?php echo formatDashboardDate($run['created_at']); ?></p>
                                            <p>Completed: <?php echo formatDashboardDate($run['completed_at']); ?></p>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-slate-500 dark:text-slate-400">
                                        <div class="space-y-1">
                                            <p>Customers: <span class="text-slate-900 dark:text-white"><?php echo formatCompactNumber($run['num_customers']); ?></span></p>
                                            <p>Transactions: <span class="text-slate-900 dark:text-white"><?php echo formatCompactNumber($run['num_transactions']); ?></span></p>
                                            <p>Revenue: <span class="text-slate-900 dark:text-white"><?php echo formatCurrencyValue($run['total_revenue']); ?></span></p>
                                            <p>Grouping confidence: <span class="text-slate-900 dark:text-white"><?php echo $run['silhouette_score'] !== null ? number_format(max(1, min(5, (int) round(1 + ((float) $run['silhouette_score'] * 4))))) . '/5' : 'Not available'; ?></span></p>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium <?php echo $run['status_badge_class']; ?>">
                                            <?php echo htmlspecialchars($run['status_label']); ?>
                                        </span>
                                        <?php if (!empty($run['error_message'])): ?>
                                        <p class="mt-2 max-w-xs text-xs text-red-600 dark:text-red-400"><?php echo htmlspecialchars($run['error_message']); ?></p>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <a class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition-colors <?php echo $run['action']['classes']; ?>" href="<?php echo htmlspecialchars($run['action']['href']); ?>" title="<?php echo htmlspecialchars($run['action']['label']); ?>">
                                            <span class="material-symbols-outlined text-[18px]"><?php echo htmlspecialchars($run['action']['icon']); ?></span>
                                            <span><?php echo htmlspecialchars($run['action']['label']); ?></span>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div id="emptyState" class="py-14 text-center">
                        <span class="material-symbols-outlined text-6xl text-slate-300 dark:text-slate-600">cloud_upload</span>
                        <h3 class="mt-4 text-xl font-semibold text-slate-900 dark:text-white">Upload your first customer file to start tracking analysis runs</h3>
                        <p class="mx-auto mt-3 max-w-2xl text-sm text-slate-500 dark:text-slate-400">
                            Once you upload a CSV and launch a segmentation job, this dashboard becomes your tracking space for pending, processing, completed, or failed runs.
                        </p>
                        <?php if ($jobsLoadError): ?>
                        <p class="mx-auto mt-3 max-w-xl text-sm text-amber-700 dark:text-amber-400"><?php echo htmlspecialchars($jobsLoadError); ?></p>
                        <?php endif; ?>
                        <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                            <a
                                class="inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-primary-hover"
                                href="upload.php"
                            >
                                <span class="material-symbols-outlined text-[18px]">upload_file</span>
                                Upload & Map Columns
                            </a>
                            <button
                                class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                                onclick="openUploadModal()"
                                type="button"
                            >
                                <span class="material-symbols-outlined text-[18px]">bolt</span>
                                Get Started Here
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Footer -->
                <div class="mt-8 flex flex-col items-center justify-between gap-4 border-t border-slate-200 py-6 text-sm text-slate-500 dark:border-slate-800 sm:flex-row">
                    <p>© <?php echo $currentYear; ?> Customer 360 Ghana. All rights reserved.</p>
                    <div class="flex gap-4">
                        <a class="hover:text-slate-800 dark:hover:text-white transition-colors" href="#">Privacy Policy</a>
                        <a class="hover:text-slate-800 dark:hover:text-white transition-colors" href="#">Terms of Service</a>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Upload Modal -->
    <div id="uploadModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="flex min-h-screen items-center justify-center p-4">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-black/50 transition-opacity" onclick="closeUploadModal()"></div>
            
            <!-- Modal Content -->
            <div class="relative w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl dark:bg-slate-900 upload-float-in">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-slate-900 dark:text-white">Upload Customer Data</h3>
                    <button class="text-slate-400 hover:text-slate-600 dark:hover:text-white transition-colors" onclick="closeUploadModal()">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                
                <form id="uploadForm" action="api/upload.php" method="POST" enctype="multipart/form-data">
                    <!-- Job Name -->
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5" for="jobName">
                            Analysis Name
                        </label>
                        <input 
                            class="w-full rounded-lg border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm text-slate-900 focus:border-primary focus:ring-primary dark:border-slate-600 dark:bg-slate-800 dark:text-white"
                            id="jobName"
                            name="job_name"
                            placeholder="e.g., Q4 Customer Segmentation"
                            required
                            type="text"
                        />
                    </div>
                    
                    <!-- File Upload Area -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                            CSV File
                        </label>
                        <div 
                            class="relative border-2 border-dashed border-slate-300 rounded-xl p-8 text-center hover:border-primary transition-colors dark:border-slate-600 cursor-pointer"
                            id="dropZone"
                            onclick="document.getElementById('csvFile').click()"
                        >
                            <input 
                                type="file" 
                                id="csvFile" 
                                name="csv_file" 
                                accept=".csv" 
                                class="hidden" 
                                required
                                onchange="handleFileSelect(this)"
                            />
                            <span class="material-symbols-outlined text-4xl text-slate-400 dark:text-slate-500">upload_file</span>
                            <p class="mt-2 text-sm font-medium text-slate-700 dark:text-slate-300" id="fileLabel">
                                Click to upload or drag and drop
                            </p>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">CSV files only (max 25MB)</p>
                        </div>
                    </div>

                    <div id="uploadProgressPanel" class="mb-6 hidden rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/60">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0 flex-1">
                                <p id="uploadProgressTitle" class="text-sm font-bold text-slate-900 dark:text-white">Preparing upload</p>
                                <p id="uploadProgressMessage" class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-300">Waiting for your dataset...</p>
                            </div>
                            <p id="uploadProgressPercent" class="text-sm font-bold text-primary dark:text-white">0%</p>
                        </div>
                        <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                            <div id="uploadProgressBar" class="h-full w-0 rounded-full bg-primary transition-all duration-300"></div>
                        </div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="flex gap-3">
                        <button 
                            type="button"
                            class="flex-1 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 transition-colors"
                            onclick="closeUploadModal()"
                        >
                            Cancel
                        </button>
                        <button 
                            type="submit"
                            class="flex-1 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-hover transition-colors disabled:opacity-50 disabled:cursor-not-allowed inline-flex items-center justify-center gap-2"
                            id="uploadBtn"
                        >
                            <span id="uploadBtnSpinner" class="upload-spinner hidden"></span>
                            <span id="uploadBtnText">Preview & Map Columns</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Upload Success Modal -->
    <div id="uploadSuccessModal" class="fixed inset-0 z-[60] hidden overflow-y-auto">
        <div class="flex min-h-screen items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/60 transition-opacity"></div>
            <div class="relative w-full max-w-md rounded-2xl bg-white p-8 text-center shadow-2xl dark:bg-slate-900 upload-float-in">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-green-100 text-green-700">
                    <span class="material-symbols-outlined text-4xl">check_circle</span>
                </div>
                <h3 class="mt-5 text-2xl font-bold text-slate-900 dark:text-white">Upload successful</h3>
                <p id="uploadSuccessMessage" class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300">
                    Your file was uploaded and the analysis job has started.
                </p>
                <div class="mt-6 rounded-xl bg-slate-50 px-4 py-3 text-left text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                    <p class="font-semibold text-slate-800 dark:text-white">Redirecting to column mapping and validation...</p>
                    <p id="uploadSuccessJobId" class="mt-1 break-all"></p>
                </div>
                <div class="mt-6 h-2 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                    <div id="uploadSuccessProgress" class="upload-progress-shimmer h-full w-1/3 rounded-full bg-primary transition-all duration-500"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Sidebar -->
    <div id="mobileSidebar" class="fixed inset-0 z-50 hidden md:hidden">
        <div class="fixed inset-0 bg-black/50" onclick="toggleMobileMenu()"></div>
        <aside class="fixed left-0 top-0 h-full w-72 bg-primary text-white overflow-y-auto">
            <!-- Same content as desktop sidebar -->
            <div class="flex h-20 items-center gap-3 px-6 border-b border-slate-700/50">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-white/10 text-white">
                    <span class="material-symbols-outlined">analytics</span>
                </div>
                <div>
                    <h1 class="text-lg font-bold leading-tight tracking-tight">Customer 360</h1>
                    <p class="text-slate-400 text-xs font-normal">SME Intelligence</p>
                </div>
                <button class="ml-auto text-slate-400 hover:text-white" onclick="toggleMobileMenu()">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            
            <nav class="px-4 py-6 space-y-2">
                <a class="flex items-center gap-3 rounded-lg bg-white/10 px-4 py-3 text-sm font-medium text-white transition-colors" href="dashboard.php">
                    <span class="material-symbols-outlined">dashboard</span>
                    Dashboard
                </a>
                <a class="flex items-center gap-3 rounded-lg px-4 py-3 text-sm font-medium text-slate-300 hover:bg-white/5 hover:text-white transition-colors" href="upload.php">
                    <span class="material-symbols-outlined">upload_file</span>
                    Upload Data
                </a>
                <a class="flex items-center gap-3 rounded-lg px-4 py-3 text-sm font-medium text-slate-300 hover:bg-white/5 hover:text-white transition-colors" href="analytics.php">
                    <span class="material-symbols-outlined">analytics</span>
                    Analytics
                </a>
                <a class="flex items-center gap-3 rounded-lg px-4 py-3 text-sm font-medium text-slate-300 hover:bg-white/5 hover:text-white transition-colors" href="reports.php">
                    <span class="material-symbols-outlined">bar_chart</span>
                    Reports
                </a>
                <div class="my-4 border-t border-slate-700/50"></div>
                <a class="flex items-center gap-3 rounded-lg px-4 py-3 text-sm font-medium text-slate-300 hover:bg-white/5 hover:text-white transition-colors" href="help.php">
                    <span class="material-symbols-outlined">help</span>
                    Help & Support
                </a>
                <a class="flex items-center gap-3 rounded-lg px-4 py-3 text-sm font-medium text-red-400 hover:bg-white/5 hover:text-red-300 transition-colors" href="api/auth.php?action=logout">
                    <span class="material-symbols-outlined">logout</span>
                    Sign Out
                </a>
            </nav>
        </aside>
    </div>

    <script>
        let uploadProgressTimer = null;

        // Toggle user dropdown menu
        function toggleUserMenu() {
            const menu = document.getElementById('userMenu');
            menu.classList.toggle('hidden');
        }

        // Toggle mobile sidebar
        function toggleMobileMenu() {
            const sidebar = document.getElementById('mobileSidebar');
            sidebar.classList.toggle('hidden');
        }

        // Open upload modal
        function openUploadModal() {
            document.getElementById('uploadModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        // Close upload modal
        function closeUploadModal() {
            document.getElementById('uploadModal').classList.add('hidden');
            document.body.style.overflow = '';
            // Reset form
            document.getElementById('uploadForm').reset();
            document.getElementById('fileLabel').textContent = 'Click to upload or drag and drop';
            clearInterval(uploadProgressTimer);
            setUploadProgressState({
                show: false,
                title: 'Preparing upload',
                message: 'Waiting for your dataset...',
                percent: 0,
                animated: false
            });
            resetUploadButton();
        }

        // Handle file selection
        function handleFileSelect(input) {
            const file = input.files[0];
            if (file) {
                document.getElementById('fileLabel').textContent = file.name;
            }
        }

        // Drag and drop functionality
        const dropZone = document.getElementById('dropZone');
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, unhighlight, false);
        });

        function highlight() {
            dropZone.classList.add('border-primary', 'bg-primary/5');
        }

        function unhighlight() {
            dropZone.classList.remove('border-primary', 'bg-primary/5');
        }

        dropZone.addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            const fileInput = document.getElementById('csvFile');
            
            if (files.length > 0 && files[0].name.endsWith('.csv')) {
                fileInput.files = files;
                document.getElementById('fileLabel').textContent = files[0].name;
            } else {
                alert('Please upload a CSV file.');
            }
        }

        function setUploadProgressState({ show = true, title, message, percent = 0, animated = true }) {
            const panel = document.getElementById('uploadProgressPanel');
            const titleEl = document.getElementById('uploadProgressTitle');
            const messageEl = document.getElementById('uploadProgressMessage');
            const percentEl = document.getElementById('uploadProgressPercent');
            const bar = document.getElementById('uploadProgressBar');
            const safePercent = Math.max(0, Math.min(100, Number(percent) || 0));

            panel.classList.toggle('hidden', !show);
            titleEl.textContent = title;
            messageEl.textContent = message;
            percentEl.textContent = `${Math.round(safePercent)}%`;
            bar.style.width = `${safePercent}%`;
            bar.classList.toggle('upload-progress-shimmer', animated);
        }

        function openUploadSuccessModal(previewName, fileName) {
            closeUploadModal();
            document.getElementById('uploadSuccessMessage').textContent =
                `${fileName} was uploaded successfully. Opening the mapping workspace so you can confirm field assignments and parsing rules.`;
            document.getElementById('uploadSuccessJobId').textContent = `Preview source: ${previewName}`;
            document.getElementById('uploadSuccessModal').classList.remove('hidden');

            const progressBar = document.getElementById('uploadSuccessProgress');
            progressBar.style.width = '35%';
            setTimeout(() => { progressBar.style.width = '70%'; }, 250);
            setTimeout(() => { progressBar.style.width = '100%'; }, 800);
            setTimeout(() => {
                window.location.href = 'column-mapping.php';
            }, 1300);
        }

        function resetUploadButton() {
            const uploadBtn = document.getElementById('uploadBtn');
            const uploadBtnSpinner = document.getElementById('uploadBtnSpinner');
            const uploadBtnText = document.getElementById('uploadBtnText');
            uploadBtn.disabled = false;
            uploadBtnSpinner.classList.add('hidden');
            uploadBtnText.textContent = 'Preview & Map Columns';
        }

        function showUploadFailure(message) {
            clearInterval(uploadProgressTimer);
            resetUploadButton();
            setUploadProgressState({
                show: true,
                title: 'Upload failed',
                message: message || 'Upload failed. Please try again.',
                percent: 100,
                animated: false
            });
        }

        // Form submission
        document.getElementById('uploadForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const uploadBtn = document.getElementById('uploadBtn');
            const uploadBtnSpinner = document.getElementById('uploadBtnSpinner');
            const uploadBtnText = document.getElementById('uploadBtnText');
            const form = e.currentTarget;
            const fileInput = document.getElementById('csvFile');
            const selectedFile = fileInput.files[0];

            if (!selectedFile) {
                showUploadFailure('Please choose a CSV file before starting the analysis.');
                return;
            }

            uploadBtn.disabled = true;
            uploadBtnSpinner.classList.remove('hidden');
            uploadBtnText.textContent = 'Uploading...';
            setUploadProgressState({
                show: true,
                        title: 'Uploading dataset',
                        message: `Uploading ${selectedFile.name} and preparing a schema preview...`,
                percent: 12,
                animated: true
            });
            clearInterval(uploadProgressTimer);
            uploadProgressTimer = setInterval(() => {
                const progressBar = document.getElementById('uploadProgressBar');
                const currentWidth = Number(progressBar.style.width.replace('%', '')) || 12;
                const nextValue = Math.min(currentWidth + 8, 88);
                setUploadProgressState({
                    show: true,
                    title: 'Uploading dataset',
                        message: nextValue >= 72
                            ? 'Profiling your dataset and opening the mapping workspace...'
                            : 'Uploading your CSV and preparing a backend preview...',
                    percent: nextValue,
                    animated: true
                });
            }, 700);

            try {
                const response = await fetch('api/upload.php?action=preview', {
                    method: 'POST',
                    body: new FormData(form),
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                });

                clearInterval(uploadProgressTimer);
                const payload = await response.json().catch(() => null);

                if (!response.ok || !payload?.success) {
                    showUploadFailure(payload?.error || `Upload failed with status ${response.status}.`);
                    return;
                }

                setUploadProgressState({
                    show: true,
                    title: 'Preview ready',
                    message: 'Upload complete. Opening column mapping and validation...',
                    percent: 100,
                    animated: false
                });
                openUploadSuccessModal(payload.filename || selectedFile.name, selectedFile.name);
            } catch (error) {
                showUploadFailure('Could not reach the upload API. Please check the backend service and try again.');
            }
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            const userMenu = document.getElementById('userMenu');
            const userTrigger = document.querySelector('[onclick="toggleUserMenu()"]');
            
            if (!userTrigger.contains(e.target) && !userMenu.contains(e.target)) {
                userMenu.classList.add('hidden');
            }
        });

        // Close modal on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeUploadModal();
            }
        });
    </script>
</body>
</html>
