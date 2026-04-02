<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: signin.php');
    exit;
}

$upload = $_SESSION['current_upload'] ?? null;
$uploadedFile = $upload['filename'] ?? $_SESSION['uploaded_file'] ?? '';
$availableColumns = $upload['columns'] ?? [];
$sampleRows = $upload['sample_rows'] ?? [];
$suggestedMapping = $upload['suggested_mapping'] ?? null;
$columnProfiles = $upload['column_profiles'] ?? [];
$rawRows = $upload['raw_rows'] ?? null;
$totalRows = $upload['total_rows'] ?? null;
$removedBlankRows = $upload['removed_blank_rows'] ?? 0;
$hasUploadPreview = $uploadedFile !== '' && !empty($availableColumns);

if (!$hasUploadPreview) {
    header('Location: upload.php?missing_preview=1');
    exit;
}

$requiredFields = [
    [
        'id' => 'customer_id',
        'label' => 'Customer ID',
        'hint' => null,
        'sample' => 'CUST-001',
        'tooltip' => 'Pick the column that identifies the same customer across multiple purchases. If this is missing, Customer 360 cannot compute true repeat-customer Frequency unless you explicitly enable synthetic IDs.'
    ],
    [
        'id' => 'date',
        'label' => 'Date',
        'hint' => '(DD/MM/YYYY)',
        'sample' => '12/10/2023',
        'tooltip' => 'Pick the transaction date column used to calculate Recency. If day/month order is ambiguous, set the exact format in the parsing rules section.'
    ],
    [
        'id' => 'invoice_id',
        'label' => 'Invoice ID',
        'hint' => null,
        'sample' => 'INV-2023-884',
        'tooltip' => 'Pick the order or invoice identifier so Frequency counts purchases correctly. If your CSV is line-item level, multiple rows can share the same Invoice ID.'
    ],
    [
        'id' => 'amount',
        'label' => 'Amount',
        'hint' => '(direct total)',
        'sample' => '2,450.00',
        'tooltip' => 'Pick the transaction total if your file already has one amount column. If not, switch Amount Source to Formula and map Quantity + Unit Price so the backend derives Amount = Quantity × Unit Price.'
    ],
];

$optionalFields = [
    ['id' => 'quantity', 'label' => 'Quantity', 'sample' => '6', 'tooltip' => 'Use this with Unit Price when your file does not contain a direct line-total amount'],
    ['id' => 'unit_price', 'label' => 'Unit Price', 'sample' => '2.55', 'tooltip' => 'Per-unit price used to derive Amount = Quantity × Unit Price'],
    ['id' => 'product', 'label' => 'Product', 'sample' => 'WHITE METAL LANTERN', 'tooltip' => 'Optional field for segment explanations'],
    ['id' => 'category', 'label' => 'Category', 'sample' => 'Home Decor', 'tooltip' => 'Optional field for segment summaries'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Customer 360 - Column Mapping</title>
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
                        "primary-subtle": "#eef2f6",
                        "border-subtle": "#d1dae5",
                    },
                    fontFamily: { "display": ["Inter", "sans-serif"], "body": ["Inter", "sans-serif"] },
                    borderRadius: { "DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "full": "9999px" },
                },
            },
        }
    </script>
    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 8px; height: 8px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #f1f1f1; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #c1c1c1; border-radius: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #a8a8a8; }
        select.mapped { border-color: #22c55e; background-color: #f0fdf4; }
        .required-star { color: #dc2626; font-weight: 900; }
        .field-help-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 18px;
            height: 18px;
            border-radius: 9999px;
            border: 1px solid #cbd5e1;
            color: #536e93;
            background: #f8fafc;
            font-size: 11px;
            font-weight: 800;
            line-height: 1;
            transition: all .2s ease;
        }
        .field-help-btn:hover {
            border-color: #0b203c;
            background: #eef2f6;
            color: #0b203c;
        }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark text-primary dark:text-white font-display min-h-screen flex flex-col overflow-x-hidden">
    <header class="flex items-center justify-between border-b border-solid border-b-border-subtle bg-white dark:bg-background-dark px-6 py-3 sticky top-0 z-50">
        <div class="flex items-center gap-4 text-primary dark:text-white">
            <a href="dashboard.php" class="flex items-center gap-4">
                <div class="size-8 flex items-center justify-center bg-primary/10 rounded-lg text-primary">
                    <span class="material-symbols-outlined text-[24px]">grid_view</span>
                </div>
                <h2 class="text-lg font-bold tracking-[-0.015em]">Customer 360</h2>
            </a>
        </div>
    </header>

    <main class="flex-1 flex justify-center py-8 px-4 sm:px-6 lg:px-8">
        <div class="w-full max-w-[1024px] flex flex-col gap-6">
            <div class="flex flex-col gap-2">
                <h1 class="text-3xl font-black tracking-[-0.033em]">Column Mapping</h1>
                <p class="text-[#536e93] text-base leading-normal">Match the columns from your uploaded file <span class="font-medium text-primary bg-primary/5 px-1 py-0.5 rounded"><?php echo htmlspecialchars($uploadedFile); ?></span> to the required Customer 360 fields below.</p>
                <?php if ($totalRows !== null || $rawRows !== null): ?>
                <p class="text-xs uppercase tracking-[0.2em] text-slate-500">
                    Parsed rows: <?php echo number_format((int) ($totalRows ?? 0)); ?>
                    <?php if ($rawRows !== null): ?> / raw rows: <?php echo number_format((int) $rawRows); ?><?php endif; ?>
                    <?php if ((int) $removedBlankRows > 0): ?> / blank rows removed: <?php echo number_format((int) $removedBlankRows); ?><?php endif; ?>
                </p>
                <?php endif; ?>
            </div>
            <div id="warningBanner" class="@container">
                <div class="flex flex-col items-start justify-between gap-4 rounded-lg border border-yellow-200 bg-yellow-50 dark:bg-yellow-900/20 dark:border-yellow-800 p-4 sm:flex-row sm:items-center">
                    <div class="flex items-start gap-3">
                        <span class="material-symbols-outlined text-yellow-600 dark:text-yellow-400 mt-0.5">warning</span>
                        <div class="flex flex-col gap-1">
                            <p class="text-sm font-bold leading-tight">Check your data formatting</p>
                            <p class="text-[#536e93] dark:text-gray-400 text-sm leading-normal">Ensure your dates and numeric columns match your uploaded data format before continuing.</p>
                        </div>
                    </div>
                    <button onclick="dismissWarning()" class="hover:bg-yellow-100 dark:hover:bg-yellow-800/40 px-3 py-1.5 rounded-md text-sm font-medium transition-colors whitespace-nowrap">Dismiss</button>
                </div>
            </div>

            <div class="rounded-2xl border border-blue-100 bg-blue-50 p-5 text-sm text-slate-700 shadow-sm dark:border-blue-900/40 dark:bg-blue-900/10 dark:text-slate-200">
                <div class="flex items-start gap-3">
                    <span class="material-symbols-outlined text-blue-700 dark:text-blue-300">school</span>
                    <div class="space-y-2">
                        <p class="font-bold text-primary dark:text-white">RFM data checklist before you continue</p>
                        <ul class="grid gap-2 md:grid-cols-2">
                            <li><strong>Customer ID:</strong> should identify the same customer across purchases. Rows without this will be excluded unless you enable synthetic IDs.</li>
                            <li><strong>Date:</strong> choose the exact date format if day/month order is ambiguous.</li>
                            <li><strong>Frequency:</strong> map Invoice ID so line-item files do not overcount purchases.</li>
                            <li><strong>Monetary:</strong> if there is no direct total amount column, derive amount from Quantity × Unit Price.</li>
                            <li><strong>Refunds:</strong> choose whether negative values should be excluded, kept as refunds, or converted to positive values.</li>
                            <li><strong>Blank rows:</strong> spreadsheet exports with empty trailing rows are removed before analysis.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div id="mappingStatusCard" class="hidden rounded-xl border border-border-subtle bg-white p-4 shadow-sm">
                <div class="flex items-start gap-3">
                    <div id="mappingStatusIcon" class="mt-0.5 flex h-9 w-9 items-center justify-center rounded-full bg-slate-100 text-slate-600">
                        <span class="material-symbols-outlined">check_circle</span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p id="mappingStatusTitle" class="text-sm font-bold text-primary">Mapping status</p>
                        <p id="mappingStatusMessage" class="mt-1 text-sm text-[#536e93]">Confirm the required fields and continue to the live processing page.</p>
                    </div>
                </div>
            </div>

            <form id="mappingForm">
                <div class="bg-white dark:bg-[#1a222c] rounded-xl border border-border-subtle shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-border-subtle bg-primary-subtle/30 dark:bg-primary/20 flex items-center justify-between">
                        <h2 class="text-lg font-bold">1. Map Columns</h2>
                        <span id="fieldsCounter" class="text-xs font-medium text-[#536e93] uppercase tracking-wider"><span id="mappedCount">0</span> of <?php echo count($requiredFields); ?> Fields Mapped</span>
                    </div>
                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <?php foreach ($requiredFields as $field): ?>
                        <div class="flex flex-col gap-2">
                            <label class="flex items-center gap-1.5 text-sm font-semibold">
                                <?php echo htmlspecialchars($field['label']); ?>
                                <span class="required-star">*</span>
                                <button
                                    type="button"
                                    class="field-help-btn"
                                    onclick="openFieldHelp('<?php echo htmlspecialchars($field['label'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($field['tooltip'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($field['sample'], ENT_QUOTES); ?>', true)"
                                    aria-label="Learn more about <?php echo htmlspecialchars($field['label']); ?>"
                                >?</button>
                                <?php if ($field['hint']): ?>
                                <span class="text-xs font-normal text-[#536e93]"><?php echo htmlspecialchars($field['hint']); ?></span>
                                <?php endif; ?>
                            </label>
                            <div class="relative">
                                <select name="mapping[<?php echo $field['id']; ?>]" class="mapping-select w-full appearance-none rounded-lg border border-border-subtle bg-white dark:bg-background-dark py-2.5 pl-3 pr-10 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary" onchange="updateMappingStatus()" required>
                                    <option disabled selected value="">Select column...</option>
                                    <?php foreach ($availableColumns as $column): ?>
                                    <option value="<?php echo htmlspecialchars($column); ?>"><?php echo htmlspecialchars($column); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-[#536e93]"><span class="material-symbols-outlined text-[20px]">expand_more</span></div>
                            </div>
                            <p class="text-xs text-[#536e93]">Sample: <span class="font-mono bg-primary-subtle px-1 rounded"><?php echo htmlspecialchars($field['sample']); ?></span></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="mt-6 grid gap-6 lg:grid-cols-2">
                    <div class="rounded-xl border border-border-subtle bg-white p-6 shadow-sm dark:bg-[#1a222c]">
                        <div class="flex items-center justify-between border-b border-border-subtle pb-4">
                            <h2 class="text-lg font-bold">2. Amount & Date Parsing Rules</h2>
                        </div>
                        <div class="mt-5 grid gap-4 md:grid-cols-2">
                            <div class="flex flex-col gap-2">
                                <label class="text-sm font-semibold">Amount source</label>
                                <select id="amountSourceMode" class="rounded-lg border border-border-subtle bg-white py-2.5 px-3 text-sm dark:bg-background-dark" onchange="updateAmountMode(); updateMappingStatus();">
                                    <option value="direct">Use direct Amount column</option>
                                    <option value="formula">Derive Amount = Quantity × Unit Price</option>
                                </select>
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="text-sm font-semibold">Date format</label>
                                <select id="invoiceDateFormat" class="rounded-lg border border-border-subtle bg-white py-2.5 px-3 text-sm dark:bg-background-dark">
                                    <option value="">Auto detect</option>
                                    <option value="%Y-%m-%d">YYYY-MM-DD</option>
                                    <option value="%d/%m/%Y">DD/MM/YYYY</option>
                                    <option value="%m/%d/%Y">MM/DD/YYYY</option>
                                    <option value="%d/%m/%Y %H:%M">DD/MM/YYYY HH:MM</option>
                                    <option value="%m/%d/%Y %H:%M">MM/DD/YYYY HH:MM</option>
                                    <option value="%Y-%m-%d %H:%M:%S">YYYY-MM-DD HH:MM:SS</option>
                                </select>
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="text-sm font-semibold">Decimal separator</label>
                                <select id="decimalSeparator" class="rounded-lg border border-border-subtle bg-white py-2.5 px-3 text-sm dark:bg-background-dark">
                                    <option value=".">Dot .</option>
                                    <option value=",">Comma ,</option>
                                </select>
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="text-sm font-semibold">Thousands separator</label>
                                <select id="thousandsSeparator" class="rounded-lg border border-border-subtle bg-white py-2.5 px-3 text-sm dark:bg-background-dark">
                                    <option value=",">Comma ,</option>
                                    <option value=".">Dot .</option>
                                    <option value="">None</option>
                                </select>
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="text-sm font-semibold">Currency symbol</label>
                                <input id="currencySymbol" type="text" maxlength="8" placeholder="e.g. GHS, $, €" class="rounded-lg border border-border-subtle bg-white py-2.5 px-3 text-sm dark:bg-background-dark"/>
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="text-sm font-semibold">Negative amount policy</label>
                                <select id="negativeAmountPolicy" class="rounded-lg border border-border-subtle bg-white py-2.5 px-3 text-sm dark:bg-background-dark">
                                    <option value="exclude">Exclude negatives</option>
                                    <option value="keep">Keep as refunds</option>
                                    <option value="absolute">Convert to positive</option>
                                </select>
                            </div>
                        </div>
                        <div class="mt-4 flex flex-col gap-2 rounded-lg bg-slate-50 p-3 text-xs text-slate-600">
                            <label class="inline-flex items-center gap-2"><input id="dayfirstFlag" type="checkbox" class="rounded border-border-subtle text-primary"> Day comes first in ambiguous dates like 12/10/2023</label>
                        </div>
                    </div>

                    <div class="rounded-xl border border-border-subtle bg-white p-6 shadow-sm dark:bg-[#1a222c]">
                        <div class="flex items-center justify-between border-b border-border-subtle pb-4">
                            <h2 class="text-lg font-bold">3. Optional Columns & Fallback Rules</h2>
                        </div>
                        <div class="mt-5 grid gap-4 md:grid-cols-2">
                            <?php foreach ($optionalFields as $field): ?>
                            <div class="flex flex-col gap-2">
                                <label class="flex items-center gap-1.5 text-sm font-semibold">
                                    <?php echo htmlspecialchars($field['label']); ?>
                                    <button
                                        type="button"
                                        class="field-help-btn"
                                        onclick="openFieldHelp('<?php echo htmlspecialchars($field['label'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($field['tooltip'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($field['sample'], ENT_QUOTES); ?>', false)"
                                        aria-label="Learn more about <?php echo htmlspecialchars($field['label']); ?>"
                                    >?</button>
                                </label>
                                <select name="mapping[<?php echo $field['id']; ?>]" class="optional-mapping-select rounded-lg border border-border-subtle bg-white py-2.5 px-3 text-sm dark:bg-background-dark" onchange="updateMappingStatus()">
                                    <option value="">Not mapped</option>
                                    <?php foreach ($availableColumns as $column): ?>
                                    <option value="<?php echo htmlspecialchars($column); ?>"><?php echo htmlspecialchars($column); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="text-xs text-[#536e93]">Sample: <span class="font-mono bg-primary-subtle px-1 rounded"><?php echo htmlspecialchars($field['sample']); ?></span></p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-4 space-y-2 rounded-lg bg-amber-50 p-3 text-xs text-amber-800">
                            <label class="flex items-start gap-2"><input id="allowSyntheticCustomerId" type="checkbox" class="mt-0.5 rounded border-amber-300 text-primary"> Allow synthetic customer IDs if no customer column exists. This is experimental and not true repeat-customer RFM.</label>
                            <label class="flex items-start gap-2"><input id="allowSyntheticInvoiceDate" type="checkbox" class="mt-0.5 rounded border-amber-300 text-primary"> Allow synthetic dates if no date column exists. This makes Recency approximate, not exact.</label>
                        </div>
                    </div>
                </div>
            </form>

            <?php if (!empty($columnProfiles)): ?>
            <div class="bg-white dark:bg-[#1a222c] rounded-xl border border-border-subtle shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-border-subtle flex items-center justify-between">
                    <h2 class="text-lg font-bold">Column Profiler</h2>
                    <span class="text-xs uppercase tracking-[0.2em] text-[#536e93]"><?php echo count($columnProfiles); ?> columns detected</span>
                </div>
                <div class="overflow-x-auto custom-scrollbar">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-primary-subtle dark:bg-primary/40 font-semibold">
                            <tr>
                                <th class="px-6 py-3 border-b border-border-subtle">Column</th>
                                <th class="px-6 py-3 border-b border-border-subtle">Detected Type</th>
                                <th class="px-6 py-3 border-b border-border-subtle">Suggested Role</th>
                                <th class="px-6 py-3 border-b border-border-subtle">Null %</th>
                                <th class="px-6 py-3 border-b border-border-subtle">Unique %</th>
                                <th class="px-6 py-3 border-b border-border-subtle">Sample Values</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-subtle dark:divide-white/10">
                            <?php foreach ($columnProfiles as $profile): ?>
                            <tr class="hover:bg-primary-subtle/50 dark:hover:bg-primary/20 transition-colors">
                                <td class="px-6 py-3 font-semibold"><?php echo htmlspecialchars((string) ($profile['column_name'] ?? '')); ?></td>
                                <td class="px-6 py-3"><?php echo htmlspecialchars((string) ($profile['detected_type'] ?? '')); ?> (<?php echo htmlspecialchars((string) ($profile['type_confidence'] ?? 0)); ?>)</td>
                                <td class="px-6 py-3"><?php echo htmlspecialchars((string) ($profile['semantic_guess'] ?? 'No strong guess')); ?></td>
                                <td class="px-6 py-3"><?php echo number_format((float) (($profile['null_rate'] ?? 0) * 100), 1); ?>%</td>
                                <td class="px-6 py-3"><?php echo number_format((float) (($profile['unique_ratio'] ?? 0) * 100), 1); ?>%</td>
                                <td class="px-6 py-3 max-w-sm truncate"><?php echo htmlspecialchars(implode(' | ', $profile['sample_values'] ?? [])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <div class="rounded-xl border border-border-subtle bg-white shadow-sm overflow-hidden dark:bg-[#1a222c]">
                <div class="px-6 py-4 border-b border-border-subtle flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-bold">Validation Report</h2>
                        <p class="text-sm text-[#536e93]">Run a parser check before analysis so you can see dropped rows, customer coverage, and the canonicalized preview.</p>
                    </div>
                    <button type="button" onclick="runValidation()" id="validateBtn" class="flex min-w-[160px] items-center justify-center rounded-lg h-10 px-4 border border-primary/20 bg-primary-subtle text-sm font-bold text-primary hover:bg-primary/10 transition-colors">
                        Validate Mapping
                    </button>
                </div>
                <div id="validationReportBody" class="hidden p-6 space-y-6">
                    <div class="grid gap-4 md:grid-cols-4">
                        <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs uppercase tracking-wide text-[#536e93]">Usable Rows</p><p id="metricRowsUsable" class="mt-2 text-2xl font-black">--</p></div>
                        <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs uppercase tracking-wide text-[#536e93]">Customers</p><p id="metricCustomerCount" class="mt-2 text-2xl font-black">--</p></div>
                        <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs uppercase tracking-wide text-[#536e93]">Invoices</p><p id="metricInvoiceCount" class="mt-2 text-2xl font-black">--</p></div>
                        <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs uppercase tracking-wide text-[#536e93]">Total Revenue</p><p id="metricTotalRevenue" class="mt-2 text-2xl font-black">--</p></div>
                    </div>
                    <div>
                        <p class="text-sm font-bold mb-3">Notices</p>
                        <div id="validationNotices" class="space-y-2"></div>
                    </div>
                    <div class="rounded-xl border border-border-subtle overflow-hidden">
                        <div class="bg-primary-subtle px-4 py-3 text-sm font-bold">Canonical Preview</div>
                        <div class="overflow-x-auto custom-scrollbar">
                            <table class="w-full text-left text-xs whitespace-nowrap">
                                <thead id="canonicalPreviewHead" class="bg-slate-50 font-semibold"></thead>
                                <tbody id="canonicalPreviewBody" class="divide-y divide-border-subtle"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-[#1a222c] rounded-xl border border-border-subtle shadow-sm overflow-hidden flex flex-col">
                <div class="px-6 py-4 border-b border-border-subtle flex items-center justify-between">
                    <h2 class="text-lg font-bold">Data Preview</h2>
                    <div class="flex items-center gap-2 text-sm text-[#536e93]">
                        <span class="material-symbols-outlined text-[18px]">table_rows</span>
                        Showing first <?php echo count($sampleRows); ?> rows
                    </div>
                </div>
                <div class="overflow-x-auto custom-scrollbar">
                    <table class="w-full text-left text-sm whitespace-nowrap">
                        <thead class="bg-primary-subtle dark:bg-primary/40 font-semibold">
                            <tr>
                                <th class="px-6 py-3 border-b border-border-subtle">Row</th>
                                <?php foreach ($availableColumns as $column): ?>
                                <th class="px-6 py-3 border-b border-border-subtle"><?php echo htmlspecialchars($column); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-subtle dark:divide-white/10">
                            <?php foreach ($sampleRows as $index => $row): ?>
                            <tr class="hover:bg-primary-subtle/50 dark:hover:bg-primary/20 transition-colors">
                                <td class="px-6 py-3 text-[#536e93]"><?php echo $index + 1; ?></td>
                                <?php foreach ($availableColumns as $column): ?>
                                <td class="px-6 py-3"><?php echo htmlspecialchars($row[$column] ?? ''); ?></td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 pb-12">
                <button type="button" onclick="window.location.href='upload.php'" class="flex min-w-[100px] items-center justify-center rounded-lg h-10 px-4 border border-border-subtle bg-white text-sm font-bold hover:bg-gray-50 transition-colors">Back</button>
                <button type="button" onclick="submitMapping()" id="continueBtn" disabled class="flex min-w-[100px] items-center justify-center rounded-lg h-10 px-4 bg-primary text-white text-sm font-bold hover:bg-[#1a3b66] transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">Continue</button>
            </div>
        </div>
    </main>

    <div id="fieldHelpModal" class="fixed inset-0 z-[70] hidden">
        <div class="absolute inset-0 bg-slate-900/55" onclick="closeFieldHelp()"></div>
        <div class="relative mx-auto mt-24 w-[92%] max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-700 dark:bg-[#1a222c]">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p id="fieldHelpBadge" class="text-xs font-black uppercase tracking-[0.2em] text-red-600">Required Field</p>
                    <h3 id="fieldHelpTitle" class="mt-2 text-2xl font-black text-primary dark:text-white">Field details</h3>
                </div>
                <button type="button" onclick="closeFieldHelp()" class="rounded-full p-2 text-slate-400 transition-colors hover:bg-slate-100 hover:text-primary dark:hover:bg-slate-800">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <p id="fieldHelpText" class="mt-4 text-sm leading-6 text-slate-600 dark:text-slate-300"></p>
            <div class="mt-5 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-[#536e93]">Example value</p>
                <p id="fieldHelpSample" class="mt-2 font-mono text-base font-bold text-primary dark:text-white"></p>
            </div>
        </div>
    </div>

    <script>
        let validationReady = false;

        function openFieldHelp(title, text, sample, required) {
            document.getElementById('fieldHelpTitle').textContent = title || 'Field details';
            document.getElementById('fieldHelpText').textContent = text || 'No extra description was provided for this field.';
            document.getElementById('fieldHelpSample').textContent = sample || 'Not available';
            const badge = document.getElementById('fieldHelpBadge');
            badge.textContent = required ? 'Required Field' : 'Optional Field';
            badge.className = required
                ? 'text-xs font-black uppercase tracking-[0.2em] text-red-600'
                : 'text-xs font-black uppercase tracking-[0.2em] text-[#536e93]';
            document.getElementById('fieldHelpModal').classList.remove('hidden');
        }

        function closeFieldHelp() {
            document.getElementById('fieldHelpModal').classList.add('hidden');
        }

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function collectMappingPayload() {
            const mappings = {};
            document.querySelectorAll('.mapping-select, .optional-mapping-select').forEach(select => {
                const fieldName = select.name.match(/\[(.+)\]/)[1];
                mappings[fieldName] = select.value;
            });

            return {
                mapping: mappings,
                amount_source_mode: document.getElementById('amountSourceMode').value,
                invoice_date_format: document.getElementById('invoiceDateFormat').value,
                dayfirst: document.getElementById('dayfirstFlag').checked,
                decimal_separator: document.getElementById('decimalSeparator').value,
                thousands_separator: document.getElementById('thousandsSeparator').value,
                currency_symbol: document.getElementById('currencySymbol').value,
                negative_amount_policy: document.getElementById('negativeAmountPolicy').value,
                allow_synthetic_customer_id: document.getElementById('allowSyntheticCustomerId').checked,
                allow_synthetic_invoice_date: document.getElementById('allowSyntheticInvoiceDate').checked
            };
        }

        function resetValidationState() {
            validationReady = false;
            document.getElementById('validationReportBody').classList.add('hidden');
        }

        function updateMappingStatus() {
            const selects = document.querySelectorAll('.mapping-select');
            const allSelects = document.querySelectorAll('.mapping-select, .optional-mapping-select');
            let mappedCount = 0;
            const selectedValues = [];
            const amountMode = document.getElementById('amountSourceMode')?.value || 'direct';
            const syntheticCustomerAllowed = document.getElementById('allowSyntheticCustomerId')?.checked || false;
            const syntheticDateAllowed = document.getElementById('allowSyntheticInvoiceDate')?.checked || false;

            allSelects.forEach(select => {
                if (select.value) {
                    selectedValues.push(select.value);
                    select.classList.add('mapped');
                } else {
                    select.classList.remove('mapped');
                }
            });

            selects.forEach(select => {
                if (select.value) {
                    mappedCount++;
                }
            });

            document.getElementById('mappedCount').textContent = mappedCount;
            const hasDuplicates = selectedValues.length !== new Set(selectedValues).size;
            const continueBtn = document.getElementById('continueBtn');
            const requiredMap = {};
            selects.forEach(select => {
                const fieldName = select.name.match(/\[(.+)\]/)[1];
                requiredMap[fieldName] = select.value;
            });
            const quantityValue = document.querySelector('select[name="mapping[quantity]"]')?.value || '';
            const unitPriceValue = document.querySelector('select[name="mapping[unit_price]"]')?.value || '';
            const hasFormulaInputs = quantityValue !== '' && unitPriceValue !== '';
            const amountValid = amountMode === 'direct' ? requiredMap.amount : hasFormulaInputs;
            const requiredCoreCount = 4;

            const customerValid = syntheticCustomerAllowed || requiredMap.customer_id;
            const dateValid = syntheticDateAllowed || requiredMap.date;

            if (customerValid && dateValid && requiredMap.invoice_id && amountValid && !hasDuplicates) {
                continueBtn.disabled = !validationReady;
                document.getElementById('fieldsCounter').innerHTML = '<span class="text-green-600"><span class="material-symbols-outlined text-sm align-middle">check_circle</span> All Fields Mapped</span>';
                const amountMessage = amountMode === 'formula'
                    ? 'Amount will be computed as Quantity × Unit Price during preprocessing.'
                    : 'Amount will be read directly from the selected Amount column.';
                setMappingStatus(
                    validationReady ? 'success' : 'info',
                    validationReady ? 'Mapping validated' : 'Mapping ready for validation',
                    validationReady
                        ? `${amountMessage} You can now launch the analysis.`
                        : `${amountMessage} Run validation to preview canonicalized rows before starting the job.`
                );
            } else if (hasDuplicates) {
                continueBtn.disabled = true;
                document.getElementById('fieldsCounter').innerHTML = '<span class="text-red-600"><span class="material-symbols-outlined text-sm align-middle">error</span> Duplicate mappings detected</span>';
                setMappingStatus('error', 'Duplicate columns selected', 'Each required field must point to a different source column.');
            } else {
                continueBtn.disabled = true;
                document.getElementById('fieldsCounter').innerHTML = '<span id="mappedCount">' + mappedCount + '</span> of ' + requiredCoreCount + ' Fields Mapped';
                setMappingStatus('info', 'Mapping in progress', 'Map Customer ID, Date, Invoice ID, and either Amount or Quantity + Unit Price.');
            }
        }

        function updateAmountMode() {
            resetValidationState();
            const mode = document.getElementById('amountSourceMode').value;
            const amountSelect = document.querySelector('select[name="mapping[amount]"]');
            const quantitySelect = document.querySelector('select[name="mapping[quantity]"]');
            const unitPriceSelect = document.querySelector('select[name="mapping[unit_price]"]');

            if (mode === 'formula') {
                amountSelect.disabled = true;
                amountSelect.required = false;
                amountSelect.value = '';
                quantitySelect.required = true;
                unitPriceSelect.required = true;
            } else {
                amountSelect.disabled = false;
                amountSelect.required = true;
                quantitySelect.required = false;
                unitPriceSelect.required = false;
            }
        }

        function setMappingStatus(type, title, message) {
            const card = document.getElementById('mappingStatusCard');
            const icon = document.getElementById('mappingStatusIcon');
            const titleEl = document.getElementById('mappingStatusTitle');
            const messageEl = document.getElementById('mappingStatusMessage');
            const styles = {
                info: ['bg-blue-100 text-blue-700', 'info'],
                success: ['bg-green-100 text-green-700', 'check_circle'],
                error: ['bg-red-100 text-red-700', 'error'],
                loading: ['bg-slate-100 text-slate-700', 'progress_activity']
            };
            const [classes, iconName] = styles[type] || styles.info;
            card.classList.remove('hidden');
            icon.className = `mt-0.5 flex h-9 w-9 items-center justify-center rounded-full ${classes}`;
            icon.innerHTML = `<span class="material-symbols-outlined ${type === 'loading' ? 'animate-spin' : ''}">${iconName}</span>`;
            titleEl.textContent = title;
            messageEl.textContent = message;
        }

        function dismissWarning() {
            const banner = document.getElementById('warningBanner');
            if (!banner) return;
            banner.style.transition = 'opacity 0.3s, max-height 0.3s';
            banner.style.opacity = '0';
            banner.style.maxHeight = '0';
            banner.style.overflow = 'hidden';
            setTimeout(() => banner.remove(), 300);
        }

        function submitMapping() {
            if (!validationReady) {
                setMappingStatus('error', 'Run validation first', 'Validate your mapping and parser settings before starting the analysis job.');
                return;
            }

            const continueBtn = document.getElementById('continueBtn');
            continueBtn.disabled = true;
            continueBtn.innerHTML = '<span class="material-symbols-outlined animate-spin text-sm mr-2">progress_activity</span> Processing...';
            setMappingStatus('loading', 'Saving mapping', 'We are saving your selected columns and preparing the analysis job.');

            fetch('api/mapping.php?action=save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(collectMappingPayload())
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success && !data.redirect) {
                    throw new Error(data.error || 'Failed to save mapping');
                }
                setMappingStatus('success', 'Mapping saved', 'The backend accepted your mapping. Redirecting to the live processing page now.');
                window.location.href = data.redirect || 'processing.php';
            })
            .catch(error => {
                setMappingStatus('error', 'Could not save mapping', error.message || 'The mapping request failed.');
                continueBtn.disabled = false;
                continueBtn.innerHTML = 'Continue';
            });
        }

        function renderValidationReport(validation) {
            const rowsUsable = validation?.rows_usable ?? 0;
            const customerCount = validation?.customer_count ?? 0;
            const invoiceCount = validation?.invoice_count ?? 0;
            const totalRevenue = validation?.total_revenue ?? 0;
            const notices = [
                validation?.amount_explanation,
                ...(validation?.notices || []),
                ...(validation?.warnings || [])
            ].filter(Boolean);
            const previewRows = validation?.canonical_preview || [];
            const previewColumns = previewRows.length > 0 ? Object.keys(previewRows[0]) : [];

            document.getElementById('metricRowsUsable').textContent = Number(rowsUsable).toLocaleString();
            document.getElementById('metricCustomerCount').textContent = Number(customerCount).toLocaleString();
            document.getElementById('metricInvoiceCount').textContent = Number(invoiceCount).toLocaleString();
            document.getElementById('metricTotalRevenue').textContent = Number(totalRevenue).toLocaleString(undefined, { maximumFractionDigits: 2 });

            document.getElementById('validationNotices').innerHTML = notices.length
                ? notices.map((notice) => `
                    <div class="flex items-start gap-2 rounded-lg bg-blue-50 px-3 py-2 text-xs text-blue-800">
                        <span class="material-symbols-outlined text-sm">info</span>
                        <span>${escapeHtml(notice)}</span>
                    </div>
                `).join('')
                : '<div class="rounded-lg bg-green-50 px-3 py-2 text-xs text-green-700">No validation warnings detected.</div>';

            document.getElementById('canonicalPreviewHead').innerHTML = previewColumns.length
                ? `<tr>${previewColumns.map((col) => `<th class="px-4 py-3 border-b border-border-subtle">${escapeHtml(col)}</th>`).join('')}</tr>`
                : '<tr><th class="px-4 py-3 border-b border-border-subtle">No preview rows</th></tr>';

            document.getElementById('canonicalPreviewBody').innerHTML = previewRows.length
                ? previewRows.map((row) => `
                    <tr class="hover:bg-slate-50">
                        ${previewColumns.map((col) => `<td class="px-4 py-3">${escapeHtml(row[col])}</td>`).join('')}
                    </tr>
                `).join('')
                : '<tr><td class="px-4 py-3 text-slate-500">No canonical preview rows were returned.</td></tr>';

            document.getElementById('validationReportBody').classList.remove('hidden');
        }

        async function runValidation() {
            const validateBtn = document.getElementById('validateBtn');
            validateBtn.disabled = true;
            validateBtn.innerHTML = '<span class="material-symbols-outlined animate-spin text-sm mr-2">progress_activity</span> Validating...';
            setMappingStatus('loading', 'Running validation', 'Checking parser rules, dropped-row counts, and canonicalized sample rows.');

            try {
                const response = await fetch('api/mapping.php?action=validate', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(collectMappingPayload())
                });
                const data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error(data.error || 'Validation failed');
                }

                validationReady = true;
                renderValidationReport(data.validation);
                updateMappingStatus();
            } catch (error) {
                validationReady = false;
                document.getElementById('validationReportBody').classList.add('hidden');
                setMappingStatus('error', 'Validation failed', error.message || 'Could not validate this mapping.');
            } finally {
                validateBtn.disabled = false;
                validateBtn.innerHTML = 'Validate Mapping';
            }
        }

        function applySuggestedMapping() {
            const suggested = <?php echo json_encode($suggestedMapping); ?>;
            if (!suggested) return;

            const fieldMap = {
                customer_id: suggested.customer_id,
                date: suggested.invoice_date,
                invoice_id: suggested.invoice_id,
                amount: suggested.amount,
                quantity: suggested.quantity,
                unit_price: suggested.unit_price,
                product: suggested.product,
                category: suggested.category
            };

            document.querySelectorAll('.mapping-select, .optional-mapping-select').forEach(select => {
                const fieldName = select.name.match(/\[(.+)\]/)[1];
                if (fieldMap[fieldName]) {
                    select.value = fieldMap[fieldName];
                }
            });

            updateMappingStatus();
        }

        document.addEventListener('DOMContentLoaded', function() {
            updateAmountMode();
            applySuggestedMapping();
            updateMappingStatus();
            document.querySelectorAll('.mapping-select, .optional-mapping-select').forEach((select) => {
                select.addEventListener('change', () => {
                    resetValidationState();
                    updateMappingStatus();
                });
            });
            ['invoiceDateFormat', 'decimalSeparator', 'thousandsSeparator', 'currencySymbol', 'negativeAmountPolicy', 'dayfirstFlag', 'allowSyntheticCustomerId', 'allowSyntheticInvoiceDate'].forEach((id) => {
                const el = document.getElementById(id);
                if (el) {
                    el.addEventListener('change', () => {
                        resetValidationState();
                        updateMappingStatus();
                    });
                }
            });
        });
    </script>
</body>
</html>
