<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: signin.php');
    exit;
}

$userName = $_SESSION['user_name'] ?? 'User';
$companyName = $_SESSION['company_name'] ?? 'My Business';
$userInitials = strtoupper(substr($userName, 0, 1));
$currentPage = 'analytics';

$analysisResults = $_SESSION['analysis_results'] ?? $_SESSION['demo_results'] ?? null;
$currentJob = $_SESSION['current_job'] ?? null;
$currentUpload = $_SESSION['current_upload'] ?? null;

$uploadedFile = $currentUpload['file_name'] ?? 'ghana_sales_Q3.csv';
$batchId = $currentJob['job_id'] ?? rand(2000, 9999);
$isDemoMode = isset($_SESSION['demo_results']);

$segmentColors = [
    'Champions' => 'yellow', 'Loyal Customers' => 'blue', 'Potential Loyalists' => 'green',
    'Recent Customers' => 'cyan', 'Promising' => 'teal', 'Needs Attention' => 'orange',
    'About to Sleep' => 'purple', 'At Risk' => 'red', 'Cannot Lose' => 'pink',
    'Hibernating' => 'gray', 'Lost' => 'slate',
];

$kpis = ($analysisResults && isset($analysisResults['summary'])) ? [
    'total_revenue' => $analysisResults['summary']['total_revenue'] ?? 125000,
    'active_customers' => $analysisResults['summary']['total_customers'] ?? 1240,
    'avg_order_value' => $analysisResults['summary']['avg_order_value'] ?? 350,
    'churn_rate' => $analysisResults['summary']['churn_rate'] ?? 2.4,
] : ['total_revenue' => 125000, 'active_customers' => 1240, 'avg_order_value' => 350, 'churn_rate' => 2.4];

if ($analysisResults && isset($analysisResults['segments'])) {
    $segments = [];
    foreach ($analysisResults['segments'] as $seg) {
        $segmentName = $seg['segment_name'] ?? $seg['name'] ?? 'Unknown';
        $segments[] = ['name' => $segmentName, 'pct' => round($seg['percentage'] ?? $seg['pct'] ?? 0), 'count' => $seg['customer_count'] ?? $seg['count'] ?? 0, 'description' => $seg['description'] ?? '', 'color' => $segmentColors[$segmentName] ?? 'gray'];
    }
} else {
    $segments = [
        ['name' => 'Champions', 'pct' => 40, 'count' => 496, 'description' => 'High spend, frequent', 'color' => 'yellow'],
        ['name' => 'Loyal Customers', 'pct' => 25, 'count' => 310, 'description' => 'Steady purchases', 'color' => 'blue'],
        ['name' => 'At Risk', 'pct' => 15, 'count' => 186, 'description' => 'Need attention', 'color' => 'red'],
        ['name' => 'Potential Loyalists', 'pct' => 12, 'count' => 149, 'description' => 'New promising', 'color' => 'green'],
        ['name' => 'Hibernating', 'pct' => 8, 'count' => 99, 'description' => 'Inactive', 'color' => 'gray'],
    ];
}

$recent = ($analysisResults && isset($analysisResults['recent_customers'])) ? array_map(function($c) {
    return ['name' => $c['customer_name'] ?? $c['name'] ?? 'Customer', 'email' => $c['email'] ?? 'N/A', 'segment' => $c['segment'] ?? 'Unknown', 'last_purchase' => $c['last_purchase_date'] ?? $c['last_purchase'] ?? 'N/A', 'status' => ($c['recency_days'] ?? 30) < 30 ? 'Active' : 'Inactive', 'spend' => $c['total_spend'] ?? $c['spend'] ?? 0];
}, array_slice($analysisResults['recent_customers'], 0, 5)) : [
    ['name'=>'Abena Boakye','email'=>'abena@example.com','segment'=>'Champions','last_purchase'=>'Oct 24, 2023','status'=>'Active','spend'=>2450],
    ['name'=>'Kwesi Mensah','email'=>'kwesi.m@example.com','segment'=>'Loyal Customers','last_purchase'=>'Oct 22, 2023','status'=>'Active','spend'=>850],
    ['name'=>'Yaw Addo','email'=>'yaw.addo@example.com','segment'=>'At Risk','last_purchase'=>'Aug 15, 2023','status'=>'Inactive','spend'=>1200],
];

function ghc($n) { return 'GH₵ ' . number_format($n, 2); }
function getSegmentBgClass($c) {
    $m = ['yellow'=>'bg-yellow-100 text-yellow-800','blue'=>'bg-blue-100 text-blue-800','green'=>'bg-green-100 text-green-800','red'=>'bg-red-100 text-red-800','orange'=>'bg-orange-100 text-orange-800','purple'=>'bg-purple-100 text-purple-800','gray'=>'bg-gray-100 text-gray-800'];
    return $m[$c] ?? $m['gray'];
}
function getSegmentBarClass($c) {
    $m = ['yellow'=>'bg-yellow-400','blue'=>'bg-blue-500','green'=>'bg-green-500','red'=>'bg-red-500','orange'=>'bg-orange-500','purple'=>'bg-purple-500','gray'=>'bg-gray-400'];
    return $m[$c] ?? $m['gray'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Analytics - Customer 360</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script>
        tailwind.config = {
            theme: { extend: { colors: { "primary": "#0b203c", "primary-hover": "#153055", "background-light": "#f6f7f8", "accent": "#e8b031" }, fontFamily: { "display": ["Inter", "sans-serif"] } } }
        }
    </script>
    <style>body { font-family: 'Inter', sans-serif; } .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }</style>
</head>
<body class="font-display bg-background-light text-slate-900 antialiased">
    <div class="flex h-screen w-full overflow-hidden">
        
        <!-- Sidebar -->
        <aside class="flex w-72 flex-col bg-primary text-white h-full border-r border-slate-800 hidden md:flex">
            <div class="flex h-20 items-center gap-3 px-6 border-b border-slate-700/50">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-white/10 text-white">
                    <span class="material-symbols-outlined">analytics</span>
                </div>
                <div>
                    <h1 class="text-lg font-bold leading-tight tracking-tight">Customer 360</h1>
                    <p class="text-slate-400 text-xs font-normal">SME Intelligence</p>
                </div>
            </div>
            
            <nav class="flex-1 overflow-y-auto px-4 py-6 space-y-2">
                <a class="flex items-center gap-3 rounded-lg <?php echo $currentPage === 'dashboard' ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white'; ?> px-4 py-3 text-sm font-medium transition-colors" href="dashboard.php">
                    <span class="material-symbols-outlined">dashboard</span>Dashboard
                </a>
                <a class="flex items-center gap-3 rounded-lg <?php echo $currentPage === 'upload' ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white'; ?> px-4 py-3 text-sm font-medium transition-colors" href="upload.php">
                    <span class="material-symbols-outlined">upload_file</span>Upload Data
                </a>
                <a class="flex items-center gap-3 rounded-lg <?php echo $currentPage === 'analytics' ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white'; ?> px-4 py-3 text-sm font-medium transition-colors" href="analytics.php">
                    <span class="material-symbols-outlined">analytics</span>Analytics
                </a>
                <a class="flex items-center gap-3 rounded-lg <?php echo $currentPage === 'reports' ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white'; ?> px-4 py-3 text-sm font-medium transition-colors" href="reports.php">
                    <span class="material-symbols-outlined">bar_chart</span>Reports
                </a>
                <div class="my-4 border-t border-slate-700/50"></div>
                <a class="flex items-center gap-3 rounded-lg <?php echo $currentPage === 'help' ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white'; ?> px-4 py-3 text-sm font-medium transition-colors" href="help.php">
                    <span class="material-symbols-outlined">help</span>Help & Support
                </a>
            </nav>
            
            <div class="border-t border-slate-700/50 p-4">
                <div class="flex items-center gap-3 rounded-lg p-2 hover:bg-white/5 cursor-pointer transition-colors group" onclick="toggleUserMenu()">
                    <div class="h-10 w-10 rounded-full bg-slate-600 flex items-center justify-center text-white font-semibold text-sm"><?php echo $userInitials; ?></div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-white truncate"><?php echo htmlspecialchars($userName); ?></p>
                        <p class="text-xs text-slate-400 truncate"><?php echo htmlspecialchars($companyName); ?></p>
                    </div>
                    <span class="material-symbols-outlined text-slate-400 text-[20px]">expand_more</span>
                </div>
                <div id="userMenu" class="hidden mt-2 py-2 bg-slate-800 rounded-lg shadow-lg">
                    <a href="help.php" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-300 hover:bg-slate-700 hover:text-white"><span class="material-symbols-outlined text-[18px]">person</span>My Profile</a>
                    <a href="api/auth.php?action=logout" class="flex items-center gap-2 px-4 py-2 text-sm text-red-400 hover:bg-slate-700"><span class="material-symbols-outlined text-[18px]">logout</span>Sign Out</a>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex flex-1 flex-col overflow-y-auto bg-background-light relative">
            <header class="sticky top-0 z-10 flex h-20 items-center justify-between border-b border-slate-200 bg-white px-6 sm:px-10">
                <div class="flex items-center gap-4">
                    <button class="md:hidden text-slate-500 hover:text-slate-700" onclick="toggleMobileMenu()"><span class="material-symbols-outlined">menu</span></button>
                    <div class="hidden sm:flex items-center text-sm text-slate-500">
                        <a class="hover:text-primary transition-colors" href="dashboard.php">Home</a>
                        <span class="mx-2 text-slate-300">/</span>
                        <span class="font-medium text-primary">Analytics</span>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <?php if($isDemoMode): ?>
                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-700">
                        <span class="material-symbols-outlined text-[14px]">science</span>Demo
                    </span>
                    <?php endif; ?>
                    <button class="relative rounded-full p-2 text-slate-500 hover:bg-slate-100 transition-colors">
                        <span class="material-symbols-outlined">notifications</span>
                    </button>
                </div>
            </header>

            <div class="p-6 sm:p-10 max-w-7xl mx-auto w-full">
                <!-- Header -->
                <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8 gap-4">
                    <div>
                        <h1 class="text-3xl font-bold text-primary tracking-tight">Analytics Overview</h1>
                        <p class="text-slate-500 mt-1">Results for batch #<?php echo htmlspecialchars($batchId); ?> — file: <?php echo htmlspecialchars($uploadedFile); ?></p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <button id="exportCsv" class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-primary hover:bg-slate-50 shadow-sm">
                            <span class="material-symbols-outlined text-[20px]">download</span>Export CSV
                        </button>
                        <button id="downloadPdf" class="flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-hover shadow-md">
                            <span class="material-symbols-outlined text-[20px]">picture_as_pdf</span>Download PDF
                        </button>
                    </div>
                </div>

                <!-- KPI Cards -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-8">
                    <div class="flex flex-col rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="mb-2 flex items-center justify-between">
                            <span class="text-sm font-medium text-slate-500">Total Revenue</span>
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-50 text-blue-600"><span class="material-symbols-outlined text-[20px]">payments</span></span>
                        </div>
                        <h3 class="text-2xl font-bold text-primary"><?php echo ghc($kpis['total_revenue']); ?></h3>
                    </div>
                    <div class="flex flex-col rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="mb-2 flex items-center justify-between">
                            <span class="text-sm font-medium text-slate-500">Active Customers</span>
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-purple-50 text-purple-600"><span class="material-symbols-outlined text-[20px]">group</span></span>
                        </div>
                        <h3 class="text-2xl font-bold text-primary"><?php echo number_format($kpis['active_customers']); ?></h3>
                    </div>
                    <div class="flex flex-col rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="mb-2 flex items-center justify-between">
                            <span class="text-sm font-medium text-slate-500">Avg Order Value</span>
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-orange-50 text-orange-600"><span class="material-symbols-outlined text-[20px]">shopping_cart</span></span>
                        </div>
                        <h3 class="text-2xl font-bold text-primary"><?php echo ghc($kpis['avg_order_value']); ?></h3>
                    </div>
                    <div class="flex flex-col rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="mb-2 flex items-center justify-between">
                            <span class="text-sm font-medium text-slate-500">Churn Rate</span>
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-red-50 text-red-600"><span class="material-symbols-outlined text-[20px]">person_remove</span></span>
                        </div>
                        <h3 class="text-2xl font-bold text-primary"><?php echo $kpis['churn_rate']; ?>%</h3>
                    </div>
                </div>

                <!-- Segments -->
                <div class="mb-8">
                    <h2 class="text-xl font-bold text-primary mb-4">Customer Segments</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php foreach($segments as $s): ?>
                        <div class="flex flex-col rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center gap-3">
                                    <div class="rounded-lg p-2 <?php echo getSegmentBgClass($s['color']); ?>">
                                        <span class="material-symbols-outlined">category</span>
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-primary"><?php echo htmlspecialchars($s['name']); ?></h4>
                                        <p class="text-xs text-slate-500"><?php echo htmlspecialchars($s['description']); ?></p>
                                    </div>
                                </div>
                                <span class="font-bold text-primary"><?php echo $s['pct']; ?>%</span>
                            </div>
                            <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full <?php echo getSegmentBarClass($s['color']); ?>" style="width: <?php echo $s['pct']; ?>%"></div>
                            </div>
                            <p class="text-xs text-slate-500 mt-2"><?php echo number_format($s['count']); ?> customers</p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Recent Customers Table -->
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-bold text-primary mb-4">Recent Customers</h2>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="border-b border-slate-200 text-slate-500">
                                <tr>
                                    <th class="px-4 py-3 font-medium">Customer</th>
                                    <th class="px-4 py-3 font-medium">Segment</th>
                                    <th class="px-4 py-3 font-medium">Last Purchase</th>
                                    <th class="px-4 py-3 font-medium">Status</th>
                                    <th class="px-4 py-3 font-medium text-right">Total Spend</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach($recent as $r): ?>
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary"><?php echo strtoupper(substr($r['name'],0,2)); ?></div>
                                            <div>
                                                <p class="font-medium text-primary"><?php echo htmlspecialchars($r['name']); ?></p>
                                                <p class="text-xs text-slate-500"><?php echo htmlspecialchars($r['email']); ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium <?php echo getSegmentBgClass($segmentColors[$r['segment']] ?? 'gray'); ?>"><?php echo htmlspecialchars($r['segment']); ?></span>
                                    </td>
                                    <td class="px-4 py-4 text-slate-500"><?php echo htmlspecialchars($r['last_purchase']); ?></td>
                                    <td class="px-4 py-4">
                                        <div class="flex items-center gap-2">
                                            <div class="h-2 w-2 rounded-full <?php echo ($r['status']=='Active') ? 'bg-green-500' : 'bg-gray-300'; ?>"></div>
                                            <span><?php echo htmlspecialchars($r['status']); ?></span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-right font-medium text-primary"><?php echo ghc($r['spend']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <div id="mobileMenuOverlay" class="fixed inset-0 bg-black/50 z-40 hidden md:hidden" onclick="toggleMobileMenu()"></div>
    <aside id="mobileSidebar" class="fixed top-0 left-0 w-72 h-full bg-primary text-white z-50 transform -translate-x-full transition-transform md:hidden">
        <div class="flex h-20 items-center gap-3 px-6 border-b border-slate-700/50">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-white/10"><span class="material-symbols-outlined">analytics</span></div>
            <div class="flex-1"><h1 class="text-lg font-bold">Customer 360</h1><p class="text-slate-400 text-xs">SME Intelligence</p></div>
            <button class="text-slate-400 hover:text-white" onclick="toggleMobileMenu()"><span class="material-symbols-outlined">close</span></button>
        </div>
        <nav class="px-4 py-6 space-y-2">
            <a class="flex items-center gap-3 rounded-lg text-slate-300 hover:bg-white/5 px-4 py-3 text-sm font-medium" href="dashboard.php"><span class="material-symbols-outlined">dashboard</span>Dashboard</a>
            <a class="flex items-center gap-3 rounded-lg text-slate-300 hover:bg-white/5 px-4 py-3 text-sm font-medium" href="upload.php"><span class="material-symbols-outlined">upload_file</span>Upload Data</a>
            <a class="flex items-center gap-3 rounded-lg bg-white/10 text-white px-4 py-3 text-sm font-medium" href="analytics.php"><span class="material-symbols-outlined">analytics</span>Analytics</a>
            <a class="flex items-center gap-3 rounded-lg text-slate-300 hover:bg-white/5 px-4 py-3 text-sm font-medium" href="reports.php"><span class="material-symbols-outlined">bar_chart</span>Reports</a>
            <div class="my-4 border-t border-slate-700/50"></div>
            <a class="flex items-center gap-3 rounded-lg text-slate-300 hover:bg-white/5 px-4 py-3 text-sm font-medium" href="help.php"><span class="material-symbols-outlined">help</span>Help & Support</a>
            <a class="flex items-center gap-3 rounded-lg text-red-400 hover:bg-white/5 px-4 py-3 text-sm font-medium" href="api/auth.php?action=logout"><span class="material-symbols-outlined">logout</span>Sign Out</a>
        </nav>
    </aside>
    
    <script>
        function toggleUserMenu() { document.getElementById('userMenu').classList.toggle('hidden'); }
        function toggleMobileMenu() {
            document.getElementById('mobileSidebar').classList.toggle('-translate-x-full');
            document.getElementById('mobileMenuOverlay').classList.toggle('hidden');
        }
        
        document.getElementById('exportCsv').addEventListener('click', () => {
            const rows = [['Customer Name','Email','Segment','Last Purchase','Status','Total Spend']];
            <?php foreach($recent as $r): ?>
            rows.push(["<?php echo addslashes($r['name']); ?>","<?php echo addslashes($r['email']); ?>","<?php echo addslashes($r['segment']); ?>","<?php echo addslashes($r['last_purchase']); ?>","<?php echo addslashes($r['status']); ?>","<?php echo number_format($r['spend'],2); ?>"]);
            <?php endforeach; ?>
            const csv = rows.map(r => r.map(c => `"${String(c).replace(/"/g,'""')}"`).join(',')).join('\n');
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = 'customer360_results.csv'; a.click();
        });
        
        document.getElementById('downloadPdf').addEventListener('click', () => {
            alert('PDF download is available when using the real analysis backend.\n\nIn demo mode, you can export to CSV instead.');
        });
    </script>
</body>
</html>
