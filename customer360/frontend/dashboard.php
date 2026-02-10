<?php
/**
 * Customer 360 - Dashboard Page
 * Main dashboard with sidebar navigation and recent analysis runs
 */
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: signin.php');
    exit;
}

// Get user info from session
$userName = isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'User';
$companyName = isset($_SESSION['company_name']) ? htmlspecialchars($_SESSION['company_name']) : 'Company';
$userEmail = isset($_SESSION['user_email']) ? htmlspecialchars($_SESSION['user_email']) : '';

$currentYear = date('Y');

// Sample data for recent runs (in production, this would come from database)
$recentRuns = [
    [
        'id' => 1,
        'name' => 'Q3 Sales Data Analysis',
        'date' => 'Oct 24, 2023',
        'records' => '4,520 Rows',
        'status' => 'completed',
        'icon' => 'table_chart',
        'color' => 'blue'
    ],
    [
        'id' => 2,
        'name' => 'Kumasi Region Segment',
        'date' => 'Oct 22, 2023',
        'records' => '1,205 Rows',
        'status' => 'completed',
        'icon' => 'pie_chart',
        'color' => 'purple'
    ],
    [
        'id' => 3,
        'name' => 'Holiday Promo Target List',
        'date' => 'Today, 10:30 AM',
        'records' => '8,900 Rows',
        'status' => 'processing',
        'icon' => 'sync',
        'color' => 'orange'
    ],
    [
        'id' => 4,
        'name' => 'Loyalty Members Batch 2',
        'date' => 'Oct 20, 2023',
        'records' => '-',
        'status' => 'failed',
        'icon' => 'warning',
        'color' => 'red'
    ],
    [
        'id' => 5,
        'name' => 'Accra New Signups',
        'date' => 'Oct 18, 2023',
        'records' => '340 Rows',
        'status' => 'completed',
        'icon' => 'table_chart',
        'color' => 'blue'
    ]
];

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
                        <?php echo strtoupper(substr($userName, 0, 1)); ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-white truncate"><?php echo $userName; ?></p>
                        <p class="text-xs text-slate-400 truncate"><?php echo $companyName; ?></p>
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
                        <p class="mt-1 text-slate-500 dark:text-slate-400">Welcome back, get insights into your customer base.</p>
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

                <!-- Recent Runs Table -->
                <div class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900 overflow-hidden">
                    <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50/50 px-6 py-4 dark:border-slate-800 dark:bg-slate-900">
                        <h3 class="text-base font-semibold text-slate-900 dark:text-white">Recent Analysis Runs</h3>
                        <a class="text-sm font-medium text-primary hover:text-primary-hover dark:text-blue-400 dark:hover:text-blue-300 transition-colors" href="reports.php">View All</a>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                                <tr>
                                    <th class="px-6 py-4 font-medium">Job Name</th>
                                    <th class="px-6 py-4 font-medium">Date Run</th>
                                    <th class="px-6 py-4 font-medium">Records Processed</th>
                                    <th class="px-6 py-4 font-medium">Status</th>
                                    <th class="px-6 py-4 font-medium text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-slate-800" id="runsTableBody">
                                <?php foreach ($recentRuns as $run): ?>
                                <tr class="group hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="h-8 w-8 rounded bg-<?php echo $run['color']; ?>-100 flex items-center justify-center text-<?php echo $run['color']; ?>-700 dark:bg-<?php echo $run['color']; ?>-900/30 dark:text-<?php echo $run['color']; ?>-400">
                                                <span class="material-symbols-outlined text-[18px]"><?php echo $run['icon']; ?></span>
                                            </div>
                                            <span class="font-medium text-slate-900 dark:text-white"><?php echo htmlspecialchars($run['name']); ?></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-slate-500 dark:text-slate-400"><?php echo $run['date']; ?></td>
                                    <td class="px-6 py-4 text-slate-500 dark:text-slate-400"><?php echo $run['records']; ?></td>
                                    <td class="px-6 py-4">
                                        <?php if ($run['status'] === 'completed'): ?>
                                        <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400">
                                            Completed
                                        </span>
                                        <?php elseif ($run['status'] === 'processing'): ?>
                                        <span class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900/30 dark:text-blue-400">
                                            Processing
                                        </span>
                                        <?php elseif ($run['status'] === 'failed'): ?>
                                        <span class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800 dark:bg-red-900/30 dark:text-red-400">
                                            Failed
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <?php if ($run['status'] === 'completed'): ?>
                                        <button class="text-slate-400 hover:text-primary dark:hover:text-white transition-colors" title="View Results">
                                            <span class="material-symbols-outlined">visibility</span>
                                        </button>
                                        <?php elseif ($run['status'] === 'processing'): ?>
                                        <button class="text-slate-400 cursor-not-allowed" disabled title="Processing...">
                                            <span class="material-symbols-outlined animate-spin">hourglass_empty</span>
                                        </button>
                                        <?php elseif ($run['status'] === 'failed'): ?>
                                        <button class="text-slate-400 hover:text-primary dark:hover:text-white transition-colors" title="Retry">
                                            <span class="material-symbols-outlined">refresh</span>
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Empty State (hidden when there are runs) -->
                    <div id="emptyState" class="hidden py-12 text-center">
                        <span class="material-symbols-outlined text-6xl text-slate-300 dark:text-slate-600">folder_open</span>
                        <h3 class="mt-4 text-lg font-medium text-slate-900 dark:text-white">No analysis runs yet</h3>
                        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Upload your first CSV file to get started with customer segmentation.</p>
                        <button 
                            class="mt-6 inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-primary-hover"
                            onclick="openUploadModal()"
                        >
                            <span class="material-symbols-outlined text-[18px]">add</span>
                            New Segmentation
                        </button>
                    </div>
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
            <div class="relative w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl dark:bg-slate-900">
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
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">CSV files only (max 10MB)</p>
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
                            class="flex-1 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-hover transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            id="uploadBtn"
                        >
                            Start Analysis
                        </button>
                    </div>
                </form>
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
            
            <nav class=\"px-4 py-6 space-y-2\">\n                <a class=\"flex items-center gap-3 rounded-lg bg-white/10 px-4 py-3 text-sm font-medium text-white transition-colors\" href=\"dashboard.php\">\n                    <span class=\"material-symbols-outlined\">dashboard</span>\n                    Dashboard\n                </a>\n                <a class=\"flex items-center gap-3 rounded-lg px-4 py-3 text-sm font-medium text-slate-300 hover:bg-white/5 hover:text-white transition-colors\" href=\"upload.php\">\n                    <span class=\"material-symbols-outlined\">upload_file</span>\n                    Upload Data\n                </a>\n                <a class=\"flex items-center gap-3 rounded-lg px-4 py-3 text-sm font-medium text-slate-300 hover:bg-white/5 hover:text-white transition-colors\" href=\"analytics.php\">\n                    <span class=\"material-symbols-outlined\">analytics</span>\n                    Analytics\n                </a>\n                <a class=\"flex items-center gap-3 rounded-lg px-4 py-3 text-sm font-medium text-slate-300 hover:bg-white/5 hover:text-white transition-colors\" href=\"reports.php\">\n                    <span class=\"material-symbols-outlined\">bar_chart</span>\n                    Reports\n                </a>\n                <div class=\"my-4 border-t border-slate-700/50\"></div>\n                <a class=\"flex items-center gap-3 rounded-lg px-4 py-3 text-sm font-medium text-slate-300 hover:bg-white/5 hover:text-white transition-colors\" href=\"help.php\">\n                    <span class=\"material-symbols-outlined\">help</span>\n                    Help & Support\n                </a>\n                <a class=\"flex items-center gap-3 rounded-lg px-4 py-3 text-sm font-medium text-red-400 hover:bg-white/5 hover:text-red-300 transition-colors\" href=\"api/auth.php?action=logout\">\n                    <span class=\"material-symbols-outlined\">logout</span>\n                    Sign Out\n                </a>\n            </nav>
        </aside>
    </div>

    <script>
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

        // Form submission
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            const uploadBtn = document.getElementById('uploadBtn');
            uploadBtn.disabled = true;
            uploadBtn.textContent = 'Uploading...';
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
