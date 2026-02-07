<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: signin.php');
    exit;
}

// Get uploaded file info from session (set by upload API)
$upload = $_SESSION['current_upload'] ?? null;
$uploadedFile = $upload['filename'] ?? $_SESSION['uploaded_file'] ?? 'ghana_sales_Q3.csv';

// Get columns and sample data from session (populated by upload API)
$availableColumns = $upload['columns'] ?? [
    'Cust_Ref_ID',
    'Customer Name',
    'Transaction_Date',
    'Inv_Num',
    'Total_GHS',
    'Status'
];

$sampleRows = $upload['sample_rows'] ?? [];
$suggestedMapping = $upload['suggested_mapping'] ?? null;

// If no sample rows from upload, use demo data
if (empty($sampleRows)) {
    $sampleRows = [
        ['Cust_Ref_ID' => 'CUST-001', 'Customer Name' => 'Kwame Enterprises', 'Transaction_Date' => '12/10/2023', 'Inv_Num' => 'INV-2023-884', 'Total_GHS' => '2,450.00', 'Status' => 'Paid'],
        ['Cust_Ref_ID' => 'CUST-002', 'Customer Name' => 'Accra Logistics Ltd', 'Transaction_Date' => '14/10/2023', 'Inv_Num' => 'INV-2023-885', 'Total_GHS' => '15,300.50', 'Status' => 'Pending'],
        ['Cust_Ref_ID' => 'CUST-003', 'Customer Name' => 'Golden Cocoa Exports', 'Transaction_Date' => '15/10/2023', 'Inv_Num' => 'INV-2023-886', 'Total_GHS' => '8,900.00', 'Status' => 'Paid'],
        ['Cust_Ref_ID' => 'CUST-004', 'Customer Name' => 'Osei & Sons Hardware', 'Transaction_Date' => '16/10/2023', 'Inv_Num' => 'INV-2023-887', 'Total_GHS' => '450.00', 'Status' => 'Paid'],
        ['Cust_Ref_ID' => 'CUST-001', 'Customer Name' => 'Kwame Enterprises', 'Transaction_Date' => '18/10/2023', 'Inv_Num' => 'INV-2023-888', 'Total_GHS' => '1,200.00', 'Status' => 'Overdue'],
        ['Cust_Ref_ID' => 'CUST-005', 'Customer Name' => 'Tema Textiles', 'Transaction_Date' => '19/10/2023', 'Inv_Num' => 'INV-2023-889', 'Total_GHS' => '5,670.00', 'Status' => 'Paid'],
    ];
}

// Required fields for Customer 360
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
                    fontFamily: {
                        "display": ["Inter", "sans-serif"],
                        "body": ["Inter", "sans-serif"],
                    },
                    borderRadius: {
                        "DEFAULT": "0.25rem",
                        "lg": "0.5rem",
                        "xl": "0.75rem",
                        "full": "9999px"
                    },
                },
            },
        }
    </script>
    <style>
        /* Custom scrollbar for the table */
        .custom-scrollbar::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 4px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
        
        /* Select styling fix */
        select.mapped {
            border-color: #22c55e;
            background-color: #f0fdf4;
        }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark text-primary dark:text-white font-display min-h-screen flex flex-col overflow-x-hidden">
    <!-- Top Navigation -->
    <header class="flex items-center justify-between whitespace-nowrap border-b border-solid border-b-border-subtle bg-white dark:bg-background-dark px-6 py-3 sticky top-0 z-50">
        <div class="flex items-center gap-4 text-primary dark:text-white">
            <a href="dashboard.php" class="flex items-center gap-4">
                <div class="size-8 flex items-center justify-center bg-primary/10 rounded-lg text-primary">
                    <span class="material-symbols-outlined text-[24px]">grid_view</span>
                </div>
                <h2 class="text-primary dark:text-white text-lg font-bold leading-tight tracking-[-0.015em]">Customer 360</h2>
            </a>
        </div>
        <div class="flex gap-2">
            <button class="flex size-10 cursor-pointer items-center justify-center overflow-hidden rounded-lg bg-primary-subtle hover:bg-border-subtle text-primary transition-colors">
                <span class="material-symbols-outlined text-[20px]">notifications</span>
            </button>
            <button class="flex size-10 cursor-pointer items-center justify-center overflow-hidden rounded-lg bg-primary-subtle hover:bg-border-subtle text-primary transition-colors">
                <span class="material-symbols-outlined text-[20px]">help</span>
            </button>
            <button class="flex size-10 cursor-pointer items-center justify-center overflow-hidden rounded-lg bg-primary-subtle hover:bg-border-subtle text-primary transition-colors">
                <span class="material-symbols-outlined text-[20px]">account_circle</span>
            </button>
        </div>
    </header>
    
    <!-- Main Content Area -->
    <main class="flex-1 flex justify-center py-8 px-4 sm:px-6 lg:px-8">
        <div class="w-full max-w-[1024px] flex flex-col gap-6">
            <!-- Page Header -->
            <div class="flex flex-col gap-2">
                <h1 class="text-primary dark:text-white text-3xl font-black leading-tight tracking-[-0.033em]">Column Mapping</h1>
                <p class="text-[#536e93] text-base font-normal leading-normal">
                    Match the columns from your uploaded file <span class="file-name-display font-medium text-primary dark:text-white bg-primary/5 px-1 py-0.5 rounded"><?php echo htmlspecialchars($uploadedFile); ?></span> to the required Customer 360 fields below.
                </p>
            </div>
            
            <!-- Warning Banner -->
            <div id="warningBanner" class="@container">
                <div class="flex flex-col items-start justify-between gap-4 rounded-lg border border-yellow-200 bg-yellow-50 dark:bg-yellow-900/20 dark:border-yellow-800 p-4 sm:flex-row sm:items-center">
                    <div class="flex items-start gap-3">
                        <span class="material-symbols-outlined text-yellow-600 dark:text-yellow-400 mt-0.5">warning</span>
                        <div class="flex flex-col gap-1">
                            <p class="text-primary dark:text-white text-sm font-bold leading-tight">Check your data formatting</p>
                            <p class="text-[#536e93] dark:text-gray-400 text-sm font-normal leading-normal">Ensure your dates are in DD/MM/YYYY format and amounts contain only numbers (e.g., 1500.00).</p>
                        </div>
                    </div>
                    <button onclick="dismissWarning()" class="text-primary dark:text-white hover:bg-yellow-100 dark:hover:bg-yellow-800/40 px-3 py-1.5 rounded-md text-sm font-medium transition-colors whitespace-nowrap">
                        Dismiss
                    </button>
                </div>
            </div>
            
            <!-- Mapping Section -->
            <form id="mappingForm" action="api/process-mapping.php" method="POST">
                <input type="hidden" name="file" value="<?php echo htmlspecialchars($uploadedFile); ?>">
                
                <div class="bg-white dark:bg-[#1a222c] rounded-xl border border-border-subtle shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-border-subtle bg-primary-subtle/30 dark:bg-primary/20 flex items-center justify-between">
                        <h2 class="text-primary dark:text-white text-lg font-bold">1. Map Columns</h2>
                        <span id="fieldsCounter" class="text-xs font-medium text-[#536e93] uppercase tracking-wider">
                            <span id="mappedCount">0</span> of <?php echo count($requiredFields); ?> Fields Mapped
                        </span>
                    </div>
                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <?php foreach ($requiredFields as $field): ?>
                        <!-- Field: <?php echo $field['label']; ?> -->
                        <div class="flex flex-col gap-2">
                            <label class="flex items-center gap-1.5 text-sm font-semibold text-primary dark:text-white">
                                <?php echo htmlspecialchars($field['label']); ?>
                                <?php if ($field['tooltip']): ?>
                                <span class="material-symbols-outlined text-[16px] text-[#536e93] cursor-help" title="<?php echo htmlspecialchars($field['tooltip']); ?>">info</span>
                                <?php endif; ?>
                                <?php if ($field['hint']): ?>
                                <span class="text-xs font-normal text-[#536e93]"><?php echo htmlspecialchars($field['hint']); ?></span>
                                <?php endif; ?>
                            </label>
                            <div class="relative">
                                <select 
                                    name="mapping[<?php echo $field['id']; ?>]" 
                                    class="mapping-select w-full appearance-none rounded-lg border border-border-subtle bg-white dark:bg-background-dark py-2.5 pl-3 pr-10 text-sm text-primary dark:text-white focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary disabled:opacity-50"
                                    onchange="updateMappingStatus()"
                                    required
                                >
                                    <option disabled selected value="">Select column...</option>
                                    <?php foreach ($availableColumns as $col): ?>
                                    <option value="<?php echo htmlspecialchars($col); ?>"><?php echo htmlspecialchars($col); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-[#536e93]">
                                    <span class="material-symbols-outlined text-[20px]">expand_more</span>
                                </div>
                            </div>
                            <p class="text-xs text-[#536e93]">Sample: <span class="font-mono bg-primary-subtle px-1 rounded"><?php echo htmlspecialchars($field['sample']); ?></span></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </form>
            
            <!-- Data Preview Section -->
            <div class="bg-white dark:bg-[#1a222c] rounded-xl border border-border-subtle shadow-sm overflow-hidden flex flex-col">
                <div class="px-6 py-4 border-b border-border-subtle flex items-center justify-between">
                    <h2 class="text-primary dark:text-white text-lg font-bold">Data Preview</h2>
                    <div class="flex items-center gap-2 text-sm text-[#536e93]">
                        <span class="material-symbols-outlined text-[18px]">table_rows</span>
                        Showing first <?php echo count($sampleData); ?> rows
                    </div>
                </div>
                <div class="overflow-x-auto custom-scrollbar">
                    <table class="w-full text-left text-sm whitespace-nowrap">
                        <thead class="bg-primary-subtle dark:bg-primary/40 text-primary dark:text-white font-semibold">
                            <tr>
                                <th class="px-6 py-3 border-b border-border-subtle">Row</th>
                                <?php foreach ($availableColumns as $col): ?>
                                <th class="px-6 py-3 border-b border-border-subtle"><?php echo htmlspecialchars($col); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-subtle dark:divide-white/10">
                            <?php foreach ($sampleRows as $i => $row): ?>
                            <tr class="hover:bg-primary-subtle/50 dark:hover:bg-primary/20 transition-colors">
                                <td class="px-6 py-3 text-[#536e93]"><?php echo $i + 1; ?></td>
                                <?php foreach ($availableColumns as $col): ?>
                                <td class="px-6 py-3 <?php echo $col === reset($availableColumns) ? 'font-medium text-primary dark:text-white' : ''; ?>">
                                    <?php 
                                    $value = $row[$col] ?? '';
                                    // Check if it's a status-like field
                                    if (in_array(strtolower($value), ['paid', 'pending', 'overdue', 'active', 'inactive'])) {
                                        $statusClasses = [
                                            'paid' => 'bg-green-50 text-green-700 ring-green-600/20',
                                            'active' => 'bg-green-50 text-green-700 ring-green-600/20',
                                            'pending' => 'bg-yellow-50 text-yellow-800 ring-yellow-600/20',
                                            'overdue' => 'bg-red-50 text-red-700 ring-red-600/10',
                                            'inactive' => 'bg-gray-50 text-gray-700 ring-gray-600/20',
                                        ];
                                        $statusClass = $statusClasses[strtolower($value)] ?? 'bg-gray-50 text-gray-700 ring-gray-600/20';
                                        echo '<span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ring-1 ring-inset ' . $statusClass . '">' . htmlspecialchars($value) . '</span>';
                                    } else {
                                        echo htmlspecialchars($value);
                                    }
                                    ?>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="flex items-center justify-end gap-3 pt-4 pb-12">
                <button type="button" onclick="window.location.href='upload.php'" class="flex min-w-[100px] cursor-pointer items-center justify-center rounded-lg h-10 px-4 border border-border-subtle bg-white text-primary text-sm font-bold leading-normal hover:bg-gray-50 transition-colors">
                    Back
                </button>
                <button type="button" onclick="submitMapping()" id="continueBtn" disabled class="flex min-w-[100px] cursor-pointer items-center justify-center rounded-lg h-10 px-4 bg-primary text-white text-sm font-bold leading-normal hover:bg-[#1a3b66] transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                    Continue
                </button>
            </div>
        </div>
    </main>
    
    <script>
        // Track mapped fields
        function updateMappingStatus() {
            const selects = document.querySelectorAll('.mapping-select');
            let mappedCount = 0;
            const selectedValues = [];
            
            selects.forEach(select => {
                if (select.value) {
                    mappedCount++;
                    selectedValues.push(select.value);
                    select.classList.add('mapped');
                    select.style.borderColor = '#22c55e';
                    select.style.backgroundColor = '#f0fdf4';
                } else {
                    select.classList.remove('mapped');
                    select.style.borderColor = '';
                    select.style.backgroundColor = '';
                }
            });
            
            // Update counter
            document.getElementById('mappedCount').textContent = mappedCount;
            
            // Check for duplicate mappings
            const hasDuplicates = selectedValues.length !== new Set(selectedValues).size;
            
            // Enable/disable continue button
            const continueBtn = document.getElementById('continueBtn');
            const totalRequired = selects.length;
            
            if (mappedCount === totalRequired && !hasDuplicates) {
                continueBtn.disabled = false;
                document.getElementById('fieldsCounter').innerHTML = 
                    '<span class="text-green-600"><span class="material-symbols-outlined text-sm align-middle">check_circle</span> All Fields Mapped</span>';
            } else if (hasDuplicates) {
                continueBtn.disabled = true;
                document.getElementById('fieldsCounter').innerHTML = 
                    '<span class="text-red-600"><span class="material-symbols-outlined text-sm align-middle">error</span> Duplicate mappings detected</span>';
            } else {
                continueBtn.disabled = true;
                document.getElementById('fieldsCounter').innerHTML = 
                    '<span id="mappedCount">' + mappedCount + '</span> of ' + totalRequired + ' Fields Mapped';
            }
        }
        
        // Dismiss warning banner
        function dismissWarning() {
            const banner = document.getElementById('warningBanner');
            banner.style.transition = 'opacity 0.3s, max-height 0.3s';
            banner.style.opacity = '0';
            banner.style.maxHeight = '0';
            banner.style.overflow = 'hidden';
            setTimeout(() => banner.remove(), 300);
        }
        
        // Submit mapping form
        function submitMapping() {
            const form = document.getElementById('mappingForm');
            const continueBtn = document.querySelector('#continueBtn');
            
            // Show loading state
            continueBtn.disabled = true;
            continueBtn.innerHTML = '<span class="material-symbols-outlined animate-spin text-sm mr-2">progress_activity</span> Processing...';
            
            // Gather mapping data
            const mappings = {};
            document.querySelectorAll('.mapping-select').forEach(select => {
                const fieldName = select.name.match(/\[(.+)\]/)[1];
                mappings[fieldName] = select.value;
            });
            
            // Submit to API
            fetch('api/mapping.php?action=save', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ mapping: mappings })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success || data.redirect) {
                    // Store in sessionStorage as backup
                    sessionStorage.setItem('columnMappings', JSON.stringify(mappings));
                    sessionStorage.setItem('jobId', data.job_id || '');
                    
                    // Redirect to processing page
                    window.location.href = data.redirect || 'processing.php';
                } else {
                    throw new Error(data.error || 'Failed to save mapping');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error saving mapping: ' + error.message);
                
                // Reset button
                continueBtn.disabled = false;
                continueBtn.innerHTML = 'Continue';
            });
        }
        
        // Apply suggested mapping if available
        function applySuggestedMapping() {
            const suggested = <?php echo json_encode($suggestedMapping); ?>;
            if (!suggested) return;
            
            const fieldMap = {
                'customer_id': suggested.customer_id,
                'date': suggested.invoice_date,
                'invoice_id': suggested.invoice_id,
                'amount': suggested.amount
            };
            
            document.querySelectorAll('.mapping-select').forEach(select => {
                const fieldName = select.name.match(/\[(.+)\]/)[1];
                if (fieldMap[fieldName]) {
                    select.value = fieldMap[fieldName];
                }
            });
            
            updateMappingStatus();
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Apply suggested mapping if available
            applySuggestedMapping();
            updateMappingStatus();
            
            // Get file name from sessionStorage if available
            const storedFile = sessionStorage.getItem('uploadedFile');
            if (storedFile) {
                const fileDisplay = document.querySelector('.file-name-display');
                if (fileDisplay) {
                    fileDisplay.textContent = storedFile;
                }
            }
        });
    </script>
</body>
</html>
