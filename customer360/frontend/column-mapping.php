<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: signin.php');
    exit;
}

// Get uploaded file info from session (would be set after upload)
$uploadedFile = $_SESSION['uploaded_file'] ?? 'ghana_sales_Q3.csv';

// Sample data that would come from parsing the uploaded file
// In production, this would be extracted from the actual uploaded CSV/Excel file
$sampleData = [
    ['row' => 1, 'cust_ref' => 'CUST-001', 'name' => 'Kwame Enterprises', 'date' => '12/10/2023', 'inv' => 'INV-2023-884', 'amount' => '2,450.00', 'status' => 'Paid'],
    ['row' => 2, 'cust_ref' => 'CUST-002', 'name' => 'Accra Logistics Ltd', 'date' => '14/10/2023', 'inv' => 'INV-2023-885', 'amount' => '15,300.50', 'status' => 'Pending'],
    ['row' => 3, 'cust_ref' => 'CUST-003', 'name' => 'Golden Cocoa Exports', 'date' => '15/10/2023', 'inv' => 'INV-2023-886', 'amount' => '8,900.00', 'status' => 'Paid'],
    ['row' => 4, 'cust_ref' => 'CUST-004', 'name' => 'Osei & Sons Hardware', 'date' => '16/10/2023', 'inv' => 'INV-2023-887', 'amount' => '450.00', 'status' => 'Paid'],
    ['row' => 5, 'cust_ref' => 'CUST-001', 'name' => 'Kwame Enterprises', 'date' => '18/10/2023', 'inv' => 'INV-2023-888', 'amount' => '1,200.00', 'status' => 'Overdue'],
    ['row' => 6, 'cust_ref' => 'CUST-005', 'name' => 'Tema Textiles', 'date' => '19/10/2023', 'inv' => 'INV-2023-889', 'amount' => '5,670.00', 'status' => 'Paid'],
];

// Available columns from the uploaded file (would be detected from file headers)
$availableColumns = [
    'Cust_Ref_ID',
    'Customer Name',
    'Transaction_Date',
    'Inv_Num',
    'Total_GHS',
    'Status'
];

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
                                <th class="px-6 py-3 border-b border-border-subtle">Cust_Ref_ID</th>
                                <th class="px-6 py-3 border-b border-border-subtle">Customer Name</th>
                                <th class="px-6 py-3 border-b border-border-subtle">Transaction_Date</th>
                                <th class="px-6 py-3 border-b border-border-subtle">Inv_Num</th>
                                <th class="px-6 py-3 border-b border-border-subtle text-right">Total_GHS</th>
                                <th class="px-6 py-3 border-b border-border-subtle">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-subtle dark:divide-white/10">
                            <?php foreach ($sampleData as $row): ?>
                            <tr class="hover:bg-primary-subtle/50 dark:hover:bg-primary/20 transition-colors">
                                <td class="px-6 py-3 text-[#536e93]"><?php echo $row['row']; ?></td>
                                <td class="px-6 py-3 font-medium text-primary dark:text-white"><?php echo htmlspecialchars($row['cust_ref']); ?></td>
                                <td class="px-6 py-3"><?php echo htmlspecialchars($row['name']); ?></td>
                                <td class="px-6 py-3"><?php echo htmlspecialchars($row['date']); ?></td>
                                <td class="px-6 py-3"><?php echo htmlspecialchars($row['inv']); ?></td>
                                <td class="px-6 py-3 text-right font-mono"><?php echo htmlspecialchars($row['amount']); ?></td>
                                <td class="px-6 py-3">
                                    <?php
                                    $statusClasses = [
                                        'Paid' => 'bg-green-50 text-green-700 ring-green-600/20',
                                        'Pending' => 'bg-yellow-50 text-yellow-800 ring-yellow-600/20',
                                        'Overdue' => 'bg-red-50 text-red-700 ring-red-600/10',
                                    ];
                                    $statusClass = $statusClasses[$row['status']] ?? 'bg-gray-50 text-gray-700 ring-gray-600/20';
                                    ?>
                                    <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ring-1 ring-inset <?php echo $statusClass; ?>">
                                        <?php echo htmlspecialchars($row['status']); ?>
                                    </span>
                                </td>
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
            const formData = new FormData(form);
            const mappings = {};
            
            document.querySelectorAll('.mapping-select').forEach(select => {
                const fieldName = select.name.match(/\[(.+)\]/)[1];
                mappings[fieldName] = select.value;
            });
            
            // Store in sessionStorage for the processing page
            sessionStorage.setItem('columnMappings', JSON.stringify(mappings));
            sessionStorage.setItem('uploadedFile', '<?php echo addslashes($uploadedFile); ?>');
            
            // Small delay for UX feedback, then redirect to processing page
            setTimeout(() => {
                window.location.href = 'processing.php';
            }, 800);
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
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
