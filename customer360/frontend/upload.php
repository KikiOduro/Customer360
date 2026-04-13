<?php
/**
 * Guided upload page for the production Customer360 workflow.
 *
 * The page accepts a CSV file from the browser, sends it to frontend/api/upload.php
 * for backend previewing, then moves the user to column-mapping.php once the server
 * has confirmed the file can be read.
 */
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: signin.php');
    exit;
}

// User data from session
$rawUserName = trim((string) ($_SESSION['user_name'] ?? ''));
$rawCompanyName = trim((string) ($_SESSION['company_name'] ?? ''));
$rawUserEmail = trim((string) ($_SESSION['user_email'] ?? ''));
$profileLabel = $rawUserName !== '' ? $rawUserName : ($rawUserEmail !== '' ? explode('@', $rawUserEmail)[0] : 'Account');
$companyLabel = $rawCompanyName !== '' ? $rawCompanyName : 'Signed in account';
$userInitials = strtoupper(substr($profileLabel, 0, 1));
$currentYear = date('Y');
$currentPage = 'upload';
// This flag is shown when someone opens the mapping page without a valid upload preview.
$missingPreview = isset($_GET['missing_preview']) && $_GET['missing_preview'] === '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Upload Data - Customer 360</title>
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
                        "primary-hover": "#153055",
                        "background-light": "#f6f7f8",
                        "background-dark": "#121820",
                        "accent": "#e8b031",
                    },
                    fontFamily: {
                        "display": ["Inter", "sans-serif"],
                        "body": ["Inter", "sans-serif"]
                    },
                },
            },
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        aside::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); }
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
                    <span class="material-symbols-outlined">dashboard</span>
                    Dashboard
                </a>
                <a class="flex items-center gap-3 rounded-lg <?php echo $currentPage === 'upload' ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white'; ?> px-4 py-3 text-sm font-medium transition-colors" href="upload.php">
                    <span class="material-symbols-outlined">upload_file</span>
                    Upload Data
                </a>
                <a class="flex items-center gap-3 rounded-lg <?php echo $currentPage === 'analytics' ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white'; ?> px-4 py-3 text-sm font-medium transition-colors" href="analytics.php">
                    <span class="material-symbols-outlined">analytics</span>
                    Analytics
                </a>
                <a class="flex items-center gap-3 rounded-lg <?php echo $currentPage === 'reports' ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white'; ?> px-4 py-3 text-sm font-medium transition-colors" href="reports.php">
                    <span class="material-symbols-outlined">bar_chart</span>
                    Reports
                </a>
                
                <div class="my-4 border-t border-slate-700/50"></div>
                
                <a class="flex items-center gap-3 rounded-lg <?php echo $currentPage === 'help' ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white'; ?> px-4 py-3 text-sm font-medium transition-colors" href="help.php">
                    <span class="material-symbols-outlined">help</span>
                    Help & Support
                </a>
            </nav>
            
            <div class="border-t border-slate-700/50 p-4">
                <div class="flex items-center gap-3 rounded-lg p-2 hover:bg-white/5 cursor-pointer transition-colors group" onclick="toggleUserMenu()">
                    <div class="h-10 w-10 rounded-full bg-slate-600 flex items-center justify-center text-white font-semibold text-sm">
                        <?php echo $userInitials; ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-white truncate"><?php echo htmlspecialchars($profileLabel); ?></p>
                        <p class="text-xs text-slate-400 truncate"><?php echo htmlspecialchars($companyLabel); ?></p>
                    </div>
                    <span class="material-symbols-outlined text-slate-400 group-hover:text-white transition-colors text-[20px]">expand_more</span>
                </div>
                
                <div id="userMenu" class="hidden mt-2 py-2 bg-slate-800 rounded-lg shadow-lg">
                    <a href="help.php" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-300 hover:bg-slate-700 hover:text-white transition-colors">
                        <span class="material-symbols-outlined text-[18px]">person</span>
                        My Profile
                    </a>
                    <a href="api/auth.php?action=logout" class="flex items-center gap-2 px-4 py-2 text-sm text-red-400 hover:bg-slate-700 hover:text-red-300 transition-colors">
                        <span class="material-symbols-outlined text-[18px]">logout</span>
                        Sign Out
                    </a>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex flex-1 flex-col overflow-y-auto bg-background-light relative">
            
            <header class="sticky top-0 z-10 flex h-20 items-center justify-between border-b border-slate-200 bg-white px-6 sm:px-10">
                <div class="flex items-center gap-4">
                    <button class="md:hidden text-slate-500 hover:text-slate-700" onclick="toggleMobileMenu()">
                        <span class="material-symbols-outlined">menu</span>
                    </button>
                    <div class="hidden sm:flex items-center text-sm text-slate-500">
                        <a class="hover:text-primary transition-colors" href="dashboard.php">Home</a>
                        <span class="mx-2 text-slate-300">/</span>
                        <span class="font-medium text-primary">Upload Data</span>
                    </div>
                </div>
                
                <div class="flex items-center gap-4">
                    <div class="relative hidden sm:block">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[20px]">search</span>
                        <input class="h-10 w-64 rounded-full border border-slate-200 bg-slate-50 pl-10 pr-4 text-sm text-slate-700 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary" placeholder="Search data..." type="text"/>
                    </div>
                    <button class="relative rounded-full p-2 text-slate-500 hover:bg-slate-100 transition-colors">
                        <span class="material-symbols-outlined">notifications</span>
                        <span class="absolute right-2 top-2 h-2 w-2 rounded-full bg-red-500 ring-2 ring-white"></span>
                    </button>
                </div>
            </header>

            <div class="p-6 sm:p-10 max-w-7xl mx-auto w-full">
                <div class="mb-8">
                    <h1 class="text-3xl font-bold text-primary tracking-tight">Data Upload</h1>
                    <p class="text-slate-500 mt-2">Securely import your customer and sales data to unlock 360-degree insights.</p>
                </div>
                
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <div class="lg:col-span-2 space-y-6">
                        <div id="dropZone" class="bg-white rounded-xl border-2 border-dashed border-slate-300 hover:border-accent transition-colors group relative overflow-hidden cursor-pointer shadow-sm">
                            <div class="flex flex-col items-center justify-center py-16 px-6 text-center">
                                <div class="h-20 w-20 bg-primary/5 rounded-full flex items-center justify-center mb-6 group-hover:scale-110 transition-transform duration-300">
                                    <span class="material-symbols-outlined text-5xl text-primary">cloud_upload</span>
                                </div>
                                <h3 class="text-xl font-bold text-primary mb-2">Drag & drop your files here</h3>
                                <p class="text-slate-500 mb-8 max-w-sm">
                                    Or <button type="button" id="browseFilesBtn" class="text-accent font-semibold hover:underline focus:outline-none">browse files</button> from your computer.
                                </p>
                                <div class="flex flex-wrap justify-center gap-4 text-xs text-slate-400 font-medium">
                                    <span class="flex items-center gap-1 bg-slate-100 px-3 py-1 rounded-full">CSV</span>
                                    <span class="flex items-center gap-1 bg-slate-100 px-3 py-1 rounded-full">Preview + Mapping</span>
                                    <span class="flex items-center gap-1 bg-slate-100 px-3 py-1 rounded-full">Max 25MB</span>
                                </div>
                            </div>
                            <input type="file" id="fileInput" class="hidden" accept=".csv" multiple />
                        </div>
                        
                        <div id="uploadsList" class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 hidden">
                            <h4 class="text-sm font-bold text-slate-500 uppercase tracking-wider mb-4">Files to Upload</h4>
                            <div id="filesContainer" class="space-y-4"></div>
                        </div>
                        
                        <div id="actionBar" class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-4 border-t border-slate-200 hidden">
                            <div class="flex items-center text-xs text-slate-500 gap-1">
                                <span class="material-symbols-outlined text-sm text-green-600">lock</span>
                                Data is encrypted & secure
                            </div>
                            <button id="uploadBtn" onclick="uploadFiles()" class="bg-primary hover:bg-primary-hover text-white px-8 py-3 rounded-lg font-bold shadow-lg transition-all flex items-center gap-2 disabled:opacity-50">
                                <span>Preview & Map Columns</span>
                                <span class="material-symbols-outlined text-sm">arrow_forward</span>
                            </button>
                        </div>

                        <div id="uploadStatusCard" class="<?php echo $missingPreview ? '' : 'hidden '; ?>rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="flex items-start gap-4">
                                <div id="uploadStatusIcon" class="mt-0.5 flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-600">
                                    <span class="material-symbols-outlined"><?php echo $missingPreview ? 'info' : 'cloud_upload'; ?></span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-col gap-1">
                                        <p id="uploadStatusTitle" class="text-sm font-bold text-primary"><?php echo $missingPreview ? 'Upload preview expired' : 'Ready to upload'; ?></p>
                                        <p id="uploadStatusMessage" class="text-sm text-slate-500"><?php echo $missingPreview ? 'We could not find the temporary preview data needed for column mapping. Please upload the file again to continue.' : 'Choose a file to begin a new segmentation run.'; ?></p>
                                    </div>
                                    <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-200">
                                        <div id="uploadProgressBar" class="h-full w-0 rounded-full bg-primary transition-all duration-300 ease-out"></div>
                                    </div>
                                    <div class="mt-2 flex items-center justify-between text-xs text-slate-500">
                                        <span id="uploadProgressLabel"><?php echo $missingPreview ? 'Upload required' : 'Waiting for your file'; ?></span>
                                        <span id="uploadProgressPercent">0%</span>
                                    </div>
                                    <div id="uploadStatusMeta" class="mt-3 <?php echo $missingPreview ? '' : 'hidden '; ?>rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800"><?php echo $missingPreview ? 'This usually happens when the session was cleared or the temporary preview file was removed.' : ''; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="space-y-6">
                        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                            <div class="flex items-center gap-2 mb-6 text-primary">
                                <span class="material-symbols-outlined text-accent">verified_user</span>
                                <h3 class="font-bold text-lg">Requirements</h3>
                            </div>
                            <ul class="space-y-5">
                                <li class="flex gap-3">
                                    <div class="shrink-0 h-6 w-6 rounded-full bg-blue-50 flex items-center justify-center text-primary">
                                        <span class="material-symbols-outlined text-sm">folder_open</span>
                                    </div>
                                    <div>
                                        <h5 class="text-sm font-bold text-primary">Supported Formats</h5>
                                        <p class="text-xs text-slate-500 mt-0.5">CSV files only for the mapping + validation flow.</p>
                                    </div>
                                </li>
                                <li class="flex gap-3">
                                    <div class="shrink-0 h-6 w-6 rounded-full bg-blue-50 flex items-center justify-center text-primary">
                                        <span class="material-symbols-outlined text-sm">sd_storage</span>
                                    </div>
                                    <div>
                                        <h5 class="text-sm font-bold text-primary">Max File Size</h5>
                                        <p class="text-xs text-slate-500 mt-0.5">Up to 25MB per file.</p>
                                    </div>
                                </li>
                            </ul>
                            <div class="mt-8 pt-6 border-t border-slate-100">
                                <a class="group flex items-center justify-between p-3 rounded-lg bg-slate-50 hover:bg-slate-100 transition-colors" href="templates/rfm_customer_transactions_template.csv" download>
                                    <div class="flex items-center gap-3">
                                        <span class="material-symbols-outlined text-green-600">download</span>
                                        <div class="text-sm font-medium text-primary">Download RFM Template</div>
                                    </div>
                                    <span class="material-symbols-outlined text-slate-400 text-sm">chevron_right</span>
                                </a>
                            </div>
                        </div>
                        
                        <div class="bg-primary rounded-xl p-6 text-white relative overflow-hidden">
                            <div class="absolute -top-10 -right-10 w-32 h-32 bg-white/10 rounded-full blur-2xl"></div>
                            <div class="relative z-10">
                                <div class="flex items-center gap-2 mb-3">
                                    <span class="material-symbols-outlined text-accent">lightbulb</span>
                                    <h4 class="font-bold">Need assistance?</h4>
                                </div>
                                <p class="text-sm text-blue-100 mb-4">Our support team can help verify your data structure.</p>
                                <a href="help.php" class="inline-block text-xs font-bold bg-white text-primary px-4 py-2 rounded-lg hover:bg-slate-50 transition-colors">Get Help</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <div id="mobileMenuOverlay" class="fixed inset-0 bg-black/50 z-40 hidden md:hidden" onclick="toggleMobileMenu()"></div>
    
    <aside id="mobileSidebar" class="fixed top-0 left-0 w-72 h-full bg-primary text-white z-50 transform -translate-x-full transition-transform md:hidden">
        <div class="flex h-20 items-center gap-3 px-6 border-b border-slate-700/50">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-white/10 text-white">
                <span class="material-symbols-outlined">analytics</span>
            </div>
            <div class="flex-1">
                <h1 class="text-lg font-bold">Customer 360</h1>
                <p class="text-slate-400 text-xs">SME Intelligence</p>
            </div>
            <button class="text-slate-400 hover:text-white" onclick="toggleMobileMenu()">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <nav class="px-4 py-6 space-y-2">
            <a class="flex items-center gap-3 rounded-lg text-slate-300 hover:bg-white/5 px-4 py-3 text-sm font-medium" href="dashboard.php"><span class="material-symbols-outlined">dashboard</span>Dashboard</a>
            <a class="flex items-center gap-3 rounded-lg bg-white/10 text-white px-4 py-3 text-sm font-medium" href="upload.php"><span class="material-symbols-outlined">upload_file</span>Upload Data</a>
            <a class="flex items-center gap-3 rounded-lg text-slate-300 hover:bg-white/5 px-4 py-3 text-sm font-medium" href="analytics.php"><span class="material-symbols-outlined">analytics</span>Analytics</a>
            <a class="flex items-center gap-3 rounded-lg text-slate-300 hover:bg-white/5 px-4 py-3 text-sm font-medium" href="reports.php"><span class="material-symbols-outlined">bar_chart</span>Reports</a>
            <div class="my-4 border-t border-slate-700/50"></div>
            <a class="flex items-center gap-3 rounded-lg text-slate-300 hover:bg-white/5 px-4 py-3 text-sm font-medium" href="help.php"><span class="material-symbols-outlined">help</span>Help & Support</a>
            <a class="flex items-center gap-3 rounded-lg text-red-400 hover:bg-white/5 px-4 py-3 text-sm font-medium" href="api/auth.php?action=logout"><span class="material-symbols-outlined">logout</span>Sign Out</a>
        </nav>
    </aside>
    
    <script>
        let filesToUpload = [];
        let uploadStageTimer = null;
        let uploadStageIndex = 0;
        // These messages keep the user informed while the browser uploads the file
        // and waits for the FastAPI preview endpoint to return column metadata.
        const uploadStages = [
            'Checking the file before upload...',
            'Building a preview so you can confirm your column mapping...',
            'Profiling columns, sample values, and parse quality...',
            'Opening the mapping workspace for validation...'
        ];

        function setUploadStatus({ type = 'info', title, message, percent = null, meta = '', show = true }) {
            const card = document.getElementById('uploadStatusCard');
            const icon = document.getElementById('uploadStatusIcon');
            const titleEl = document.getElementById('uploadStatusTitle');
            const messageEl = document.getElementById('uploadStatusMessage');
            const bar = document.getElementById('uploadProgressBar');
            const label = document.getElementById('uploadProgressLabel');
            const percentEl = document.getElementById('uploadProgressPercent');
            const metaEl = document.getElementById('uploadStatusMeta');

            const styles = {
                info: ['bg-slate-100', 'text-slate-600', 'cloud_upload'],
                uploading: ['bg-blue-100', 'text-blue-700', 'progress_activity'],
                success: ['bg-green-100', 'text-green-700', 'check_circle'],
                warning: ['bg-amber-100', 'text-amber-700', 'warning'],
                error: ['bg-red-100', 'text-red-700', 'error']
            };
            const [bgClass, textClass, iconName] = styles[type] || styles.info;

            card.classList.toggle('hidden', !show);
            icon.className = `mt-0.5 flex h-10 w-10 items-center justify-center rounded-full ${bgClass} ${textClass}`;
            icon.innerHTML = `<span class="material-symbols-outlined ${type === 'uploading' ? 'animate-spin' : ''}">${iconName}</span>`;
            titleEl.textContent = title;
            messageEl.textContent = message;

            if (percent !== null) {
                const boundedPercent = Math.max(0, Math.min(100, percent));
                bar.style.width = `${boundedPercent}%`;
                percentEl.textContent = `${Math.round(boundedPercent)}%`;
                label.textContent = boundedPercent >= 100 ? 'Complete' : 'In progress';
            }

            if (meta) {
                metaEl.textContent = meta;
                metaEl.classList.remove('hidden');
            } else {
                metaEl.textContent = '';
                metaEl.classList.add('hidden');
            }
        }

        function showInlineError(message) {
            setUploadStatus({
                type: 'error',
                title: 'Upload could not continue',
                message,
                meta: 'You can adjust the file or retry once the backend issue is resolved.',
                percent: 0
            });
        }

        function startUploadNarration(fileName) {
            clearInterval(uploadStageTimer);
            uploadStageIndex = 0;
            setUploadStatus({
                type: 'uploading',
                title: 'Uploading your dataset',
                message: `${uploadStages[uploadStageIndex]} ${fileName}`,
                percent: 8
            });

            uploadStageTimer = setInterval(() => {
                uploadStageIndex = Math.min(uploadStageIndex + 1, uploadStages.length - 1);
                const stagedPercent = Math.min(25 + uploadStageIndex * 18, 82);
                setUploadStatus({
                    type: 'uploading',
                    title: 'Uploading your dataset',
                    message: `${uploadStages[uploadStageIndex]} ${fileName}`,
                    percent: stagedPercent
                });
            }, 1800);
        }

        function stopUploadNarration() {
            clearInterval(uploadStageTimer);
            uploadStageTimer = null;
        }
        
        function toggleUserMenu() {
            document.getElementById('userMenu').classList.toggle('hidden');
        }
        
        function toggleMobileMenu() {
            document.getElementById('mobileSidebar').classList.toggle('-translate-x-full');
            document.getElementById('mobileMenuOverlay').classList.toggle('hidden');
        }
        
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('fileInput');
        
        document.getElementById('browseFilesBtn')?.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            fileInput.click();
        });

        dropZone.addEventListener('click', (event) => {
            if (event.target?.closest('#browseFilesBtn')) {
                return;
            }
            fileInput.click();
        });
        dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.classList.add('border-accent', 'bg-accent/5'); });
        dropZone.addEventListener('dragleave', (e) => { e.preventDefault(); dropZone.classList.remove('border-accent', 'bg-accent/5'); });
        dropZone.addEventListener('drop', (e) => { e.preventDefault(); dropZone.classList.remove('border-accent', 'bg-accent/5'); handleFiles(Array.from(e.dataTransfer.files)); });
        fileInput.addEventListener('change', (e) => handleFiles(Array.from(e.target.files)));
        
        function handleFiles(files) {
            const validExtensions = ['.csv'];
            const maxSize = 25 * 1024 * 1024;
            
            files.forEach(file => {
                const ext = '.' + file.name.split('.').pop().toLowerCase();
                if (!validExtensions.includes(ext)) {
                    showInlineError(`"${file.name}" is not supported. Upload a CSV file for the mapping + validation flow.`);
                    return;
                }
                if (file.size > maxSize) {
                    showInlineError(`"${file.name}" is larger than the 25 MB upload limit.`);
                    return;
                }
                if (!filesToUpload.find(f => f.name === file.name)) {
                    filesToUpload.push({ file, name: file.name, size: file.size, status: 'ready', progress: 0 });
                    setUploadStatus({
                        type: 'info',
                        title: 'File ready',
                        message: `${file.name} is queued and ready for upload.`,
                        percent: 0,
                        meta: 'When you continue, we will generate a preview first so you can validate column mapping before launching the job.'
                    });
                }
            });
            renderFilesList();
        }
        
        function renderFilesList() {
            const uploadsList = document.getElementById('uploadsList');
            const filesContainer = document.getElementById('filesContainer');
            const actionBar = document.getElementById('actionBar');
            
            if (filesToUpload.length === 0) { uploadsList.classList.add('hidden'); actionBar.classList.add('hidden'); return; }
            
            uploadsList.classList.remove('hidden');
            actionBar.classList.remove('hidden');
            
            filesContainer.innerHTML = filesToUpload.map((f, i) => `
                <div class="flex items-center justify-between p-3 bg-slate-50 rounded-lg border border-slate-200">
                    <div class="flex items-center gap-4 flex-1">
                        <div class="h-10 w-10 bg-green-100 text-green-700 rounded-lg flex items-center justify-center">
                            <span class="material-symbols-outlined">description</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-primary truncate">${f.name}</p>
                            <p class="text-xs text-slate-400">${formatFileSize(f.size)} • ${f.status === 'ready' ? 'Ready' : f.status}</p>
                        </div>
                    </div>
                    <button onclick="removeFile(${i})" class="ml-4 p-1.5 text-slate-400 hover:text-red-500 rounded-md">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
            `).join('');
        }
        
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024, sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
        }
        
        function removeFile(index) {
            filesToUpload.splice(index, 1);
            renderFilesList();
            if (filesToUpload.length === 0) {
                setUploadStatus({
                    type: 'info',
                    title: 'Ready to upload',
                    message: 'Choose a file to begin a new segmentation run.',
                    percent: 0,
                    meta: ''
                });
            }
        }
        
        function uploadFiles() {
            // The upload endpoint stores preview metadata in the PHP session so the
            // mapping page can render suggested fields without exposing server paths.
            if (filesToUpload.length === 0) {
                showInlineError('Please select a file before starting the upload.');
                return;
            }

            const uploadBtn = document.getElementById('uploadBtn');
            const btnText   = uploadBtn.querySelector('span:first-child');
            uploadBtn.disabled = true;
            btnText.textContent = 'Uploading...';

            const fileData = filesToUpload[0];
            const formData = new FormData();
            formData.append('file', fileData.file);
            startUploadNarration(fileData.name);

            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'api/upload.php?action=preview');
            xhr.responseType = 'json';

            xhr.upload.addEventListener('progress', (event) => {
                if (!event.lengthComputable) return;
                const percent = Math.max(10, Math.min((event.loaded / event.total) * 100, 92));
                setUploadStatus({
                    type: 'uploading',
                    title: 'Uploading your dataset',
                    message: `Sending ${fileData.name} to the server...`,
                    percent
                });
            });

            xhr.onload = () => {
                stopUploadNarration();
                const data = xhr.response || safeJsonParse(xhr.responseText);

                if (xhr.status >= 200 && xhr.status < 300 && data?.success) {
                    setUploadStatus({
                        type: 'success',
                        title: 'Preview ready',
                        message: 'Your dataset was uploaded to a temporary preview area. Opening column mapping and validation...',
                        percent: 100,
                        meta: `${data.filename || fileData.name} is ready for field mapping.`
                    });
                    setTimeout(() => {
                        window.location.href = 'column-mapping.php';
                    }, 900);
                    return;
                }

                const backendResponse = data?.backend_response ? ` Backend says: ${data.backend_response}` : '';
                showInlineError((data?.error || `Upload failed with status ${xhr.status}.`) + backendResponse);
                uploadBtn.disabled = false;
                btnText.textContent = 'Preview & Map Columns';
            };

            xhr.onerror = () => {
                stopUploadNarration();
                showInlineError('Network error while contacting the upload API. Please check the backend connection and retry.');
                uploadBtn.disabled = false;
                btnText.textContent = 'Preview & Map Columns';
            };

            xhr.send(formData);
        }

        function safeJsonParse(raw) {
            try {
                return JSON.parse(raw);
            } catch (error) {
                return null;
            }
        }
    </script>
</body>
</html>
