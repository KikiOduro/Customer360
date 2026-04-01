<?php
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
$currentPage = 'analytics';
$authToken = $_SESSION['auth_token'] ?? null;

$jobId = $_GET['job_id'] ?? ($_SESSION['current_job']['job_id'] ?? null);
$analysisResults = null;
$loadError = null;

if ($jobId && $authToken) {
    $result = apiRequest("/jobs/results/$jobId", 'GET', null, $authToken);
    if ($result['success'] && is_array($result['data'])) {
        $analysisResults = $result['data'];
        $_SESSION['analysis_results'] = $analysisResults;
    } else {
        $loadError = $result['data']['detail'] ?? $result['error'] ?? 'Could not load results.';
    }
} elseif ($jobId) {
    $loadError = 'A live backend session is required to load analytics.';
}

$meta = $analysisResults['meta'] ?? [];
$summary = $analysisResults['segment_summary'] ?? [];
$prepSummary = $analysisResults['preprocessing']['summary'] ?? [];
$segmentsRaw = $analysisResults['segments'] ?? [];
$recentCustomers = $analysisResults['recent_customers'] ?? [];
$hasAnalyticsData = is_array($analysisResults) && $analysisResults !== null;

$uploadedFile = $_SESSION['current_job']['filename'] ?? 'Uploaded file';

$kpis = [
    ['label' => 'Total Revenue', 'value' => $meta['total_revenue'] ?? $prepSummary['total_revenue'] ?? null, 'icon' => 'payments', 'type' => 'currency', 'color' => 'blue'],
    ['label' => 'Active Customers', 'value' => $meta['num_customers'] ?? $summary['total_customers'] ?? null, 'icon' => 'group', 'type' => 'number', 'color' => 'purple'],
    ['label' => 'Avg Transaction', 'value' => $prepSummary['avg_transaction'] ?? null, 'icon' => 'shopping_cart', 'type' => 'currency', 'color' => 'orange'],
    ['label' => 'Total Transactions', 'value' => $meta['num_transactions'] ?? $prepSummary['num_transactions'] ?? null, 'icon' => 'receipt_long', 'type' => 'number', 'color' => 'green'],
];

$segmentColors = [
    'Champions' => 'yellow',
    'Loyal Customers' => 'blue',
    'Potential Loyalists' => 'green',
    'New Customers' => 'cyan',
    'Promising' => 'teal',
    'Need Attention' => 'orange',
    'Needs Attention' => 'orange',
    'About to Sleep' => 'purple',
    'At Risk' => 'red',
    'Cannot Lose' => 'pink',
    'Hibernating' => 'gray',
    'Lost Customers' => 'slate',
    'Lost' => 'slate',
];

$segments = [];
foreach ($segmentsRaw as $segment) {
    $name = $segment['segment_label'] ?? $segment['segment_name'] ?? $segment['name'] ?? 'Unknown';
    $segments[] = [
        'name' => $name,
        'pct' => round($segment['percentage'] ?? 0),
        'count' => $segment['num_customers'] ?? null,
        'avg_recency' => $segment['avg_recency'] ?? null,
        'avg_freq' => $segment['avg_frequency'] ?? null,
        'avg_monetary' => $segment['avg_monetary'] ?? null,
        'actions' => $segment['recommended_actions'] ?? [],
        'description' => $segment['description'] ?? '',
        'color' => $segmentColors[$name] ?? 'gray',
    ];
}

function formatCurrency($value): string {
    return $value === null ? 'Not available' : 'GH₵ ' . number_format((float) $value, 2);
}

function formatNumberValue($value): string {
    return $value === null ? 'Not available' : number_format((float) $value);
}

function getSegmentBgClass($color): string {
    $map = [
        'yellow' => 'bg-yellow-100 text-yellow-800',
        'blue' => 'bg-blue-100 text-blue-800',
        'green' => 'bg-green-100 text-green-800',
        'red' => 'bg-red-100 text-red-800',
        'orange' => 'bg-orange-100 text-orange-800',
        'purple' => 'bg-purple-100 text-purple-800',
        'teal' => 'bg-teal-100 text-teal-800',
        'cyan' => 'bg-cyan-100 text-cyan-800',
        'pink' => 'bg-pink-100 text-pink-800',
        'slate' => 'bg-slate-100 text-slate-800',
        'gray' => 'bg-gray-100 text-gray-800',
    ];
    return $map[$color] ?? $map['gray'];
}

function getSegmentBarClass($color): string {
    $map = [
        'yellow' => 'bg-yellow-400',
        'blue' => 'bg-blue-500',
        'green' => 'bg-green-500',
        'red' => 'bg-red-500',
        'orange' => 'bg-orange-500',
        'purple' => 'bg-purple-500',
        'teal' => 'bg-teal-500',
        'cyan' => 'bg-cyan-500',
        'pink' => 'bg-pink-500',
        'slate' => 'bg-slate-500',
        'gray' => 'bg-gray-400',
    ];
    return $map[$color] ?? $map['gray'];
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
        tailwind.config = { theme: { extend: { colors: { "primary": "#0b203c", "primary-hover": "#153055", "background-light": "#f6f7f8", "accent": "#e8b031" }, fontFamily: { "display": ["Inter", "sans-serif"] } } } }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
    </style>
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
            <a class="flex items-center gap-3 rounded-lg bg-white/10 text-white px-4 py-3 text-sm font-medium transition-colors" href="analytics.php"><span class="material-symbols-outlined">analytics</span>Analytics</a>
            <a class="flex items-center gap-3 rounded-lg text-slate-300 hover:bg-white/5 hover:text-white px-4 py-3 text-sm font-medium transition-colors" href="reports.php"><span class="material-symbols-outlined">bar_chart</span>Reports</a>
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
                    <span class="font-medium text-primary">Analytics</span>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <?php if ($loadError): ?>
                <span class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2.5 py-1 text-xs font-medium text-red-700">
                    <span class="material-symbols-outlined text-[14px]">warning</span><?php echo htmlspecialchars($loadError); ?>
                </span>
                <?php endif; ?>
                <button class="relative rounded-full p-2 text-slate-500 hover:bg-slate-100 transition-colors">
                    <span class="material-symbols-outlined">notifications</span>
                </button>
            </div>
        </header>

        <div class="p-6 sm:p-10 max-w-7xl mx-auto w-full">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8 gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-primary tracking-tight">Analytics Overview</h1>
                    <p class="text-slate-500 mt-1">
                        <?php if ($jobId): ?>
                        Job <code class="bg-slate-100 px-1.5 py-0.5 rounded text-xs"><?php echo htmlspecialchars(substr($jobId, 0, 8)); ?>…</code> — <?php echo htmlspecialchars($uploadedFile); ?>
                        <?php else: ?>
                        Select a completed analysis job to view live results
                        <?php endif; ?>
                    </p>
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

            <?php if (!$hasAnalyticsData): ?>
            <div class="rounded-xl border border-slate-200 bg-white p-10 shadow-sm text-center">
                <span class="material-symbols-outlined text-5xl text-slate-300">analytics</span>
                <h2 class="mt-4 text-xl font-bold text-primary">No live analytics available yet</h2>
                <p class="mt-2 text-sm text-slate-500"><?php echo htmlspecialchars($loadError ?: 'Run and complete an analysis job to view customer segments and KPIs here.'); ?></p>
                <div class="mt-6 flex justify-center gap-3">
                    <a href="analysis.php" class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-white hover:bg-primary-hover"><span class="material-symbols-outlined text-[20px]">upload_file</span>Start Analysis</a>
                    <a href="reports.php" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-primary hover:bg-slate-50"><span class="material-symbols-outlined text-[20px]">bar_chart</span>View Jobs</a>
                </div>
            </div>
            <?php else: ?>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-8">
                <?php foreach ($kpis as $kpi): ?>
                <div class="flex flex-col rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="mb-2 flex items-center justify-between">
                        <span class="text-sm font-medium text-slate-500"><?php echo htmlspecialchars($kpi['label']); ?></span>
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-<?php echo $kpi['color']; ?>-50 text-<?php echo $kpi['color']; ?>-600">
                            <span class="material-symbols-outlined text-[20px]"><?php echo htmlspecialchars($kpi['icon']); ?></span>
                        </span>
                    </div>
                    <h3 class="text-2xl font-bold text-primary">
                        <?php echo $kpi['type'] === 'currency' ? formatCurrency($kpi['value']) : formatNumberValue($kpi['value']); ?>
                    </h3>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="mb-8">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-bold text-primary">Customer Segments</h2>
                    <?php if (isset($meta['silhouette_score'])): ?>
                    <span class="text-xs text-slate-500 bg-slate-100 px-3 py-1 rounded-full">Silhouette score: <strong><?php echo number_format((float) $meta['silhouette_score'], 3); ?></strong></span>
                    <?php endif; ?>
                </div>
                <?php if (empty($segments)): ?>
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm text-sm text-slate-500">This analysis result does not include segment rows yet.</div>
                <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($segments as $segment): ?>
                    <div class="flex flex-col rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-3">
                                <div class="rounded-lg p-2 <?php echo getSegmentBgClass($segment['color']); ?>">
                                    <span class="material-symbols-outlined">category</span>
                                </div>
                                <div>
                                    <h4 class="font-bold text-primary"><?php echo htmlspecialchars($segment['name']); ?></h4>
                                    <p class="text-xs text-slate-500"><?php echo htmlspecialchars($segment['description'] !== '' ? $segment['description'] : formatNumberValue($segment['count']) . ' customers'); ?></p>
                                </div>
                            </div>
                            <span class="font-bold text-primary"><?php echo htmlspecialchars((string) $segment['pct']); ?>%</span>
                        </div>
                        <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full <?php echo getSegmentBarClass($segment['color']); ?>" style="width:<?php echo max(0, min(100, (int) $segment['pct'])); ?>%"></div>
                        </div>
                        <div class="mt-3 grid grid-cols-3 gap-2 text-center">
                            <div><p class="text-xs text-slate-400">Recency</p><p class="text-sm font-semibold text-primary"><?php echo $segment['avg_recency'] !== null ? round((float) $segment['avg_recency']) . 'd' : 'N/A'; ?></p></div>
                            <div><p class="text-xs text-slate-400">Frequency</p><p class="text-sm font-semibold text-primary"><?php echo $segment['avg_freq'] !== null ? round((float) $segment['avg_freq'], 1) . 'x' : 'N/A'; ?></p></div>
                            <div><p class="text-xs text-slate-400">Avg Spend</p><p class="text-sm font-semibold text-primary"><?php echo formatCurrency($segment['avg_monetary']); ?></p></div>
                        </div>
                        <?php if (!empty($segment['actions'])): ?>
                        <div class="mt-3 pt-3 border-t border-slate-100">
                            <p class="text-xs font-semibold text-slate-500 mb-1">Top Action</p>
                            <p class="text-xs text-slate-600"><?php echo htmlspecialchars($segment['actions'][0]); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-xl font-bold text-primary mb-4">Recent Customers</h2>
                <?php if (empty($recentCustomers)): ?>
                <p class="text-sm text-slate-500">This analysis result did not include recent customer-level rows.</p>
                <?php else: ?>
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
                            <?php foreach ($recentCustomers as $customer): ?>
                            <?php $segmentName = $customer['segment'] ?? 'Unknown'; $segmentColor = $segmentColors[$segmentName] ?? 'gray'; ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary"><?php echo htmlspecialchars(strtoupper(substr($customer['name'] ?? 'C', 0, 2))); ?></div>
                                        <div>
                                            <p class="font-medium text-primary"><?php echo htmlspecialchars($customer['name'] ?? 'Customer'); ?></p>
                                            <p class="text-xs text-slate-500"><?php echo htmlspecialchars($customer['email'] ?? 'N/A'); ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4"><span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium <?php echo getSegmentBgClass($segmentColor); ?>"><?php echo htmlspecialchars($segmentName); ?></span></td>
                                <td class="px-4 py-4 text-slate-500"><?php echo htmlspecialchars($customer['last_purchase_date'] ?? $customer['last_purchase'] ?? 'N/A'); ?></td>
                                <td class="px-4 py-4"><?php echo htmlspecialchars(($customer['status'] ?? (($customer['recency_days'] ?? 30) < 30 ? 'Active' : 'Inactive'))); ?></td>
                                <td class="px-4 py-4 text-right font-medium text-primary"><?php echo formatCurrency($customer['total_spend'] ?? $customer['spend'] ?? null); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
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
    const JOB_ID = <?= json_encode($jobId) ?>;

    function toggleUserMenu() { document.getElementById('userMenu').classList.toggle('hidden'); }
    function toggleMobileMenu() {
        document.getElementById('mobileSidebar').classList.toggle('-translate-x-full');
        document.getElementById('mobileMenuOverlay').classList.toggle('hidden');
    }

    document.getElementById('exportCsv').addEventListener('click', () => {
        const rows = [['Customer', 'Email', 'Segment', 'Last Purchase', 'Status', 'Total Spend']];
        document.querySelectorAll('tbody tr').forEach(row => {
            const cells = row.querySelectorAll('td');
            if (cells.length === 5) {
                rows.push([
                    cells[0].textContent.trim(),
                    cells[0].querySelector('p.text-xs')?.textContent.trim() || '',
                    cells[1].textContent.trim(),
                    cells[2].textContent.trim(),
                    cells[3].textContent.trim(),
                    cells[4].textContent.trim()
                ]);
            }
        });
        const csv = rows.map(r => r.map(c => `"${String(c).replace(/"/g, '""')}"`).join(',')).join('\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'customer360_segments.csv';
        a.click();
    });

    document.getElementById('downloadPdf').addEventListener('click', () => {
        if (!JOB_ID) {
            alert('PDF download requires a completed live analysis job.');
            return;
        }
        window.location.href = `api/process.php?action=report&job_id=${encodeURIComponent(JOB_ID)}`;
    });
</script>
</body>
</html>
