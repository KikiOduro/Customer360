<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: signin.php');
    exit;
}

$userName     = $_SESSION['user_name']    ?? 'User';
$companyName  = $_SESSION['company_name'] ?? 'My Business';
$userInitials = strtoupper(substr($userName, 0, 1));
$currentPage  = 'analytics';
$authToken    = $_SESSION['auth_token']   ?? null;

// ── Resolve job ID ────────────────────────────────────────────────────────────
// Priority: URL param → session current_job → null
$jobId = $_GET['job_id']
      ?? $_SESSION['current_job']['job_id']
      ?? null;

// ── Load results ──────────────────────────────────────────────────────────────
$analysisResults = null;
$loadError       = null;

if ($jobId && $authToken) {
    // Fetch fresh results from the API proxy
    $context = stream_context_create([
        'http' => [
            'method'        => 'GET',
            'header'        => "Authorization: Bearer {$authToken}\r\nContent-Type: application/json",
            'timeout'       => 15,
            'ignore_errors' => true,
        ]
    ]);
    $raw = @file_get_contents(
        "http://localhost:8000/api/jobs/results/{$jobId}",
        false,
        $context
    );
    if ($raw) {
        $decoded = json_decode($raw, true);
        if (isset($decoded['meta'])) {
            $analysisResults = $decoded;
            // Cache in session for page reloads
            $_SESSION['analysis_results'] = $analysisResults;
        } else {
            $loadError = $decoded['detail'] ?? 'Could not load results.';
        }
    } else {
        $loadError = 'Could not reach the analysis server.';
    }
} elseif ($jobId && !$authToken) {
    // Demo mode: use session results if available
    $analysisResults = $_SESSION['analysis_results']
                    ?? $_SESSION['demo_results']
                    ?? null;
} else {
    // No job_id — fall back to whatever is in session
    $analysisResults = $_SESSION['analysis_results']
                    ?? $_SESSION['demo_results']
                    ?? null;
}

$isDemoMode   = !$authToken || ($analysisResults === null && $loadError === null);
$currentJob   = $_SESSION['current_job']   ?? null;
$currentUpload= $_SESSION['current_upload']?? null;
$uploadedFile = $currentJob['filename']    ?? $currentUpload['file_name'] ?? 'data.csv';
$batchId      = $jobId                     ?? rand(2000, 9999);

// ── Map pipeline output → page variables ──────────────────────────────────────
// The pipeline stores top-level keys: meta, segments, segment_summary, preprocessing
$meta    = $analysisResults['meta']            ?? [];
$summary = $analysisResults['segment_summary'] ?? [];
$prep    = $analysisResults['preprocessing']   ?? [];
$prepSum = $prep['summary']                    ?? [];

$kpis = [
    'total_revenue'    => $meta['total_revenue']          ?? $prepSum['total_revenue']    ?? 125000,
    'active_customers' => $meta['num_customers']           ?? $summary['total_customers']  ?? 1240,
    'avg_order_value'  => $prepSum['avg_transaction']      ?? 350,
    'num_transactions' => $meta['num_transactions']        ?? $prepSum['num_transactions'] ?? 0,
];

// ── Segment colors ────────────────────────────────────────────────────────────
$segmentColors = [
    'Champions'           => 'yellow',
    'Loyal Customers'     => 'blue',
    'Potential Loyalists' => 'green',
    'New Customers'       => 'cyan',
    'Promising'           => 'teal',
    'Need Attention'      => 'orange',
    'Needs Attention'     => 'orange',
    'About to Sleep'      => 'purple',
    'At Risk'             => 'red',
    'Cannot Lose'         => 'pink',
    'Hibernating'         => 'gray',
    'Lost Customers'      => 'slate',
    'Lost'                => 'slate',
];

// ── Build segments array ──────────────────────────────────────────────────────
// Pipeline field names: segment_label, num_customers, percentage, total_revenue,
//                       avg_recency, avg_frequency, avg_monetary, recommended_actions
if ($analysisResults && !empty($analysisResults['segments'])) {
    $segments = [];
    foreach ($analysisResults['segments'] as $seg) {
        $name = $seg['segment_label']                              // pipeline field
             ?? $seg['segment_name'] ?? $seg['name'] ?? 'Unknown';
        $segments[] = [
            'name'        => $name,
            'pct'         => round($seg['percentage']    ?? $seg['pct']         ?? 0),
            'count'       => $seg['num_customers']       ?? $seg['customer_count'] ?? $seg['count'] ?? 0,
            'revenue'     => $seg['total_revenue']       ?? 0,
            'avg_recency' => $seg['avg_recency']         ?? 0,
            'avg_freq'    => $seg['avg_frequency']       ?? 0,
            'avg_monetary'=> $seg['avg_monetary']        ?? 0,
            'actions'     => $seg['recommended_actions'] ?? [],
            'description' => $seg['description']         ?? '',
            'color'       => $segmentColors[$name]       ?? 'gray',
        ];
    }
} else {
    // Demo fallback
    $isDemoMode = true;
    $segments = [
        ['name'=>'Champions',         'pct'=>40,'count'=>496, 'revenue'=>62000,'avg_recency'=>5, 'avg_freq'=>12,'avg_monetary'=>2500,'actions'=>['Reward with exclusive loyalty programs','Offer early access to new products'],'description'=>'High spend, frequent','color'=>'yellow'],
        ['name'=>'Loyal Customers',   'pct'=>25,'count'=>310, 'revenue'=>31000,'avg_recency'=>15,'avg_freq'=>8, 'avg_monetary'=>1200,'actions'=>['Upsell premium products','Referral program incentives'],'description'=>'Steady purchases','color'=>'blue'],
        ['name'=>'At Risk',           'pct'=>15,'count'=>186, 'revenue'=>18600,'avg_recency'=>60,'avg_freq'=>3, 'avg_monetary'=>600, 'actions'=>['Launch urgent win-back campaigns','Personal outreach from customer service'],'description'=>'Need attention','color'=>'red'],
        ['name'=>'Potential Loyalists','pct'=>12,'count'=>149,'revenue'=>11920,'avg_recency'=>20,'avg_freq'=>4, 'avg_monetary'=>800, 'actions'=>['Offer membership programs','Send targeted recommendations'],'description'=>'New promising','color'=>'green'],
        ['name'=>'Hibernating',        'pct'=>8, 'count'=>99, 'revenue'=>7920, 'avg_recency'=>120,'avg_freq'=>1,'avg_monetary'=>200,'actions'=>['Send re-engagement emails','Offer comeback deals'],'description'=>'Inactive','color'=>'gray'],
    ];
}

// ── Recent customers ──────────────────────────────────────────────────────────
// Pipeline doesn't produce a recent_customers list; show segment leaders instead
$recent = [];
if ($analysisResults && !empty($analysisResults['recent_customers'])) {
    foreach (array_slice($analysisResults['recent_customers'], 0, 6) as $c) {
        $recent[] = [
            'name'          => $c['customer_name'] ?? $c['name']          ?? 'Customer',
            'email'         => $c['email']                                ?? 'N/A',
            'segment'       => $c['segment']                              ?? 'Unknown',
            'last_purchase' => $c['last_purchase_date'] ?? $c['last_purchase'] ?? 'N/A',
            'status'        => ($c['recency_days'] ?? 30) < 30            ? 'Active' : 'Inactive',
            'spend'         => $c['total_spend'] ?? $c['spend']           ?? 0,
        ];
    }
}
if (empty($recent)) {
    $recent = [
        ['name'=>'Abena Boakye', 'email'=>'abena@example.com',  'segment'=>'Champions',      'last_purchase'=>'Oct 24, 2023','status'=>'Active',  'spend'=>2450],
        ['name'=>'Kwesi Mensah', 'email'=>'kwesi.m@example.com','segment'=>'Loyal Customers','last_purchase'=>'Oct 22, 2023','status'=>'Active',  'spend'=>850],
        ['name'=>'Yaw Addo',     'email'=>'yaw.addo@example.com','segment'=>'At Risk',        'last_purchase'=>'Aug 15, 2023','status'=>'Inactive','spend'=>1200],
    ];
}

// ── Helper functions ──────────────────────────────────────────────────────────
function ghc($n) { return 'GH₵ ' . number_format((float)$n, 2); }

function getSegmentBgClass($c) {
    $m = [
        'yellow'=>'bg-yellow-100 text-yellow-800','blue'  =>'bg-blue-100 text-blue-800',
        'green' =>'bg-green-100 text-green-800',  'red'   =>'bg-red-100 text-red-800',
        'orange'=>'bg-orange-100 text-orange-800','purple'=>'bg-purple-100 text-purple-800',
        'teal'  =>'bg-teal-100 text-teal-800',    'cyan'  =>'bg-cyan-100 text-cyan-800',
        'pink'  =>'bg-pink-100 text-pink-800',    'slate' =>'bg-slate-100 text-slate-800',
        'gray'  =>'bg-gray-100 text-gray-800',
    ];
    return $m[$c] ?? $m['gray'];
}
function getSegmentBarClass($c) {
    $m = [
        'yellow'=>'bg-yellow-400','blue'  =>'bg-blue-500',  'green' =>'bg-green-500',
        'red'   =>'bg-red-500',   'orange'=>'bg-orange-500','purple'=>'bg-purple-500',
        'teal'  =>'bg-teal-500',  'cyan'  =>'bg-cyan-500',  'pink'  =>'bg-pink-500',
        'slate' =>'bg-slate-500', 'gray'  =>'bg-gray-400',
    ];
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
            theme: { extend: {
                colors: { "primary": "#0b203c", "primary-hover": "#153055", "background-light": "#f6f7f8", "accent": "#e8b031" },
                fontFamily: { "display": ["Inter", "sans-serif"] }
            }}
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
    </style>
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
            <?php
            $navItems = [
                ['dashboard','dashboard','Dashboard'],
                ['upload','upload_file','Upload Data'],
                ['analytics','analytics','Analytics'],
                ['reports','bar_chart','Reports'],
            ];
            foreach ($navItems as [$page, $icon, $label]):
            ?>
            <a class="flex items-center gap-3 rounded-lg <?php echo $currentPage===$page ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white'; ?> px-4 py-3 text-sm font-medium transition-colors" href="<?php echo $page; ?>.php">
                <span class="material-symbols-outlined"><?php echo $icon; ?></span><?php echo $label; ?>
            </a>
            <?php endforeach; ?>
            <div class="my-4 border-t border-slate-700/50"></div>
            <a class="flex items-center gap-3 rounded-lg text-slate-300 hover:bg-white/5 hover:text-white px-4 py-3 text-sm font-medium transition-colors" href="help.php">
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

    <!-- Main -->
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
                <?php if($loadError): ?>
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

            <!-- Page header -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8 gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-primary tracking-tight">Analytics Overview</h1>
                    <p class="text-slate-500 mt-1">
                        <?php if($jobId): ?>
                            Job <code class="bg-slate-100 px-1.5 py-0.5 rounded text-xs"><?php echo htmlspecialchars(substr($jobId,0,8)); ?>…</code>
                            — <?php echo htmlspecialchars($uploadedFile); ?>
                        <?php else: ?>
                            Showing demo data
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

            <!-- KPI Cards -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-8">
                <?php
                $kpiCards = [
                    ['Total Revenue',      ghc($kpis['total_revenue']),           'payments',      'blue'],
                    ['Active Customers',   number_format($kpis['active_customers']),'group',        'purple'],
                    ['Avg Transaction',    ghc($kpis['avg_order_value']),          'shopping_cart', 'orange'],
                    ['Total Transactions', number_format($kpis['num_transactions']),'receipt_long', 'green'],
                ];
                foreach ($kpiCards as [$label, $value, $icon, $color]):
                ?>
                <div class="flex flex-col rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="mb-2 flex items-center justify-between">
                        <span class="text-sm font-medium text-slate-500"><?php echo $label; ?></span>
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-<?php echo $color; ?>-50 text-<?php echo $color; ?>-600">
                            <span class="material-symbols-outlined text-[20px]"><?php echo $icon; ?></span>
                        </span>
                    </div>
                    <h3 class="text-2xl font-bold text-primary"><?php echo $value; ?></h3>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Segments -->
            <div class="mb-8">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-bold text-primary">Customer Segments</h2>
                    <?php if(!empty($analysisResults['meta']['silhouette_score'])): ?>
                    <span class="text-xs text-slate-500 bg-slate-100 px-3 py-1 rounded-full">
                        Silhouette score: <strong><?php echo round($analysisResults['meta']['silhouette_score'],3); ?></strong>
                    </span>
                    <?php endif; ?>
                </div>
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
                                    <p class="text-xs text-slate-500"><?php echo htmlspecialchars($s['description'] ?: number_format($s['count']).' customers'); ?></p>
                                </div>
                            </div>
                            <span class="font-bold text-primary"><?php echo $s['pct']; ?>%</span>
                        </div>
                        <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full <?php echo getSegmentBarClass($s['color']); ?>" style="width:<?php echo $s['pct']; ?>%"></div>
                        </div>
                        <div class="mt-3 grid grid-cols-3 gap-2 text-center">
                            <div>
                                <p class="text-xs text-slate-400">Recency</p>
                                <p class="text-sm font-semibold text-primary"><?php echo round($s['avg_recency']); ?>d</p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-400">Frequency</p>
                                <p class="text-sm font-semibold text-primary"><?php echo round($s['avg_freq'],1); ?>x</p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-400">Avg Spend</p>
                                <p class="text-sm font-semibold text-primary"><?php echo ghc($s['avg_monetary']); ?></p>
                            </div>
                        </div>
                        <?php if(!empty($s['actions'])): ?>
                        <div class="mt-3 pt-3 border-t border-slate-100">
                            <p class="text-xs font-semibold text-slate-500 mb-1">Top Action</p>
                            <p class="text-xs text-slate-600"><?php echo htmlspecialchars($s['actions'][0]); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- SHAP Feature Importance (shown when real pipeline data available) -->
            <?php if(!empty($analysisResults['shap']['ranked_features'])): ?>
            <div class="mb-8 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-xl font-bold text-primary mb-4">Feature Importance (SHAP)</h2>
                <p class="text-sm text-slate-500 mb-4">Which RFM factors most influenced the segmentation.</p>
                <div class="space-y-3">
                    <?php
                    $shap   = $analysisResults['shap']['ranked_features'];
                    $maxVal = $shap[0]['importance'] ?? 1;
                    foreach ($shap as $f):
                        $pct = $maxVal > 0 ? round($f['importance'] / $maxVal * 100) : 0;
                    ?>
                    <div class="flex items-center gap-4">
                        <div class="w-24 text-sm font-medium text-primary capitalize"><?php echo htmlspecialchars($f['feature']); ?></div>
                        <div class="flex-1 h-3 bg-slate-100 rounded-full overflow-hidden">
                            <div class="h-full bg-primary rounded-full" style="width:<?php echo $pct; ?>%"></div>
                        </div>
                        <div class="w-16 text-right text-sm text-slate-500"><?php echo round($f['importance'],4); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

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
                                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary">
                                            <?php echo strtoupper(substr($r['name'],0,2)); ?>
                                        </div>
                                        <div>
                                            <p class="font-medium text-primary"><?php echo htmlspecialchars($r['name']); ?></p>
                                            <p class="text-xs text-slate-500"><?php echo htmlspecialchars($r['email']); ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <?php $sc = $segmentColors[$r['segment']] ?? 'gray'; ?>
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium <?php echo getSegmentBgClass($sc); ?>">
                                        <?php echo htmlspecialchars($r['segment']); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-slate-500"><?php echo htmlspecialchars($r['last_purchase']); ?></td>
                                <td class="px-4 py-4">
                                    <div class="flex items-center gap-2">
                                        <div class="h-2 w-2 rounded-full <?php echo ($r['status']==='Active') ? 'bg-green-500' : 'bg-gray-300'; ?>"></div>
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

        </div><!-- /content -->
    </main>
</div>

<!-- Mobile overlay & sidebar -->
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

    function toggleUserMenu()  { document.getElementById('userMenu').classList.toggle('hidden'); }
    function toggleMobileMenu() {
        document.getElementById('mobileSidebar').classList.toggle('-translate-x-full');
        document.getElementById('mobileMenuOverlay').classList.toggle('hidden');
    }

    // CSV export
    document.getElementById('exportCsv').addEventListener('click', () => {
        const rows = [['Customer','Email','Segment','Last Purchase','Status','Total Spend']];
        <?php foreach($recent as $r): ?>
        rows.push([
            "<?php echo addslashes($r['name']); ?>",
            "<?php echo addslashes($r['email']); ?>",
            "<?php echo addslashes($r['segment']); ?>",
            "<?php echo addslashes($r['last_purchase']); ?>",
            "<?php echo addslashes($r['status']); ?>",
            "<?php echo number_format($r['spend'],2); ?>"
        ]);
        <?php endforeach; ?>
        const csv  = rows.map(r => r.map(c => `"${String(c).replace(/"/g,'""')}"`).join(',')).join('\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const a    = document.createElement('a');
        a.href     = URL.createObjectURL(blob);
        a.download = 'customer360_segments.csv';
        a.click();
    });

    // PDF download — hit the FastAPI report endpoint via the PHP proxy
    document.getElementById('downloadPdf').addEventListener('click', () => {
        if (!JOB_ID || JOB_ID.startsWith('demo_')) {
            alert('PDF download requires a real analysis job.\nExport to CSV instead.');
            return;
        }
        window.location.href = `api/process.php?action=report&job_id=${encodeURIComponent(JOB_ID)}`;
    });
</script>
</body>
</html>