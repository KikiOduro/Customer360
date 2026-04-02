<?php
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
$userInitials = strtoupper(substr($profileLabel ?: 'A', 0, 1));
$currentPage = 'analysis';
$initialJobId = $_GET['job_id'] ?? ($_SESSION['current_job']['job_id'] ?? '');

if ($initialJobId === '') {
    header('Location: upload.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Analysis - Customer 360</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#0b203c',
                        'primary-hover': '#153055',
                        'background-light': '#f6f7f8',
                        accent: '#e8b031',
                    },
                    fontFamily: { display: ['Inter', 'sans-serif'] }
                }
            }
        };
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 500, 'GRAD' 0, 'opsz' 24; }
        .spinner { border: 4px solid rgba(255,255,255,.18); border-top-color: white; border-radius: 9999px; animation: spin 0.8s linear infinite; }
        .progress-fill { transition: width .8s ease-out; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body class="bg-background-light text-slate-900 antialiased">
<div class="flex h-screen w-full overflow-hidden">
    <aside class="hidden md:flex w-72 flex-col bg-primary text-white border-r border-slate-800">
        <div class="flex h-20 items-center gap-3 px-6 border-b border-slate-700/50">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-white/10"><span class="material-symbols-outlined">analytics</span></div>
            <div><h1 class="text-lg font-bold">Customer 360</h1><p class="text-slate-400 text-xs">SME Intelligence</p></div>
        </div>
        <nav class="flex-1 overflow-y-auto px-4 py-6 space-y-2">
            <a class="flex items-center gap-3 rounded-lg px-4 py-3 text-sm text-slate-300 hover:bg-white/5" href="dashboard.php"><span class="material-symbols-outlined">dashboard</span>Dashboard</a>
            <a class="flex items-center gap-3 rounded-lg bg-white/10 px-4 py-3 text-sm text-white" href="analysis.php"><span class="material-symbols-outlined">analytics</span>Analysis</a>
            <a class="flex items-center gap-3 rounded-lg px-4 py-3 text-sm text-slate-300 hover:bg-white/5" href="reports.php"><span class="material-symbols-outlined">bar_chart</span>Reports</a>
            <a class="flex items-center gap-3 rounded-lg px-4 py-3 text-sm text-slate-300 hover:bg-white/5" href="help.php"><span class="material-symbols-outlined">help</span>Help & Support</a>
        </nav>
        <div class="border-t border-slate-700/50 p-4">
            <div class="flex items-center gap-3 rounded-lg p-2 bg-white/5">
                <div class="h-10 w-10 rounded-full bg-slate-600 flex items-center justify-center font-semibold text-sm"><?php echo htmlspecialchars($userInitials); ?></div>
                <div class="min-w-0">
                    <p class="text-sm font-medium truncate"><?php echo htmlspecialchars($profileLabel); ?></p>
                    <p class="text-xs text-slate-400 truncate"><?php echo htmlspecialchars($profileSubLabel); ?></p>
                </div>
            </div>
        </div>
    </aside>

    <main class="flex-1 overflow-y-auto">
        <header class="sticky top-0 z-10 flex h-20 items-center justify-between border-b border-slate-200 bg-white px-6 sm:px-10">
            <div>
                <p class="text-sm text-slate-500">Home / Analysis</p>
                <h1 class="text-3xl font-bold text-primary">Customer Segmentation Analysis</h1>
            </div>
            <div class="flex gap-3">
                <a href="reports.php" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-primary hover:bg-slate-50"><span class="material-symbols-outlined text-[20px]">history</span>View Reports</a>
            </div>
        </header>

        <div class="mx-auto max-w-7xl px-6 py-8 sm:px-10">
            <div id="uploadState" class="space-y-6">
                <div class="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
                    <div id="dropZone" class="rounded-2xl border-2 border-dashed border-slate-300 px-8 py-14 text-center transition-colors hover:border-accent cursor-pointer">
                        <div class="mx-auto mb-5 flex h-20 w-20 items-center justify-center rounded-full bg-primary/5 text-primary">
                            <span class="material-symbols-outlined text-5xl">upload_file</span>
                        </div>
                        <h2 class="text-2xl font-bold text-primary">Upload your customer transactions CSV</h2>
                        <p class="mt-2 text-sm text-slate-500">Drag and drop a `.csv` file here, or <button type="button" class="font-semibold text-primary underline" onclick="document.getElementById('csvInput').click()">browse from your computer</button>.</p>
                        <p id="fileMeta" class="mt-4 text-sm text-slate-600 hidden"></p>
                        <p id="uploadError" class="mt-3 text-sm text-red-600 hidden"></p>
                        <input id="csvInput" type="file" class="hidden" accept=".csv,text/csv"/>
                    </div>
                </div>
            </div>

            <div id="mappingState" class="hidden space-y-6">
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h2 class="text-2xl font-bold text-primary">Map your CSV columns</h2>
                            <p class="mt-1 text-sm text-slate-500">Assign each column to the pipeline field it represents. Required fields must be mapped before analysis can run.</p>
                        </div>
                        <button id="skipOptionalBtn" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-primary hover:bg-slate-50">Skip optional fields</button>
                    </div>
                    <div class="mt-6 grid gap-4 lg:grid-cols-[1.8fr,1fr]">
                        <div class="overflow-hidden rounded-xl border border-slate-200">
                            <table class="w-full text-left text-sm">
                                <thead class="bg-slate-50 text-slate-500">
                                    <tr>
                                        <th class="px-4 py-3 font-semibold">CSV Column</th>
                                        <th class="px-4 py-3 font-semibold">Map To</th>
                                    </tr>
                                </thead>
                                <tbody id="mappingTableBody" class="divide-y divide-slate-100"></tbody>
                            </table>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <h3 class="font-semibold text-primary">Required fields</h3>
                            <ul id="requiredChecklist" class="mt-3 space-y-2 text-sm"></ul>
                            <div class="mt-6">
                                <h4 class="font-semibold text-primary">Sample rows</h4>
                                <div id="samplePreview" class="mt-3 max-h-80 overflow-auto rounded-lg border border-slate-200 bg-white p-3 text-xs text-slate-600"></div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end gap-3">
                        <button id="backToUploadBtn" class="rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-primary hover:bg-slate-50">Back</button>
                        <button id="runAnalysisBtn" class="rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-50 disabled:cursor-not-allowed" disabled>Run Analysis</button>
                    </div>
                </div>
            </div>

            <div id="dashboardState" class="hidden space-y-8">
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 class="text-3xl font-bold text-primary">Analysis Dashboard</h2>
                        <p id="dashboardSubtitle" class="mt-1 text-sm text-slate-500"></p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <a id="downloadReportBtn" href="#" class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-hover"><span class="material-symbols-outlined text-[20px]">picture_as_pdf</span>Download PDF</a>
                        <button id="newAnalysisBtn" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-primary hover:bg-slate-50"><span class="material-symbols-outlined text-[20px]">add</span>New Analysis</button>
                    </div>
                </div>

                <div id="kpiGrid" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"></div>

                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-xl font-bold text-primary">Dataset Insights</h3>
                    <div id="edaSummary" class="mt-5"></div>
                </section>

                <section>
                    <h3 class="mb-4 text-xl font-bold text-primary">Segments</h3>
                    <div id="segmentGrid" class="grid gap-4 lg:grid-cols-2"></div>
                </section>

                <section>
                    <h3 class="mb-4 text-xl font-bold text-primary">Charts</h3>
                    <div id="chartsGrid" class="grid gap-4 lg:grid-cols-2"></div>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <button id="validationToggle" class="flex w-full items-center justify-between text-left">
                        <span class="text-xl font-bold text-primary">Statistical validation</span>
                        <span class="material-symbols-outlined">expand_more</span>
                    </button>
                    <div id="validationPanel" class="mt-5 hidden"></div>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-xl font-bold text-primary">SHAP Feature Importance</h3>
                    <div id="shapBars" class="mt-5 space-y-4"></div>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-xl font-bold text-primary">Recent Customers</h3>
                    <div id="recentCustomersTable" class="mt-4 overflow-auto"></div>
                </section>
            </div>
        </div>
    </main>
</div>

<div id="loadingOverlay" class="fixed inset-0 z-50 hidden bg-primary/95 px-6 text-white">
    <div class="mx-auto flex min-h-screen max-w-2xl flex-col items-center justify-center text-center">
        <div class="spinner h-16 w-16"></div>
        <h2 class="mt-6 text-3xl font-bold">Running your analysis</h2>
        <p id="loadingMessage" class="mt-3 text-lg text-slate-200">Reading your transaction data...</p>
        <div class="mt-8 h-3 w-full overflow-hidden rounded-full bg-white/15">
            <div id="loadingProgress" class="progress-fill h-full w-0 rounded-full bg-accent"></div>
        </div>
        <p id="loadingPercent" class="mt-3 text-sm text-slate-200">0%</p>
        <button id="dismissSlowBtn" class="mt-8 hidden rounded-lg border border-white/30 px-4 py-2 text-sm font-medium hover:bg-white/10">This is taking longer than expected — still working...</button>
    </div>
</div>

<script>
const REQUIRED_FIELDS = [
    { id: 'customer_id', label: 'Customer ID', required: true },
    { id: 'purchase_date', label: 'Purchase Date', required: true },
    { id: 'revenue', label: 'Revenue / Total Amount', required: true },
    { id: 'order_id', label: 'Order ID', required: false },
    { id: 'product_name', label: 'Product Name', required: false },
    { id: 'category', label: 'Category', required: false },
    { id: 'unit_price', label: 'Unit Price', required: false },
    { id: 'quantity', label: 'Quantity', required: false },
    { id: 'discount_amount', label: 'Discount Amount', required: false },
    { id: 'promo_code', label: 'Promo Code', required: false },
    { id: 'payment_method', label: 'Payment Method', required: false },
    { id: 'shipping_method', label: 'Shipping Method', required: false }
];

const FIELD_KEYWORDS = {
    customer_id: ['customer id', 'customer_id', 'cust_id', 'client_id', 'buyer_id', 'user_id'],
    purchase_date: ['date', 'purchase date', 'invoice_date', 'transaction_date', 'order_date'],
    revenue: ['amount', 'revenue', 'sales', 'total amount', 'value', 'payment'],
    order_id: ['order id', 'invoice_id', 'transaction_id', 'receipt', 'invoice'],
    product_name: ['product', 'product name', 'item', 'sku', 'description'],
    category: ['category', 'type', 'class', 'group'],
    unit_price: ['unit price', 'price', 'selling_price', 'rate'],
    quantity: ['quantity', 'qty', 'units', 'count'],
    discount_amount: ['discount', 'discount amount', 'savings'],
    promo_code: ['promo', 'promo code', 'coupon', 'voucher'],
    payment_method: ['payment', 'payment method', 'channel', 'tender'],
    shipping_method: ['shipping', 'shipping method', 'delivery', 'fulfillment']
};

let selectedFile = null;
let fileRows = [];
let fileHeaders = [];
let mappingState = {};
let loadingTimer = null;
let loadingMessageTimer = null;
let currentAnalysis = null;
const initialJobId = <?php echo json_encode($initialJobId); ?>;

const uploadState = document.getElementById('uploadState');
const mappingStateEl = document.getElementById('mappingState');
const dashboardState = document.getElementById('dashboardState');
const csvInput = document.getElementById('csvInput');
const dropZone = document.getElementById('dropZone');

dropZone.addEventListener('click', () => csvInput.click());
dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('border-accent', 'bg-accent/5'); });
dropZone.addEventListener('dragleave', e => { e.preventDefault(); dropZone.classList.remove('border-accent', 'bg-accent/5'); });
dropZone.addEventListener('drop', e => {
    e.preventDefault();
    dropZone.classList.remove('border-accent', 'bg-accent/5');
    const file = e.dataTransfer.files?.[0];
    if (file) handleCsvSelection(file);
});
csvInput.addEventListener('change', e => {
    const file = e.target.files?.[0];
    if (file) handleCsvSelection(file);
});

document.getElementById('backToUploadBtn').addEventListener('click', () => switchState('upload'));
document.getElementById('skipOptionalBtn').addEventListener('click', skipOptionalFields);
document.getElementById('runAnalysisBtn').addEventListener('click', runAnalysis);
document.getElementById('newAnalysisBtn').addEventListener('click', resetAnalysisPage);
document.getElementById('validationToggle').addEventListener('click', () => {
    document.getElementById('validationPanel').classList.toggle('hidden');
});
document.getElementById('dismissSlowBtn').addEventListener('click', () => {
    document.getElementById('loadingOverlay').classList.add('hidden');
});

function switchState(state) {
    uploadState.classList.toggle('hidden', state !== 'upload');
    mappingStateEl.classList.toggle('hidden', state !== 'mapping');
    dashboardState.classList.toggle('hidden', state !== 'dashboard');
}

function handleCsvSelection(file) {
    const errorEl = document.getElementById('uploadError');
    errorEl.classList.add('hidden');

    if (!file.name.toLowerCase().endsWith('.csv')) {
        errorEl.textContent = 'Only .csv files are allowed for the analysis flow.';
        errorEl.classList.remove('hidden');
        return;
    }
    if (file.size > 50 * 1024 * 1024) {
        errorEl.textContent = 'The file exceeds the 50MB limit.';
        errorEl.classList.remove('hidden');
        return;
    }

    selectedFile = file;
    parseCsvFile(file).then(({ headers, rows, totalRows }) => {
        fileHeaders = headers;
        fileRows = rows;
        document.getElementById('fileMeta').textContent = `${file.name} • ${totalRows.toLocaleString()} data rows • ${headers.length} columns`;
        document.getElementById('fileMeta').classList.remove('hidden');
        initialiseMapping();
        switchState('mapping');
    }).catch(err => {
        errorEl.textContent = err.message || 'Could not parse the CSV file.';
        errorEl.classList.remove('hidden');
    });
}

async function parseCsvFile(file) {
    const text = await file.text();
    const lines = text.split(/\r?\n/).filter(Boolean);
    if (lines.length < 2) throw new Error('The CSV must contain a header row and at least one data row.');
    const headers = splitCsvLine(lines[0]);
    const rows = lines.slice(1).map(splitCsvLine).map(values => Object.fromEntries(headers.map((header, index) => [header, values[index] ?? '']))).slice(0, 8);
    return { headers, rows, totalRows: lines.length - 1 };
}

function splitCsvLine(line) {
    const result = [];
    let current = '';
    let inQuotes = false;
    for (let i = 0; i < line.length; i++) {
        const char = line[i];
        if (char === '"' && line[i + 1] === '"') {
            current += '"';
            i++;
        } else if (char === '"') {
            inQuotes = !inQuotes;
        } else if (char === ',' && !inQuotes) {
            result.push(current.trim());
            current = '';
        } else {
            current += char;
        }
    }
    result.push(current.trim());
    return result;
}

function initialiseMapping() {
    mappingState = {};
    const usedFields = new Set();
    fileHeaders.forEach(header => {
        const suggestedField = suggestField(header, usedFields);
        mappingState[header] = suggestedField || '';
        if (suggestedField) usedFields.add(suggestedField);
    });
    renderMappingTable();
    renderRequiredChecklist();
    renderSamplePreview();
    updateRunButtonState();
}

function suggestField(header, usedFields) {
    const normalized = header.toLowerCase().replace(/[_-]+/g, ' ');
    for (const [fieldId, keywords] of Object.entries(FIELD_KEYWORDS)) {
        if (usedFields.has(fieldId)) continue;
        if (keywords.some(keyword => normalized.includes(keyword) || keyword.includes(normalized))) {
            return fieldId;
        }
    }
    return '';
}

function renderMappingTable() {
    const tbody = document.getElementById('mappingTableBody');
    tbody.innerHTML = fileHeaders.map(header => {
        const options = ['<option value="">Unmapped</option>'].concat(REQUIRED_FIELDS.map(field => `<option value="${field.id}" ${mappingState[header] === field.id ? 'selected' : ''}>${field.label}${field.required ? ' *' : ''}</option>`)).join('');
        return `
            <tr>
                <td class="px-4 py-3 font-medium text-slate-800">${escapeHtml(header)}</td>
                <td class="px-4 py-3">
                    <select class="w-full rounded-lg border-slate-200 bg-white text-sm" data-header="${escapeHtml(header)}">${options}</select>
                </td>
            </tr>
        `;
    }).join('');

    tbody.querySelectorAll('select').forEach(select => {
        select.addEventListener('change', e => {
            const header = e.target.dataset.header;
            const value = e.target.value;
            Object.keys(mappingState).forEach(key => {
                if (key !== header && mappingState[key] === value && value) {
                    mappingState[key] = '';
                }
            });
            mappingState[header] = value;
            renderMappingTable();
            renderRequiredChecklist();
            updateRunButtonState();
        });
    });
}

function renderRequiredChecklist() {
    const checklist = document.getElementById('requiredChecklist');
    const selected = new Set(Object.values(mappingState).filter(Boolean));
    checklist.innerHTML = REQUIRED_FIELDS.filter(field => field.required).map(field => {
        const isMapped = selected.has(field.id);
        return `<li class="flex items-center gap-2 ${isMapped ? 'text-emerald-700' : 'text-red-600'}"><span class="material-symbols-outlined text-[18px]">${isMapped ? 'check_circle' : 'error'}</span>${field.label}</li>`;
    }).join('');
}

function renderSamplePreview() {
    const preview = document.getElementById('samplePreview');
    if (!fileRows.length) {
        preview.innerHTML = '<p>No preview rows available.</p>';
        return;
    }
    const headersHtml = fileHeaders.map(h => `<th class="border-b border-slate-200 px-2 py-2 text-left">${escapeHtml(h)}</th>`).join('');
    const rowsHtml = fileRows.map(row => `<tr>${fileHeaders.map(h => `<td class="border-b border-slate-100 px-2 py-2">${escapeHtml(String(row[h] ?? ''))}</td>`).join('')}</tr>`).join('');
    preview.innerHTML = `<table class="w-full"><thead><tr>${headersHtml}</tr></thead><tbody>${rowsHtml}</tbody></table>`;
}

function updateRunButtonState() {
    const selected = new Set(Object.values(mappingState).filter(Boolean));
    const allRequiredMapped = REQUIRED_FIELDS.filter(field => field.required).every(field => selected.has(field.id));
    document.getElementById('runAnalysisBtn').disabled = !allRequiredMapped || !selectedFile;
}

function skipOptionalFields() {
    Object.keys(mappingState).forEach(header => {
        const fieldId = mappingState[header];
        const isRequired = REQUIRED_FIELDS.some(field => field.id === fieldId && field.required);
        if (!isRequired) mappingState[header] = '';
    });
    renderMappingTable();
    renderRequiredChecklist();
    updateRunButtonState();
}

function getMappingPayload() {
    const payload = {};
    Object.entries(mappingState).forEach(([header, fieldId]) => {
        if (fieldId) payload[fieldId] = header;
    });
    return payload;
}

function showLoadingOverlay() {
    const overlay = document.getElementById('loadingOverlay');
    const fill = document.getElementById('loadingProgress');
    const percent = document.getElementById('loadingPercent');
    const messageEl = document.getElementById('loadingMessage');
    const dismissBtn = document.getElementById('dismissSlowBtn');
    const messages = [
        'Reading your transaction data...',
        'Cleaning and validating records...',
        'Computing RFM scores for each customer...',
        'Scaling features for clustering...',
        'Finding optimal number of segments...',
        'Running K-Means, GMM, and Hierarchical clustering...',
        'Selecting best algorithm by Silhouette Score...',
        'Labelling segments: Champions, At Risk, Loyalists...',
        'Running SHAP explainability analysis...',
        'Generating your insights...',
        'Almost done — building your dashboard...'
    ];
    let progress = 0;
    let messageIndex = 0;
    overlay.classList.remove('hidden');
    dismissBtn.classList.add('hidden');
    messageEl.textContent = messages[0];
    fill.style.width = '0%';
    percent.textContent = '0%';
    clearInterval(loadingTimer);
    clearInterval(loadingMessageTimer);

    loadingTimer = setInterval(() => {
        progress = Math.min(progress + 2.1, 95);
        fill.style.width = `${progress}%`;
        percent.textContent = `${Math.round(progress)}%`;
    }, 1000);

    loadingMessageTimer = setInterval(() => {
        messageIndex = (messageIndex + 1) % messages.length;
        messageEl.textContent = messages[messageIndex];
    }, 5000);

    setTimeout(() => dismissBtn.classList.remove('hidden'), 120000);
}

function hideLoadingOverlay(success = true) {
    clearInterval(loadingTimer);
    clearInterval(loadingMessageTimer);
    const overlay = document.getElementById('loadingOverlay');
    const fill = document.getElementById('loadingProgress');
    const percent = document.getElementById('loadingPercent');
    if (success) {
        fill.style.width = '100%';
        percent.textContent = '100%';
        setTimeout(() => overlay.classList.add('hidden'), 400);
        return;
    }
    overlay.classList.add('hidden');
}

async function runAnalysis() {
    const formData = new FormData();
    formData.append('file', selectedFile);
    formData.append('mapping', JSON.stringify(getMappingPayload()));
    formData.append('include_comparison', 'true');
    formData.append('clustering_method', 'kmeans');

    showLoadingOverlay();
    try {
        const response = await fetch('api/analyze.php?action=analyze', { method: 'POST', body: formData });
        const data = await response.json();
        if (!data.success) throw new Error(data.error || 'Analysis failed.');
        currentAnalysis = data.data;
        history.replaceState({}, '', `analysis.php?job_id=${encodeURIComponent(currentAnalysis.job_id)}`);
        renderDashboard(currentAnalysis);
        switchState('dashboard');
        hideLoadingOverlay(true);
    } catch (error) {
        hideLoadingOverlay(false);
        alert(error.message || 'Analysis failed.');
    }
}

function renderDashboard(data) {
    currentAnalysis = data;
    document.getElementById('dashboardSubtitle').textContent = `${data.meta?.source_file || 'Uploaded CSV'} • ${data.meta?.best_algorithm || 'Analysis complete'} • ${data.meta?.currency || 'GHS'}`;
    document.getElementById('downloadReportBtn').href = data.report_url || '#';

    const kpis = [
        { label: 'Total Customers Analysed', value: formatNumber(data.meta?.n_customers), detail: '' },
        { label: 'Segments Found', value: formatNumber(data.meta?.n_segments), detail: '' },
        { label: 'Best Algorithm', value: data.meta?.best_algorithm || 'Not available', detail: data.meta?.silhouette_score != null ? `Silhouette ${Number(data.meta.silhouette_score).toFixed(3)}` : '' },
        { label: 'Total Revenue Analysed', value: formatCurrency(data.meta?.total_revenue, data.meta?.currency), detail: '' }
    ];
    document.getElementById('kpiGrid').innerHTML = kpis.map(kpi => `
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-500">${escapeHtml(kpi.label)}</p>
            <h3 class="mt-3 text-3xl font-bold text-primary">${escapeHtml(kpi.value)}</h3>
            <p class="mt-1 text-xs text-slate-500">${escapeHtml(kpi.detail)}</p>
        </div>
    `).join('');

    const eda = data.meta?.eda || {};
    const numericSummary = Object.entries(eda.numeric_summary || {}).map(([metric, stats]) => `
        <div class="rounded-xl bg-slate-50 p-4">
            <h4 class="font-semibold text-primary">${escapeHtml(metric.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()))}</h4>
            <p class="mt-2 text-sm text-slate-600">Mean: ${formatNumber(stats.mean)}</p>
            <p class="text-sm text-slate-600">Median: ${formatNumber(stats.median)}</p>
            <p class="text-sm text-slate-600">Range: ${formatNumber(stats.min)} to ${formatNumber(stats.max)}</p>
        </div>
    `).join('');
    const topCategories = (eda.top_categories || []).slice(0, 5).map(item => `<li>• ${escapeHtml(item.label)} (${formatNumber(item.count)})</li>`).join('');
    const topProducts = (eda.top_products || []).slice(0, 5).map(item => `<li>• ${escapeHtml(item.label)} (${formatNumber(item.count)})</li>`).join('');
    const warnings = (eda.warnings || []).map(item => `<li>• ${escapeHtml(item)}</li>`).join('');
    document.getElementById('edaSummary').innerHTML = `
        <div class="grid gap-4 lg:grid-cols-3">
            <div class="rounded-xl border border-slate-200 p-4">
                <p class="text-sm font-medium text-slate-500">Business Type</p>
                <h4 class="mt-2 text-xl font-bold text-primary">${escapeHtml(data.meta?.business_type || 'General Retail')}</h4>
                <p class="mt-3 text-sm text-slate-600">Rows: ${formatNumber(eda.rows)} • Columns: ${formatNumber(eda.columns)}</p>
                <p class="text-sm text-slate-600">Duplicates: ${formatNumber(eda.duplicates)} • Missing values: ${formatNumber(eda.missing_values)}</p>
            </div>
            <div class="rounded-xl border border-slate-200 p-4">
                <h4 class="text-base font-bold text-primary">Top Categories</h4>
                <ul class="mt-3 space-y-1 text-sm text-slate-600">${topCategories || '<li>No category summary available.</li>'}</ul>
                <h4 class="mt-5 text-base font-bold text-primary">Top Products</h4>
                <ul class="mt-3 space-y-1 text-sm text-slate-600">${topProducts || '<li>No product summary available.</li>'}</ul>
            </div>
            <div class="space-y-4">
                ${numericSummary || '<div class="rounded-xl border border-slate-200 p-4 text-sm text-slate-500">No numeric summary available.</div>'}
                ${warnings ? `<div class="rounded-xl border border-amber-200 bg-amber-50 p-4"><h4 class="font-semibold text-amber-800">Data warnings</h4><ul class="mt-2 space-y-1 text-sm text-amber-700">${warnings}</ul></div>` : ''}
            </div>
        </div>
    `;

    document.getElementById('segmentGrid').innerHTML = (data.segments || []).map((segment, index) => {
        const segmentId = `segment-ai-${index}`;
        const encodedSegment = encodeURIComponent(JSON.stringify(segment));
        return `
        <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h4 class="text-xl font-bold text-primary">${escapeHtml(segment.name)}</h4>
                    <p class="mt-1 text-sm text-slate-500">${formatNumber(segment.customer_count)} customers • ${segment.customer_pct}% of base</p>
                </div>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">${segment.revenue_share}% revenue share</span>
            </div>
            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                ${metricBadge('Recency', `${segment.avg_recency}d`)}
                ${metricBadge('Frequency', `${segment.avg_frequency}`)}
                ${metricBadge('Monetary', formatCurrency(segment.avg_monetary, data.meta?.currency))}
            </div>
            <div class="mt-4">
                <div class="mb-1 flex items-center justify-between text-xs text-slate-500"><span>Revenue share</span><span>${segment.revenue_share}%</span></div>
                <div class="h-2.5 rounded-full bg-slate-100"><div class="h-2.5 rounded-full bg-primary" style="width:${Math.min(segment.revenue_share || 0, 100)}%"></div></div>
            </div>
            <div class="mt-4 grid gap-2 text-sm text-slate-600 sm:grid-cols-2">
                <p><strong class="text-primary">Top category:</strong> ${escapeHtml(segment.top_category || 'Not available')}</p>
                <p><strong class="text-primary">Top payment:</strong> ${escapeHtml(segment.top_payment || 'Not available')}</p>
            </div>
            <div class="mt-4">
                <h5 class="text-sm font-semibold text-primary">Recommended actions</h5>
                <ul class="mt-2 space-y-1 text-sm text-slate-600">${(segment.recommended_actions || []).slice(0, 3).map(action => `<li>• ${escapeHtml(action)}</li>`).join('')}</ul>
            </div>
            <div class="mt-5">
                <button class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-primary hover:bg-slate-50" onclick='loadAiProfile("${encodedSegment}", "${segmentId}")'>Get AI Profile</button>
                <div id="${segmentId}" class="mt-4 hidden rounded-xl bg-slate-50 p-4"></div>
            </div>
        </article>
        `;
    }).join('');

    const chartCaptions = {
        segment_distribution: 'How your customers are split across segments by count and percentage.',
        revenue_pareto: 'Which segments drive the most revenue — the 80/20 rule in action.',
        radar_chart: 'RFM profile comparison across all segments — outward = better performance.',
        rfm_violin_plots: 'Distribution of Recency, Frequency, and Monetary value within each segment.',
        shap_bar: 'Which RFM feature most strongly determines which segment a customer falls into.',
        pca_clusters_2d: 'Your customer clusters visualised in 2D space using PCA dimensionality reduction.',
        algorithm_comparison: 'How K-Means, GMM, and Hierarchical Clustering compared — the winning algorithm is highlighted.',
        rfm_distributions: 'How recency, frequency, and monetary values are distributed across your customer base.'
    };
    document.getElementById('chartsGrid').innerHTML = Object.entries(data.charts || {})
        .filter(([, value]) => value)
        .map(([key, value]) => `
            <figure class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h4 class="text-base font-bold text-primary">${escapeHtml(key.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()))}</h4>
                <img src="${value}" alt="${escapeHtml(key)}" class="mt-3 w-full rounded-xl border border-slate-100 bg-slate-50"/>
                <figcaption class="mt-3 text-sm text-slate-500">${escapeHtml(chartCaptions[key] || 'Analysis chart generated from the current segment results.')}</figcaption>
            </figure>
        `).join('') || '<div class="rounded-2xl border border-slate-200 bg-white p-6 text-sm text-slate-500 shadow-sm">No chart images were generated for this analysis.</div>';

    const validation = data.validation || {};
    const anovaRows = Object.entries(validation.anova || {}).map(([metric, row]) => `
        <tr class="border-b border-slate-100">
            <td class="px-4 py-3 font-medium text-primary">${escapeHtml(metric)}</td>
            <td class="px-4 py-3">${row.F}</td>
            <td class="px-4 py-3">${row.p}</td>
            <td class="px-4 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-semibold ${row.significant ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-700'}">${row.significant ? 'Significant' : 'Not significant'}</span></td>
        </tr>
    `).join('');
    document.getElementById('validationPanel').innerHTML = `
        <div class="overflow-hidden rounded-xl border border-slate-200">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-slate-500"><tr><th class="px-4 py-3">Metric</th><th class="px-4 py-3">F</th><th class="px-4 py-3">p</th><th class="px-4 py-3">Result</th></tr></thead>
                <tbody>${anovaRows}</tbody>
            </table>
        </div>
        <div class="mt-5 rounded-xl bg-slate-50 p-4 text-sm text-slate-600">
            <p><strong class="text-primary">ARI stability:</strong> ${validation.ari_stability?.avg ?? '0.000'} (${escapeHtml(validation.ari_stability?.rating || 'Limited')})</p>
            <p class="mt-2">${escapeHtml(validation.explanation || '')}</p>
        </div>
    `;

    const shapEntries = Object.entries(data.shap?.feature_importances || {});
    document.getElementById('shapBars').innerHTML = shapEntries.map(([feature, value]) => `
        <div>
            <div class="mb-1 flex items-center justify-between text-sm">
                <span class="font-medium text-primary">${escapeHtml(feature)}</span>
                <span class="text-slate-500">${Math.round(value * 100)}%</span>
            </div>
            <div class="h-3 rounded-full bg-slate-100"><div class="h-3 rounded-full bg-accent" style="width:${Math.round(value * 100)}%"></div></div>
        </div>
    `).join('') || '<p class="text-sm text-slate-500">No SHAP output available for this analysis.</p>';

    const rows = (data.recent_customers || []).map(customer => `
        <tr class="border-b border-slate-100">
            <td class="px-4 py-3 font-medium text-primary">${escapeHtml(customer.customer_name || customer.customer_id || 'Customer')}</td>
            <td class="px-4 py-3">${escapeHtml(customer.segment || 'Unknown')}</td>
            <td class="px-4 py-3">${formatDate(customer.last_purchase)}</td>
            <td class="px-4 py-3">${escapeHtml(customer.status || 'Active')}</td>
            <td class="px-4 py-3">${formatCurrency(customer.total_spend, data.meta?.currency)}</td>
        </tr>
    `).join('');
    document.getElementById('recentCustomersTable').innerHTML = rows ? `
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-slate-500"><tr><th class="px-4 py-3">Customer</th><th class="px-4 py-3">Segment</th><th class="px-4 py-3">Last Purchase</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Total Spend</th></tr></thead>
            <tbody>${rows}</tbody>
        </table>
    ` : '<p class="text-sm text-slate-500">No recent customer rows were generated.</p>';
}

async function loadAiProfile(serializedSegment, targetId) {
    const target = document.getElementById(targetId);
    target.classList.remove('hidden');
    target.innerHTML = '<div class="flex items-center gap-2 text-sm text-slate-500"><div class="spinner h-5 w-5 border-slate-300 border-t-primary"></div>Generating AI profile...</div>';
    try {
        const segment = JSON.parse(decodeURIComponent(serializedSegment));
        const response = await fetch('api/groq-insight.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ segment })
        });
        const data = await response.json();
        if (!data.success) throw new Error(data.error || 'Could not generate AI profile.');
        const profile = data.profile || {};
        target.innerHTML = `
            <div class="space-y-3 text-sm text-slate-600">
                <div><p class="text-base font-bold text-primary">${escapeHtml(profile.headline || 'AI Profile')}</p><p class="mt-1 text-xs uppercase tracking-wide text-slate-400">${escapeHtml(data.source || 'groq')}</p></div>
                <p><strong class="text-primary">Lifestyle:</strong> ${escapeHtml(profile.lifestyle || '')}</p>
                <p><strong class="text-primary">Buying personality:</strong> ${escapeHtml(profile.buying_personality || '')}</p>
                <p><strong class="text-primary">Churn risk:</strong> ${escapeHtml(profile.churn_risk?.level || '')} — ${escapeHtml(profile.churn_risk?.reason || '')}</p>
                <div><strong class="text-primary">Messaging angles:</strong><ul class="mt-1 space-y-1">${(profile.messaging_angles || []).map(item => `<li>• ${escapeHtml(item)}</li>`).join('')}</ul></div>
                <div><strong class="text-primary">Recommended channels:</strong> ${escapeHtml((profile.channels || []).join(', '))}</div>
                <div><strong class="text-primary">Offer ideas:</strong><ul class="mt-1 space-y-1">${(profile.offers || []).map(item => `<li>• ${escapeHtml(item)}</li>`).join('')}</ul></div>
            </div>
        `;
    } catch (error) {
        target.innerHTML = `<p class="text-sm text-red-600">${escapeHtml(error.message || 'Could not generate AI profile.')}</p>`;
    }
}

async function loadExistingJob(jobId) {
    showLoadingOverlay();
    try {
        const response = await fetch(`api/process.php?action=results&job_id=${encodeURIComponent(jobId)}`);
        const payload = await response.json();
        if (!payload.success || !payload.results) {
            hideLoadingOverlay(false);
            window.location.href = `processing.php?job_id=${encodeURIComponent(jobId)}`;
            return;
        }
        const results = payload.results;
        if (!results.meta) {
            hideLoadingOverlay(false);
            window.location.href = `processing.php?job_id=${encodeURIComponent(jobId)}`;
            return;
        }
        currentAnalysis = {
            ...results,
            job_id: jobId,
            report_url: `api/process.php?action=report&job_id=${encodeURIComponent(jobId)}`,
        };
        if (currentAnalysis.charts && typeof currentAnalysis.charts === 'object') {
            Object.keys(currentAnalysis.charts).forEach(key => {
                const value = currentAnalysis.charts[key];
                if (typeof value === 'string' && value !== '') {
                    currentAnalysis.charts[key] = `api/analyze.php?action=chart&job_id=${encodeURIComponent(jobId)}&file=${encodeURIComponent(value.split('/').pop())}`;
                }
            });
        }
        renderDashboard(currentAnalysis);
        switchState('dashboard');
        hideLoadingOverlay(true);
    } catch (error) {
        console.error(error);
        hideLoadingOverlay(false);
        window.location.href = `processing.php?job_id=${encodeURIComponent(jobId)}`;
    }
}

function resetAnalysisPage() {
    window.location.href = 'upload.php';
}

function metricBadge(label, value) {
    return `<div class="rounded-xl bg-slate-50 p-3"><p class="text-xs uppercase tracking-wide text-slate-400">${escapeHtml(label)}</p><p class="mt-1 font-semibold text-primary">${escapeHtml(String(value))}</p></div>`;
}

function formatCurrency(value, currency = 'GHS') {
    if (value == null || value === '') return 'Not available';
    const symbol = currency === 'GHS' ? 'GH₵' : `${currency} `;
    return `${symbol} ${Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function formatNumber(value) {
    if (value == null || value === '') return 'Not available';
    return Number(value).toLocaleString();
}

function formatDate(value) {
    if (!value) return 'Not available';
    const date = new Date(value);
    return isNaN(date) ? 'Not available' : date.toLocaleDateString();
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

if (initialJobId) {
    loadExistingJob(initialJobId);
}
</script>
</body>
</html>
