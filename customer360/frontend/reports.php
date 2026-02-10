<?php
/**
 * Customer 360 - Reports History Page
 * View and manage uploaded dataset history
 */
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: signin.php');
    exit;
}

// User data from session
$userName = $_SESSION['user_name'] ?? 'User';
$userEmail = $_SESSION['user_email'] ?? '';
$companyName = $_SESSION['company_name'] ?? 'Your Company';
$userInitials = strtoupper(substr($userName, 0, 1) . (strpos($userName, ' ') ? substr($userName, strpos($userName, ' ') + 1, 1) : ''));
$currentYear = date('Y');
$currentPage = 'reports';
$isDemoMode = isset($_SESSION['demo_mode']) && $_SESSION['demo_mode'];

// Pagination settings
$perPage = 5;
$currentPageNum = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$statusFilter = $_GET['status'] ?? '';
$searchQuery = $_GET['search'] ?? '';

// Fetch reports from API or use demo data
function fetchReports($page, $perPage, $status, $search) {
    // Try to get from API
    $queryParams = http_build_query([
        'action' => 'history',
        'page' => $page,
        'per_page' => $perPage,
        'status' => $status,
        'search' => $search
    ]);
    
    $apiUrl = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/api/process.php?' . $queryParams;
    
    // For now, use demo data directly
    return getDemoReports($page, $perPage, $status, $search);
}

function getDemoReports($page, $perPage, $statusFilter, $search) {
    $allReports = [
        [
            'id' => 'job_001',
            'filename' => 'Q3_Sales_Analysis_Final.csv',
            'date_generated' => '2026-02-05',
            'customer_count' => 1240,
            'status' => 'completed',
            'segments_count' => 5,
        ],
        [
            'id' => 'job_002',
            'filename' => 'Accra_Customer_Segment_v2.csv',
            'date_generated' => '2026-02-03',
            'customer_count' => 856,
            'status' => 'completed',
            'segments_count' => 4,
        ],
        [
            'id' => 'job_003',
            'filename' => 'Kumasi_Branch_Leads.csv',
            'date_generated' => '2026-02-01',
            'customer_count' => null,
            'status' => 'failed',
            'error_message' => 'Invalid date format in column 3',
        ],
        [
            'id' => 'job_004',
            'filename' => 'Churn_Prediction_Feb.csv',
            'date_generated' => '2026-01-28',
            'customer_count' => 2100,
            'status' => 'processing',
            'progress' => 65,
        ],
        [
            'id' => 'job_005',
            'filename' => 'Jan_Performance_Review.csv',
            'date_generated' => '2026-01-20',
            'customer_count' => 4320,
            'status' => 'completed',
            'segments_count' => 6,
        ],
        [
            'id' => 'job_006',
            'filename' => 'December_Sales_Report.csv',
            'date_generated' => '2025-12-28',
            'customer_count' => 2890,
            'status' => 'completed',
            'segments_count' => 5,
        ],
        [
            'id' => 'job_007',
            'filename' => 'Takoradi_Customers.csv',
            'date_generated' => '2025-12-20',
            'customer_count' => 567,
            'status' => 'completed',
            'segments_count' => 4,
        ],
    ];
    
    // Filter by status
    if ($statusFilter) {
        $allReports = array_filter($allReports, function($r) use ($statusFilter) {
            return $r['status'] === $statusFilter;
        });
    }
    
    // Filter by search
    if ($search) {
        $searchLower = strtolower($search);
        $allReports = array_filter($allReports, function($r) use ($searchLower) {
            return strpos(strtolower($r['filename']), $searchLower) !== false;
        });
    }
    
    $total = count($allReports);
    $offset = ($page - 1) * $perPage;
    $reports = array_slice(array_values($allReports), $offset, $perPage);
    
    return [
        'reports' => $reports,
        'total' => $total
    ];
}

$result = fetchReports($currentPageNum, $perPage, $statusFilter, $searchQuery);
$reports = $result['reports'];
$totalReports = $result['total'];
$totalPages = ceil($totalReports / $perPage);

// Helper function to format date
function formatDate($dateStr) {
    return date('M d, Y', strtotime($dateStr));
}

// Get status badge HTML
function getStatusBadge($status) {
    switch ($status) {
        case 'completed':
            return '<span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                Completed
            </span>';
        case 'processing':
            return '<span class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                <span class="animate-spin material-symbols-outlined text-[12px]">progress_activity</span>
                Processing
            </span>';
        case 'failed':
            return '<span class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800 dark:bg-red-900/30 dark:text-red-400">
                <span class="material-symbols-outlined text-[14px]">error</span>
                Failed
            </span>';
        default:
            return '<span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                Unknown
            </span>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Customer 360 - My Reports History</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#0b203c",
                        "background-light": "#f6f7f8",
                        "background-dark": "#121820",
                    },
                    fontFamily: {
                        "display": ["Inter", "sans-serif"],
                        "sans": ["Inter", "sans-serif"],
                    },
                },
            },
        }
    </script>
    <style>
        /* Smooth transitions for interactive elements */
        .nav-link {
            transition: all 0.2s ease-in-out;
        }
        .table-row {
            transition: background-color 0.15s ease-in-out;
        }
        .action-btn {
            transition: all 0.15s ease-in-out;
        }
        /* Loading skeleton animation */
        @keyframes shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }
        .skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: shimmer 1.5s infinite;
        }
        .dark .skeleton {
            background: linear-gradient(90deg, #2a3441 25%, #354150 50%, #2a3441 75%);
            background-size: 200% 100%;
        }
        /* Page transition */
        .page-content {
            animation: fadeIn 0.3s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark font-display text-primary dark:text-white antialiased">
    <div class="flex h-screen w-full overflow-hidden">
        <!-- Sidebar Navigation -->
        <aside class="hidden w-64 flex-col border-r border-[#e8ecf2] bg-white dark:bg-[#1a222d] dark:border-[#2a3441] md:flex">
            <div class="flex h-16 items-center px-6 border-b border-[#e8ecf2] dark:border-[#2a3441]">
                <a href="dashboard.php" class="flex items-center gap-2 text-primary dark:text-white hover:opacity-80 transition-opacity">
                    <div class="size-6 text-primary dark:text-blue-400">
                        <svg class="h-full w-full" fill="none" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
                            <path d="M4 4H17.3334V17.3334H30.6666V30.6666H44V44H4V4Z" fill="currentColor"></path>
                        </svg>
                    </div>
                    <h2 class="text-lg font-bold leading-tight tracking-tight">Customer 360</h2>
                </a>
            </div>
            
            <div class="flex flex-1 flex-col justify-between overflow-y-auto p-4">
                <div class="flex flex-col gap-4">
                    <!-- User Profile Snippet -->
                    <div class="flex items-center gap-3 rounded-lg border border-[#e8ecf2] p-3 dark:border-[#2a3441] bg-[#f8fafb] dark:bg-[#121820]">
                        <div class="h-10 w-10 rounded-full bg-primary flex items-center justify-center text-white font-bold text-sm">
                            <?php echo $userInitials; ?>
                        </div>
                        <div class="flex flex-col overflow-hidden">
                            <h3 class="truncate text-sm font-semibold text-primary dark:text-white"><?php echo htmlspecialchars($companyName); ?></h3>
                            <p class="truncate text-xs text-slate-500 dark:text-slate-400">
                                <?php echo $isDemoMode ? 'Demo Mode' : 'Premium Plan'; ?>
                            </p>
                        </div>
                    </div>
                    
                    <!-- Nav Links -->
                    <nav class="flex flex-col gap-1">
                        <a class="nav-link flex items-center gap-3 rounded-lg px-3 py-2 text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-[#2a3441]" href="dashboard.php">
                            <span class="material-symbols-outlined text-[20px]">dashboard</span>
                            <span class="text-sm font-medium">Dashboard</span>
                        </a>
                        <a class="nav-link flex items-center gap-3 rounded-lg px-3 py-2 text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-[#2a3441]" href="upload.php">
                            <span class="material-symbols-outlined text-[20px]">cloud_upload</span>
                            <span class="text-sm font-medium">Upload Data</span>
                        </a>
                        <a class="nav-link flex items-center gap-3 rounded-lg px-3 py-2 text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-[#2a3441]" href="analytics.php">
                            <span class="material-symbols-outlined text-[20px]">analytics</span>
                            <span class="text-sm font-medium">Analytics</span>
                        </a>
                        <a class="nav-link flex items-center gap-3 rounded-lg bg-primary/10 px-3 py-2 text-primary dark:bg-blue-500/20 dark:text-blue-200" href="reports.php">
                            <span class="material-symbols-outlined text-[20px]">description</span>
                            <span class="text-sm font-medium">Reports</span>
                        </a>
                    </nav>
                </div>
                
                <!-- Bottom Actions -->
                <div class="flex flex-col gap-2 border-t border-[#e8ecf2] pt-4 dark:border-[#2a3441]">
                    <a href="help.php" class="nav-link flex items-center gap-3 rounded-lg px-3 py-2 text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-[#2a3441]">
                        <span class="material-symbols-outlined text-[20px]">help</span>
                        <span class="text-sm font-medium">Help & Support</span>
                    </a>
                    <a href="api/auth.php?action=logout" class="nav-link flex items-center gap-3 rounded-lg px-3 py-2 text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-[#2a3441]">
                        <span class="material-symbols-outlined text-[20px]">logout</span>
                        <span class="text-sm font-medium">Log Out</span>
                    </a>
                </div>
            </div>
        </aside>
        
        <!-- Main Content Area -->
        <main class="flex h-full flex-1 flex-col overflow-hidden bg-background-light dark:bg-background-dark">
            <!-- Header -->
            <header class="flex h-16 items-center justify-between border-b border-[#e8ecf2] bg-white px-4 dark:border-[#2a3441] dark:bg-[#1a222d] md:px-8">
                <!-- Mobile Menu Button -->
                <div class="flex items-center gap-4 md:hidden">
                    <button id="mobileMenuBtn" class="text-slate-500 hover:text-primary dark:text-slate-300">
                        <span class="material-symbols-outlined">menu</span>
                    </button>
                    <a href="dashboard.php" class="size-5 text-primary dark:text-blue-400">
                        <svg class="h-full w-full" fill="none" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
                            <path d="M4 4H17.3334V17.3334H30.6666V30.6666H44V44H4V4Z" fill="currentColor"></path>
                        </svg>
                    </a>
                </div>
                
                <!-- Breadcrumbs (Desktop) / Title (Mobile) -->
                <div class="flex flex-1 items-center gap-2">
                    <nav class="hidden items-center gap-2 text-sm text-slate-500 dark:text-slate-400 md:flex">
                        <a class="hover:text-primary dark:hover:text-white transition-colors" href="dashboard.php">Home</a>
                        <span class="text-slate-300">/</span>
                        <a class="hover:text-primary dark:hover:text-white transition-colors" href="reports.php">Reports</a>
                        <span class="text-slate-300">/</span>
                        <span class="font-medium text-primary dark:text-white">History</span>
                    </nav>
                    <h1 class="block text-lg font-bold text-primary dark:text-white md:hidden">Reports History</h1>
                </div>
                
                <!-- Right Header Actions -->
                <div class="flex items-center gap-3">
                    <?php if($isDemoMode): ?>
                    <span class="hidden sm:inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                        <span class="material-symbols-outlined text-[14px]">science</span>
                        Demo
                    </span>
                    <?php endif; ?>
                    <button class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-[#2a3441] dark:text-slate-300 dark:hover:bg-[#354150] transition-colors">
                        <span class="material-symbols-outlined">notifications</span>
                    </button>
                    <div class="hidden md:flex h-10 w-10 rounded-full bg-primary items-center justify-center text-white font-bold text-sm">
                        <?php echo $userInitials; ?>
                    </div>
                </div>
            </header>
            
            <!-- Scrollable Page Content -->
            <div class="flex-1 overflow-y-auto p-4 md:p-8">
                <div class="mx-auto max-w-6xl page-content">
                    <!-- Page Title Section -->
                    <div class="mb-8 flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                        <div>
                            <h1 class="text-3xl font-extrabold tracking-tight text-primary dark:text-white sm:text-4xl">My Reports History</h1>
                            <p class="mt-2 text-slate-500 dark:text-slate-400">View and manage your generated customer analysis reports.</p>
                        </div>
                        <div class="mt-4 md:mt-0">
                            <a href="upload.php" class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-medium text-white shadow-sm transition-all hover:bg-primary/90 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 dark:bg-blue-600 dark:hover:bg-blue-700">
                                <span class="material-symbols-outlined text-[20px]">add</span>
                                New Analysis
                            </a>
                        </div>
                    </div>
                    
                    <!-- Filters & Search Toolbar -->
                    <div class="mb-6 grid gap-4 rounded-xl bg-white p-4 shadow-sm dark:bg-[#1a222d] md:grid-cols-12 md:items-end">
                        <!-- Search -->
                        <div class="col-span-12 md:col-span-4">
                            <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Search Filename</label>
                            <div class="relative">
                                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                    <span class="material-symbols-outlined text-slate-400">search</span>
                                </div>
                                <input 
                                    id="searchInput"
                                    class="block w-full rounded-lg border-slate-200 bg-slate-50 py-2.5 pl-10 pr-3 text-sm text-slate-800 placeholder-slate-400 focus:border-primary focus:bg-white focus:ring-primary dark:border-[#2a3441] dark:bg-[#121820] dark:text-white dark:placeholder-slate-500 dark:focus:border-blue-500 transition-colors" 
                                    placeholder="e.g., Q3 Sales Analysis" 
                                    type="text"
                                    value="<?php echo htmlspecialchars($searchQuery); ?>"
                                />
                            </div>
                        </div>
                        
                        <!-- Date Range -->
                        <div class="col-span-12 md:col-span-4">
                            <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Date Range</label>
                            <div class="relative">
                                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                    <span class="material-symbols-outlined text-slate-400">calendar_today</span>
                                </div>
                                <input 
                                    id="dateRange"
                                    class="block w-full rounded-lg border-slate-200 bg-slate-50 py-2.5 pl-10 pr-3 text-sm text-slate-800 placeholder-slate-400 focus:border-primary focus:bg-white focus:ring-primary dark:border-[#2a3441] dark:bg-[#121820] dark:text-white dark:placeholder-slate-500 dark:focus:border-blue-500 transition-colors" 
                                    placeholder="Select dates" 
                                    type="date"
                                />
                            </div>
                        </div>
                        
                        <!-- Status Filter -->
                        <div class="col-span-12 md:col-span-3">
                            <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Status</label>
                            <select 
                                id="statusFilter"
                                class="block w-full rounded-lg border-slate-200 bg-slate-50 py-2.5 px-3 text-sm text-slate-800 focus:border-primary focus:bg-white focus:ring-primary dark:border-[#2a3441] dark:bg-[#121820] dark:text-white dark:focus:border-blue-500 transition-colors"
                            >
                                <option value="">All Statuses</option>
                                <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="processing" <?php echo $statusFilter === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                <option value="failed" <?php echo $statusFilter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                            </select>
                        </div>
                        
                        <!-- Export Button -->
                        <div class="col-span-12 flex justify-end md:col-span-1">
                            <button 
                                id="exportBtn"
                                class="flex h-[42px] w-full items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 dark:border-[#2a3441] dark:bg-[#121820] dark:text-slate-300 dark:hover:bg-[#1a222d] md:w-auto md:px-3 transition-colors" 
                                title="Export List"
                            >
                                <span class="material-symbols-outlined">download</span>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Data Table -->
                    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-[#2a3441] dark:bg-[#1a222d]">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm text-slate-600 dark:text-slate-300">
                                <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-500 dark:border-[#2a3441] dark:bg-[#2a3441]/50 dark:text-slate-400">
                                    <tr>
                                        <th class="px-6 py-4 font-semibold tracking-wider" scope="col">
                                            <button class="flex items-center gap-1 cursor-pointer hover:text-primary dark:hover:text-white transition-colors" data-sort="date">
                                                Date Generated
                                                <span class="material-symbols-outlined text-[16px]">arrow_drop_down</span>
                                            </button>
                                        </th>
                                        <th class="px-6 py-4 font-semibold tracking-wider" scope="col">Filename</th>
                                        <th class="px-6 py-4 font-semibold tracking-wider" scope="col">
                                            <button class="flex items-center gap-1 cursor-pointer hover:text-primary dark:hover:text-white transition-colors" data-sort="count">
                                                Customer Count
                                                <span class="material-symbols-outlined text-[16px]">unfold_more</span>
                                            </button>
                                        </th>
                                        <th class="px-6 py-4 font-semibold tracking-wider" scope="col">Status</th>
                                        <th class="px-6 py-4 text-right font-semibold tracking-wider" scope="col">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="reportsTableBody" class="divide-y divide-slate-100 dark:divide-[#2a3441]">
                                    <?php foreach ($reports as $report): ?>
                                    <tr class="table-row group hover:bg-slate-50 dark:hover:bg-[#202936]" data-id="<?php echo $report['id']; ?>">
                                        <td class="whitespace-nowrap px-6 py-4 font-medium text-primary dark:text-white">
                                            <?php echo formatDate($report['date_generated']); ?>
                                        </td>
                                        <td class="px-6 py-4 font-medium text-slate-800 dark:text-slate-200">
                                            <div class="flex items-center gap-2">
                                                <span class="material-symbols-outlined text-slate-400 text-[18px]">description</span>
                                                <?php echo htmlspecialchars($report['filename']); ?>
                                            </div>
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 tabular-nums">
                                            <?php echo $report['customer_count'] ? number_format($report['customer_count']) : '-'; ?>
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4">
                                            <?php echo getStatusBadge($report['status']); ?>
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <?php if ($report['status'] === 'completed'): ?>
                                                <a href="analytics.php?job_id=<?php echo $report['id']; ?>" 
                                                   class="action-btn rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-primary dark:hover:bg-[#2a3441] dark:hover:text-blue-400" 
                                                   title="View Report">
                                                    <span class="material-symbols-outlined text-[20px]">visibility</span>
                                                </a>
                                                <button 
                                                    class="action-btn rounded p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20 dark:hover:text-red-400" 
                                                    title="Delete"
                                                    onclick="deleteReport('<?php echo $report['id']; ?>')"
                                                >
                                                    <span class="material-symbols-outlined text-[20px]">delete</span>
                                                </button>
                                                <?php elseif ($report['status'] === 'failed'): ?>
                                                <button 
                                                    class="action-btn rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-primary dark:hover:bg-[#2a3441] dark:hover:text-blue-400" 
                                                    title="Retry"
                                                    onclick="retryReport('<?php echo $report['id']; ?>')"
                                                >
                                                    <span class="material-symbols-outlined text-[20px]">refresh</span>
                                                </button>
                                                <button 
                                                    class="action-btn rounded p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20 dark:hover:text-red-400" 
                                                    title="Delete"
                                                    onclick="deleteReport('<?php echo $report['id']; ?>')"
                                                >
                                                    <span class="material-symbols-outlined text-[20px]">delete</span>
                                                </button>
                                                <?php elseif ($report['status'] === 'processing'): ?>
                                                <button class="rounded p-1.5 text-slate-300 cursor-not-allowed" disabled title="View Report">
                                                    <span class="material-symbols-outlined text-[20px]">visibility</span>
                                                </button>
                                                <button 
                                                    class="action-btn rounded p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20 dark:hover:text-red-400" 
                                                    title="Cancel"
                                                    onclick="cancelReport('<?php echo $report['id']; ?>')"
                                                >
                                                    <span class="material-symbols-outlined text-[20px]">close</span>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Empty State (hidden by default) -->
                        <div id="emptyState" class="hidden py-12 text-center">
                            <span class="material-symbols-outlined text-[64px] text-slate-300 dark:text-slate-600">folder_open</span>
                            <h3 class="mt-4 text-lg font-semibold text-slate-700 dark:text-slate-300">No reports found</h3>
                            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Upload a dataset to start your first analysis.</p>
                            <a href="upload.php" class="mt-4 inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary/90 transition-colors">
                                <span class="material-symbols-outlined text-[18px]">add</span>
                                New Analysis
                            </a>
                        </div>
                        
                        <!-- Pagination -->
                        <div class="flex items-center justify-between border-t border-slate-200 bg-white px-6 py-4 dark:border-[#2a3441] dark:bg-[#1a222d]">
                            <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-sm text-slate-700 dark:text-slate-400">
                                        Showing <span class="font-medium" id="showingFrom">1</span> to <span class="font-medium" id="showingTo">5</span> of <span class="font-medium" id="totalCount"><?php echo $totalReports; ?></span> results
                                    </p>
                                </div>
                                <div>
                                    <nav aria-label="Pagination" class="isolate inline-flex -space-x-px rounded-md shadow-sm">
                                        <a href="?page=<?php echo max(1, $currentPageNum - 1); ?>" 
                                           class="relative inline-flex items-center rounded-l-md px-2 py-2 text-slate-400 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 focus:z-20 focus:outline-offset-0 dark:ring-[#2a3441] dark:hover:bg-[#2a3441] transition-colors <?php echo $currentPageNum <= 1 ? 'pointer-events-none opacity-50' : ''; ?>">
                                            <span class="sr-only">Previous</span>
                                            <span class="material-symbols-outlined text-[20px]">chevron_left</span>
                                        </a>
                                        
                                        <?php for ($i = 1; $i <= min(3, $totalPages); $i++): ?>
                                        <a href="?page=<?php echo $i; ?>" 
                                           class="relative inline-flex items-center px-4 py-2 text-sm font-semibold ring-1 ring-inset ring-slate-300 focus:z-20 focus:outline-offset-0 transition-colors <?php echo $i === $currentPageNum ? 'z-10 bg-primary text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary dark:bg-blue-600' : 'text-slate-900 hover:bg-slate-50 dark:text-white dark:ring-[#2a3441] dark:hover:bg-[#2a3441]'; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                        <?php endfor; ?>
                                        
                                        <?php if ($totalPages > 3): ?>
                                        <span class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-slate-700 ring-1 ring-inset ring-slate-300 focus:outline-offset-0 dark:text-slate-500 dark:ring-[#2a3441]">...</span>
                                        <?php endif; ?>
                                        
                                        <a href="?page=<?php echo min($totalPages, $currentPageNum + 1); ?>" 
                                           class="relative inline-flex items-center rounded-r-md px-2 py-2 text-slate-400 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 focus:z-20 focus:outline-offset-0 dark:ring-[#2a3441] dark:hover:bg-[#2a3441] transition-colors <?php echo $currentPageNum >= $totalPages ? 'pointer-events-none opacity-50' : ''; ?>">
                                            <span class="sr-only">Next</span>
                                            <span class="material-symbols-outlined text-[20px]">chevron_right</span>
                                        </a>
                                    </nav>
                                </div>
                            </div>
                            
                            <!-- Mobile Pagination View -->
                            <div class="flex flex-1 justify-between sm:hidden">
                                <a href="?page=<?php echo max(1, $currentPageNum - 1); ?>" 
                                   class="relative inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-[#2a3441] dark:bg-[#1a222d] dark:text-slate-300 transition-colors">
                                    Previous
                                </a>
                                <a href="?page=<?php echo min($totalPages, $currentPageNum + 1); ?>" 
                                   class="relative ml-3 inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-[#2a3441] dark:bg-[#1a222d] dark:text-slate-300 transition-colors">
                                    Next
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Mobile Menu Overlay -->
    <div id="mobileMenuOverlay" class="fixed inset-0 z-40 bg-black/50 opacity-0 pointer-events-none transition-opacity md:hidden"></div>
    
    <!-- Mobile Menu Drawer -->
    <div id="mobileMenuDrawer" class="fixed inset-y-0 left-0 z-50 w-64 bg-white dark:bg-[#1a222d] transform -translate-x-full transition-transform md:hidden">
        <div class="flex h-16 items-center justify-between px-6 border-b border-[#e8ecf2] dark:border-[#2a3441]">
            <a href="dashboard.php" class="flex items-center gap-2 text-primary dark:text-white">
                <div class="size-6 text-primary dark:text-blue-400">
                    <svg class="h-full w-full" fill="none" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
                        <path d="M4 4H17.3334V17.3334H30.6666V30.6666H44V44H4V4Z" fill="currentColor"></path>
                    </svg>
                </div>
                <h2 class="text-lg font-bold">Customer 360</h2>
            </a>
            <button id="closeMobileMenu" class="text-slate-500 hover:text-primary">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <nav class="p-4 flex flex-col gap-1">
            <a class="nav-link flex items-center gap-3 rounded-lg px-3 py-2 text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-[#2a3441]" href="dashboard.php">
                <span class="material-symbols-outlined text-[20px]">dashboard</span>
                <span class="text-sm font-medium">Dashboard</span>
            </a>
            <a class="nav-link flex items-center gap-3 rounded-lg px-3 py-2 text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-[#2a3441]" href="upload.php">
                <span class="material-symbols-outlined text-[20px]">cloud_upload</span>
                <span class="text-sm font-medium">Upload Data</span>
            </a>
            <a class="nav-link flex items-center gap-3 rounded-lg px-3 py-2 text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-[#2a3441]" href="analytics.php">
                <span class="material-symbols-outlined text-[20px]">analytics</span>
                <span class="text-sm font-medium">Analytics</span>
            </a>
            <a class="nav-link flex items-center gap-3 rounded-lg bg-primary/10 px-3 py-2 text-primary dark:bg-blue-500/20 dark:text-blue-200" href="reports.php">
                <span class="material-symbols-outlined text-[20px]">description</span>
                <span class="text-sm font-medium">Reports</span>
            </a>
            <a class="nav-link flex items-center gap-3 rounded-lg px-3 py-2 text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-[#2a3441]" href="help.php">
                <span class="material-symbols-outlined text-[20px]">help</span>
                <span class="text-sm font-medium">Help & Support</span>
            </a>
        </nav>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 opacity-0 pointer-events-none transition-opacity">
        <div class="bg-white dark:bg-[#1a222d] rounded-xl shadow-xl max-w-md w-full mx-4 transform scale-95 transition-transform">
            <div class="p-6">
                <div class="flex items-center gap-4">
                    <div class="flex-shrink-0 h-12 w-12 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
                        <span class="material-symbols-outlined text-red-600 dark:text-red-400">delete</span>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-primary dark:text-white">Delete Report</h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Are you sure you want to delete this report? This action cannot be undone.</p>
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-6">
                    <button onclick="closeDeleteModal()" class="px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-[#2a3441] rounded-lg hover:bg-slate-200 dark:hover:bg-[#354150] transition-colors">
                        Cancel
                    </button>
                    <button onclick="confirmDelete()" class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors">
                        Delete
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Toast Notification -->
    <div id="toast" class="fixed bottom-4 right-4 z-50 transform translate-y-20 opacity-0 transition-all">
        <div class="flex items-center gap-3 bg-white dark:bg-[#1a222d] rounded-lg shadow-lg border border-slate-200 dark:border-[#2a3441] px-4 py-3">
            <span id="toastIcon" class="material-symbols-outlined text-green-600">check_circle</span>
            <span id="toastMessage" class="text-sm font-medium text-slate-700 dark:text-slate-300">Report deleted successfully</span>
        </div>
    </div>

    <script>
        // State
        let currentDeleteId = null;
        let reports = <?php echo json_encode($reports); ?>;
        
        // Mobile Menu
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const mobileMenuOverlay = document.getElementById('mobileMenuOverlay');
        const mobileMenuDrawer = document.getElementById('mobileMenuDrawer');
        const closeMobileMenu = document.getElementById('closeMobileMenu');
        
        function openMobileMenu() {
            mobileMenuOverlay.classList.remove('opacity-0', 'pointer-events-none');
            mobileMenuDrawer.classList.remove('-translate-x-full');
        }
        
        function closeMobileMenuFn() {
            mobileMenuOverlay.classList.add('opacity-0', 'pointer-events-none');
            mobileMenuDrawer.classList.add('-translate-x-full');
        }
        
        mobileMenuBtn?.addEventListener('click', openMobileMenu);
        closeMobileMenu?.addEventListener('click', closeMobileMenuFn);
        mobileMenuOverlay?.addEventListener('click', closeMobileMenuFn);
        
        // Search & Filter - Server-side filtering
        const searchInput = document.getElementById('searchInput');
        const statusFilter = document.getElementById('statusFilter');
        const dateRange = document.getElementById('dateRange');
        
        let filterTimeout = null;
        
        function applyFilters() {
            const params = new URLSearchParams();
            
            if (searchInput.value.trim()) {
                params.set('search', searchInput.value.trim());
            }
            if (statusFilter.value) {
                params.set('status', statusFilter.value);
            }
            
            const queryString = params.toString();
            window.location.href = 'reports.php' + (queryString ? '?' + queryString : '');
        }
        
        // Debounced search
        searchInput?.addEventListener('input', () => {
            clearTimeout(filterTimeout);
            filterTimeout = setTimeout(applyFilters, 500);
        });
        
        // Immediate filter on status change
        statusFilter?.addEventListener('change', applyFilters);
        
        // Client-side quick filter for responsiveness
        function filterReports() {
            const searchTerm = searchInput.value.toLowerCase();
            const statusValue = statusFilter.value;
            
            const rows = document.querySelectorAll('#reportsTableBody tr');
            let visibleCount = 0;
            
            rows.forEach(row => {
                const filename = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
                const status = row.querySelector('td:nth-child(4)').textContent.toLowerCase().trim();
                
                let show = true;
                
                if (searchTerm && !filename.includes(searchTerm)) {
                    show = false;
                }
                
                if (statusValue && !status.includes(statusValue)) {
                    show = false;
                }
                
                row.style.display = show ? '' : 'none';
                if (show) visibleCount++;
            });
            
            // Show/hide empty state
            const emptyState = document.getElementById('emptyState');
            const tableBody = document.getElementById('reportsTableBody');
            
            if (visibleCount === 0) {
                tableBody.style.display = 'none';
                emptyState.classList.remove('hidden');
            } else {
                tableBody.style.display = '';
                emptyState.classList.add('hidden');
            }
        }
        
        // Delete Report
        function deleteReport(id) {
            currentDeleteId = id;
            const modal = document.getElementById('deleteModal');
            modal.classList.remove('opacity-0', 'pointer-events-none');
            modal.querySelector('div > div').classList.remove('scale-95');
        }
        
        function closeDeleteModal() {
            const modal = document.getElementById('deleteModal');
            modal.classList.add('opacity-0', 'pointer-events-none');
            modal.querySelector('div > div').classList.add('scale-95');
            currentDeleteId = null;
        }
        
        function confirmDelete() {
            if (!currentDeleteId) return;
            
            // Remove from DOM
            const row = document.querySelector(`tr[data-id="${currentDeleteId}"]`);
            if (row) {
                row.style.transition = 'opacity 0.3s, transform 0.3s';
                row.style.opacity = '0';
                row.style.transform = 'translateX(-20px)';
                setTimeout(() => row.remove(), 300);
            }
            
            closeDeleteModal();
            showToast('Report deleted successfully', 'success');
            
            // API call would go here
            // fetch(`api/process.php?action=delete&job_id=${currentDeleteId}`, { method: 'DELETE' });
        }
        
        // Retry Report
        function retryReport(id) {
            showToast('Retrying analysis...', 'info');
            // API call would go here
            setTimeout(() => {
                window.location.href = `processing.php?job_id=${id}`;
            }, 1000);
        }
        
        // Cancel Report
        function cancelReport(id) {
            if (confirm('Are you sure you want to cancel this analysis?')) {
                showToast('Analysis cancelled', 'success');
                // API call would go here
                const row = document.querySelector(`tr[data-id="${id}"]`);
                if (row) {
                    row.remove();
                }
            }
        }
        
        // Toast Notification
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            const toastIcon = document.getElementById('toastIcon');
            const toastMessage = document.getElementById('toastMessage');
            
            toastMessage.textContent = message;
            
            if (type === 'success') {
                toastIcon.textContent = 'check_circle';
                toastIcon.className = 'material-symbols-outlined text-green-600';
            } else if (type === 'error') {
                toastIcon.textContent = 'error';
                toastIcon.className = 'material-symbols-outlined text-red-600';
            } else if (type === 'info') {
                toastIcon.textContent = 'info';
                toastIcon.className = 'material-symbols-outlined text-blue-600';
            }
            
            toast.classList.remove('translate-y-20', 'opacity-0');
            
            setTimeout(() => {
                toast.classList.add('translate-y-20', 'opacity-0');
            }, 3000);
        }
        
        // Export functionality
        document.getElementById('exportBtn')?.addEventListener('click', () => {
            const rows = [['Date Generated', 'Filename', 'Customer Count', 'Status']];
            
            document.querySelectorAll('#reportsTableBody tr:not([style*="display: none"])').forEach(row => {
                const cells = row.querySelectorAll('td');
                rows.push([
                    cells[0].textContent.trim(),
                    cells[1].textContent.trim(),
                    cells[2].textContent.trim(),
                    cells[3].textContent.trim()
                ]);
            });
            
            const csv = rows.map(r => r.map(c => `"${String(c).replace(/"/g, '""')}"`).join(',')).join('\n');
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'reports_history.csv';
            a.click();
            URL.revokeObjectURL(url);
            
            showToast('Reports exported successfully', 'success');
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            // Escape to close modals
            if (e.key === 'Escape') {
                closeDeleteModal();
                closeMobileMenuFn();
            }
            
            // Ctrl/Cmd + K for search
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                searchInput?.focus();
            }
        });
        
        // Polling for processing jobs (check every 10 seconds)
        function pollProcessingJobs() {
            const processingRows = document.querySelectorAll('tr[data-id]');
            processingRows.forEach(row => {
                const statusCell = row.querySelector('td:nth-child(4)');
                if (statusCell?.textContent.includes('Processing')) {
                    const jobId = row.dataset.id;
                    // In real implementation, poll the API
                    // fetch(`api/process.php?action=status&job_id=${jobId}`)
                    //     .then(r => r.json())
                    //     .then(data => {
                    //         if (data.status === 'completed') {
                    //             location.reload();
                    //         }
                    //     });
                }
            });
        }
        
        // Start polling if there are processing jobs
        if (document.querySelector('td:nth-child(4) .animate-spin')) {
            setInterval(pollProcessingJobs, 10000);
        }
    </script>
</body>
</html>
