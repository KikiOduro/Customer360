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
$hasUploadPreview = $uploadedFile !== '' && !empty($availableColumns);

if (!$hasUploadPreview) {
    header('Location: upload.php?missing_preview=1');
    exit;
}

$requiredFields = [
    ['id' => 'customer_id', 'label' => 'Customer ID', 'hint' => null, 'sample' => 'CUST-001', 'tooltip' => 'Unique identifier for the customer'],
    ['id' => 'date', 'label' => 'Date', 'hint' => '(DD/MM/YYYY)', 'sample' => '12/10/2023', 'tooltip' => null],
    ['id' => 'invoice_id', 'label' => 'Invoice ID', 'hint' => null, 'sample' => 'INV-2023-884', 'tooltip' => null],
    ['id' => 'amount', 'label' => 'Amount', 'hint' => '(GHS)', 'sample' => '2,450.00', 'tooltip' => null],
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
                                <?php if ($field['tooltip']): ?>
                                <span class="material-symbols-outlined text-[16px] text-[#536e93] cursor-help" title="<?php echo htmlspecialchars($field['tooltip']); ?>">info</span>
                                <?php endif; ?>
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
            </form>

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

    <script>
        function updateMappingStatus() {
            const selects = document.querySelectorAll('.mapping-select');
            let mappedCount = 0;
            const selectedValues = [];

            selects.forEach(select => {
                if (select.value) {
                    mappedCount++;
                    selectedValues.push(select.value);
                    select.classList.add('mapped');
                } else {
                    select.classList.remove('mapped');
                }
            });

            document.getElementById('mappedCount').textContent = mappedCount;
            const hasDuplicates = selectedValues.length !== new Set(selectedValues).size;
            const continueBtn = document.getElementById('continueBtn');
            const totalRequired = selects.length;

            if (mappedCount === totalRequired && !hasDuplicates) {
                continueBtn.disabled = false;
                document.getElementById('fieldsCounter').innerHTML = '<span class="text-green-600"><span class="material-symbols-outlined text-sm align-middle">check_circle</span> All Fields Mapped</span>';
                setMappingStatus('success', 'Mapping ready', 'All required fields are mapped. You can continue to launch the analysis.');
            } else if (hasDuplicates) {
                continueBtn.disabled = true;
                document.getElementById('fieldsCounter').innerHTML = '<span class="text-red-600"><span class="material-symbols-outlined text-sm align-middle">error</span> Duplicate mappings detected</span>';
                setMappingStatus('error', 'Duplicate columns selected', 'Each required field must point to a different source column.');
            } else {
                continueBtn.disabled = true;
                document.getElementById('fieldsCounter').innerHTML = '<span id="mappedCount">' + mappedCount + '</span> of ' + totalRequired + ' Fields Mapped';
                setMappingStatus('info', 'Mapping in progress', `You have mapped ${mappedCount} of ${totalRequired} required fields.`);
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
            const continueBtn = document.getElementById('continueBtn');
            continueBtn.disabled = true;
            continueBtn.innerHTML = '<span class="material-symbols-outlined animate-spin text-sm mr-2">progress_activity</span> Processing...';
            setMappingStatus('loading', 'Saving mapping', 'We are saving your selected columns and preparing the analysis job.');

            const mappings = {};
            document.querySelectorAll('.mapping-select').forEach(select => {
                const fieldName = select.name.match(/\[(.+)\]/)[1];
                mappings[fieldName] = select.value;
            });

            fetch('api/mapping.php?action=save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ mapping: mappings })
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

        function applySuggestedMapping() {
            const suggested = <?php echo json_encode($suggestedMapping); ?>;
            if (!suggested) return;

            const fieldMap = {
                customer_id: suggested.customer_id,
                date: suggested.invoice_date,
                invoice_id: suggested.invoice_id,
                amount: suggested.amount
            };

            document.querySelectorAll('.mapping-select').forEach(select => {
                const fieldName = select.name.match(/\[(.+)\]/)[1];
                if (fieldMap[fieldName]) {
                    select.value = fieldMap[fieldName];
                }
            });

            updateMappingStatus();
        }

        document.addEventListener('DOMContentLoaded', function() {
            applySuggestedMapping();
            updateMappingStatus();
        });
    </script>
</body>
</html>
