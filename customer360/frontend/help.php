<?php
/**
 * Customer 360 - Help & Support Page
 */
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: signin.php');
    exit;
}

$userName = $_SESSION['user_name'] ?? 'User';
$companyName = $_SESSION['company_name'] ?? 'My Business';
$userInitials = strtoupper(substr($userName, 0, 1));
$currentPage = 'help';
$isDemoMode = isset($_SESSION['demo_mode']) && $_SESSION['demo_mode'];
$currentYear = date('Y');

$sampleData = [
    ['name' => 'Kwame Mensah', 'phone' => '+233 24 123 4567', 'email' => 'kwame@example.com', 'region' => 'Accra', 'region_color' => 'blue'],
    ['name' => 'Ama Osei', 'phone' => '050 987 6543', 'email' => 'ama.o@store.gh', 'region' => 'Ashanti', 'region_color' => 'amber'],
    ['name' => 'Kofi Boateng', 'phone' => '+233 20 555 1234', 'email' => 'kofi.b@biz.com', 'region' => 'Western', 'region_color' => 'green'],
];

$faqs = [
    ['question' => 'Why is my upload failing at 99%?', 'answer' => 'This usually happens if there is a special character in the \'Email\' column or if a required field is empty. Please check rows 50-100 in your spreadsheet for any missing data points.'],
    ['question' => 'How do I format Ghanaian phone numbers?', 'answer' => 'We support both international format (+233 24...) and local format (024...). Spaces and dashes are automatically removed during import.'],
    ['question' => 'Can I export my analytics reports?', 'answer' => 'Yes! Navigate to the \'Analytics\' tab. In the top right corner of any chart, you\'ll see a \'Download\' icon. You can export reports as PDF or CSV files.'],
    ['question' => 'How do I segment my customers?', 'answer' => 'Customer 360 automatically segments your customers using RFM (Recency, Frequency, Monetary) analysis. Once you upload your transaction data, our system will identify Champions, Loyal Customers, At-Risk customers, and more.'],
    ['question' => 'What file formats are supported?', 'answer' => 'We support CSV (.csv) and Excel (.xlsx, .xls) files up to 25MB. For larger datasets, please contact our support team.'],
    ['question' => 'How long does analysis take?', 'answer' => 'Most analyses complete within 2-5 minutes depending on the size of your dataset. You\'ll receive a notification when your results are ready.'],
];

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Help & Support - Customer 360</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script>
        tailwind.config = {
            theme: { extend: { colors: { "primary": "#0b203c", "primary-hover": "#153055", "background-light": "#f6f7f8", "accent": "#e8b031" }, fontFamily: { "display": ["Inter", "sans-serif"] } } }
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
        details summary::-webkit-details-marker { display: none; }
        details[open] summary ~ * { animation: fadeIn 0.3s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
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
                <a class="flex items-center gap-3 rounded-lg <?php echo $currentPage === 'dashboard' ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white'; ?> px-4 py-3 text-sm font-medium transition-colors" href="dashboard.php">
                    <span class="material-symbols-outlined">dashboard</span>Dashboard
                </a>
                <a class="flex items-center gap-3 rounded-lg <?php echo $currentPage === 'upload' ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white'; ?> px-4 py-3 text-sm font-medium transition-colors" href="upload.php">
                    <span class="material-symbols-outlined">upload_file</span>Upload Data
                </a>
                <a class="flex items-center gap-3 rounded-lg <?php echo $currentPage === 'analytics' ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white'; ?> px-4 py-3 text-sm font-medium transition-colors" href="analytics.php">
                    <span class="material-symbols-outlined">analytics</span>Analytics
                </a>
                <a class="flex items-center gap-3 rounded-lg <?php echo $currentPage === 'reports' ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white'; ?> px-4 py-3 text-sm font-medium transition-colors" href="reports.php">
                    <span class="material-symbols-outlined">bar_chart</span>Reports
                </a>
                <div class="my-4 border-t border-slate-700/50"></div>
                <a class="flex items-center gap-3 rounded-lg <?php echo $currentPage === 'help' ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white'; ?> px-4 py-3 text-sm font-medium transition-colors" href="help.php">
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

        <!-- Main Content -->
        <main class="flex flex-1 flex-col overflow-y-auto bg-background-light relative">
            <header class="sticky top-0 z-10 flex h-20 items-center justify-between border-b border-slate-200 bg-white px-6 sm:px-10">
                <div class="flex items-center gap-4">
                    <button class="md:hidden text-slate-500 hover:text-slate-700" onclick="toggleMobileMenu()"><span class="material-symbols-outlined">menu</span></button>
                    <div class="hidden sm:flex items-center text-sm text-slate-500">
                        <a class="hover:text-primary transition-colors" href="dashboard.php">Home</a>
                        <span class="mx-2 text-slate-300">/</span>
                        <span class="font-medium text-primary">Help & Support</span>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <?php if($isDemoMode): ?>
                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-700">
                        <span class="material-symbols-outlined text-[14px]">science</span>Demo
                    </span>
                    <?php endif; ?>
                    <button class="relative rounded-full p-2 text-slate-500 hover:bg-slate-100 transition-colors">
                        <span class="material-symbols-outlined">notifications</span>
                    </button>
                </div>
            </header>

            <div class="p-6 sm:p-10 max-w-5xl mx-auto w-full">
                <!-- Header -->
                <div class="mb-10">
                    <h1 class="text-3xl font-bold text-primary tracking-tight">Help & Support</h1>
                    <p class="text-slate-500 mt-2">Guides, resources, and expert support for your Customer 360 account.</p>
                </div>

                <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
                    <!-- Left Column -->
                    <div class="lg:col-span-2 space-y-8">
                        <!-- Data Import Guide -->
                        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h2 class="text-xl font-bold text-primary">Data Import Guide</h2>
                                    <p class="mt-1 text-sm text-slate-500">Ensure your CSV matches the format below before uploading.</p>
                                </div>
                                <a href="templates/customer_template.csv" download class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-white hover:bg-primary-hover">
                                    <span class="material-symbols-outlined text-[20px]">download</span>Download Template
                                </a>
                            </div>
                            
                            <div class="overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left text-sm">
                                        <thead class="bg-slate-100">
                                            <tr>
                                                <th class="px-4 py-3 font-semibold text-primary">Customer Name</th>
                                                <th class="px-4 py-3 font-semibold text-primary">Phone Number</th>
                                                <th class="px-4 py-3 font-semibold text-primary">Email Address</th>
                                                <th class="px-4 py-3 font-semibold text-primary">Region</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-200">
                                            <?php foreach ($sampleData as $row): ?>
                                            <tr class="bg-white">
                                                <td class="px-4 py-3 text-slate-700"><?php echo htmlspecialchars($row['name']); ?></td>
                                                <td class="px-4 py-3 text-slate-700 font-mono text-xs"><?php echo htmlspecialchars($row['phone']); ?></td>
                                                <td class="px-4 py-3 text-slate-700"><?php echo htmlspecialchars($row['email']); ?></td>
                                                <td class="px-4 py-3">
                                                    <span class="inline-flex items-center rounded-full bg-<?php echo $row['region_color']; ?>-100 px-2 py-0.5 text-xs font-medium text-<?php echo $row['region_color']; ?>-800"><?php echo htmlspecialchars($row['region']); ?></span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <div class="mt-4 flex gap-2 rounded-lg bg-blue-50 p-3 text-blue-900">
                                <span class="material-symbols-outlined text-sm mt-0.5">info</span>
                                <p class="text-sm"><strong>Tip:</strong> Ensure phone numbers include the country code (+233) or start with a standard 0 prefix.</p>
                            </div>
                        </section>

                        <!-- Required Fields -->
                        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                            <h2 class="text-xl font-bold text-primary mb-4">Required CSV Columns</h2>
                            <p class="text-sm text-slate-500 mb-6">For customer segmentation analysis, your file should include these columns:</p>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="flex items-start gap-3 p-4 rounded-lg bg-green-50">
                                    <span class="material-symbols-outlined text-green-600 mt-0.5">check_circle</span>
                                    <div>
                                        <h4 class="font-medium text-green-800">Customer ID</h4>
                                        <p class="text-xs text-green-700/80 mt-0.5">Unique identifier for each customer</p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-3 p-4 rounded-lg bg-green-50">
                                    <span class="material-symbols-outlined text-green-600 mt-0.5">check_circle</span>
                                    <div>
                                        <h4 class="font-medium text-green-800">Transaction Date</h4>
                                        <p class="text-xs text-green-700/80 mt-0.5">Date of each purchase (YYYY-MM-DD)</p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-3 p-4 rounded-lg bg-green-50">
                                    <span class="material-symbols-outlined text-green-600 mt-0.5">check_circle</span>
                                    <div>
                                        <h4 class="font-medium text-green-800">Amount</h4>
                                        <p class="text-xs text-green-700/80 mt-0.5">Transaction value (numeric)</p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-3 p-4 rounded-lg bg-gray-50">
                                    <span class="material-symbols-outlined text-gray-400 mt-0.5">radio_button_unchecked</span>
                                    <div>
                                        <h4 class="font-medium text-gray-700">Customer Name <span class="text-xs font-normal">(Optional)</span></h4>
                                        <p class="text-xs text-gray-500 mt-0.5">For easier identification</p>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- FAQs -->
                        <section>
                            <h2 class="mb-5 text-xl font-bold text-primary">Frequently Asked Questions</h2>
                            <div class="space-y-3">
                                <?php foreach ($faqs as $index => $faq): ?>
                                <details class="group rounded-xl border border-slate-200 bg-white shadow-sm" <?php echo $index === 0 ? 'open' : ''; ?>>
                                    <summary class="flex cursor-pointer items-center justify-between p-5 font-medium text-primary hover:bg-slate-50 rounded-xl transition-colors">
                                        <span><?php echo htmlspecialchars($faq['question']); ?></span>
                                        <span class="material-symbols-outlined transition-transform duration-200 group-open:rotate-180">expand_more</span>
                                    </summary>
                                    <div class="border-t border-slate-200 px-5 pb-5 pt-3 text-slate-600"><?php echo htmlspecialchars($faq['answer']); ?></div>
                                </details>
                                <?php endforeach; ?>
                            </div>
                        </section>

                        <!-- Quick Actions -->
                        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                            <h2 class="text-xl font-bold text-primary mb-4">Quick Actions</h2>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <a href="upload.php" class="flex items-center gap-4 p-4 rounded-lg border border-slate-200 hover:bg-slate-50 transition-colors group">
                                    <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center"><span class="material-symbols-outlined text-blue-600">cloud_upload</span></div>
                                    <div><h4 class="font-medium text-primary group-hover:text-blue-600 transition-colors">Upload New Data</h4><p class="text-xs text-slate-500">Start a new customer analysis</p></div>
                                </a>
                                <a href="reports.php" class="flex items-center gap-4 p-4 rounded-lg border border-slate-200 hover:bg-slate-50 transition-colors group">
                                    <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-purple-100 flex items-center justify-center"><span class="material-symbols-outlined text-purple-600">description</span></div>
                                    <div><h4 class="font-medium text-primary group-hover:text-purple-600 transition-colors">View Reports</h4><p class="text-xs text-slate-500">See your analysis history</p></div>
                                </a>
                                <a href="analytics.php" class="flex items-center gap-4 p-4 rounded-lg border border-slate-200 hover:bg-slate-50 transition-colors group">
                                    <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center"><span class="material-symbols-outlined text-green-600">analytics</span></div>
                                    <div><h4 class="font-medium text-primary group-hover:text-green-600 transition-colors">View Analytics</h4><p class="text-xs text-slate-500">Explore customer insights</p></div>
                                </a>
                                <a href="dashboard.php" class="flex items-center gap-4 p-4 rounded-lg border border-slate-200 hover:bg-slate-50 transition-colors group">
                                    <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center"><span class="material-symbols-outlined text-gray-600">dashboard</span></div>
                                    <div><h4 class="font-medium text-primary group-hover:text-gray-600 transition-colors">Go to Dashboard</h4><p class="text-xs text-slate-500">View your overview</p></div>
                                </a>
                            </div>
                        </section>
                    </div>

                    <!-- Right Column -->
                    <div class="lg:col-span-1 space-y-6">
                        <!-- Contact Card -->
                        <div class="sticky top-24 rounded-xl bg-primary text-white p-6 shadow-lg">
                            <h3 class="text-lg font-bold">Need Personal Assistance?</h3>
                            <p class="mt-2 text-sm text-white/80">Our support team in Accra is available Mon-Fri, 8am - 5pm to help you succeed.</p>
                            
                            <div class="mt-6 space-y-3">
                                <a href="mailto:support@customer360.gh" class="flex w-full items-center justify-center gap-3 rounded-lg bg-white/10 px-4 py-3 text-sm font-medium transition-colors hover:bg-white/20">
                                    <span class="material-symbols-outlined text-[20px]">mail</span>Email Support
                                </a>
                                <a href="https://wa.me/233241234567?text=Hi, I need help with Customer 360" target="_blank" class="flex w-full items-center justify-center gap-3 rounded-lg bg-[#25D366] px-4 py-3 text-sm font-bold text-white transition-opacity hover:opacity-90 shadow-sm">
                                    <svg class="h-5 w-5 fill-current" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
                                    Chat on WhatsApp
                                </a>
                            </div>
                            
                            <div class="mt-6 pt-4 border-t border-white/20">
                                <p class="text-xs text-white/60">Response time: Usually within 2 hours during business hours.</p>
                            </div>
                        </div>

                        <!-- Resources -->
                        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                            <h3 class="mb-4 text-base font-bold text-primary">Other Resources</h3>
                            <ul class="space-y-3 text-sm">
                                <?php foreach ($resources as $resource): ?>
                                <li>
                                    <a class="flex items-center gap-2 text-slate-600 hover:text-primary transition-colors" href="<?php echo $resource['url']; ?>">
                                        <span class="material-symbols-outlined text-lg"><?php echo $resource['icon']; ?></span>
                                        <span><?php echo htmlspecialchars($resource['title']); ?></span>
                                    </a>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>

                        <!-- System Status -->
                        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                            <h3 class="mb-4 text-base font-bold text-primary">System Status</h3>
                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-slate-600">API Service</span>
                                    <span class="flex items-center gap-1.5 text-xs font-medium text-green-600"><span class="h-2 w-2 rounded-full bg-green-500 animate-pulse"></span>Operational</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-slate-600">File Processing</span>
                                    <span class="flex items-center gap-1.5 text-xs font-medium text-green-600"><span class="h-2 w-2 rounded-full bg-green-500 animate-pulse"></span>Operational</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-slate-600">SMS Gateway</span>
                                    <span class="flex items-center gap-1.5 text-xs font-medium text-green-600"><span class="h-2 w-2 rounded-full bg-green-500 animate-pulse"></span>Operational</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <footer class="mt-auto border-t border-slate-200 bg-white px-6 py-8">
                <div class="mx-auto max-w-5xl flex flex-col md:flex-row justify-between items-center gap-4 text-center md:text-left">
                    <div>
                        <p class="text-sm font-semibold text-primary">© <?php echo $currentYear; ?> Customer 360 Ghana Ltd.</p>
                        <p class="text-xs text-slate-500">Made with ❤️ in Accra.</p>
                    </div>
                    <div class="flex gap-6">
                        <a class="text-sm text-slate-500 hover:text-primary transition-colors" href="#">Privacy Policy</a>
                        <a class="text-sm text-slate-500 hover:text-primary transition-colors" href="#">Terms of Service</a>
                    </div>
                </div>
            </footer>
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
            <a class="flex items-center gap-3 rounded-lg text-slate-300 hover:bg-white/5 px-4 py-3 text-sm font-medium" href="reports.php"><span class="material-symbols-outlined">bar_chart</span>Reports</a>
            <div class="my-4 border-t border-slate-700/50"></div>
            <a class="flex items-center gap-3 rounded-lg bg-white/10 text-white px-4 py-3 text-sm font-medium" href="help.php"><span class="material-symbols-outlined">help</span>Help & Support</a>
            <a class="flex items-center gap-3 rounded-lg text-red-400 hover:bg-white/5 px-4 py-3 text-sm font-medium" href="api/auth.php?action=logout"><span class="material-symbols-outlined">logout</span>Sign Out</a>
        </nav>
    </aside>
    
    <script>
        function toggleUserMenu() { document.getElementById('userMenu').classList.toggle('hidden'); }
        function toggleMobileMenu() {
            document.getElementById('mobileSidebar').classList.toggle('-translate-x-full');
            document.getElementById('mobileMenuOverlay').classList.toggle('hidden');
        }
    </script>
</body>
</html>
