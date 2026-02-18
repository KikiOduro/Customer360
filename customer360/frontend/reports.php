<?php
/**
 * Customer 360 - Reports History Page
 */
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: signin.php');
    exit;
}

$userName = $_SESSION['user_name'] ?? 'User';
$companyName = $_SESSION['company_name'] ?? 'My Business';
$userInitials = strtoupper(substr($userName, 0, 1));
$currentPage = 'reports';
$isDemoMode = isset($_SESSION['demo_mode']) && $_SESSION['demo_mode'];

// Pagination settings
$perPage = 5;
$currentPageNum = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$statusFilter = $_GET['status'] ?? '';
$searchQuery = $_GET['search'] ?? '';

function getDemoReports($page, $perPage, $statusFilter, $search) {
    $allReports = [
        ['id' => 'job_001', 'filename' => 'Q3_Sales_Analysis_Final.csv', 'date_generated' => '2026-02-05', 'customer_count' => 1240, 'status' => 'completed', 'segments_count' => 5],
        ['id' => 'job_002', 'filename' => 'Accra_Customer_Segment_v2.csv', 'date_generated' => '2026-02-03', 'customer_count' => 856, 'status' => 'completed', 'segments_count' => 4],
        ['id' => 'job_003', 'filename' => 'Kumasi_Branch_Leads.csv', 'date_generated' => '2026-02-01', 'customer_count' => null, 'status' => 'failed', 'error_message' => 'Invalid date format in column 3'],
        ['id' => 'job_004', 'filename' => 'Churn_Prediction_Feb.csv', 'date_generated' => '2026-01-28', 'customer_count' => 2100, 'status' => 'processing', 'progress' => 65],
        ['id' => 'job_005', 'filename' => 'Jan_Performance_Review.csv', 'date_generated' => '2026-01-20', 'customer_count' => 4320, 'status' => 'completed', 'segments_count' => 6],
        ['id' => 'job_006', 'filename' => 'December_Sales_Report.csv', 'date_generated' => '2025-12-28', 'customer_count' => 2890, 'status' => 'completed', 'segments_count' => 5],
        ['id' => 'job_007', 'filename' => 'Takoradi_Customers.csv', 'date_generated' => '2025-12-20', 'customer_count' => 567, 'status' => 'completed', 'segments_count' => 4],
    ];
    
    if ($statusFilter) {
        $allReports = array_filter($allReports, fn($r) => $r['status'] === $statusFilter);
    }
    if ($search) {
        $searchLower = strtolower($search);
        $allReports = array_filter($allReports, fn($r) => strpos(strtolower($r['filename']), $searchLower) !== false);
    }
    
    $total = count($allReports);
    $offset = ($page - 1) * $perPage;
    return ['reports' => array_slice(array_values($allReports), $offset, $perPage), 'total' => $total];
}

$result = getDemoReports($currentPageNum, $perPage, $statusFilter, $searchQuery);
$reports = $result['reports'];
$totalReports = $result['total'];
$totalPages = ceil($totalReports / $perPage);

function formatDate($d) { return date('M d, Y', strtotime($d)); }
function getStatusBadge($s) {
    switch($s) {
        case 'completed': return '<span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-800"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Completed</span>';
        case 'processing': return '<span class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800"><span class="animate-spin material-symbols-outlined text-[12px]">progress_activity</span>Processing</span>';
        case 'failed': return '<span class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800"><span class="material-symbols-outlined text-[14px]">error</span>Failed</span>';
        default: return '<span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800">Unknown</span>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Reports - Customer 360</title>
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
                        <span class="font-medium text-primary">Reports</span>
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

            <div class="p-6 sm:p-10 max-w-6xl mx-auto w-full">
                <!-- Header -->
                <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8 gap-4">
                    <div>
                        <h1 class="text-3xl font-bold text-primary tracking-tight">Reports History</h1>
                        <p class="text-slate-500 mt-1">View and manage your generated customer analysis reports.</p>
                    </div>
                    <a href="upload.php" class="inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-medium text-white hover:bg-primary-hover shadow-md">
                        <span class="material-symbols-outlined text-[20px]">add</span>New Analysis
                    </a>
                </div>

                <!-- Filters -->
                <div class="mb-6 grid gap-4 rounded-xl bg-white p-4 shadow-sm border border-slate-200 md:grid-cols-12 md:items-end">
                    <div class="col-span-12 md:col-span-4">
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Search Filename</label>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3"><span class="material-symbols-outlined text-slate-400">search</span></div>
                            <input id="searchInput" class="block w-full rounded-lg border-slate-200 bg-slate-50 py-2.5 pl-10 pr-3 text-sm text-slate-800 placeholder-slate-400 focus:border-primary focus:bg-white focus:ring-primary transition-colors" placeholder="e.g., Q3 Sales Analysis" type="text" value="<?php echo htmlspecialchars($searchQuery); ?>"/>
                        </div>
                    </div>
                    <div class="col-span-12 md:col-span-4">
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Date Range</label>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3"><span class="material-symbols-outlined text-slate-400">calendar_today</span></div>
                            <input id="dateRange" class="block w-full rounded-lg border-slate-200 bg-slate-50 py-2.5 pl-10 pr-3 text-sm text-slate-800 placeholder-slate-400 focus:border-primary focus:bg-white focus:ring-primary transition-colors" placeholder="Select dates" type="date"/>
                        </div>
                    </div>
                    <div class="col-span-12 md:col-span-3">
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Status</label>
                        <select id="statusFilter" class="block w-full rounded-lg border-slate-200 bg-slate-50 py-2.5 px-3 text-sm text-slate-800 focus:border-primary focus:bg-white focus:ring-primary transition-colors">
                            <option value="">All Statuses</option>
                            <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="processing" <?php echo $statusFilter === 'processing' ? 'selected' : ''; ?>>Processing</option>
                            <option value="failed" <?php echo $statusFilter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                        </select>
                    </div>
                    <div class="col-span-12 flex justify-end md:col-span-1">
                        <button id="exportBtn" class="flex h-[42px] w-full items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 md:w-auto md:px-3 transition-colors" title="Export List">
                            <span class="material-symbols-outlined">download</span>
                        </button>
                    </div>
                </div>

                <!-- Table -->
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-slate-600">
                            <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-500">
                                <tr>
                                    <th class="px-6 py-4 font-semibold tracking-wider">Date Generated</th>
                                    <th class="px-6 py-4 font-semibold tracking-wider">Filename</th>
                                    <th class="px-6 py-4 font-semibold tracking-wider">Customer Count</th>
                                    <th class="px-6 py-4 font-semibold tracking-wider">Status</th>
                                    <th class="px-6 py-4 text-right font-semibold tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="reportsTableBody" class="divide-y divide-slate-100">
                                <?php foreach ($reports as $report): ?>
                                <tr class="hover:bg-slate-50" data-id="<?php echo $report['id']; ?>">
                                    <td class="whitespace-nowrap px-6 py-4 font-medium text-primary"><?php echo formatDate($report['date_generated']); ?></td>
                                    <td class="px-6 py-4 font-medium text-slate-800">
                                        <div class="flex items-center gap-2">
                                            <span class="material-symbols-outlined text-slate-400 text-[18px]">description</span>
                                            <?php echo htmlspecialchars($report['filename']); ?>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 tabular-nums"><?php echo $report['customer_count'] ? number_format($report['customer_count']) : '-'; ?></td>
                                    <td class="whitespace-nowrap px-6 py-4"><?php echo getStatusBadge($report['status']); ?></td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <?php if ($report['status'] === 'completed'): ?>
                                            <a href="analytics.php?job_id=<?php echo $report['id']; ?>" class="rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-primary" title="View Report"><span class="material-symbols-outlined text-[20px]">visibility</span></a>
                                            <button class="rounded p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600" title="Delete" onclick="deleteReport('<?php echo $report['id']; ?>')"><span class="material-symbols-outlined text-[20px]">delete</span></button>
                                            <?php elseif ($report['status'] === 'failed'): ?>
                                            <button class="rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-primary" title="Retry"><span class="material-symbols-outlined text-[20px]">refresh</span></button>
                                            <button class="rounded p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600" title="Delete"><span class="material-symbols-outlined text-[20px]">delete</span></button>
                                            <?php elseif ($report['status'] === 'processing'): ?>
                                            <button class="rounded p-1.5 text-slate-300 cursor-not-allowed" disabled title="View Report"><span class="material-symbols-outlined text-[20px]">visibility</span></button>
                                            <button class="rounded p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600" title="Cancel"><span class="material-symbols-outlined text-[20px]">close</span></button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="flex items-center justify-between border-t border-slate-200 bg-white px-6 py-4">
                        <p class="text-sm text-slate-700">Showing <span class="font-medium">1</span> to <span class="font-medium"><?php echo count($reports); ?></span> of <span class="font-medium"><?php echo $totalReports; ?></span> results</p>
                        <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm">
                            <a href="?page=<?php echo max(1, $currentPageNum - 1); ?>" class="relative inline-flex items-center rounded-l-md px-2 py-2 text-slate-400 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 <?php echo $currentPageNum <= 1 ? 'pointer-events-none opacity-50' : ''; ?>">
                                <span class="material-symbols-outlined text-[20px]">chevron_left</span>
                            </a>
                            <?php for ($i = 1; $i <= min(3, $totalPages); $i++): ?>
                            <a href="?page=<?php echo $i; ?>" class="relative inline-flex items-center px-4 py-2 text-sm font-semibold ring-1 ring-inset ring-slate-300 <?php echo $i === $currentPageNum ? 'z-10 bg-primary text-white' : 'text-slate-900 hover:bg-slate-50'; ?>"><?php echo $i; ?></a>
                            <?php endfor; ?>
                            <a href="?page=<?php echo min($totalPages, $currentPageNum + 1); ?>" class="relative inline-flex items-center rounded-r-md px-2 py-2 text-slate-400 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 <?php echo $currentPageNum >= $totalPages ? 'pointer-events-none opacity-50' : ''; ?>">
                                <span class="material-symbols-outlined text-[20px]">chevron_right</span>
                            </a>
                        </nav>
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
            <a class="flex items-center gap-3 rounded-lg text-slate-300 hover:bg-white/5 px-4 py-3 text-sm font-medium" href="analytics.php"><span class="material-symbols-outlined">analytics</span>Analytics</a>
            <a class="flex items-center gap-3 rounded-lg bg-white/10 text-white px-4 py-3 text-sm font-medium" href="reports.php"><span class="material-symbols-outlined">bar_chart</span>Reports</a>
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
        
        let filterTimeout = null;
        function applyFilters() {
            const params = new URLSearchParams();
            const search = document.getElementById('searchInput').value.trim();
            const status = document.getElementById('statusFilter').value;
            if (search) params.set('search', search);
            if (status) params.set('status', status);
            window.location.href = 'reports.php' + (params.toString() ? '?' + params.toString() : '');
        }
        
        document.getElementById('searchInput')?.addEventListener('input', () => {
            clearTimeout(filterTimeout);
            filterTimeout = setTimeout(applyFilters, 500);
        });
        document.getElementById('statusFilter')?.addEventListener('change', applyFilters);
        
        document.getElementById('exportBtn')?.addEventListener('click', () => {
            const rows = [['Date Generated', 'Filename', 'Customer Count', 'Status']];
            document.querySelectorAll('#reportsTableBody tr').forEach(row => {
                const cells = row.querySelectorAll('td');
                rows.push([cells[0].textContent.trim(), cells[1].textContent.trim(), cells[2].textContent.trim(), cells[3].textContent.trim()]);
            });
            const csv = rows.map(r => r.map(c => `"${String(c).replace(/"/g, '""')}"`).join(',')).join('\n');
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'reports_history.csv';
            a.click();
        });
        
        function deleteReport(id) {
            if (confirm('Are you sure you want to delete this report?')) {
                const row = document.querySelector(`tr[data-id="${id}"]`);
                if (row) row.remove();
            }
        }
    </script>
</body>
</html>
