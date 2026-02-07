<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: signin.php');
    exit;
}

// Get job info from session (set by mapping API)
$currentJob = $_SESSION['current_job'] ?? null;
$jobId = $currentJob['job_id'] ?? 'demo_' . uniqid();
$uploadedFile = $currentJob['filename'] ?? $_SESSION['uploaded_file'] ?? 'customer_data.csv';
$batchId = $_SESSION['batch_id'] ?? rand(1000, 9999);
$recordCount = $_SESSION['record_count'] ?? 1420;
$isDemoMode = $currentJob['demo_mode'] ?? !isset($_SESSION['auth_token']);

// Store batch ID for this session
$_SESSION['batch_id'] = $batchId;

// User info
$userName = $_SESSION['user_name'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Customer 360 - Processing Analysis</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
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
                        "display": ["Inter", "sans-serif"],
                        "body": ["Inter", "sans-serif"]
                    },
                    borderRadius: {
                        "DEFAULT": "0.25rem",
                        "lg": "0.5rem",
                        "xl": "0.75rem",
                        "2xl": "1rem",
                        "full": "9999px"
                    },
                },
            },
        }
    </script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        .material-symbols-outlined.filled {
            font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        .shimmer {
            animation: shimmer 2s infinite;
        }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark text-primary dark:text-white antialiased overflow-x-hidden">
    <div class="relative flex min-h-screen w-full flex-col">
        <!-- Top Navigation -->
        <header class="sticky top-0 z-50 flex items-center justify-between whitespace-nowrap border-b border-solid border-slate-200 dark:border-slate-800 bg-white dark:bg-[#1a222e] px-6 md:px-10 py-3 shadow-sm">
            <div class="flex items-center gap-4 text-primary dark:text-white">
                <a href="dashboard.php" class="flex items-center gap-4">
                    <div class="size-8 flex items-center justify-center rounded bg-primary/10 text-primary">
                        <span class="material-symbols-outlined">analytics</span>
                    </div>
                    <h2 class="text-primary dark:text-white text-lg font-bold leading-tight tracking-[-0.015em]">Customer 360</h2>
                </a>
            </div>
            <div class="flex flex-1 justify-end gap-8">
                <div class="hidden md:flex items-center gap-9">
                    <a class="text-primary dark:text-white text-sm font-medium leading-normal hover:text-primary/70 dark:hover:text-white/70 transition-colors" href="dashboard.php">Dashboard</a>
                    <a class="text-primary dark:text-white text-sm font-medium leading-normal hover:text-primary/70 dark:hover:text-white/70 transition-colors" href="customers.php">Customers</a>
                    <a class="text-primary dark:text-white text-sm font-medium leading-normal hover:text-primary/70 dark:hover:text-white/70 transition-colors" href="campaigns.php">Campaigns</a>
                    <a class="text-primary dark:text-white text-sm font-medium leading-normal hover:text-primary/70 dark:hover:text-white/70 transition-colors" href="settings.php">Settings</a>
                </div>
                <div class="flex gap-3 items-center">
                    <button class="flex items-center justify-center rounded-lg size-10 bg-slate-100 dark:bg-slate-800 text-primary dark:text-white hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors">
                        <span class="material-symbols-outlined">notifications</span>
                    </button>
                    <div class="bg-gradient-to-tr from-primary to-blue-600 rounded-full size-10 flex items-center justify-center text-white font-bold text-sm border-2 border-white dark:border-slate-700">
                        <?php echo strtoupper(substr($userName, 0, 1)); ?>
                    </div>
                </div>
            </div>
        </header>
        
        <div class="layout-container flex h-full grow flex-col">
            <div class="px-4 md:px-10 lg:px-40 flex flex-1 justify-center py-8">
                <div class="layout-content-container flex flex-col max-w-[800px] flex-1 w-full gap-8">
                    <!-- Header Section -->
                    <div class="flex flex-col gap-2 text-center">
                        <h1 class="text-primary dark:text-white tracking-tight text-3xl md:text-4xl font-bold leading-tight">Analyzing Your Customer Data</h1>
                        <p class="text-slate-500 dark:text-slate-400 text-base font-normal leading-relaxed max-w-2xl mx-auto">Please wait while we crunch the numbers to find your best customers. This usually takes 1-2 minutes depending on your data volume.</p>
                    </div>
                    
                    <!-- Main Content Card -->
                    <div class="bg-white dark:bg-[#1a222e] rounded-2xl shadow-sm border border-slate-200 dark:border-slate-800 overflow-hidden">
                        <!-- Progress Bar Section -->
                        <div class="p-6 md:p-8 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-[#1e2837]">
                            <div class="flex justify-between items-end mb-3">
                                <div>
                                    <p class="text-primary dark:text-white text-lg font-bold leading-normal">Overall Progress</p>
                                    <p id="timeRemaining" class="text-slate-500 dark:text-slate-400 text-sm">Estimated time remaining: 2 mins</p>
                                </div>
                                <span id="progressPercent" class="text-2xl font-bold text-primary dark:text-white">0%</span>
                            </div>
                            <div class="relative w-full h-3 bg-slate-200 dark:bg-slate-700 rounded-full overflow-hidden">
                                <div id="progressBar" class="absolute top-0 left-0 h-full bg-primary transition-all duration-500 ease-out rounded-full" style="width: 0%;">
                                    <div class="absolute inset-0 bg-white/20 shimmer w-full h-full" style="background-image: linear-gradient(to right, transparent 0%, rgba(255,255,255,0.3) 50%, transparent 100%); background-size: 200% 100%;"></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Stepper Section -->
                        <div class="p-6 md:p-8 flex flex-col md:flex-row gap-8">
                            <!-- Illustration Area -->
                            <div class="hidden md:flex w-1/3 flex-col justify-center items-center p-4 bg-slate-50 dark:bg-slate-800/50 rounded-xl">
                                <div class="w-full aspect-square bg-gradient-to-br from-primary/20 via-blue-500/20 to-purple-500/20 rounded-lg mb-4 relative overflow-hidden">
                                    <div class="absolute inset-0 flex items-center justify-center">
                                        <div class="w-3/4 h-3/4 border-4 border-primary/30 rounded-full animate-ping"></div>
                                    </div>
                                    <div class="absolute inset-0 flex items-center justify-center">
                                        <span class="material-symbols-outlined text-6xl text-primary/60">query_stats</span>
                                    </div>
                                </div>
                                <p class="text-center text-sm font-medium text-slate-600 dark:text-slate-300">Processing Batch #<?php echo $batchId; ?></p>
                            </div>
                            
                            <!-- Vertical Stepper -->
                            <div class="flex-1 space-y-0">
                                <!-- Step 1: Validating -->
                                <div id="step1" class="relative pl-10 pb-8 group" data-status="pending">
                                    <div class="absolute left-0 top-0 flex h-full w-10 justify-center">
                                        <div class="step-line h-full w-0.5 bg-slate-200 dark:bg-slate-700"></div>
                                    </div>
                                    <div class="step-icon absolute left-0 top-0 flex size-8 -ml-4 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800 text-slate-400 ring-4 ring-white dark:ring-[#1a222e]">
                                        <span class="material-symbols-outlined text-[10px] filled">circle</span>
                                    </div>
                                    <div class="step-content flex flex-col gap-1 opacity-60">
                                        <h3 class="text-base font-medium text-primary dark:text-white">Validating Data Structure</h3>
                                        <p class="step-text text-sm text-slate-500 dark:text-slate-400">Pending</p>
                                    </div>
                                </div>
                                
                                <!-- Step 2: Cleaning -->
                                <div id="step2" class="relative pl-10 pb-8 group" data-status="pending">
                                    <div class="absolute left-0 top-0 flex h-full w-10 justify-center">
                                        <div class="step-line h-full w-0.5 bg-slate-200 dark:bg-slate-700"></div>
                                    </div>
                                    <div class="step-icon absolute left-0 top-0 flex size-8 -ml-4 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800 text-slate-400 ring-4 ring-white dark:ring-[#1a222e]">
                                        <span class="material-symbols-outlined text-[10px] filled">circle</span>
                                    </div>
                                    <div class="step-content flex flex-col gap-1 opacity-60">
                                        <h3 class="text-base font-medium text-primary dark:text-white">Cleaning & Normalizing</h3>
                                        <p class="step-text text-sm text-slate-500 dark:text-slate-400">Pending</p>
                                    </div>
                                </div>
                                
                                <!-- Step 3: Computing RFM -->
                                <div id="step3" class="relative pl-10 pb-8 group" data-status="pending">
                                    <div class="absolute left-0 top-0 flex h-full w-10 justify-center">
                                        <div class="step-line h-full w-0.5 bg-slate-200 dark:bg-slate-700"></div>
                                    </div>
                                    <div class="step-icon absolute left-0 top-0 flex size-8 -ml-4 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800 text-slate-400 ring-4 ring-white dark:ring-[#1a222e]">
                                        <span class="material-symbols-outlined text-[10px] filled">circle</span>
                                    </div>
                                    <div class="step-content flex flex-col gap-1 opacity-60">
                                        <h3 class="text-base font-medium text-primary dark:text-white">Computing RFM Models</h3>
                                        <p class="step-text text-sm text-slate-500 dark:text-slate-400">Pending</p>
                                    </div>
                                </div>
                                
                                <!-- Step 4: Segmenting -->
                                <div id="step4" class="relative pl-10 pb-8 group" data-status="pending">
                                    <div class="absolute left-0 top-0 flex h-full w-10 justify-center">
                                        <div class="step-line h-full w-0.5 bg-slate-200 dark:bg-slate-700"></div>
                                    </div>
                                    <div class="step-icon absolute left-0 top-0 flex size-8 -ml-4 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800 text-slate-400 ring-4 ring-white dark:ring-[#1a222e]">
                                        <span class="material-symbols-outlined text-[10px] filled">circle</span>
                                    </div>
                                    <div class="step-content flex flex-col gap-1 opacity-60">
                                        <h3 class="text-base font-medium text-primary dark:text-white">Segmenting Customer Base</h3>
                                        <p class="step-text text-sm text-slate-500 dark:text-slate-400">Pending</p>
                                    </div>
                                </div>
                                
                                <!-- Step 5: Generating Insights -->
                                <div id="step5" class="relative pl-10 group" data-status="pending">
                                    <div class="absolute left-0 top-0 flex h-full w-10 justify-center">
                                        <div class="step-line h-full w-0.5 bg-slate-200 dark:bg-slate-700 hidden"></div>
                                    </div>
                                    <div class="step-icon absolute left-0 top-0 flex size-8 -ml-4 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800 text-slate-400 ring-4 ring-white dark:ring-[#1a222e]">
                                        <span class="material-symbols-outlined text-[10px] filled">circle</span>
                                    </div>
                                    <div class="step-content flex flex-col gap-1 opacity-60">
                                        <h3 class="text-base font-medium text-primary dark:text-white">Generating Actionable Insights</h3>
                                        <p class="step-text text-sm text-slate-500 dark:text-slate-400">Pending</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Friendly Tip & Action -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Friendly Tip Card -->
                        <div class="md:col-span-2 bg-blue-50 dark:bg-blue-900/10 border border-blue-100 dark:border-blue-800/30 rounded-xl p-5 flex gap-4 items-start">
                            <div class="bg-blue-100 dark:bg-blue-800/30 text-primary dark:text-blue-200 p-2 rounded-lg shrink-0">
                                <span class="material-symbols-outlined">lightbulb</span>
                            </div>
                            <div>
                                <h4 class="text-sm font-bold text-primary dark:text-blue-100 mb-1">Did you know?</h4>
                                <p class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                                    RFM analysis helps you identify your <strong>'Champions'</strong>—customers who buy often and spend the most. Targeting them can increase revenue by up to 30%.
                                </p>
                            </div>
                        </div>
                        
                        <!-- Action Button Container -->
                        <div class="flex flex-col justify-center">
                            <button id="viewResultsBtn" onclick="goToResults()" class="w-full h-full min-h-[60px] flex items-center justify-center gap-2 rounded-xl bg-slate-200 dark:bg-slate-800 text-slate-400 dark:text-slate-500 font-bold text-base cursor-not-allowed transition-all" disabled>
                                <span>View Results</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Processing steps configuration
        const steps = [
            { id: 'step1', name: 'Validating Data Structure', duration: 3000, completeText: 'Completed successfully' },
            { id: 'step2', name: 'Cleaning & Normalizing', duration: 4000, completeText: '<?php echo number_format($recordCount); ?> records processed' },
            { id: 'step3', name: 'Computing RFM Models', duration: 5000, completeText: 'RFM scores calculated', activeText: 'Analyzing recency, frequency, and monetary value...' },
            { id: 'step4', name: 'Segmenting Customer Base', duration: 4000, completeText: '5 segments identified', activeText: 'Clustering customers by behavior...' },
            { id: 'step5', name: 'Generating Actionable Insights', duration: 3000, completeText: 'Analysis complete!', activeText: 'Preparing recommendations...' }
        ];
        
        let currentStep = 0;
        let totalDuration = steps.reduce((sum, s) => sum + s.duration, 0);
        let elapsedTime = 0;
        
        // Update step status
        function updateStep(stepIndex, status) {
            const step = document.getElementById(steps[stepIndex].id);
            const icon = step.querySelector('.step-icon');
            const content = step.querySelector('.step-content');
            const text = step.querySelector('.step-text');
            const line = step.querySelector('.step-line');
            
            step.dataset.status = status;
            
            if (status === 'active') {
                icon.className = 'step-icon absolute left-0 top-0 flex size-8 -ml-4 items-center justify-center rounded-full bg-primary text-white ring-4 ring-white dark:ring-[#1a222e] shadow-md shadow-primary/20';
                icon.innerHTML = '<span class="material-symbols-outlined text-lg animate-spin">progress_activity</span>';
                content.classList.remove('opacity-60');
                content.querySelector('h3').classList.add('font-bold');
                content.querySelector('h3').classList.remove('font-medium');
                text.className = 'step-text text-sm text-primary/80 dark:text-blue-300 font-medium';
                text.textContent = steps[stepIndex].activeText || 'Processing...';
            } else if (status === 'completed') {
                icon.className = 'step-icon absolute left-0 top-0 flex size-8 -ml-4 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400 ring-4 ring-white dark:ring-[#1a222e]';
                icon.innerHTML = '<span class="material-symbols-outlined text-lg">check</span>';
                content.classList.remove('opacity-60');
                content.querySelector('h3').classList.remove('font-bold');
                content.querySelector('h3').classList.add('font-semibold');
                text.className = 'step-text text-sm text-slate-500 dark:text-slate-400';
                text.textContent = steps[stepIndex].completeText;
                if (line) {
                    line.classList.remove('bg-slate-200', 'dark:bg-slate-700');
                    line.classList.add('bg-primary/20');
                }
            }
        }
        
        // Update progress bar
        function updateProgress(percent) {
            document.getElementById('progressBar').style.width = percent + '%';
            document.getElementById('progressPercent').textContent = Math.round(percent) + '%';
            
            const remainingMs = totalDuration - elapsedTime;
            const remainingMins = Math.ceil(remainingMs / 60000);
            const remainingSecs = Math.ceil((remainingMs % 60000) / 1000);
            
            if (remainingMins > 0) {
                document.getElementById('timeRemaining').textContent = `Estimated time remaining: ${remainingMins} min${remainingMins > 1 ? 's' : ''}`;
            } else if (remainingSecs > 0) {
                document.getElementById('timeRemaining').textContent = `Estimated time remaining: ${remainingSecs} seconds`;
            } else {
                document.getElementById('timeRemaining').textContent = 'Almost done...';
            }
        }
        
        // Enable results button
        function enableResultsButton() {
            const btn = document.getElementById('viewResultsBtn');
            btn.disabled = false;
            btn.className = 'w-full h-full min-h-[60px] flex items-center justify-center gap-2 rounded-xl bg-primary hover:bg-primary/90 text-white shadow-lg shadow-primary/30 font-bold text-base transition-all transform hover:-translate-y-0.5 cursor-pointer';
            btn.innerHTML = '<span>View Results</span><span class="material-symbols-outlined">arrow_forward</span>';
        }
        
        // Navigate to results
        function goToResults() {
            window.location.href = 'analytics.php';
        }
        
        // Job ID and demo mode from PHP
        const jobId = '<?php echo addslashes($jobId); ?>';
        const isDemoMode = <?php echo $isDemoMode ? 'true' : 'false'; ?>;
        
        // Poll API for real job status
        async function pollJobStatus() {
            try {
                const response = await fetch(`api/process.php?action=status&job_id=${jobId}`);
                const data = await response.json();
                
                if (data.success) {
                    // Update progress if provided
                    if (data.progress !== undefined) {
                        updateProgress(data.progress);
                        
                        // Map progress to steps
                        const stepProgress = Math.floor(data.progress / 20); // 5 steps, 20% each
                        for (let i = 0; i < stepProgress && i < steps.length; i++) {
                            if (document.getElementById(steps[i].id).dataset.status !== 'completed') {
                                updateStep(i, 'completed');
                            }
                        }
                        if (stepProgress < steps.length) {
                            updateStep(stepProgress, 'active');
                        }
                    }
                    
                    if (data.status === 'completed') {
                        // Mark all steps complete
                        for (let i = 0; i < steps.length; i++) {
                            updateStep(i, 'completed');
                        }
                        updateProgress(100);
                        enableResultsButton();
                        return; // Stop polling
                    } else if (data.status === 'failed') {
                        alert('Processing failed: ' + (data.error_message || 'Unknown error'));
                        return;
                    }
                }
                
                // Continue polling
                setTimeout(pollJobStatus, 1000);
            } catch (error) {
                console.error('Error polling status:', error);
                // Continue with demo simulation on error
                if (!isDemoMode) {
                    setTimeout(pollJobStatus, 2000);
                }
            }
        }
        
        // Run the processing simulation (for demo mode or as visual feedback)
        function runProcessing() {
            let stepStartTime = 0;
            
            function processStep() {
                if (currentStep >= steps.length) {
                    updateProgress(100);
                    enableResultsButton();
                    return;
                }
                
                // Mark current step as active
                updateStep(currentStep, 'active');
                
                const stepDuration = steps[currentStep].duration;
                const progressInterval = 100; // Update every 100ms
                let stepElapsed = 0;
                
                const interval = setInterval(() => {
                    stepElapsed += progressInterval;
                    elapsedTime += progressInterval;
                    
                    // Calculate overall progress
                    const overallProgress = (elapsedTime / totalDuration) * 100;
                    updateProgress(Math.min(overallProgress, 99));
                    
                    if (stepElapsed >= stepDuration) {
                        clearInterval(interval);
                        updateStep(currentStep, 'completed');
                        currentStep++;
                        setTimeout(processStep, 500); // Small delay between steps
                    }
                }, progressInterval);
            }
            
            // Start processing after a brief delay
            setTimeout(processStep, 500);
        }
        
        // Start processing on page load
        document.addEventListener('DOMContentLoaded', function() {
            if (isDemoMode) {
                // Run visual simulation for demo
                runProcessing();
            } else {
                // Poll real API for status
                pollJobStatus();
                // Also run visual simulation as backup
                runProcessing();
            }
        });
    </script>
</body>
</html>
