<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: signin.php');
    exit;
}

// User data from session
$userName = $_SESSION['user_name'] ?? 'User';
$userInitials = strtoupper(substr($userName, 0, 1) . (strpos($userName, ' ') ? substr($userName, strpos($userName, ' ') + 1, 1) : ''));
$currentYear = date('Y');
$currentPage = 'upload';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Customer 360 - Data Upload</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        primary: "#0b203c",
                        secondary: "#d4a017",
                        "background-light": "#f6f7f8",
                        "background-dark": "#121820",
                        "surface-light": "#ffffff",
                        "surface-dark": "#1a222e",
                    },
                    fontFamily: {
                        display: ["Inter", "sans-serif"],
                        body: ["Inter", "sans-serif"],
                    },
                    borderRadius: {
                        DEFAULT: "0.5rem",
                        lg: "0.75rem",
                        xl: "1rem",
                        full: "9999px"
                    },
                },
            },
        }
    </script>
</head>
<body class="bg-background-light dark:bg-background-dark font-display text-primary dark:text-white min-h-screen flex flex-col">
    <!-- Top Navigation -->
    <header class="sticky top-0 z-50 bg-surface-light dark:bg-surface-dark border-b border-gray-200 dark:border-gray-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <!-- Logo & Brand -->
                <div class="flex items-center gap-3">
                    <a href="dashboard.php" class="flex items-center gap-3">
                        <div class="bg-primary text-white p-1.5 rounded-lg">
                            <span class="material-symbols-outlined text-2xl">analytics</span>
                        </div>
                        <span class="font-bold text-xl tracking-tight text-primary dark:text-white">Customer 360</span>
                    </a>
                </div>
                
                <!-- Navigation Links -->
                <nav class="hidden md:flex items-center space-x-8">
                    <a class="text-gray-500 dark:text-gray-400 hover:text-primary dark:hover:text-white px-3 py-2 rounded-md text-sm font-medium transition-colors" href="dashboard.php">Dashboard</a>
                    <a class="text-primary dark:text-white bg-primary/5 dark:bg-white/10 px-3 py-2 rounded-md text-sm font-semibold" href="upload.php">Upload Data</a>
                    <a class="text-gray-500 dark:text-gray-400 hover:text-primary dark:hover:text-white px-3 py-2 rounded-md text-sm font-medium transition-colors" href="analytics.php">Analytics</a>
                    <a class="text-gray-500 dark:text-gray-400 hover:text-primary dark:hover:text-white px-3 py-2 rounded-md text-sm font-medium transition-colors" href="settings.php">Settings</a>
                </nav>
                
                <!-- Actions -->
                <div class="flex items-center gap-3">
                    <button class="p-2 text-gray-500 hover:text-primary dark:hover:text-white hover:bg-gray-100 dark:hover:bg-white/10 rounded-full transition-colors">
                        <span class="material-symbols-outlined">notifications</span>
                    </button>
                    <button class="p-2 text-gray-500 hover:text-primary dark:hover:text-white hover:bg-gray-100 dark:hover:bg-white/10 rounded-full transition-colors">
                        <span class="material-symbols-outlined">help</span>
                    </button>
                    
                    <!-- User Menu -->
                    <div class="relative" id="userMenuContainer">
                        <button onclick="toggleUserMenu()" class="h-9 w-9 rounded-full bg-gradient-to-tr from-primary to-blue-600 flex items-center justify-center text-white font-bold text-sm ring-2 ring-white dark:ring-gray-800 ml-2 hover:ring-secondary transition-all">
                            <?php echo htmlspecialchars($userInitials); ?>
                        </button>
                        <div id="userDropdown" class="hidden absolute right-0 mt-2 w-48 bg-white dark:bg-surface-dark rounded-lg shadow-lg border border-gray-100 dark:border-gray-700 py-1 z-50">
                            <div class="px-4 py-2 border-b border-gray-100 dark:border-gray-700">
                                <p class="text-sm font-medium text-primary dark:text-white"><?php echo htmlspecialchars($userName); ?></p>
                            </div>
                            <a href="settings.php" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">Settings</a>
                            <a href="api/auth.php?action=logout" class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-50 dark:hover:bg-gray-800">Sign Out</a>
                        </div>
                    </div>
                    
                    <!-- Mobile Menu Button -->
                    <button onclick="toggleMobileMenu()" class="md:hidden p-2 text-gray-500 hover:text-primary dark:hover:text-white hover:bg-gray-100 dark:hover:bg-white/10 rounded-full transition-colors">
                        <span class="material-symbols-outlined">menu</span>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Mobile Navigation -->
        <div id="mobileMenu" class="hidden md:hidden border-t border-gray-200 dark:border-gray-800 bg-surface-light dark:bg-surface-dark">
            <nav class="px-4 py-3 space-y-2">
                <a class="block text-gray-500 dark:text-gray-400 hover:text-primary dark:hover:text-white px-3 py-2 rounded-md text-sm font-medium transition-colors" href="dashboard.php">Dashboard</a>
                <a class="block text-primary dark:text-white bg-primary/5 dark:bg-white/10 px-3 py-2 rounded-md text-sm font-semibold" href="upload.php">Upload Data</a>
                <a class="block text-gray-500 dark:text-gray-400 hover:text-primary dark:hover:text-white px-3 py-2 rounded-md text-sm font-medium transition-colors" href="analytics.php">Analytics</a>
                <a class="block text-gray-500 dark:text-gray-400 hover:text-primary dark:hover:text-white px-3 py-2 rounded-md text-sm font-medium transition-colors" href="settings.php">Settings</a>
            </nav>
        </div>
    </header>
    
    <!-- Main Content -->
    <main class="flex-grow py-8 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto w-full">
        <!-- Header Section -->
        <div class="mb-10 text-center md:text-left">
            <h1 class="text-3xl md:text-4xl font-extrabold text-primary dark:text-white tracking-tight mb-3">Data Upload</h1>
            <p class="text-lg text-gray-500 dark:text-gray-400 max-w-3xl">
                Securely import your customer and sales data to unlock 360-degree insights. We support CSV and Excel formats to help you get started quickly.
            </p>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left Column: Upload Area (Span 2) -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Drag & Drop Zone -->
                <div id="dropZone" class="bg-surface-light dark:bg-surface-dark rounded-xl border-2 border-dashed border-gray-300 dark:border-gray-700 hover:border-secondary dark:hover:border-secondary transition-colors group relative overflow-hidden cursor-pointer">
                    <div class="absolute inset-0 bg-gradient-to-br from-transparent to-primary/5 opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none"></div>
                    <div class="flex flex-col items-center justify-center py-16 px-6 text-center">
                        <div class="h-20 w-20 bg-primary/5 dark:bg-white/5 rounded-full flex items-center justify-center mb-6 group-hover:scale-110 transition-transform duration-300">
                            <span class="material-symbols-outlined text-5xl text-primary dark:text-white">cloud_upload</span>
                        </div>
                        <h3 class="text-xl font-bold text-primary dark:text-white mb-2">Drag & drop your files here</h3>
                        <p class="text-gray-500 dark:text-gray-400 mb-8 max-w-sm">
                            Or <button type="button" onclick="document.getElementById('fileInput').click()" class="text-secondary font-semibold hover:underline focus:outline-none">browse files</button> from your computer to get started.
                        </p>
                        <div class="flex flex-wrap justify-center gap-4 text-xs text-gray-400 dark:text-gray-500 font-medium">
                            <span class="flex items-center gap-1 bg-gray-100 dark:bg-white/5 px-3 py-1 rounded-full">
                                <span class="material-symbols-outlined text-sm">csv</span> CSV
                            </span>
                            <span class="flex items-center gap-1 bg-gray-100 dark:bg-white/5 px-3 py-1 rounded-full">
                                <span class="material-symbols-outlined text-sm">table_view</span> XLSX
                            </span>
                            <span class="flex items-center gap-1 bg-gray-100 dark:bg-white/5 px-3 py-1 rounded-full">
                                <span class="material-symbols-outlined text-sm">hard_drive</span> Max 25MB
                            </span>
                        </div>
                    </div>
                    <input type="file" id="fileInput" class="hidden" accept=".csv,.xlsx,.xls" multiple />
                </div>
                
                <!-- Active Uploads List -->
                <div id="uploadsList" class="bg-surface-light dark:bg-surface-dark rounded-xl shadow-sm border border-gray-100 dark:border-gray-800 p-6 hidden">
                    <h4 class="text-sm font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-4">Files to Upload</h4>
                    <div id="filesContainer" class="space-y-4">
                        <!-- File items will be dynamically added here -->
                    </div>
                </div>
                
                <!-- Action Bar -->
                <div id="actionBar" class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-4 border-t border-gray-200 dark:border-gray-800 hidden">
                    <label class="flex items-center cursor-pointer select-none group">
                        <div class="relative">
                            <input id="saveConfig" class="sr-only peer" type="checkbox"/>
                            <div class="block bg-gray-300 dark:bg-gray-600 w-10 h-6 rounded-full peer-checked:bg-secondary transition-colors"></div>
                            <div class="dot absolute left-1 top-1 bg-white w-4 h-4 rounded-full transition transform peer-checked:translate-x-4"></div>
                        </div>
                        <div class="ml-3 text-sm font-medium text-primary dark:text-white group-hover:text-secondary transition-colors">
                            Save this run configuration
                        </div>
                    </label>
                    <div class="flex items-center gap-4 w-full sm:w-auto">
                        <div class="flex items-center text-xs text-gray-500 dark:text-gray-400 gap-1 hidden md:flex">
                            <span class="material-symbols-outlined text-sm text-green-600">lock</span>
                            Data is encrypted & secure
                        </div>
                        <button id="uploadBtn" onclick="uploadFiles()" class="flex-1 sm:flex-none bg-primary hover:bg-primary/90 text-white px-8 py-3 rounded-lg font-bold shadow-lg shadow-primary/20 transition-all flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                            <span>Upload & Analyze</span>
                            <span class="material-symbols-outlined text-sm">arrow_forward</span>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Right Column: Requirements (Span 1) -->
            <div class="space-y-6">
                <!-- Requirements Card -->
                <div class="bg-surface-light dark:bg-surface-dark rounded-xl shadow-sm border border-gray-100 dark:border-gray-800 p-6 sticky top-24">
                    <div class="flex items-center gap-2 mb-6 text-primary dark:text-white">
                        <span class="material-symbols-outlined text-secondary">verified_user</span>
                        <h3 class="font-bold text-lg">Requirements</h3>
                    </div>
                    <ul class="space-y-5">
                        <li class="flex gap-3">
                            <div class="shrink-0 h-6 w-6 rounded-full bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center text-primary dark:text-blue-300">
                                <span class="material-symbols-outlined text-sm font-bold">folder_open</span>
                            </div>
                            <div>
                                <h5 class="text-sm font-bold text-primary dark:text-white">Supported Formats</h5>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">We strictly support .CSV and .XLSX files to ensure data integrity.</p>
                            </div>
                        </li>
                        <li class="flex gap-3">
                            <div class="shrink-0 h-6 w-6 rounded-full bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center text-primary dark:text-blue-300">
                                <span class="material-symbols-outlined text-sm font-bold">sd_storage</span>
                            </div>
                            <div>
                                <h5 class="text-sm font-bold text-primary dark:text-white">Max File Size</h5>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Up to 25MB per file. For larger datasets, please contact support.</p>
                            </div>
                        </li>
                        <li class="flex gap-3">
                            <div class="shrink-0 h-6 w-6 rounded-full bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center text-primary dark:text-blue-300">
                                <span class="material-symbols-outlined text-sm font-bold">view_column</span>
                            </div>
                            <div>
                                <h5 class="text-sm font-bold text-primary dark:text-white">Column Headers</h5>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Ensure your first row contains the exact headers as defined in the template.</p>
                            </div>
                        </li>
                    </ul>
                    <div class="mt-8 pt-6 border-t border-gray-100 dark:border-gray-800">
                        <h5 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Resources</h5>
                        <a class="group flex items-center justify-between p-3 rounded-lg bg-background-light dark:bg-background-dark hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors border border-transparent hover:border-gray-200 dark:hover:border-gray-700" href="templates/customer_template.csv" download>
                            <div class="flex items-center gap-3">
                                <span class="material-symbols-outlined text-green-600">download</span>
                                <div class="text-sm font-medium text-primary dark:text-white">Download Template</div>
                            </div>
                            <span class="material-symbols-outlined text-gray-400 text-sm group-hover:translate-x-1 transition-transform">chevron_right</span>
                        </a>
                    </div>
                </div>
                
                <!-- Help Card -->
                <div class="bg-primary rounded-xl p-6 text-white relative overflow-hidden">
                    <!-- Abstract pattern background -->
                    <div class="absolute -top-10 -right-10 w-32 h-32 bg-white/10 rounded-full blur-2xl"></div>
                    <div class="absolute bottom-0 left-0 w-24 h-24 bg-secondary/20 rounded-full blur-xl"></div>
                    <div class="relative z-10">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="material-symbols-outlined text-secondary">lightbulb</span>
                            <h4 class="font-bold">Need assistance?</h4>
                        </div>
                        <p class="text-sm text-blue-100 mb-4 leading-relaxed">
                            Our support team is available to help verify your data structure before uploading.
                        </p>
                        <button onclick="window.location.href='support.php'" class="text-xs font-bold bg-white text-primary px-4 py-2 rounded-lg hover:bg-gray-50 transition-colors w-full sm:w-auto">
                            Chat with Support
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <footer class="mt-auto border-t border-gray-200 dark:border-gray-800 bg-surface-light dark:bg-surface-dark py-6">
        <div class="max-w-7xl mx-auto px-4 text-center text-sm text-gray-500 dark:text-gray-400">
            © <?php echo $currentYear; ?> Customer 360 Ghana. All rights reserved. | <a class="hover:text-primary dark:hover:text-white" href="#">Privacy Policy</a>
        </div>
    </footer>
    
    <script>
        // File storage
        let filesToUpload = [];
        
        // Toggle user menu
        function toggleUserMenu() {
            const dropdown = document.getElementById('userDropdown');
            dropdown.classList.toggle('hidden');
        }
        
        // Toggle mobile menu
        function toggleMobileMenu() {
            const mobileMenu = document.getElementById('mobileMenu');
            mobileMenu.classList.toggle('hidden');
        }
        
        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            const userMenuContainer = document.getElementById('userMenuContainer');
            if (!userMenuContainer.contains(e.target)) {
                document.getElementById('userDropdown').classList.add('hidden');
            }
        });
        
        // Drag and drop handling
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('fileInput');
        
        dropZone.addEventListener('click', () => fileInput.click());
        
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('border-secondary', 'bg-secondary/5');
        });
        
        dropZone.addEventListener('dragleave', (e) => {
            e.preventDefault();
            dropZone.classList.remove('border-secondary', 'bg-secondary/5');
        });
        
        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('border-secondary', 'bg-secondary/5');
            const files = Array.from(e.dataTransfer.files);
            handleFiles(files);
        });
        
        fileInput.addEventListener('change', (e) => {
            const files = Array.from(e.target.files);
            handleFiles(files);
        });
        
        function handleFiles(files) {
            const validExtensions = ['.csv', '.xlsx', '.xls'];
            const maxSize = 25 * 1024 * 1024; // 25MB
            
            files.forEach(file => {
                const ext = '.' + file.name.split('.').pop().toLowerCase();
                
                if (!validExtensions.includes(ext)) {
                    alert(`Invalid file type: ${file.name}. Please upload CSV or Excel files only.`);
                    return;
                }
                
                if (file.size > maxSize) {
                    alert(`File too large: ${file.name}. Maximum file size is 25MB.`);
                    return;
                }
                
                // Check for duplicates
                if (!filesToUpload.find(f => f.name === file.name)) {
                    filesToUpload.push({
                        file: file,
                        name: file.name,
                        size: file.size,
                        status: 'ready',
                        progress: 0
                    });
                }
            });
            
            renderFilesList();
        }
        
        function renderFilesList() {
            const uploadsList = document.getElementById('uploadsList');
            const filesContainer = document.getElementById('filesContainer');
            const actionBar = document.getElementById('actionBar');
            
            if (filesToUpload.length === 0) {
                uploadsList.classList.add('hidden');
                actionBar.classList.add('hidden');
                return;
            }
            
            uploadsList.classList.remove('hidden');
            actionBar.classList.remove('hidden');
            
            filesContainer.innerHTML = filesToUpload.map((fileData, index) => {
                const isExcel = fileData.name.endsWith('.xlsx') || fileData.name.endsWith('.xls');
                const iconBg = isExcel ? 'bg-blue-100 dark:bg-blue-900/30' : 'bg-green-100 dark:bg-green-900/30';
                const iconColor = isExcel ? 'text-primary dark:text-blue-300' : 'text-green-700 dark:text-green-400';
                const icon = isExcel ? 'table_chart' : 'description';
                const sizeFormatted = formatFileSize(fileData.size);
                
                let statusHtml = '';
                let progressWidth = '0%';
                let statusText = '';
                let opacity = '';
                
                if (fileData.status === 'ready') {
                    statusHtml = `<span class="text-xs text-green-600 dark:text-green-400 font-bold flex items-center gap-1">
                        <span class="material-symbols-outlined text-sm">check_circle</span> Ready
                    </span>`;
                    progressWidth = '100%';
                    statusText = `${sizeFormatted} • Ready to upload`;
                    opacity = 'opacity-60';
                } else if (fileData.status === 'uploading') {
                    statusHtml = `<span class="text-xs text-gray-500 dark:text-gray-400">${fileData.progress}%</span>`;
                    progressWidth = `${fileData.progress}%`;
                    statusText = `${formatFileSize(fileData.size * fileData.progress / 100)} of ${sizeFormatted}`;
                } else if (fileData.status === 'completed') {
                    statusHtml = `<span class="text-xs text-green-600 dark:text-green-400 font-bold flex items-center gap-1">
                        <span class="material-symbols-outlined text-sm">check_circle</span> Completed
                    </span>`;
                    progressWidth = '100%';
                    statusText = `${sizeFormatted} • Completed`;
                    opacity = 'opacity-60';
                }
                
                const progressBg = fileData.status === 'uploading' ? 'bg-secondary' : 'bg-primary dark:bg-blue-500';
                
                return `
                    <div class="flex items-center justify-between p-3 bg-background-light dark:bg-background-dark rounded-lg border border-gray-200 dark:border-gray-700 ${opacity}">
                        <div class="flex items-center gap-4 flex-1">
                            <div class="h-10 w-10 ${iconBg} ${iconColor} rounded-lg flex items-center justify-center">
                                <span class="material-symbols-outlined">${icon}</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex justify-between mb-1">
                                    <p class="text-sm font-medium text-primary dark:text-white truncate">${fileData.name}</p>
                                    ${statusHtml}
                                </div>
                                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5">
                                    <div class="${progressBg} h-1.5 rounded-full transition-all duration-300" style="width: ${progressWidth}"></div>
                                </div>
                                <p class="text-xs text-gray-400 mt-1">${statusText}</p>
                            </div>
                        </div>
                        <button onclick="removeFile(${index})" class="ml-4 p-1.5 text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-md transition-colors">
                            <span class="material-symbols-outlined text-lg">${fileData.status === 'completed' ? 'delete' : 'close'}</span>
                        </button>
                    </div>
                `;
            }).join('');
        }
        
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
        }
        
        function removeFile(index) {
            filesToUpload.splice(index, 1);
            renderFilesList();
        }
        
        function uploadFiles() {
            if (filesToUpload.length === 0) {
                alert('Please select at least one file to upload.');
                return;
            }
            
            const uploadBtn = document.getElementById('uploadBtn');
            uploadBtn.disabled = true;
            
            // Simulate upload for each file
            filesToUpload.forEach((fileData, index) => {
                if (fileData.status !== 'ready') return;
                
                fileData.status = 'uploading';
                fileData.progress = 0;
                
                // Simulate upload progress
                const interval = setInterval(() => {
                    fileData.progress += Math.random() * 15 + 5;
                    if (fileData.progress >= 100) {
                        fileData.progress = 100;
                        fileData.status = 'completed';
                        clearInterval(interval);
                        
                        // Check if all files are uploaded
                        const allCompleted = filesToUpload.every(f => f.status === 'completed');
                        if (allCompleted) {
                            uploadBtn.disabled = false;
                            // Store file info and redirect to column mapping
                            setTimeout(() => {
                                // Store the first file name for column mapping
                                sessionStorage.setItem('uploadedFile', filesToUpload[0].name);
                                window.location.href = 'column-mapping.php';
                            }, 500);
                        }
                    }
                    renderFilesList();
                }, 200);
            });
            
            renderFilesList();
        }
    </script>
</body>
</html>
