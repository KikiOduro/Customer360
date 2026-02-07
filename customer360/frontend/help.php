<?php
/**
 * Customer 360 - Help & Support Page
 * Guides, resources, and expert support
 */
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: signin.php');
    exit;
}

// User data from session
$userName = $_SESSION['user_name'] ?? 'User';
$userEmail = $_SESSION['user_email'] ?? '';
$companyName = $_SESSION['company_name'] ?? 'Your Company';
$userInitials = strtoupper(substr($userName, 0, 1) . (strpos($userName, ' ') ? substr($userName, strpos($userName, ' ') + 1, 1) : ''));
$currentYear = date('Y');
$currentPage = 'help';
$isDemoMode = isset($_SESSION['demo_mode']) && $_SESSION['demo_mode'];

// Sample data for the import guide table
$sampleData = [
    ['name' => 'Kwame Mensah', 'phone' => '+233 24 123 4567', 'email' => 'kwame@example.com', 'region' => 'Accra', 'region_color' => 'blue'],
    ['name' => 'Ama Osei', 'phone' => '050 987 6543', 'email' => 'ama.o@store.gh', 'region' => 'Ashanti', 'region_color' => 'amber'],
    ['name' => 'Kofi Boateng', 'phone' => '+233 20 555 1234', 'email' => 'kofi.b@biz.com', 'region' => 'Western', 'region_color' => 'green'],
];

// FAQ items
$faqs = [
    [
        'question' => 'Why is my upload failing at 99%?',
        'answer' => 'This usually happens if there is a special character in the \'Email\' column or if a required field is empty. Please check rows 50-100 in your spreadsheet for any missing data points.'
    ],
    [
        'question' => 'How do I format Ghanaian phone numbers?',
        'answer' => 'We support both international format (+233 24...) and local format (024...). Spaces and dashes are automatically removed during import, so "+233 24 123 4567" and "0241234567" both work perfectly.'
    ],
    [
        'question' => 'Can I export my analytics reports?',
        'answer' => 'Yes! Navigate to the \'Analytics\' tab. In the top right corner of any chart, you\'ll see a \'Download\' icon. You can export reports as PDF or CSV files for your weekly meetings.'
    ],
    [
        'question' => 'How do I segment my customers?',
        'answer' => 'Customer 360 automatically segments your customers using RFM (Recency, Frequency, Monetary) analysis. Once you upload your transaction data, our system will identify Champions, Loyal Customers, At-Risk customers, and more.'
    ],
    [
        'question' => 'What file formats are supported?',
        'answer' => 'We support CSV (.csv) and Excel (.xlsx, .xls) files up to 25MB. For larger datasets, please contact our support team for assistance with bulk imports.'
    ],
    [
        'question' => 'How long does analysis take?',
        'answer' => 'Most analyses complete within 2-5 minutes depending on the size of your dataset. You\'ll receive a notification when your results are ready, and you can continue using the platform while processing.'
    ],
];

// Resources
$resources = [
    ['icon' => 'play_circle', 'title' => 'Video Tutorials', 'url' => '#tutorials'],
    ['icon' => 'menu_book', 'title' => 'Documentation', 'url' => '#docs'],
    ['icon' => 'forum', 'title' => 'Community Forum', 'url' => '#forum'],
    ['icon' => 'school', 'title' => 'Getting Started Guide', 'url' => '#getting-started'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Customer 360 - Help & Support</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;900&display=swap" rel="stylesheet"/>
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
                    },
                    fontFamily: {
                        "display": ["Inter", "sans-serif"]
                    },
                    borderRadius: {"DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "full": "9999px"},
                },
            },
        }
    </script>
    <style>
        /* Smooth animations */
        details summary::-webkit-details-marker {
            display: none;
        }
        details[open] summary ~ * {
            animation: fadeIn 0.3s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .page-content {
            animation: pageIn 0.4s ease-out;
        }
        @keyframes pageIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark font-display text-primary dark:text-white antialiased">
    <div class="relative flex min-h-screen w-full flex-col overflow-x-hidden">
        <!-- Navigation -->
        <header class="sticky top-0 z-50 flex w-full items-center justify-between border-b border-primary/10 bg-white/90 px-6 py-4 backdrop-blur-md dark:border-white/10 dark:bg-background-dark/90 lg:px-10">
            <a href="dashboard.php" class="flex items-center gap-3 hover:opacity-80 transition-opacity">
                <div class="flex size-8 items-center justify-center rounded-lg bg-primary text-white">
                    <span class="material-symbols-outlined text-xl">analytics</span>
                </div>
                <h2 class="text-xl font-bold tracking-tight text-primary dark:text-white">Customer 360</h2>
            </a>
            
            <nav class="hidden md:flex flex-1 items-center justify-end gap-8">
                <a class="text-sm font-medium text-primary/70 hover:text-primary transition-colors dark:text-white/70 dark:hover:text-white" href="dashboard.php">Dashboard</a>
                <a class="text-sm font-medium text-primary/70 hover:text-primary transition-colors dark:text-white/70 dark:hover:text-white" href="analytics.php">Analytics</a>
                <a class="text-sm font-medium text-primary/70 hover:text-primary transition-colors dark:text-white/70 dark:hover:text-white" href="reports.php">Reports</a>
                <a class="text-sm font-medium text-primary/70 hover:text-primary transition-colors dark:text-white/70 dark:hover:text-white" href="settings.php">Settings</a>
                <a class="text-sm font-bold text-primary dark:text-white" href="help.php">Help & Support</a>
            </nav>
            
            <div class="ml-8 flex items-center gap-4">
                <?php if($isDemoMode): ?>
                <span class="hidden md:inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                    <span class="material-symbols-outlined text-[14px]">science</span>
                    Demo
                </span>
                <?php endif; ?>
                <button class="hidden md:flex size-9 items-center justify-center rounded-full bg-primary/5 text-primary dark:bg-white/10 dark:text-white hover:bg-primary/10 dark:hover:bg-white/20 transition-colors">
                    <span class="material-symbols-outlined">notifications</span>
                </button>
                <div class="size-9 rounded-full bg-primary flex items-center justify-center text-white font-bold text-sm ring-2 ring-white dark:ring-primary">
                    <?php echo $userInitials; ?>
                </div>
                <!-- Mobile Menu Button -->
                <button id="mobileMenuBtn" class="md:hidden flex items-center justify-center text-primary dark:text-white">
                    <span class="material-symbols-outlined">menu</span>
                </button>
            </div>
        </header>
        
        <!-- Mobile Menu -->
        <div id="mobileMenu" class="fixed inset-0 z-40 bg-black/50 opacity-0 pointer-events-none transition-opacity md:hidden">
            <div id="mobileMenuDrawer" class="absolute right-0 top-0 h-full w-64 bg-white dark:bg-[#1a222c] transform translate-x-full transition-transform shadow-xl">
                <div class="flex items-center justify-between p-4 border-b border-primary/10 dark:border-white/10">
                    <span class="font-bold text-primary dark:text-white">Menu</span>
                    <button id="closeMobileMenu" class="text-primary dark:text-white">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <nav class="p-4 space-y-2">
                    <a class="block px-4 py-2 rounded-lg text-primary/70 hover:bg-primary/5 dark:text-white/70 dark:hover:bg-white/5" href="dashboard.php">Dashboard</a>
                    <a class="block px-4 py-2 rounded-lg text-primary/70 hover:bg-primary/5 dark:text-white/70 dark:hover:bg-white/5" href="analytics.php">Analytics</a>
                    <a class="block px-4 py-2 rounded-lg text-primary/70 hover:bg-primary/5 dark:text-white/70 dark:hover:bg-white/5" href="reports.php">Reports</a>
                    <a class="block px-4 py-2 rounded-lg text-primary/70 hover:bg-primary/5 dark:text-white/70 dark:hover:bg-white/5" href="settings.php">Settings</a>
                    <a class="block px-4 py-2 rounded-lg bg-primary/10 text-primary font-medium dark:bg-white/10 dark:text-white" href="help.php">Help & Support</a>
                    <hr class="my-4 border-primary/10 dark:border-white/10">
                    <a class="block px-4 py-2 rounded-lg text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20" href="api/auth.php?action=logout">Log Out</a>
                </nav>
            </div>
        </div>
        
        <!-- Main Content -->
        <main class="flex-1 px-4 py-8 md:px-10 lg:px-40 lg:py-12">
            <div class="mx-auto max-w-5xl page-content">
                <!-- Page Header -->
                <div class="mb-10 text-center md:text-left">
                    <h1 class="text-3xl font-black tracking-tight text-primary dark:text-white sm:text-4xl">Help & Support</h1>
                    <p class="mt-3 text-lg text-primary/60 dark:text-white/60">Guides, resources, and expert support for your Customer 360 account.</p>
                </div>
                
                <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
                    <!-- Left Column: Main Content -->
                    <div class="lg:col-span-2 space-y-8">
                        <!-- Data Import Section -->
                        <section class="rounded-xl border border-primary/10 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-[#1a222c] sm:p-8">
                            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h2 class="text-xl font-bold text-primary dark:text-white">Data Import Guide</h2>
                                    <p class="mt-1 text-sm text-primary/60 dark:text-white/60">Ensure your CSV matches the format below before uploading.</p>
                                </div>
                                <a href="templates/customer_template.csv" download class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 dark:bg-white dark:text-primary dark:hover:bg-gray-100">
                                    <span class="material-symbols-outlined text-[20px]">download</span>
                                    <span>Download Template</span>
                                </a>
                            </div>
                            
                            <!-- Sample Table -->
                            <div class="overflow-hidden rounded-lg border border-primary/10 bg-background-light dark:border-white/10 dark:bg-background-dark">
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left text-sm">
                                        <thead class="bg-primary/5 dark:bg-white/5">
                                            <tr>
                                                <th class="px-4 py-3 font-semibold text-primary dark:text-white">Customer Name</th>
                                                <th class="px-4 py-3 font-semibold text-primary dark:text-white">Phone Number</th>
                                                <th class="px-4 py-3 font-semibold text-primary dark:text-white">Email Address</th>
                                                <th class="px-4 py-3 font-semibold text-primary dark:text-white">Region</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-primary/10 dark:divide-white/10">
                                            <?php foreach ($sampleData as $row): ?>
                                            <tr class="bg-white dark:bg-[#1a222c]">
                                                <td class="px-4 py-3 text-primary/80 dark:text-white/80"><?php echo htmlspecialchars($row['name']); ?></td>
                                                <td class="px-4 py-3 text-primary/80 dark:text-white/80 font-mono text-xs"><?php echo htmlspecialchars($row['phone']); ?></td>
                                                <td class="px-4 py-3 text-primary/80 dark:text-white/80"><?php echo htmlspecialchars($row['email']); ?></td>
                                                <td class="px-4 py-3">
                                                    <span class="inline-flex items-center rounded-full bg-<?php echo $row['region_color']; ?>-100 px-2 py-0.5 text-xs font-medium text-<?php echo $row['region_color']; ?>-800 dark:bg-<?php echo $row['region_color']; ?>-900/30 dark:text-<?php echo $row['region_color']; ?>-300">
                                                        <?php echo htmlspecialchars($row['region']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <div class="mt-4 flex gap-2 rounded-lg bg-blue-50 p-3 text-blue-900 dark:bg-blue-900/20 dark:text-blue-200">
                                <span class="material-symbols-outlined text-sm mt-0.5">info</span>
                                <p class="text-sm"><strong>Tip:</strong> Ensure phone numbers include the country code (+233) or start with a standard 0 prefix to be processed correctly by our SMS gateway.</p>
                            </div>
                        </section>
                        
                        <!-- Required Fields Section -->
                        <section class="rounded-xl border border-primary/10 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-[#1a222c] sm:p-8">
                            <h2 class="text-xl font-bold text-primary dark:text-white mb-4">Required CSV Columns</h2>
                            <p class="text-sm text-primary/60 dark:text-white/60 mb-6">For customer segmentation analysis, your file should include these columns:</p>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="flex items-start gap-3 p-4 rounded-lg bg-green-50 dark:bg-green-900/20">
                                    <span class="material-symbols-outlined text-green-600 dark:text-green-400 mt-0.5">check_circle</span>
                                    <div>
                                        <h4 class="font-medium text-green-800 dark:text-green-300">Customer ID</h4>
                                        <p class="text-xs text-green-700/80 dark:text-green-400/80 mt-0.5">Unique identifier for each customer</p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-3 p-4 rounded-lg bg-green-50 dark:bg-green-900/20">
                                    <span class="material-symbols-outlined text-green-600 dark:text-green-400 mt-0.5">check_circle</span>
                                    <div>
                                        <h4 class="font-medium text-green-800 dark:text-green-300">Transaction Date</h4>
                                        <p class="text-xs text-green-700/80 dark:text-green-400/80 mt-0.5">Date of each purchase (YYYY-MM-DD)</p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-3 p-4 rounded-lg bg-green-50 dark:bg-green-900/20">
                                    <span class="material-symbols-outlined text-green-600 dark:text-green-400 mt-0.5">check_circle</span>
                                    <div>
                                        <h4 class="font-medium text-green-800 dark:text-green-300">Amount</h4>
                                        <p class="text-xs text-green-700/80 dark:text-green-400/80 mt-0.5">Transaction value (numeric)</p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-3 p-4 rounded-lg bg-gray-50 dark:bg-white/5">
                                    <span class="material-symbols-outlined text-gray-400 mt-0.5">radio_button_unchecked</span>
                                    <div>
                                        <h4 class="font-medium text-gray-700 dark:text-gray-300">Customer Name <span class="text-xs font-normal">(Optional)</span></h4>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">For easier identification</p>
                                    </div>
                                </div>
                            </div>
                        </section>
                        
                        <!-- FAQ Section -->
                        <section>
                            <h2 class="mb-5 text-xl font-bold text-primary dark:text-white">Frequently Asked Questions</h2>
                            <div class="space-y-3">
                                <?php foreach ($faqs as $index => $faq): ?>
                                <details class="group rounded-xl border border-primary/10 bg-white shadow-sm dark:border-white/10 dark:bg-[#1a222c]" <?php echo $index === 0 ? 'open' : ''; ?>>
                                    <summary class="flex cursor-pointer items-center justify-between p-5 font-medium text-primary hover:bg-primary/5 dark:text-white dark:hover:bg-white/5 rounded-xl transition-colors">
                                        <span><?php echo htmlspecialchars($faq['question']); ?></span>
                                        <span class="material-symbols-outlined transition-transform duration-200 group-open:rotate-180">expand_more</span>
                                    </summary>
                                    <div class="border-t border-primary/10 px-5 pb-5 pt-3 text-primary/70 dark:border-white/10 dark:text-white/70">
                                        <?php echo htmlspecialchars($faq['answer']); ?>
                                    </div>
                                </details>
                                <?php endforeach; ?>
                            </div>
                        </section>
                        
                        <!-- Quick Actions -->
                        <section class="rounded-xl border border-primary/10 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-[#1a222c]">
                            <h2 class="text-xl font-bold text-primary dark:text-white mb-4">Quick Actions</h2>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <a href="upload.php" class="flex items-center gap-4 p-4 rounded-lg border border-primary/10 dark:border-white/10 hover:bg-primary/5 dark:hover:bg-white/5 transition-colors group">
                                    <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                                        <span class="material-symbols-outlined text-blue-600 dark:text-blue-400">cloud_upload</span>
                                    </div>
                                    <div>
                                        <h4 class="font-medium text-primary dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">Upload New Data</h4>
                                        <p class="text-xs text-primary/60 dark:text-white/60">Start a new customer analysis</p>
                                    </div>
                                </a>
                                <a href="reports.php" class="flex items-center gap-4 p-4 rounded-lg border border-primary/10 dark:border-white/10 hover:bg-primary/5 dark:hover:bg-white/5 transition-colors group">
                                    <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center">
                                        <span class="material-symbols-outlined text-purple-600 dark:text-purple-400">description</span>
                                    </div>
                                    <div>
                                        <h4 class="font-medium text-primary dark:text-white group-hover:text-purple-600 dark:group-hover:text-purple-400 transition-colors">View Reports</h4>
                                        <p class="text-xs text-primary/60 dark:text-white/60">See your analysis history</p>
                                    </div>
                                </a>
                                <a href="analytics.php" class="flex items-center gap-4 p-4 rounded-lg border border-primary/10 dark:border-white/10 hover:bg-primary/5 dark:hover:bg-white/5 transition-colors group">
                                    <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                                        <span class="material-symbols-outlined text-green-600 dark:text-green-400">analytics</span>
                                    </div>
                                    <div>
                                        <h4 class="font-medium text-primary dark:text-white group-hover:text-green-600 dark:group-hover:text-green-400 transition-colors">View Analytics</h4>
                                        <p class="text-xs text-primary/60 dark:text-white/60">Explore customer insights</p>
                                    </div>
                                </a>
                                <a href="settings.php" class="flex items-center gap-4 p-4 rounded-lg border border-primary/10 dark:border-white/10 hover:bg-primary/5 dark:hover:bg-white/5 transition-colors group">
                                    <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-gray-100 dark:bg-white/10 flex items-center justify-center">
                                        <span class="material-symbols-outlined text-gray-600 dark:text-gray-400">settings</span>
                                    </div>
                                    <div>
                                        <h4 class="font-medium text-primary dark:text-white group-hover:text-gray-600 dark:group-hover:text-gray-400 transition-colors">Account Settings</h4>
                                        <p class="text-xs text-primary/60 dark:text-white/60">Manage your preferences</p>
                                    </div>
                                </a>
                            </div>
                        </section>
                    </div>
                    
                    <!-- Right Column: Contact & Support -->
                    <div class="lg:col-span-1 space-y-6">
                        <!-- Contact Card -->
                        <div class="sticky top-24 rounded-xl border border-primary/10 bg-primary text-white p-6 shadow-lg dark:border-white/10 dark:bg-[#1a222c]">
                            <h3 class="text-lg font-bold">Need Personal Assistance?</h3>
                            <p class="mt-2 text-sm text-white/80">Our support team in Accra is available Mon-Fri, 8am - 5pm to help you succeed.</p>
                            
                            <div class="mt-6 space-y-3">
                                <a href="mailto:support@customer360.gh" class="flex w-full items-center justify-center gap-3 rounded-lg bg-white/10 px-4 py-3 text-sm font-medium transition-colors hover:bg-white/20">
                                    <span class="material-symbols-outlined text-[20px]">mail</span>
                                    <span>Email Support</span>
                                </a>
                                <a href="https://wa.me/233241234567?text=Hi, I need help with Customer 360" target="_blank" class="flex w-full items-center justify-center gap-3 rounded-lg bg-[#25D366] px-4 py-3 text-sm font-bold text-white transition-opacity hover:opacity-90 shadow-sm">
                                    <svg class="h-5 w-5 fill-current" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                                    </svg>
                                    <span>Chat on WhatsApp</span>
                                </a>
                            </div>
                            
                            <div class="mt-6 pt-4 border-t border-white/20">
                                <p class="text-xs text-white/60">Response time: Usually within 2 hours during business hours.</p>
                            </div>
                        </div>
                        
                        <!-- Resources Card -->
                        <div class="rounded-xl border border-primary/10 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-[#1a222c]">
                            <h3 class="mb-4 text-base font-bold text-primary dark:text-white">Other Resources</h3>
                            <ul class="space-y-3 text-sm">
                                <?php foreach ($resources as $resource): ?>
                                <li>
                                    <a class="flex items-center gap-2 text-primary/70 hover:text-primary dark:text-white/70 dark:hover:text-white transition-colors" href="<?php echo $resource['url']; ?>">
                                        <span class="material-symbols-outlined text-lg"><?php echo $resource['icon']; ?></span>
                                        <span><?php echo htmlspecialchars($resource['title']); ?></span>
                                    </a>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        
                        <!-- System Status -->
                        <div class="rounded-xl border border-primary/10 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-[#1a222c]">
                            <h3 class="mb-4 text-base font-bold text-primary dark:text-white">System Status</h3>
                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-primary/70 dark:text-white/70">API Service</span>
                                    <span class="flex items-center gap-1.5 text-xs font-medium text-green-600 dark:text-green-400">
                                        <span class="h-2 w-2 rounded-full bg-green-500 animate-pulse"></span>
                                        Operational
                                    </span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-primary/70 dark:text-white/70">File Processing</span>
                                    <span class="flex items-center gap-1.5 text-xs font-medium text-green-600 dark:text-green-400">
                                        <span class="h-2 w-2 rounded-full bg-green-500 animate-pulse"></span>
                                        Operational
                                    </span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-primary/70 dark:text-white/70">SMS Gateway</span>
                                    <span class="flex items-center gap-1.5 text-xs font-medium text-green-600 dark:text-green-400">
                                        <span class="h-2 w-2 rounded-full bg-green-500 animate-pulse"></span>
                                        Operational
                                    </span>
                                </div>
                            </div>
                            <a href="#status" class="mt-4 inline-flex items-center gap-1 text-xs text-primary/60 hover:text-primary dark:text-white/60 dark:hover:text-white transition-colors">
                                View status page
                                <span class="material-symbols-outlined text-sm">arrow_forward</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
        
        <!-- Footer -->
        <footer class="mt-auto border-t border-primary/10 bg-white px-6 py-8 dark:border-white/10 dark:bg-background-dark">
            <div class="mx-auto max-w-5xl flex flex-col md:flex-row justify-between items-center gap-4 text-center md:text-left">
                <div>
                    <p class="text-sm font-semibold text-primary dark:text-white">© <?php echo $currentYear; ?> Customer 360 Ghana Ltd.</p>
                    <p class="text-xs text-primary/60 dark:text-white/60">Made with ❤️ in Accra.</p>
                </div>
                <div class="flex gap-6">
                    <a class="text-sm text-primary/60 hover:text-primary dark:text-white/60 dark:hover:text-white transition-colors" href="#">Privacy Policy</a>
                    <a class="text-sm text-primary/60 hover:text-primary dark:text-white/60 dark:hover:text-white transition-colors" href="#">Terms of Service</a>
                </div>
            </div>
        </footer>
    </div>
    
    <script>
        // Mobile menu functionality
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const mobileMenu = document.getElementById('mobileMenu');
        const mobileMenuDrawer = document.getElementById('mobileMenuDrawer');
        const closeMobileMenu = document.getElementById('closeMobileMenu');
        
        function openMenu() {
            mobileMenu.classList.remove('opacity-0', 'pointer-events-none');
            mobileMenuDrawer.classList.remove('translate-x-full');
        }
        
        function closeMenu() {
            mobileMenu.classList.add('opacity-0', 'pointer-events-none');
            mobileMenuDrawer.classList.add('translate-x-full');
        }
        
        mobileMenuBtn?.addEventListener('click', openMenu);
        closeMobileMenu?.addEventListener('click', closeMenu);
        mobileMenu?.addEventListener('click', (e) => {
            if (e.target === mobileMenu) closeMenu();
        });
        
        // Escape key to close menu
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeMenu();
        });
        
        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                const href = this.getAttribute('href');
                if (href === '#') return;
                
                e.preventDefault();
                const target = document.querySelector(href);
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
        
        // Search functionality for FAQs
        function searchFAQs(query) {
            const faqs = document.querySelectorAll('details');
            const searchLower = query.toLowerCase();
            
            faqs.forEach(faq => {
                const question = faq.querySelector('summary span').textContent.toLowerCase();
                const answer = faq.querySelector('div').textContent.toLowerCase();
                
                if (question.includes(searchLower) || answer.includes(searchLower)) {
                    faq.style.display = '';
                    if (searchLower.length > 2) {
                        faq.open = true;
                    }
                } else {
                    faq.style.display = searchLower.length > 0 ? 'none' : '';
                }
            });
        }
    </script>
</body>
</html>
