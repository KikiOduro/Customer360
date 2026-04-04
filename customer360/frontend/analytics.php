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
$storySummary = $analysisResults['story_summary'] ?? [];
$llmAnalysis = $analysisResults['llm_analysis'] ?? null;
$edaSummary = $analysisResults['preprocessing']['eda'] ?? [];
$cleaningStats = $analysisResults['preprocessing']['cleaning_stats'] ?? [];
$outlierSummary = $analysisResults['outlier_treatment'] ?? [];
$shapSummary = $analysisResults['shap']['ranked_features'] ?? [];
$chartsRaw = $analysisResults['charts'] ?? [];
$segmentsRaw = $analysisResults['segments'] ?? [];
$recentCustomers = $analysisResults['recent_customers'] ?? [];
$customerRows = $analysisResults['customer_table'] ?? $recentCustomers;
$hasAnalyticsData = is_array($analysisResults) && $analysisResults !== null;

$uploadedFile = $_SESSION['current_job']['filename'] ?? 'Uploaded file';

$kpis = [
    [
        'label' => 'Total Revenue',
        'value' => $meta['total_revenue'] ?? $prepSummary['total_revenue'] ?? null,
        'icon' => 'payments',
        'type' => 'currency',
        'color' => 'blue',
        'help_title' => 'Total Revenue',
        'help_text' => 'This is the total sales value found in the cleaned data that went into the customer grouping. If some rows were removed during cleaning, this number reflects only the rows that were kept for analysis.'
    ],
    [
        'label' => 'Total Customers',
        'value' => $meta['num_customers'] ?? $summary['total_customers'] ?? null,
        'icon' => 'group',
        'type' => 'number',
        'color' => 'purple',
        'help_title' => 'Total Customers',
        'help_text' => 'This is the number of unique customers found after the platform grouped your transactions by customer ID. If some rows had no customer ID and you did not allow synthetic IDs, those rows were excluded.'
    ],
    [
        'label' => 'Average Sale Value',
        'value' => $prepSummary['avg_transaction'] ?? null,
        'icon' => 'shopping_cart',
        'type' => 'currency',
        'color' => 'orange',
        'help_title' => 'Average Sale Value',
        'help_text' => 'This is the average amount per transaction row after cleaning. It gives a quick sense of the typical sale size in the dataset.'
    ],
    [
        'label' => 'Total Transactions',
        'value' => $meta['num_transactions'] ?? $prepSummary['num_transactions'] ?? null,
        'icon' => 'receipt_long',
        'type' => 'number',
        'color' => 'green',
        'help_title' => 'Total Transactions',
        'help_text' => 'This is the number of usable transaction rows that remained after blank rows, invalid dates, missing IDs, or unwanted negative amounts were handled.'
    ],
    [
        'label' => 'Customer Health Score',
        'value' => $storySummary['health_score'] ?? null,
        'icon' => 'favorite',
        'type' => 'percentage',
        'color' => 'green',
        'help_title' => 'Customer Health Score',
        'help_text' => 'This estimates what percentage of your customers are in stronger repeat-buyer groups. A higher number usually means more of your customer base is active or growing, but you should still check whether one small group is carrying most of the revenue.'
    ],
    [
        'label' => 'Top-Group Revenue Share',
        'value' => $storySummary['revenue_concentration'] ?? null,
        'icon' => 'leaderboard',
        'type' => 'percentage',
        'color' => 'orange',
        'help_title' => 'Top-Group Revenue Share',
        'help_text' => 'This shows how much of your total revenue comes from your two biggest customer groups. If it is very high, your business may depend heavily on a small set of customer types, which can be risky if they stop buying.'
    ],
    [
        'label' => 'Grouping Confidence',
        'value' => $storySummary['quality_rating'] ?? null,
        'icon' => 'hotel_class',
        'type' => 'stars',
        'color' => 'purple',
        'help_title' => 'Grouping Confidence',
        'help_text' => 'This score is a simple 1 to 5 star summary of how clearly the customer groups separated. Fewer stars does not automatically mean your data is bad; it can also mean your customers behave in very similar ways, so the boundaries between groups are naturally soft.'
    ],
];

$segmentColors = [
    'Your Star Customers' => 'yellow',
    'Your Faithful Regulars' => 'blue',
    'Almost Regulars' => 'green',
    'Fresh Faces' => 'cyan',
    'Showing Interest' => 'teal',
    'Slipping Away Slowly' => 'orange',
    'About to Forget You' => 'purple',
    'Danger Zone' => 'red',
    'Sleeping Customers' => 'gray',
    'Gone Customers' => 'slate',
    'Best Repeat Buyers' => 'yellow',
    'Steady Regular Buyers' => 'blue',
    'Growing Repeat Buyers' => 'green',
    'First-Time or New Buyers' => 'cyan',
    'New Buyers With Good Potential' => 'teal',
    'Customers Who Need a Follow-Up' => 'orange',
    'Cooling-Off Customers' => 'purple',
    'Valuable Customers You May Be Losing' => 'red',
    'Quiet Low-Activity Customers' => 'gray',
    'Customers Who Have Likely Left' => 'slate',
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

$segmentDisplayMap = [
    'Your Star Customers' => ['title' => 'Your Star Customers', 'emoji' => '⭐'],
    'Your Faithful Regulars' => ['title' => 'Your Faithful Regulars', 'emoji' => '💙'],
    'Almost Regulars' => ['title' => 'Almost Regulars', 'emoji' => '📈'],
    'Fresh Faces' => ['title' => 'Fresh Faces', 'emoji' => '🌱'],
    'Showing Interest' => ['title' => 'Showing Interest', 'emoji' => '✨'],
    'Slipping Away Slowly' => ['title' => 'Slipping Away Slowly', 'emoji' => '📣'],
    'About to Forget You' => ['title' => 'About to Forget You', 'emoji' => '🌙'],
    'Danger Zone' => ['title' => 'Danger Zone', 'emoji' => '⚠️'],
    'Sleeping Customers' => ['title' => 'Sleeping Customers', 'emoji' => '💤'],
    'Gone Customers' => ['title' => 'Gone Customers', 'emoji' => '🧊'],
    'Champions' => ['title' => 'Star Customers', 'emoji' => '⭐'],
    'Loyal Customers' => ['title' => 'Faithful Regulars', 'emoji' => '💙'],
    'Potential Loyalists' => ['title' => 'Almost Regulars', 'emoji' => '📈'],
    'New Customers' => ['title' => 'Fresh Buyers', 'emoji' => '🌱'],
    'Promising' => ['title' => 'Warm Leads', 'emoji' => '✨'],
    'Need Attention' => ['title' => 'Needs Follow-up', 'emoji' => '📣'],
    'Needs Attention' => ['title' => 'Needs Follow-up', 'emoji' => '📣'],
    'About to Sleep' => ['title' => 'Cooling Off', 'emoji' => '🌙'],
    'At Risk' => ['title' => 'Danger Zone', 'emoji' => '⚠️'],
    'Cannot Lose' => ['title' => 'Must Save', 'emoji' => '🛟'],
    'Hibernating' => ['title' => 'Silent Customers', 'emoji' => '💤'],
    'Lost Customers' => ['title' => 'Dormant Customers', 'emoji' => '🧊'],
    'Lost' => ['title' => 'Dormant Customers', 'emoji' => '🧊'],
];

$chartDisplay = [
    'pareto' => ['title' => 'Revenue Breakdown', 'caption' => 'See which customer groups contribute the largest share of your revenue first.'],
    'segment_sizes' => ['title' => 'Customer Groups', 'caption' => 'Understand how your customer base is distributed across the discovered segments.'],
    'pca_scatter' => ['title' => 'Customer Map', 'caption' => 'Customers that appear close together behave in similar ways. Customers far apart behave differently.'],
    'rfm_distributions' => ['title' => 'Buying Patterns', 'caption' => 'See how recent purchases, repeat visits, and spending levels are spread across your customer base.'],
    'radar_chart' => ['title' => 'Group Profiles', 'caption' => 'Compare which groups buy more recently, buy more often, or spend more money on average.'],
    'rfm_violin_plots' => ['title' => 'Group Differences', 'caption' => 'See whether a customer group is tightly packed or mixed, and how wide the spending and visit differences are inside that group.'],
    'algorithm_comparison' => ['title' => 'Grouping Confidence', 'caption' => 'This checks which grouping method separated your customers most clearly. You do not need to choose manually; the platform uses this to pick the strongest option.'],
];

$segments = [];
foreach ($segmentsRaw as $segment) {
    $name = $segment['segment_label'] ?? $segment['segment_name'] ?? $segment['name'] ?? 'Unknown';
    $baseName = $segment['segment_base_label'] ?? $name;
    $displayMeta = $segmentDisplayMap[$baseName] ?? $segmentDisplayMap[$name] ?? ['title' => $name, 'emoji' => '👥'];
    $aiInsight = trim((string) ($segment['ai_insight'] ?? ''));
    $aiActions = is_array($segment['ai_actions'] ?? null) ? $segment['ai_actions'] : [];
    $fallbackDescription = trim((string) ($segment['description'] ?? ''));
    $fallbackActions = is_array($segment['recommended_actions'] ?? null) ? $segment['recommended_actions'] : [];
    $segments[] = [
        'name' => $name,
        'friendly_name' => $segment['segment_label'] ?? $displayMeta['title'],
        'technical_name' => $segment['segment_short_name'] ?? $name,
        'emoji' => $segment['segment_emoji'] ?? $displayMeta['emoji'],
        'pct' => round($segment['percentage'] ?? 0),
        'count' => $segment['num_customers'] ?? null,
        'avg_recency' => $segment['avg_recency'] ?? null,
        'avg_freq' => $segment['avg_frequency'] ?? null,
        'avg_monetary' => $segment['avg_monetary'] ?? null,
        'actions' => !empty($aiActions) ? $aiActions : $fallbackActions,
        'description' => $aiInsight !== '' ? $aiInsight : $fallbackDescription,
        'color' => $segmentColors[$baseName] ?? $segmentColors[$name] ?? 'gray',
    ];
}

$chartPanels = [];
foreach ($chartDisplay as $chartKey => $chartInfo) {
    if (!empty($chartsRaw[$chartKey]) && $jobId) {
        $chartPanels[] = [
            'key' => $chartKey,
            'title' => $chartInfo['title'],
            'caption' => $chartInfo['caption'],
            'url' => 'api/process.php?action=chart&job_id=' . rawurlencode($jobId) . '&chart=' . rawurlencode($chartKey),
        ];
    }
}

function formatCurrency($value): string {
    return $value === null ? 'Not available' : 'GHS ' . number_format((float) $value, 2);
}

function formatNumberValue($value): string {
    return $value === null ? 'Not available' : number_format((float) $value);
}

function formatKpiValue(array $kpi): string {
    $value = $kpi['value'];
    if ($kpi['type'] === 'currency') {
        return formatCurrency($value);
    }
    if ($kpi['type'] === 'percentage') {
        return $value === null ? 'Not available' : number_format((float) $value, 1) . '%';
    }
    if ($kpi['type'] === 'stars') {
        $rating = max(0, min(5, (int) round((float) ($value ?? 0))));
        return $rating > 0 ? str_repeat('★', $rating) . str_repeat('☆', 5 - $rating) : 'Not available';
    }
    return formatNumberValue($value);
}

function getPlainFeatureLabel(string $featureName): string {
    $map = [
        'recency' => 'How recently customers bought',
        'frequency' => 'How often customers come back',
        'monetary' => 'How much customers spend',
    ];
    return $map[strtolower($featureName)] ?? ucfirst(str_replace('_', ' ', $featureName));
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
            <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <?php foreach ($kpis as $kpi): ?>
                <div class="flex min-h-[148px] flex-col justify-between rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-start gap-2">
                            <span class="max-w-[10rem] text-sm font-semibold leading-5 text-slate-500"><?php echo htmlspecialchars($kpi['label']); ?></span>
                            <button
                                type="button"
                                class="info-help-btn mt-0.5 inline-flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full border border-slate-200 bg-slate-50 text-[11px] font-bold text-slate-500 hover:bg-slate-100"
                                data-help-title="<?php echo htmlspecialchars($kpi['help_title']); ?>"
                                data-help-text="<?php echo htmlspecialchars($kpi['help_text']); ?>"
                                aria-label="Explain <?php echo htmlspecialchars($kpi['label']); ?>"
                            >?</button>
                        </div>
                        <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-<?php echo $kpi['color']; ?>-50 text-<?php echo $kpi['color']; ?>-600">
                            <span class="material-symbols-outlined text-[20px]"><?php echo htmlspecialchars($kpi['icon']); ?></span>
                        </span>
                    </div>
                    <h3 class="pt-4 text-3xl font-bold tracking-tight text-primary">
                        <?php echo htmlspecialchars(formatKpiValue($kpi)); ?>
                    </h3>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if (!(is_array($llmAnalysis) && !empty($llmAnalysis))): ?>
            <div class="mb-8 rounded-2xl border border-amber-100 bg-amber-50 p-6 shadow-sm">
                <div class="flex items-start gap-4">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-accent/20 text-primary">
                        <span class="material-symbols-outlined">auto_stories</span>
                    </div>
                    <div class="flex-1">
                        <p class="text-xs font-bold uppercase tracking-[0.2em] text-amber-700">Your Customer Story</p>
                        <h2 class="mt-2 text-xl font-bold text-primary"><?php echo htmlspecialchars($storySummary['headline'] ?? 'Your customer story is being prepared.'); ?></h2>
                        <p class="mt-2 text-sm leading-6 text-slate-700"><?php echo htmlspecialchars($storySummary['narrative'] ?? 'Complete an analysis job to receive a plain-language summary of your customer base and what to do next.'); ?></p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (is_array($llmAnalysis) && !empty($llmAnalysis)): ?>
            <div class="mb-8 rounded-2xl border-2 border-accent bg-gradient-to-r from-amber-50 to-yellow-50 p-6 shadow-sm">
                <div class="flex items-start gap-4">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-accent/20 text-primary">
                        <span class="material-symbols-outlined">auto_awesome</span>
                    </div>
                    <div class="flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-xs font-bold uppercase tracking-[0.2em] text-amber-700">Business Coach Summary</p>
                            <?php if (($llmAnalysis['source'] ?? '') === 'groq'): ?>
                            <span class="rounded-full bg-white/80 px-2 py-0.5 text-[10px] font-bold uppercase tracking-[0.18em] text-primary">AI-assisted</span>
                            <?php endif; ?>
                        </div>
                        <h2 class="mt-2 text-xl font-bold text-primary"><?php echo htmlspecialchars($llmAnalysis['headline'] ?? 'Here is a simple summary of what your customer groups mean.'); ?></h2>
                        <p class="mt-2 text-sm leading-6 text-slate-700"><?php echo htmlspecialchars($llmAnalysis['story'] ?? 'Use the customer groups and recommended actions below to plan your follow-up messages and retention campaigns.'); ?></p>

                        <?php if (isset($llmAnalysis['health_score']) && is_array($llmAnalysis['health_score'])): ?>
                        <div class="mt-4 inline-flex items-center gap-3 rounded-full border border-amber-200 bg-white px-4 py-2">
                            <span class="text-base font-extrabold text-primary"><?php echo htmlspecialchars((string) ((int) ($llmAnalysis['health_score']['score'] ?? 0))); ?>/10</span>
                            <span class="text-sm font-semibold text-slate-700"><?php echo htmlspecialchars((string) ($llmAnalysis['health_score']['label'] ?? 'Business Health')); ?></span>
                            <button
                                type="button"
                                class="info-help-btn inline-flex h-5 w-5 items-center justify-center rounded-full border border-slate-200 bg-slate-50 text-[11px] font-bold text-slate-500 hover:bg-slate-100"
                                data-help-title="Business Health Score"
                                data-help-text="<?php echo htmlspecialchars((string) ($llmAnalysis['health_score']['explanation'] ?? 'This gives a simple 1 to 10 summary of how strong your customer base looks and how urgently you may need follow-up action.')); ?>"
                                aria-label="Explain business health score"
                            >?</button>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($llmAnalysis['key_findings']) && is_array($llmAnalysis['key_findings'])): ?>
                        <div class="mt-5 grid gap-2">
                            <?php foreach (array_slice($llmAnalysis['key_findings'], 0, 3) as $finding): ?>
                            <div class="flex items-start gap-2 text-sm text-slate-700">
                                <span class="mt-1 text-accent">●</span>
                                <p><?php echo htmlspecialchars((string) $finding); ?></p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($llmAnalysis['top_3_actions']) && is_array($llmAnalysis['top_3_actions'])): ?>
                        <div class="mt-5 border-t border-amber-200 pt-4">
                            <h3 class="text-sm font-bold uppercase tracking-[0.18em] text-primary">What To Do This Week</h3>
                            <div class="mt-3 grid gap-3">
                                <?php foreach (array_slice($llmAnalysis['top_3_actions'], 0, 3) as $index => $actionItem): ?>
                                <?php if (!is_array($actionItem)) continue; ?>
                                <div class="flex gap-3 rounded-xl bg-white/80 p-3">
                                    <div class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-primary text-xs font-bold text-white">
                                        <?php echo $index + 1; ?>
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-primary"><?php echo htmlspecialchars((string) ($actionItem['action'] ?? 'Take one focused customer follow-up action')); ?></p>
                                        <p class="mt-1 text-xs leading-5 text-slate-600">
                                            <?php echo htmlspecialchars((string) ($actionItem['how'] ?? $actionItem['why'] ?? 'Use this recommendation to plan your next customer outreach.')); ?>
                                        </p>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($chartPanels)): ?>
            <div class="mb-8 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-2 mb-5">
                    <h2 class="text-xl font-bold text-primary">Visual Charts</h2>
                    <p class="text-sm text-slate-500">Each picture explains one part of your customer story, such as who brings in the most revenue and which groups are slipping away.</p>
                </div>
                <div class="flex flex-wrap gap-2 mb-5" id="chartTabs">
                    <?php foreach ($chartPanels as $index => $chart): ?>
                    <button type="button" class="chart-tab rounded-full px-4 py-2 text-sm font-semibold transition-colors <?php echo $index === 0 ? 'bg-primary text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'; ?>" data-chart-index="<?php echo $index; ?>">
                        <?php echo htmlspecialchars($chart['title']); ?>
                    </button>
                    <?php endforeach; ?>
                </div>
                <div id="chartPanels">
                    <?php foreach ($chartPanels as $index => $chart): ?>
                    <article class="chart-panel <?php echo $index === 0 ? '' : 'hidden'; ?>" data-chart-index="<?php echo $index; ?>">
                        <div class="rounded-2xl bg-slate-50 p-4 border border-slate-100">
                            <img src="<?php echo htmlspecialchars($chart['url']); ?>" alt="<?php echo htmlspecialchars($chart['title']); ?>" class="w-full rounded-xl bg-white object-contain" loading="lazy"/>
                        </div>
                        <p class="mt-3 text-sm text-slate-600"><strong class="text-primary"><?php echo htmlspecialchars($chart['title']); ?>:</strong> <?php echo htmlspecialchars($chart['caption']); ?></p>
                    </article>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($shapSummary)): ?>
            <div class="mb-8 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-xl font-bold text-primary mb-2">What Mostly Separates One Customer Group From Another</h2>
                <p class="text-sm text-slate-500 mb-5">This shows whether recent buying, repeat visits, or total spending had the strongest influence when the platform formed the customer groups.</p>
                <div class="space-y-4">
                    <?php $maxImportance = max(array_map(fn($item) => (float) ($item['importance'] ?? 0), $shapSummary)) ?: 1; ?>
                    <?php foreach ($shapSummary as $featureItem): ?>
                    <?php
                        $featureLabel = getPlainFeatureLabel((string) ($featureItem['feature'] ?? 'feature'));
                        $importance = (float) ($featureItem['importance'] ?? 0);
                        $width = max(8, min(100, ($importance / $maxImportance) * 100));
                    ?>
                    <div>
                        <div class="flex items-center justify-between text-sm font-medium text-slate-700">
                            <span><?php echo htmlspecialchars($featureLabel); ?></span>
                            <span><?php echo number_format($importance, 4); ?></span>
                        </div>
                        <div class="mt-2 h-3 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-primary" style="width:<?php echo $width; ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="mb-8">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-bold text-primary">Customer Segments</h2>
                    <?php if (isset($storySummary['quality_rating'])): ?>
                    <span class="text-xs text-slate-500 bg-slate-100 px-3 py-1 rounded-full">
                        Grouping confidence:
                        <strong><?php echo htmlspecialchars(formatKpiValue(['value' => $storySummary['quality_rating'], 'type' => 'stars'])); ?></strong>
                    </span>
                    <?php endif; ?>
                </div>
                <?php if (empty($segments)): ?>
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm text-sm text-slate-500">This analysis result does not include segment rows yet.</div>
                <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($segments as $segIdx => $segment): ?>
                    <div
                        class="flex flex-col rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
                        data-segment-card
                        data-segment-index="<?php echo (int) $segIdx; ?>"
                    >
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-3">
                                <div class="rounded-lg p-2 <?php echo getSegmentBgClass($segment['color']); ?>">
                                    <span class="text-lg" aria-hidden="true"><?php echo htmlspecialchars($segment['emoji']); ?></span>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h4 class="font-bold text-primary"><?php echo htmlspecialchars($segment['friendly_name']); ?></h4>
                                        <button
                                            type="button"
                                            class="info-help-btn inline-flex h-5 w-5 items-center justify-center rounded-full border border-slate-200 bg-slate-50 text-[11px] font-bold text-slate-500 hover:bg-slate-100"
                                            data-help-title="<?php echo htmlspecialchars($segment['friendly_name']); ?>"
                                            data-help-text="<?php echo htmlspecialchars($segment['description'] !== '' ? $segment['description'] : 'This customer group was formed from recent buying behaviour, repeat visits, and total spend.'); ?>"
                                            aria-label="Explain this customer group"
                                        >?</button>
                                    </div>
                                    <p class="text-xs text-slate-500">
                                        <?php if (!empty($segment['technical_name']) && $segment['technical_name'] !== $segment['friendly_name']): ?>
                                            Simple label: <?php echo htmlspecialchars($segment['technical_name']); ?> ·
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars(formatNumberValue($segment['count'])); ?> customers
                                    </p>
                                </div>
                            </div>
                            <span class="font-bold text-primary"><?php echo htmlspecialchars((string) $segment['pct']); ?>%</span>
                        </div>
                        <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full <?php echo getSegmentBarClass($segment['color']); ?>" style="width:<?php echo max(0, min(100, (int) $segment['pct'])); ?>%"></div>
                        </div>
                        <div class="mt-3 grid grid-cols-3 gap-2 text-center">
                            <div><p class="text-xs text-slate-400">Days Since Last Buy</p><p class="text-sm font-semibold text-primary"><?php echo $segment['avg_recency'] !== null ? round((float) $segment['avg_recency']) . 'd' : 'N/A'; ?></p></div>
                            <div><p class="text-xs text-slate-400">Repeat Purchases</p><p class="text-sm font-semibold text-primary"><?php echo $segment['avg_freq'] !== null ? round((float) $segment['avg_freq'], 1) . 'x' : 'N/A'; ?></p></div>
                            <div><p class="text-xs text-slate-400">Avg Spend</p><p class="text-sm font-semibold text-primary"><?php echo formatCurrency($segment['avg_monetary']); ?></p></div>
                        </div>
                        <p class="mt-3 text-sm text-slate-600"><?php echo htmlspecialchars($segment['description'] !== '' ? $segment['description'] : 'Customer behaviour in this segment is summarized by the RFM averages above.'); ?></p>
                        <?php if (!empty($segment['actions'])): ?>
                        <details class="mt-3 pt-3 border-t border-slate-100 group">
                            <summary class="cursor-pointer list-none text-xs font-semibold text-primary flex items-center justify-between">
                                <span>Top Actions & Segment Details</span>
                                <span class="material-symbols-outlined text-[18px] group-open:rotate-180 transition-transform">expand_more</span>
                            </summary>
                            <div class="mt-3 space-y-2">
                            <p class="text-xs font-semibold text-slate-500 mb-1">Top Action</p>
                            <p class="text-xs text-slate-600"><?php echo htmlspecialchars($segment['actions'][0]); ?></p>
                                <?php if (count($segment['actions']) > 1): ?>
                                <ul class="list-disc pl-4 text-xs text-slate-600 space-y-1">
                                    <?php foreach (array_slice($segment['actions'], 1) as $actionItem): ?>
                                    <li><?php echo htmlspecialchars($actionItem); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <?php endif; ?>
                                <button type="button" class="segment-focus-btn mt-2 inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline" data-segment="<?php echo htmlspecialchars($segment['name']); ?>">
                                    View these customers
                                    <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                                </button>
                            </div>
                        </details>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between mb-4">
                    <div>
                        <h2 class="text-xl font-bold text-primary">Customer Explorer</h2>
                        <p class="text-sm text-slate-500">Search and filter the full customer list to see which group each customer belongs to and what action is recommended.</p>
                    </div>
                    <div class="flex flex-col sm:flex-row gap-2 sm:items-center">
                        <input id="customerSearch" type="search" placeholder="Search customer ID, name, or segment..." class="w-full sm:w-72 rounded-lg border-slate-200 text-sm focus:border-primary focus:ring-primary"/>
                        <select id="segmentFilter" class="rounded-lg border-slate-200 text-sm focus:border-primary focus:ring-primary">
                            <option value="">All Segments</option>
                            <?php foreach ($segments as $segment): ?>
                            <option value="<?php echo htmlspecialchars($segment['name']); ?>"><?php echo htmlspecialchars($segment['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select id="statusFilter" class="rounded-lg border-slate-200 text-sm focus:border-primary focus:ring-primary">
                            <option value="">All Statuses</option>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <?php if (empty($customerRows)): ?>
                <p class="text-sm text-slate-500">This analysis result did not include customer-level rows.</p>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-slate-200 text-slate-500">
                            <tr>
                                <th class="px-4 py-3 font-medium">Customer</th>
                                <th class="px-4 py-3 font-medium">Segment</th>
                                <th class="px-4 py-3 font-medium">Last Purchase</th>
                                <th class="px-4 py-3 font-medium">Visits</th>
                                <th class="px-4 py-3 font-medium">Risk</th>
                                <th class="px-4 py-3 font-medium">Status</th>
                                <th class="px-4 py-3 font-medium text-right">Total Spend</th>
                            </tr>
                        </thead>
                        <tbody id="customerTableBody" class="divide-y divide-slate-100"></tbody>
                    </table>
                </div>
                <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-t border-slate-100 pt-4">
                    <p id="customerTableMeta" class="text-sm text-slate-500"></p>
                    <div class="flex items-center gap-2">
                        <button id="prevPage" type="button" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-primary hover:bg-slate-50">Previous</button>
                        <span id="pageLabel" class="text-sm font-semibold text-slate-700"></span>
                        <button id="nextPage" type="button" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-primary hover:bg-slate-50">Next</button>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <details class="mt-8 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <summary class="cursor-pointer list-none">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h2 class="text-xl font-bold text-primary">Data Quality Summary</h2>
                            <p class="mt-1 text-sm text-slate-500">Review what the pipeline cleaned, capped, or flagged before generating customer segments.</p>
                        </div>
                        <span class="material-symbols-outlined text-slate-500">expand_more</span>
                    </div>
                </summary>
                <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-xl bg-slate-50 p-4 border border-slate-100">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Rows Processed</p>
                        <p class="mt-2 text-2xl font-bold text-primary"><?php echo htmlspecialchars(formatNumberValue($prepSummary['num_transactions'] ?? $meta['num_transactions'] ?? null)); ?></p>
                        <p class="mt-1 text-xs text-slate-500">Blank rows removed: <?php echo htmlspecialchars(formatNumberValue($analysisResults['preprocessing']['removed_blank_rows'] ?? 0)); ?></p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-4 border border-slate-100">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Rows Removed</p>
                        <p class="mt-2 text-2xl font-bold text-primary"><?php echo htmlspecialchars(formatNumberValue($cleaningStats['rows_removed'] ?? 0)); ?></p>
                        <p class="mt-1 text-xs text-slate-500">Retention rate: <?php echo htmlspecialchars(isset($cleaningStats['retention_rate']) ? number_format((float) $cleaningStats['retention_rate'] * 100, 1) . '%' : 'N/A'); ?></p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-4 border border-slate-100">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Outliers Capped</p>
                        <p class="mt-2 text-2xl font-bold text-primary"><?php echo htmlspecialchars(formatNumberValue(($outlierSummary['capped_below'] ?? 0) + ($outlierSummary['capped_above'] ?? 0))); ?></p>
                        <p class="mt-1 text-xs text-slate-500">IQR bounds: <?php echo htmlspecialchars(formatCurrency($outlierSummary['lower_bound'] ?? null)); ?> to <?php echo htmlspecialchars(formatCurrency($outlierSummary['upper_bound'] ?? null)); ?></p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-4 border border-slate-100">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Data Warnings</p>
                        <p class="mt-2 text-2xl font-bold text-primary"><?php echo htmlspecialchars(formatNumberValue(count($analysisResults['preprocessing']['warnings'] ?? []))); ?></p>
                        <p class="mt-1 text-xs text-slate-500">Missing values handled: <?php echo htmlspecialchars(formatNumberValue($edaSummary['missing_values'] ?? 0)); ?></p>
                    </div>
                </div>
                <?php if (!empty($analysisResults['preprocessing']['warnings'])): ?>
                <div class="mt-5 rounded-xl bg-amber-50 p-4 border border-amber-100">
                    <p class="text-sm font-semibold text-amber-800">Pipeline Notices</p>
                    <ul class="mt-2 space-y-1 text-sm text-amber-800">
                        <?php foreach ($analysisResults['preprocessing']['warnings'] as $warningMessage): ?>
                        <li><?php echo htmlspecialchars((string) $warningMessage); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </details>
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
    window.__C360_SEGMENTS = <?php echo json_encode(array_map(function ($segment) {
        return [
            'name' => $segment['name'],
            'count' => $segment['count'],
            'pct' => $segment['pct'],
            'avg_recency' => $segment['avg_recency'],
            'avg_frequency' => $segment['avg_freq'],
            'avg_monetary' => $segment['avg_monetary'],
            'description' => $segment['description'],
            'actions' => $segment['actions'],
            'color' => $segment['color'],
        ];
    }, $segments), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;

    window.__C360_META = <?php echo json_encode([
        'total_revenue' => $meta['total_revenue'] ?? 0,
        'num_customers' => $meta['num_customers'] ?? 0,
        'num_transactions' => $meta['num_transactions'] ?? 0,
        'silhouette_score' => $meta['silhouette_score'] ?? 0,
        'clustering_method' => $meta['clustering_method'] ?? 'kmeans',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="assets/js/ai_profiler.js"></script>

<div id="helpModal" class="fixed inset-0 z-[70] hidden items-center justify-center bg-slate-900/60 px-4">
    <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-slate-400">Quick Explanation</p>
                <h3 id="helpModalTitle" class="mt-2 text-xl font-bold text-primary">About this metric</h3>
            </div>
            <button id="closeHelpModal" type="button" class="rounded-full p-2 text-slate-500 hover:bg-slate-100" aria-label="Close explanation">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <p id="helpModalText" class="mt-4 text-sm leading-6 text-slate-700"></p>
        <button id="dismissHelpModal" type="button" class="mt-6 w-full rounded-xl bg-primary px-4 py-3 text-sm font-semibold text-white hover:bg-primary-hover">
            Got it
        </button>
    </div>
</div>

<script>
    const JOB_ID = <?= json_encode($jobId) ?>;
    const CUSTOMER_ROWS = <?= json_encode(array_values($customerRows ?? [])) ?>;
    const SEGMENT_BADGE_CLASSES = <?= json_encode(array_map(fn($color) => getSegmentBgClass($color), $segmentColors)) ?>;
    const ROWS_PER_PAGE = 10;
    let activePage = 1;

    function toggleUserMenu() { document.getElementById('userMenu').classList.toggle('hidden'); }
    function toggleMobileMenu() {
        document.getElementById('mobileSidebar').classList.toggle('-translate-x-full');
        document.getElementById('mobileMenuOverlay').classList.toggle('hidden');
    }

    function openHelpModal(title, text) {
        const modal = document.getElementById('helpModal');
        const titleNode = document.getElementById('helpModalTitle');
        const textNode = document.getElementById('helpModalText');
        if (titleNode) {
            titleNode.textContent = title || 'Quick Explanation';
        }
        if (textNode) {
            textNode.textContent = text || 'No extra explanation is available for this card yet.';
        }
        if (modal) {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }
    }

    function closeHelpModal() {
        const modal = document.getElementById('helpModal');
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, (char) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        }[char]));
    }

    function formatGhs(value) {
        const numeric = Number(value);
        if (!Number.isFinite(numeric)) {
            return 'Not available';
        }
        return `GHS ${numeric.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    }

    function getFilteredRows() {
        const searchValue = (document.getElementById('customerSearch')?.value || '').trim().toLowerCase();
        const segmentValue = document.getElementById('segmentFilter')?.value || '';
        const statusValue = document.getElementById('statusFilter')?.value || '';

        return CUSTOMER_ROWS.filter((row) => {
            const searchText = [
                row.customer_id,
                row.customer_name,
                row.customer_email,
                row.segment,
                row.risk_level,
                row.status
            ].join(' ').toLowerCase();
            const matchesSearch = !searchValue || searchText.includes(searchValue);
            const matchesSegment = !segmentValue || row.segment === segmentValue;
            const matchesStatus = !statusValue || row.status === statusValue;
            return matchesSearch && matchesSegment && matchesStatus;
        });
    }

    function renderCustomerTable() {
        const body = document.getElementById('customerTableBody');
        if (!body) {
            return;
        }

        const rows = getFilteredRows();
        const totalPages = Math.max(1, Math.ceil(rows.length / ROWS_PER_PAGE));
        activePage = Math.min(Math.max(1, activePage), totalPages);
        const startIndex = (activePage - 1) * ROWS_PER_PAGE;
        const pageRows = rows.slice(startIndex, startIndex + ROWS_PER_PAGE);

        body.innerHTML = pageRows.map((customer) => {
            const initialsSource = (customer.customer_name || customer.customer_id || 'C').trim();
            const initials = initialsSource
                .split(/\s+/)
                .map((part) => part.slice(0, 1).toUpperCase())
                .join('')
                .slice(0, 2) || 'C';
            const segmentKey = customer.segment_base_label || customer.segment || 'Unknown';
            const badgeClass = SEGMENT_BADGE_CLASSES[segmentKey]
                || SEGMENT_BADGE_CLASSES[customer.segment]
                || 'bg-gray-100 text-gray-800';
            const riskClass = customer.risk_level === 'High'
                ? 'bg-red-100 text-red-800'
                : customer.risk_level === 'Low'
                    ? 'bg-green-100 text-green-800'
                    : 'bg-orange-100 text-orange-800';
            const lastPurchaseSource = customer.last_purchase_date || customer.last_purchase || null;
            const lastPurchase = lastPurchaseSource
                ? new Date(lastPurchaseSource).toLocaleDateString()
                : 'N/A';

            return `
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary">${escapeHtml(initials)}</div>
                            <div>
                                <p class="font-medium text-primary">${escapeHtml(customer.customer_name || customer.customer_id || 'Customer')}</p>
                                <p class="text-xs text-slate-500">${escapeHtml(customer.customer_id || 'N/A')}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-4"><span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${escapeHtml(badgeClass)}">${escapeHtml(customer.segment || 'Unknown')}</span></td>
                    <td class="px-4 py-4 text-slate-500">${escapeHtml(lastPurchase)}</td>
                    <td class="px-4 py-4 text-slate-700">${escapeHtml(customer.frequency_count ?? 0)}</td>
                    <td class="px-4 py-4"><span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${escapeHtml(riskClass)}">${escapeHtml(customer.risk_level || 'Medium')}</span></td>
                    <td class="px-4 py-4">${escapeHtml(customer.status || 'Inactive')}</td>
                    <td class="px-4 py-4 text-right font-medium text-primary">${escapeHtml(formatGhs(customer.total_spend))}</td>
                </tr>
            `;
        }).join('');

        if (pageRows.length === 0) {
            body.innerHTML = '<tr><td colspan="7" class="px-4 py-10 text-center text-sm text-slate-500">No customers match your filters.</td></tr>';
        }

        const meta = document.getElementById('customerTableMeta');
        if (meta) {
            const endIndex = Math.min(rows.length, startIndex + pageRows.length);
            meta.textContent = rows.length
                ? `Showing ${startIndex + 1}-${endIndex} of ${rows.length} customers`
                : 'No customers to display';
        }

        const pageLabel = document.getElementById('pageLabel');
        if (pageLabel) {
            pageLabel.textContent = `Page ${activePage} of ${totalPages}`;
        }

        const prevButton = document.getElementById('prevPage');
        const nextButton = document.getElementById('nextPage');
        if (prevButton) prevButton.disabled = activePage <= 1;
        if (nextButton) nextButton.disabled = activePage >= totalPages;
        [prevButton, nextButton].forEach((button) => {
            if (!button) return;
            button.classList.toggle('opacity-50', button.disabled);
            button.classList.toggle('cursor-not-allowed', button.disabled);
        });
    }

    function activateChartTab(index) {
        document.querySelectorAll('.chart-tab').forEach((button) => {
            const isActive = Number(button.dataset.chartIndex) === index;
            button.classList.toggle('bg-primary', isActive);
            button.classList.toggle('text-white', isActive);
            button.classList.toggle('bg-slate-100', !isActive);
            button.classList.toggle('text-slate-600', !isActive);
        });
        document.querySelectorAll('.chart-panel').forEach((panel) => {
            panel.classList.toggle('hidden', Number(panel.dataset.chartIndex) !== index);
        });
    }

    document.getElementById('exportCsv').addEventListener('click', () => {
        if (!JOB_ID) {
            alert('CSV export requires a completed live analysis job.');
            return;
        }
        window.location.href = `api/process.php?action=download_csv&job_id=${encodeURIComponent(JOB_ID)}`;
    });

    document.getElementById('downloadPdf').addEventListener('click', () => {
        if (!JOB_ID) {
            alert('PDF download requires a completed live analysis job.');
            return;
        }
        window.location.href = `api/process.php?action=report&job_id=${encodeURIComponent(JOB_ID)}`;
    });

    document.getElementById('customerSearch')?.addEventListener('input', () => {
        activePage = 1;
        renderCustomerTable();
    });
    document.getElementById('segmentFilter')?.addEventListener('change', () => {
        activePage = 1;
        renderCustomerTable();
    });
    document.getElementById('statusFilter')?.addEventListener('change', () => {
        activePage = 1;
        renderCustomerTable();
    });
    document.getElementById('prevPage')?.addEventListener('click', () => {
        activePage -= 1;
        renderCustomerTable();
    });
    document.getElementById('nextPage')?.addEventListener('click', () => {
        activePage += 1;
        renderCustomerTable();
    });

    document.querySelectorAll('.chart-tab').forEach((button) => {
        button.addEventListener('click', () => activateChartTab(Number(button.dataset.chartIndex || 0)));
    });

    document.querySelectorAll('.segment-focus-btn').forEach((button) => {
        button.addEventListener('click', () => {
            const segment = button.dataset.segment || '';
            const segmentFilter = document.getElementById('segmentFilter');
            if (segmentFilter) {
                segmentFilter.value = segment;
            }
            activePage = 1;
            renderCustomerTable();
            document.getElementById('customerTableBody')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    document.querySelectorAll('.info-help-btn').forEach((button) => {
        button.addEventListener('click', () => {
            openHelpModal(button.dataset.helpTitle || 'Quick Explanation', button.dataset.helpText || '');
        });
    });

    document.getElementById('closeHelpModal')?.addEventListener('click', closeHelpModal);
    document.getElementById('dismissHelpModal')?.addEventListener('click', closeHelpModal);
    document.getElementById('helpModal')?.addEventListener('click', (event) => {
        if (event.target?.id === 'helpModal') {
            closeHelpModal();
        }
    });

    renderCustomerTable();
</script>
</body>
</html>
