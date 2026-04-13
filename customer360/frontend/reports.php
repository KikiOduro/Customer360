<?php
/**
 * Customer 360 - Reports History Page
 */
require_once __DIR__ . '/api/config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: signin.php');
    exit;
}

$userName = trim((string) ($_SESSION['user_name'] ?? ''));
$companyName = trim((string) ($_SESSION['company_name'] ?? ''));
$userEmail = trim((string) ($_SESSION['user_email'] ?? ''));
$profileLabel = $userName !== '' ? $userName : $userEmail;
$profileSubLabel = $companyName !== '' ? $companyName : 'Signed in account';
$userInitials = strtoupper(substr($profileLabel, 0, 1));
$currentPage = 'reports';
$authToken = $_SESSION['auth_token'] ?? null;

$perPage = 5;
$currentPageNum = max(1, intval($_GET['page'] ?? 1));
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$searchQuery = trim((string) ($_GET['search'] ?? ''));

$reports = [];
$totalReports = 0;
$totalPages = 1;
$loadError = null;

if ($authToken) {
    // Report history is backed by the jobs API so each row links to a real completed
    // or failed analysis record owned by the signed-in user.
    $result = apiRequest('/jobs/', 'GET', null, $authToken);
    if ($result['success'] && is_array($result['data'])) {
        $allReports = array_map(function ($job) {
            return [
                'id' => $job['job_id'],
                // Show only the original filename, never the server-side upload path.
                'filename' => basename((string) ($job['original_filename'] ?? 'Uploaded file')),
                'date_generated' => $job['created_at'] ?? null,
                'customer_count' => $job['num_customers'] ?? null,
                'status' => $job['status'] ?? 'pending',
            ];
        }, $result['data']);

        if ($statusFilter !== '') {
            // Filtering is done in PHP because the page already has the user's job list.
            $allReports = array_values(array_filter($allReports, fn($report) => $report['status'] === $statusFilter));
        }

        if ($searchQuery !== '') {
            $searchLower = strtolower($searchQuery);
            $allReports = array_values(array_filter($allReports, function ($report) use ($searchLower) {
                return strpos(strtolower($report['filename']), $searchLower) !== false;
            }));
        }

        $totalReports = count($allReports);
        $totalPages = max(1, (int) ceil($totalReports / $perPage));
        $currentPageNum = min($currentPageNum, $totalPages);
        $reports = array_slice($allReports, ($currentPageNum - 1) * $perPage, $perPage);
    } else {
        $loadError = $result['data']['detail'] ?? $result['error'] ?? 'Could not load reports from the backend.';
    }
} else {
    $loadError = 'No authenticated backend session is available.';
}

function formatDate(?string $value): string {
    if (!$value) {
        return '-';
    }
    return date('M d, Y', strtotime($value));
}

function getStatusBadge(string $status): string {
    switch ($status) {
        case 'completed':
            return '<span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-800"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Completed</span>';
        case 'processing':
            return '<span class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800"><span class="animate-spin material-symbols-outlined text-[12px]">progress_activity</span>Processing</span>';
        case 'pending':
            return '<span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800"><span class="material-symbols-outlined text-[14px]">schedule</span>Pending</span>';
        case 'failed':
            return '<span class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800"><span class="material-symbols-outlined text-[14px]">error</span>Failed</span>';
        case 'cancelled':
            return '<span class="inline-flex items-center gap-1 rounded-full bg-slate-200 px-2.5 py-0.5 text-xs font-medium text-slate-800"><span class="material-symbols-outlined text-[14px]">block</span>Cancelled</span>';
        default:
            return '<span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800">Unknown</span>';
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
                <a class="flex items-center gap-3 rounded-lg text-slate-300 hover:bg-white/5 hover:text-white px-4 py-3 text-sm font-medium transition-colors" href="dashboard.php"><span class="material-symbols-outlined">dashboard</span>Dashboard</a>
                <a class="flex items-center gap-3 rounded-lg text-slate-300 hover:bg-white/5 hover:text-white px-4 py-3 text-sm font-medium transition-colors" href="upload.php"><span class="material-symbols-outlined">upload_file</span>Upload Data</a>
                <a class="flex items-center gap-3 rounded-lg text-slate-300 hover:bg-white/5 hover:text-white px-4 py-3 text-sm font-medium transition-colors" href="analytics.php"><span class="material-symbols-outlined">analytics</span>Analytics</a>
                <a class="flex items-center gap-3 rounded-lg bg-white/10 text-white px-4 py-3 text-sm font-medium transition-colors" href="reports.php"><span class="material-symbols-outlined">bar_chart</span>Reports</a>
                <div class="my-4 border-t border-slate-700/50"></div>
                <a class="flex items-center gap-3 rounded-lg text-slate-300 hover:bg-white/5 hover:text-white px-4 py-3 text-sm font-medium transition-colors" href="help.php"><span class="material-symbols-outlined">help</span>Help & Support</a>
            </nav>
            <div class="border-t border-slate-700/50 p-4">
                <div class="flex items-center gap-3 rounded-lg p-2 hover:bg-white/5 cursor-pointer transition-colors group" onclick="toggleUserMenu()">
                    <div class="h-10 w-10 rounded-full bg-slate-600 flex items-center justify-center text-white font-semibold text-sm"><?php echo htmlspecialchars($userInitials); ?></div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-white truncate"><?php echo htmlspecialchars($profileLabel); ?></p>
                        <p class="text-xs text-slate-400 truncate"><?php echo htmlspecialchars($profileSubLabel); ?></p>
                    </div>
                    <span class="material-symbols-outlined text-slate-400 text-[20px]">expand_more</span>
                </div>
                <div id="userMenu" class="hidden mt-2 py-2 bg-slate-800 rounded-lg shadow-lg">
                    <a href="help.php" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-300 hover:bg-slate-700 hover:text-white"><span class="material-symbols-outlined text-[18px]">person</span>My Profile</a>
                    <a href="api/auth.php?action=logout" class="flex items-center gap-2 px-4 py-2 text-sm text-red-400 hover:bg-slate-700"><span class="material-symbols-outlined text-[18px]">logout</span>Sign Out</a>
                </div>
            </div>
        </aside>

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
                <button class="relative rounded-full p-2 text-slate-500 hover:bg-slate-100 transition-colors">
                    <span class="material-symbols-outlined">notifications</span>
                </button>
            </header>

            <div class="p-6 sm:p-10 max-w-6xl mx-auto w-full">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8 gap-4">
                    <div>
                        <h1 class="text-3xl font-bold text-primary tracking-tight">Reports History</h1>
                        <p class="text-slate-500 mt-1">View and manage your generated customer analysis reports.</p>
                    </div>
                    <a href="upload.php" class="inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-medium text-white hover:bg-primary-hover shadow-md">
                        <span class="material-symbols-outlined text-[20px]">add</span>New Analysis
                    </a>
                </div>

                <div class="mb-6 grid gap-4 rounded-xl bg-white p-4 shadow-sm border border-slate-200 md:grid-cols-12 md:items-end">
                    <div class="col-span-12 md:col-span-4">
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Search Filename</label>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3"><span class="material-symbols-outlined text-slate-400">search</span></div>
                            <input id="searchInput" class="block w-full rounded-lg border-slate-200 bg-slate-50 py-2.5 pl-10 pr-3 text-sm text-slate-800 placeholder-slate-400 focus:border-primary focus:bg-white focus:ring-primary transition-colors" placeholder="Search uploaded filenames" type="text" value="<?php echo htmlspecialchars($searchQuery); ?>"/>
                        </div>
                    </div>
                    <div class="col-span-12 md:col-span-4"></div>
                    <div class="col-span-12 md:col-span-3">
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Status</label>
                        <select id="statusFilter" class="block w-full rounded-lg border-slate-200 bg-slate-50 py-2.5 px-3 text-sm text-slate-800 focus:border-primary focus:bg-white focus:ring-primary transition-colors">
                            <option value="">All Statuses</option>
                            <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="processing" <?php echo $statusFilter === 'processing' ? 'selected' : ''; ?>>Processing</option>
                            <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="failed" <?php echo $statusFilter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                        </select>
                    </div>
                </div>

                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <?php if ($loadError): ?>
                    <div class="border-b border-slate-200 bg-red-50 px-6 py-4 text-sm text-red-700"><?php echo htmlspecialchars($loadError); ?></div>
                    <?php endif; ?>

                    <?php if (empty($reports)): ?>
                    <div class="px-6 py-14 text-center">
                        <span class="material-symbols-outlined text-5xl text-slate-300">description</span>
                        <h2 class="mt-4 text-lg font-semibold text-primary">No analysis reports yet</h2>
                        <p class="mt-2 text-sm text-slate-500">Analysis jobs will appear here once you upload customer data and start processing.</p>
                        <a href="upload.php" class="mt-6 inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-medium text-white hover:bg-primary-hover shadow-md">
                            <span class="material-symbols-outlined text-[20px]">upload_file</span>Upload Data
                        </a>
                    </div>
                    <?php else: ?>
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
                                <tr class="hover:bg-slate-50" data-id="<?php echo htmlspecialchars($report['id']); ?>">
                                    <td class="whitespace-nowrap px-6 py-4 font-medium text-primary"><?php echo htmlspecialchars(formatDate($report['date_generated'])); ?></td>
                                    <td class="px-6 py-4 font-medium text-slate-800">
                                        <div class="flex items-center gap-2">
                                            <span class="material-symbols-outlined text-slate-400 text-[18px]">description</span>
                                            <?php if ($report['status'] === 'completed'): ?>
                                            <a href="analytics.php?job_id=<?php echo urlencode($report['id']); ?>" class="text-primary hover:underline">
                                                <?php echo htmlspecialchars($report['filename']); ?>
                                            </a>
                                            <?php else: ?>
                                            <?php echo htmlspecialchars($report['filename']); ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 tabular-nums"><?php echo $report['customer_count'] !== null ? number_format((int) $report['customer_count']) : '-'; ?></td>
                                    <td class="whitespace-nowrap px-6 py-4"><?php echo getStatusBadge($report['status']); ?></td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <?php if ($report['status'] === 'completed'): ?>
                                            <a href="analytics.php?job_id=<?php echo urlencode($report['id']); ?>" class="rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-primary" title="View Report"><span class="material-symbols-outlined text-[20px]">visibility</span></a>
                                            <?php else: ?>
                                            <a href="processing.php?job_id=<?php echo urlencode($report['id']); ?>" class="rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-primary" title="Track Job"><span class="material-symbols-outlined text-[20px]">schedule</span></a>
                                            <?php endif; ?>
                                            <button class="rounded p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600" title="Delete" onclick="deleteReport('<?php echo addslashes($report['id']); ?>')"><span class="material-symbols-outlined text-[20px]">delete</span></button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="flex items-center justify-between border-t border-slate-200 bg-white px-6 py-4">
                        <p class="text-sm text-slate-700">Showing <span class="font-medium"><?php echo $totalReports === 0 ? 0 : (($currentPageNum - 1) * $perPage) + 1; ?></span> to <span class="font-medium"><?php echo (($currentPageNum - 1) * $perPage) + count($reports); ?></span> of <span class="font-medium"><?php echo $totalReports; ?></span> results</p>
                        <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm">
                            <a href="?page=<?php echo max(1, $currentPageNum - 1); ?>&search=<?php echo urlencode($searchQuery); ?>&status=<?php echo urlencode($statusFilter); ?>" class="relative inline-flex items-center rounded-l-md px-2 py-2 text-slate-400 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 <?php echo $currentPageNum <= 1 ? 'pointer-events-none opacity-50' : ''; ?>"><span class="material-symbols-outlined text-[20px]">chevron_left</span></a>
                            <?php for ($i = 1; $i <= min(3, $totalPages); $i++): ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($searchQuery); ?>&status=<?php echo urlencode($statusFilter); ?>" class="relative inline-flex items-center px-4 py-2 text-sm font-semibold ring-1 ring-inset ring-slate-300 <?php echo $i === $currentPageNum ? 'z-10 bg-primary text-white' : 'text-slate-900 hover:bg-slate-50'; ?>"><?php echo $i; ?></a>
                            <?php endfor; ?>
                            <a href="?page=<?php echo min($totalPages, $currentPageNum + 1); ?>&search=<?php echo urlencode($searchQuery); ?>&status=<?php echo urlencode($statusFilter); ?>" class="relative inline-flex items-center rounded-r-md px-2 py-2 text-slate-400 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 <?php echo $currentPageNum >= $totalPages ? 'pointer-events-none opacity-50' : ''; ?>"><span class="material-symbols-outlined text-[20px]">chevron_right</span></a>
                        </nav>
                    </div>
                    <?php endif; ?>
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

    <div id="reportsStatusToast" class="pointer-events-none fixed bottom-6 right-6 z-50 hidden max-w-sm rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-xl">
        <div class="flex items-start gap-3">
            <div id="reportsStatusIcon" class="mt-0.5 flex h-9 w-9 items-center justify-center rounded-full bg-slate-100 text-slate-600">
                <span class="material-symbols-outlined">info</span>
            </div>
            <div class="min-w-0 flex-1">
                <p id="reportsStatusTitle" class="text-sm font-bold text-primary">Status</p>
                <p id="reportsStatusMessage" class="mt-1 text-sm text-slate-500">Update complete.</p>
            </div>
        </div>
    </div>

    <script>
        let reportsToastTimer = null;
        function toggleUserMenu() { document.getElementById('userMenu').classList.toggle('hidden'); }
        function toggleMobileMenu() {
            document.getElementById('mobileSidebar').classList.toggle('-translate-x-full');
            document.getElementById('mobileMenuOverlay').classList.toggle('hidden');
        }

        function showReportsToast(type, title, message) {
            const toast = document.getElementById('reportsStatusToast');
            const icon = document.getElementById('reportsStatusIcon');
            const titleEl = document.getElementById('reportsStatusTitle');
            const messageEl = document.getElementById('reportsStatusMessage');
            const styles = {
                info: ['bg-blue-100 text-blue-700', 'info'],
                success: ['bg-green-100 text-green-700', 'check_circle'],
                error: ['bg-red-100 text-red-700', 'error'],
                loading: ['bg-slate-100 text-slate-700', 'progress_activity']
            };
            const [classes, iconName] = styles[type] || styles.info;
            icon.className = `mt-0.5 flex h-9 w-9 items-center justify-center rounded-full ${classes}`;
            icon.innerHTML = `<span class="material-symbols-outlined ${type === 'loading' ? 'animate-spin' : ''}">${iconName}</span>`;
            titleEl.textContent = title;
            messageEl.textContent = message;
            toast.classList.remove('hidden');
            clearTimeout(reportsToastTimer);
            if (type !== 'loading') {
                reportsToastTimer = setTimeout(() => toast.classList.add('hidden'), 3500);
            }
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

        function deleteReport(id) {
            if (!confirm('Are you sure you want to delete this report?')) return;
            showReportsToast('loading', 'Deleting report', 'We are removing this report and refreshing the page.');

            fetch(`api/process.php?action=delete&job_id=${encodeURIComponent(id)}`)
                .then(response => response.json())
                .then(data => {
                    if (!data.success) throw new Error(data.error || 'Delete failed');
                    showReportsToast('success', 'Report deleted', 'The selected report was removed successfully.');
                    window.location.reload();
                })
                .catch(error => showReportsToast('error', 'Delete failed', error.message));
        }
    </script>
</body>
</html>
