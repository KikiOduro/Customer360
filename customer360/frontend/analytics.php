<?php
session_start();

// Require login
if (!isset($_SESSION['user_id'])) {
    header('Location: signin.php');
    exit;
}

// Try to read analysis results from session (from real API or demo mode)
$analysisResults = $_SESSION['analysis_results'] ?? $_SESSION['demo_results'] ?? null;
$currentJob = $_SESSION['current_job'] ?? null;
$currentUpload = $_SESSION['current_upload'] ?? null;

// File info
$uploadedFile = $currentUpload['file_name'] ?? 'ghana_sales_Q3.csv';
$batchId = $currentJob['job_id'] ?? rand(2000, 9999);
$recordCount = $analysisResults['summary']['total_customers'] ?? $currentUpload['total_rows'] ?? 1240;
$isDemoMode = isset($_SESSION['demo_results']);

// Default segment colors by name
$segmentColors = [
    'Champions' => 'yellow',
    'Loyal Customers' => 'blue',
    'Potential Loyalists' => 'green',
    'Recent Customers' => 'cyan',
    'Promising' => 'teal',
    'Needs Attention' => 'orange',
    'About to Sleep' => 'purple',
    'At Risk' => 'red',
    'Cannot Lose' => 'pink',
    'Hibernating' => 'gray',
    'Lost' => 'slate',
];

// Get KPIs from results or use defaults
if ($analysisResults && isset($analysisResults['summary'])) {
    $summary = $analysisResults['summary'];
    $kpis = [
        'total_revenue' => $summary['total_revenue'] ?? 125000,
        'active_customers' => $summary['total_customers'] ?? 1240,
        'avg_order_value' => $summary['avg_order_value'] ?? 350,
        'churn_rate' => $summary['churn_rate'] ?? 2.4,
    ];
} else {
    $kpis = [
        'total_revenue' => 125000,
        'active_customers' => 1240,
        'avg_order_value' => 350,
        'churn_rate' => 2.4,
    ];
}

// Get segments from results or use defaults
if ($analysisResults && isset($analysisResults['segments'])) {
    $segments = [];
    foreach ($analysisResults['segments'] as $seg) {
        $segmentName = $seg['segment_name'] ?? $seg['name'] ?? 'Unknown';
        $segments[] = [
            'name' => $segmentName,
            'pct' => round($seg['percentage'] ?? $seg['pct'] ?? 0),
            'count' => $seg['customer_count'] ?? $seg['count'] ?? 0,
            'description' => $seg['description'] ?? '',
            'color' => $segmentColors[$segmentName] ?? 'gray',
            'avg_revenue' => $seg['avg_revenue'] ?? 0,
            'avg_recency' => $seg['avg_recency'] ?? 0,
            'avg_frequency' => $seg['avg_frequency'] ?? 0,
        ];
    }
} else {
    $segments = [
        ['name' => 'Champions', 'pct' => 40, 'count' => 496, 'description' => 'High spend, frequent', 'color' => 'yellow', 'avg_revenue' => 2500, 'avg_recency' => 5, 'avg_frequency' => 12],
        ['name' => 'Loyal Customers', 'pct' => 25, 'count' => 310, 'description' => 'Steady purchases', 'color' => 'blue', 'avg_revenue' => 1200, 'avg_recency' => 15, 'avg_frequency' => 8],
        ['name' => 'At Risk', 'pct' => 15, 'count' => 186, 'description' => 'Need attention', 'color' => 'red', 'avg_revenue' => 800, 'avg_recency' => 45, 'avg_frequency' => 3],
        ['name' => 'Potential Loyalists', 'pct' => 12, 'count' => 149, 'description' => 'New promising customers', 'color' => 'green', 'avg_revenue' => 600, 'avg_recency' => 10, 'avg_frequency' => 4],
        ['name' => 'Hibernating', 'pct' => 8, 'count' => 99, 'description' => 'Inactive customers', 'color' => 'gray', 'avg_revenue' => 400, 'avg_recency' => 90, 'avg_frequency' => 1],
    ];
}

// Get recent customers from results or use defaults
if ($analysisResults && isset($analysisResults['recent_customers'])) {
    $recent = [];
    foreach (array_slice($analysisResults['recent_customers'], 0, 10) as $cust) {
        $recent[] = [
            'name' => $cust['customer_name'] ?? $cust['name'] ?? 'Customer',
            'email' => $cust['email'] ?? 'N/A',
            'segment' => $cust['segment'] ?? 'Unknown',
            'last_purchase' => $cust['last_purchase_date'] ?? $cust['last_purchase'] ?? 'N/A',
            'status' => ($cust['recency_days'] ?? 30) < 30 ? 'Active' : 'Inactive',
            'spend' => $cust['total_spend'] ?? $cust['spend'] ?? 0,
        ];
    }
} else {
    $recent = [
        ['name'=>'Abena Boakye','email'=>'abena@example.com','segment'=>'Champions','last_purchase'=>'Oct 24, 2023','status'=>'Active','spend'=>2450.00],
        ['name'=>'Kwesi Mensah','email'=>'kwesi.m@example.com','segment'=>'Loyal Customers','last_purchase'=>'Oct 22, 2023','status'=>'Active','spend'=>850.00],
        ['name'=>'Yaw Addo','email'=>'yaw.addo@example.com','segment'=>'At Risk','last_purchase'=>'Aug 15, 2023','status'=>'Inactive','spend'=>1200.00],
        ['name'=>'Esi Koomson','email'=>'esi.k@example.com','segment'=>'Loyal Customers','last_purchase'=>'Oct 20, 2023','status'=>'Active','spend'=>560.00],
        ['name'=>'Kofi Asante','email'=>'kofi.a@example.com','segment'=>'Champions','last_purchase'=>'Oct 23, 2023','status'=>'Active','spend'=>1850.00],
    ];
}

function ghc($n){
    return 'GH₵ ' . number_format($n, 2);
}

// Get segment color class for Tailwind
function getSegmentBgClass($color) {
    $colorMap = [
        'yellow' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
        'blue' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
        'green' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
        'red' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
        'orange' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400',
        'purple' => 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400',
        'pink' => 'bg-pink-100 text-pink-800 dark:bg-pink-900/30 dark:text-pink-400',
        'cyan' => 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900/30 dark:text-cyan-400',
        'teal' => 'bg-teal-100 text-teal-800 dark:bg-teal-900/30 dark:text-teal-400',
        'gray' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
        'slate' => 'bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300',
    ];
    return $colorMap[$color] ?? $colorMap['gray'];
}

function getSegmentBarClass($color) {
    $colorMap = [
        'yellow' => 'bg-yellow-400',
        'blue' => 'bg-blue-500',
        'green' => 'bg-green-500',
        'red' => 'bg-red-500',
        'orange' => 'bg-orange-500',
        'purple' => 'bg-purple-500',
        'pink' => 'bg-pink-500',
        'cyan' => 'bg-cyan-500',
        'teal' => 'bg-teal-500',
        'gray' => 'bg-gray-400',
        'slate' => 'bg-slate-400',
    ];
    return $colorMap[$color] ?? $colorMap['gray'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Customer 360 Results Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: { extend: { colors: { primary:'#0b203c', 'background-light':'#f6f7f8', 'background-dark':'#121820' } } }
        }
    </script>
</head>
<body class="bg-background-light dark:bg-background-dark font-display text-primary dark:text-white">
<div class="flex h-screen w-full">
    <!-- Sidebar -->
    <aside class="flex w-64 flex-col border-r border-gray-200 bg-white dark:border-gray-800 dark:bg-[#1a222c] hidden md:flex">
        <div class="flex h-16 items-center gap-3 px-6 border-b border-gray-100 dark:border-gray-800">
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary text-white">
                <span class="material-symbols-outlined text-[20px]">analytics</span>
            </div>
            <h1 class="text-lg font-bold tracking-tight text-primary dark:text-white">Customer 360</h1>
        </div>
        <div class="flex flex-1 flex-col justify-between p-4">
            <nav class="flex flex-col gap-2">
                <a href="dashboard.php" class="flex items-center gap-3 rounded-lg px-3 py-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800">
                    <span class="material-symbols-outlined">dashboard</span>
                    <span class="text-sm font-medium">Dashboard</span>
                </a>
                <a href="upload.php" class="flex items-center gap-3 rounded-lg px-3 py-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800">
                    <span class="material-symbols-outlined">upload_file</span>
                    <span class="text-sm font-medium">Upload Data</span>
                </a>
                <a href="analytics.php" class="flex items-center gap-3 rounded-lg bg-primary/10 px-3 py-2 text-primary dark:text-white dark:bg-white/10">
                    <span class="material-symbols-outlined">analytics</span>
                    <span class="text-sm font-medium">Analytics</span>
                </a>
                <a href="reports.php" class="flex items-center gap-3 rounded-lg px-3 py-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800">
                    <span class="material-symbols-outlined">bar_chart</span>
                    <span class="text-sm font-medium">Reports</span>
                </a>
            </nav>
            <div class="flex flex-col gap-2">
                <a href="help.php" class="flex items-center gap-3 rounded-lg px-3 py-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800">
                    <span class="material-symbols-outlined">help</span>
                    <span class="text-sm font-medium">Help & Support</span>
                </a>
                <div class="mt-4 flex items-center gap-3 border-t border-gray-100 pt-4 dark:border-gray-800">
                    <div class="h-10 w-10 overflow-hidden rounded-full bg-gray-200 bg-center bg-cover" style="background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuDHPryeJlt-KAnpYpMT8jqoGREFuY1XijiPNZALaT8F6aC9Kn0VFD06uDAJKdrVRxQ5fS3IvGrHKKG4wOCtb2KO_13UFB3yWBJI7CWfYi0cmlxYZM40cNOhnVgh0m_NQkqhNMBpIyTTRpkHTjdoDir1tJkdiAjZFPagfvKADsycOwyP-95G_cx-MdmD_E0KeGyOr1zbS_gnsg00FC8cNktrcmjfnz2rKUqfsanTCmI7E6cE_9YAVRX4-zbqRzySwBsqFrY7z_GDLh8');"></div>
                    <div class="flex flex-col">
                        <span class="text-sm font-medium text-primary dark:text-white"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'SME Admin'); ?></span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">SME Admin</span>
                    </div>
                </div>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 overflow-y-auto bg-background-light dark:bg-background-dark p-4 md:p-8">
        <div class="mx-auto max-w-7xl flex flex-col gap-8">
            <!-- Header -->
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <div class="flex items-center gap-3">
                        <h2 class="text-2xl font-bold text-primary dark:text-white">Dashboard Overview</h2>
                        <?php if($isDemoMode): ?>
                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                            <span class="material-symbols-outlined text-[14px]">science</span>
                            Demo Mode
                        </span>
                        <?php endif; ?>
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Results for batch #<?php echo htmlspecialchars($batchId); ?> — file: <?php echo htmlspecialchars($uploadedFile); ?></p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <button class="flex items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-primary hover:bg-gray-50 dark:border-gray-700 dark:bg-[#1a222c] dark:text-white dark:hover:bg-gray-800 shadow-sm transition-colors">
                        <span class="material-symbols-outlined text-[20px]">calendar_today</span>
                        <span>Oct 1 - Dec 31</span>
                    </button>
                    <button id="exportCsv" class="flex items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-primary hover:bg-gray-50 dark:border-gray-700 dark:bg-[#1a222c] dark:text-white dark:hover:bg-gray-800 shadow-sm transition-colors">
                        <span class="material-symbols-outlined text-[20px]">download</span>
                        <span>Export CSV</span>
                    </button>
                    <button id="downloadPdf" class="flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-[#0f2a4d] shadow-md transition-colors">
                        <span class="material-symbols-outlined text-[20px]">picture_as_pdf</span>
                        <span>Download PDF</span>
                    </button>
                </div>
            </div>

            <!-- KPI Cards -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="flex flex-col rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-[#1a222c]">
                    <div class="mb-2 flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Revenue</span>
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400">
                            <span class="material-symbols-outlined text-[20px]">payments</span>
                        </span>
                    </div>
                    <div class="flex items-end justify-between">
                        <h3 class="text-2xl font-bold text-primary dark:text-white"><?php echo ghc($kpis['total_revenue']); ?></h3>
                        <span class="flex items-center gap-1 text-xs font-medium text-green-600 dark:text-green-400">
                            <span class="material-symbols-outlined text-[16px]">trending_up</span>
                            +12%
                        </span>
                    </div>
                </div>
                <div class="flex flex-col rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-[#1a222c]">
                    <div class="mb-2 flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Active Customers</span>
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-purple-50 text-purple-600 dark:bg-purple-900/30 dark:text-purple-400">
                            <span class="material-symbols-outlined text-[20px]">group</span>
                        </span>
                    </div>
                    <div class="flex items-end justify-between">
                        <h3 class="text-2xl font-bold text-primary dark:text-white"><?php echo number_format($kpis['active_customers']); ?></h3>
                        <span class="flex items-center gap-1 text-xs font-medium text-green-600 dark:text-green-400">
                            <span class="material-symbols-outlined text-[16px]">trending_up</span>
                            +5%
                        </span>
                    </div>
                </div>
                <div class="flex flex-col rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-[#1a222c]">
                    <div class="mb-2 flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Avg Order Value</span>
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-orange-50 text-orange-600 dark:bg-orange-900/30 dark:text-orange-400">
                            <span class="material-symbols-outlined text-[20px]">shopping_cart</span>
                        </span>
                    </div>
                    <div class="flex items-end justify-between">
                        <h3 class="text-2xl font-bold text-primary dark:text-white"><?php echo ghc($kpis['avg_order_value']); ?></h3>
                        <span class="flex items-center gap-1 text-xs font-medium text-green-600 dark:text-green-400">
                            <span class="material-symbols-outlined text-[16px]">trending_up</span>
                            +8%
                        </span>
                    </div>
                </div>
                <div class="flex flex-col rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-[#1a222c]">
                    <div class="mb-2 flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Churn Rate</span>
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-red-50 text-red-600 dark:bg-red-900/30 dark:text-red-400">
                            <span class="material-symbols-outlined text-[20px]">person_remove</span>
                        </span>
                    </div>
                    <div class="flex items-end justify-between">
                        <h3 class="text-2xl font-bold text-primary dark:text-white"><?php echo $kpis['churn_rate']; ?>%</h3>
                        <span class="flex items-center gap-1 text-xs font-medium text-green-600 dark:text-green-400">
                            <span class="material-symbols-outlined text-[16px]">trending_down</span>
                            -0.5%
                        </span>
                    </div>
                </div>
            </div>

            <!-- Chart & Segments -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="flex flex-col rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-[#1a222c] lg:col-span-2">
                    <div class="mb-6 flex items-center justify-between">
                        <h3 class="text-lg font-bold text-primary dark:text-white">Revenue Overview</h3>
                        <div class="flex items-center gap-2">
                            <select class="rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-600 focus:border-primary focus:ring-primary dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300">
                                <option>Monthly</option>
                                <option>Weekly</option>
                                <option>Daily</option>
                            </select>
                        </div>
                    </div>
                    <div class="relative flex h-64 w-full flex-col justify-end gap-2">
                        <!-- Simplified bars -->
                        <div class="flex h-full items-end justify-between gap-2 px-2">
                            <div class="w-full rounded-t bg-primary/10 h-[40%]"></div>
                            <div class="w-full rounded-t bg-primary/20 h-[55%]"></div>
                            <div class="w-full rounded-t bg-primary/30 h-[35%]"></div>
                            <div class="w-full rounded-t bg-primary/50 h-[70%]"></div>
                            <div class="w-full rounded-t bg-primary/40 h-[60%]"></div>
                            <div class="w-full rounded-t bg-primary/80 h-[85%]"></div>
                            <div class="w-full rounded-t bg-primary h-[65%]"></div>
                        </div>
                        <div class="flex justify-between border-t border-gray-200 pt-2 text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            <span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span><span>Sun</span>
                        </div>
                    </div>
                </div>

                <!-- Segments -->
                <div class="flex flex-col gap-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-bold text-primary dark:text-white">Segments</h3>
                        <?php if($isDemoMode): ?>
                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                            <span class="material-symbols-outlined text-[14px]">science</span>
                            Demo
                        </span>
                        <?php endif; ?>
                    </div>
                    <?php 
                    $iconMap = [
                        'Champions' => 'trophy',
                        'Loyal Customers' => 'favorite',
                        'Potential Loyalists' => 'thumb_up',
                        'Recent Customers' => 'schedule',
                        'Promising' => 'trending_up',
                        'Needs Attention' => 'notification_important',
                        'About to Sleep' => 'bedtime',
                        'At Risk' => 'warning',
                        'Cannot Lose' => 'priority_high',
                        'Hibernating' => 'nights_stay',
                        'Lost' => 'person_off',
                    ];
                    foreach($segments as $s): 
                        $icon = $iconMap[$s['name']] ?? 'category';
                    ?>
                    <div class="flex flex-1 flex-col justify-between rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition-all hover:shadow-md dark:border-gray-700 dark:bg-[#1a222c]">
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-3">
                                <div class="rounded-lg p-2 <?php echo getSegmentBgClass($s['color']); ?>">
                                    <span class="material-symbols-outlined"><?php echo $icon; ?></span>
                                </div>
                                <div>
                                    <h4 class="font-bold text-primary dark:text-white"><?php echo htmlspecialchars($s['name']); ?></h4>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        <?php echo htmlspecialchars($s['description']); ?>
                                        <?php if(isset($s['count']) && $s['count'] > 0): ?>
                                        <span class="ml-1 font-medium">(<?php echo number_format($s['count']); ?> customers)</span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                            <span class="font-bold text-primary dark:text-white"><?php echo $s['pct']; ?>%</span>
                        </div>
                        <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                            <div class="h-full rounded-full <?php echo getSegmentBarClass($s['color']); ?>" style="width: <?php echo $s['pct']; ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Recent Transactions Table -->
            <div class="flex flex-col gap-4 rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-[#1a222c]">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <h3 class="text-lg font-bold text-primary dark:text-white">Recent Transactions</h3>
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[20px] text-gray-400">search</span>
                            <input id="searchInput" class="h-10 w-full rounded-lg border-gray-200 bg-gray-50 pl-10 pr-4 text-sm text-gray-900 focus:border-primary focus:ring-primary dark:border-gray-600 dark:bg-gray-800 dark:text-white sm:w-64" placeholder="Search customers..." type="text" />
                        </div>
                        <button class="flex h-10 items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-4 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
                            <span class="material-symbols-outlined text-[20px]">filter_list</span>
                            Filter
                        </button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[600px] text-left text-sm">
                        <thead class="border-b border-gray-200 text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-3 font-medium">Customer Name</th>
                                <th class="px-4 py-3 font-medium">Segment</th>
                                <th class="px-4 py-3 font-medium">Last Purchase</th>
                                <th class="px-4 py-3 font-medium">Status</th>
                                <th class="px-4 py-3 font-medium text-right">Total Spend</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800" id="resultsBody">
                            <?php foreach($recent as $r): ?>
                            <tr class="group hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="px-4 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary dark:bg-white/10 dark:text-white"><?php echo strtoupper(substr($r['name'],0,2)); ?></div>
                                        <div>
                                            <p class="font-medium text-primary dark:text-white"><?php echo htmlspecialchars($r['name']); ?></p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($r['email']); ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <?php 
                                    $segColor = $segmentColors[$r['segment']] ?? 'gray';
                                    ?>
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium <?php echo getSegmentBgClass($segColor); ?>">
                                        <?php echo htmlspecialchars($r['segment']); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($r['last_purchase']); ?></td>
                                <td class="px-4 py-4">
                                    <div class="flex items-center gap-2">
                                        <div class="h-2 w-2 rounded-full <?php echo ($r['status']=='Active') ? 'bg-green-500' : (($r['status']=='Inactive') ? 'bg-gray-300' : 'bg-yellow-500'); ?>"></div>
                                        <span class="text-gray-700 dark:text-gray-300"><?php echo htmlspecialchars($r['status']); ?></span>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-right font-medium text-primary dark:text-white"><?php echo ghc($r['spend']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="flex items-center justify-between border-t border-gray-100 pt-4 dark:border-gray-800">
                    <span class="text-xs text-gray-500 dark:text-gray-400">Showing <?php echo count($recent); ?> of <?php echo number_format($kpis['active_customers']); ?> customers</span>
                    <div class="flex gap-1">
                        <button class="flex h-8 w-8 items-center justify-center rounded border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                            <span class="material-symbols-outlined text-[16px]">chevron_left</span>
                        </button>
                        <button class="flex h-8 w-8 items-center justify-center rounded border border-gray-200 bg-primary text-white hover:bg-primary/90 dark:border-gray-700">1</button>
                        <button class="flex h-8 w-8 items-center justify-center rounded border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">2</button>
                        <button class="flex h-8 w-8 items-center justify-center rounded border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">3</button>
                        <button class="flex h-8 w-8 items-center justify-center rounded border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                            <span class="material-symbols-outlined text-[16px]">chevron_right</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
// Simple client-side download for CSV export of the recent results (demo)
document.getElementById('exportCsv').addEventListener('click', () => {
    const rows = [
        ['Customer Name','Email','Segment','Last Purchase','Status','Total Spend']
    ];
    <?php foreach($recent as $r): ?>
    rows.push(["<?php echo addslashes($r['name']); ?>","<?php echo addslashes($r['email']); ?>","<?php echo addslashes($r['segment']); ?>","<?php echo addslashes($r['last_purchase']); ?>","<?php echo addslashes($r['status']); ?>","<?php echo number_format($r['spend'],2); ?>"]);
    <?php endforeach; ?>

    const csv = rows.map(r => r.map(c => `"${String(c).replace(/"/g,'""')}"`).join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'customer360_results.csv';
    a.click();
    URL.revokeObjectURL(url);
});

// Download PDF button - calls API for real PDF or shows demo message
document.getElementById('downloadPdf').addEventListener('click', async () => {
    const jobId = '<?php echo addslashes($currentJob['job_id'] ?? ''); ?>';
    const isDemoMode = <?php echo $isDemoMode ? 'true' : 'false'; ?>;
    
    if (isDemoMode || !jobId) {
        alert('PDF download is available when using the real analysis backend.\n\nIn demo mode, you can export to CSV instead.');
        return;
    }
    
    try {
        const btn = document.getElementById('downloadPdf');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<span class="material-symbols-outlined text-[20px] animate-spin">sync</span><span>Generating...</span>';
        btn.disabled = true;
        
        // Call the API to get PDF
        const response = await fetch(`api/process.php?action=report&job_id=${encodeURIComponent(jobId)}`);
        
        if (response.ok) {
            const blob = await response.blob();
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `customer360_report_${jobId}.pdf`;
            a.click();
            URL.revokeObjectURL(url);
        } else {
            const data = await response.json();
            alert(data.error || 'Failed to generate PDF report');
        }
        
        btn.innerHTML = originalText;
        btn.disabled = false;
    } catch (error) {
        console.error('PDF download error:', error);
        alert('Failed to download PDF. Please try again.');
    }
});
</script>
</body>
</html>
