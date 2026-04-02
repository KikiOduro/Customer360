<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: signin.php');
    exit;
}

$currentJob = $_SESSION['current_job'] ?? null;
$jobId = $_GET['job_id'] ?? ($currentJob['job_id'] ?? null);
if (!$jobId) {
    header('Location: upload.php');
    exit;
}

$uploadedFile = $currentJob['filename'] ?? 'Uploaded file';
$userName = trim((string) ($_SESSION['user_name'] ?? ''));
$userEmail = trim((string) ($_SESSION['user_email'] ?? ''));
$profileLabel = $userName !== '' ? $userName : $userEmail;
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
                    colors: { "primary": "#0b203c", "background-light": "#f6f7f8", "background-dark": "#121820" },
                    fontFamily: { "display": ["Inter", "sans-serif"], "body": ["Inter", "sans-serif"] },
                    borderRadius: { "DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "2xl": "1rem", "full": "9999px" }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
        .material-symbols-outlined.filled { font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark text-primary dark:text-white antialiased overflow-x-hidden">
    <div class="relative flex min-h-screen w-full flex-col">
        <header class="sticky top-0 z-50 flex items-center justify-between border-b border-slate-200 dark:border-slate-800 bg-white dark:bg-[#1a222e] px-6 md:px-10 py-3 shadow-sm">
            <div class="flex items-center gap-4 text-primary dark:text-white">
                <a href="dashboard.php" class="flex items-center gap-4">
                    <div class="size-8 flex items-center justify-center rounded bg-primary/10 text-primary">
                        <span class="material-symbols-outlined">analytics</span>
                    </div>
                    <h2 class="text-lg font-bold tracking-[-0.015em]">Customer 360</h2>
                </a>
            </div>
            <div class="flex flex-1 justify-end gap-8">
                <div class="hidden md:flex items-center gap-9">
                    <a class="text-sm font-medium hover:text-primary/70 transition-colors" href="dashboard.php">Dashboard</a>
                    <a class="text-sm font-medium hover:text-primary/70 transition-colors" href="upload.php">Upload</a>
                    <a class="text-sm font-medium hover:text-primary/70 transition-colors" href="analytics.php">Analytics</a>
                    <a class="text-sm font-medium hover:text-primary/70 transition-colors" href="reports.php">Reports</a>
                </div>
                <div class="flex gap-3 items-center">
                    <button class="flex items-center justify-center rounded-lg size-10 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors">
                        <span class="material-symbols-outlined">notifications</span>
                    </button>
                    <div class="bg-gradient-to-tr from-primary to-blue-600 rounded-full size-10 flex items-center justify-center text-white font-bold text-sm border-2 border-white dark:border-slate-700">
                        <?php echo htmlspecialchars(strtoupper(substr($profileLabel, 0, 1))); ?>
                    </div>
                </div>
            </div>
        </header>

        <div class="px-4 md:px-10 lg:px-40 flex flex-1 justify-center py-8">
            <div class="flex flex-col max-w-[800px] flex-1 w-full gap-8">
                <div class="flex flex-col gap-2 text-center">
                    <h1 class="tracking-tight text-3xl md:text-4xl font-bold leading-tight">Analyzing Your Customer Data</h1>
                    <p class="text-slate-500 dark:text-slate-400 text-base leading-relaxed max-w-2xl mx-auto">We are tracking your live analysis job and will unlock the results as soon as the backend marks it complete.</p>
                </div>

                <div class="bg-white dark:bg-[#1a222e] rounded-2xl shadow-sm border border-slate-200 dark:border-slate-800 overflow-hidden">
                    <div class="p-6 md:p-8 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-[#1e2837]">
                        <div class="flex justify-between items-end mb-3">
                            <div>
                                <p class="text-lg font-bold leading-normal">Overall Progress</p>
                                <p id="timeRemaining" class="text-slate-500 dark:text-slate-400 text-sm">Waiting for the backend to start processing...</p>
                            </div>
                            <span id="progressPercent" class="text-2xl font-bold">0%</span>
                        </div>
                        <div class="relative w-full h-3 bg-slate-200 dark:bg-slate-700 rounded-full overflow-hidden">
                            <div id="progressBar" class="absolute top-0 left-0 h-full bg-primary transition-all duration-500 ease-out rounded-full" style="width: 0%;"></div>
                        </div>
                    </div>

                    <div class="p-6 md:p-8 flex flex-col md:flex-row gap-8">
                        <div class="hidden md:flex w-1/3 flex-col justify-center items-center p-4 bg-slate-50 dark:bg-slate-800/50 rounded-xl">
                            <div class="w-full aspect-square bg-gradient-to-br from-primary/20 via-blue-500/20 to-purple-500/20 rounded-lg mb-4 relative overflow-hidden flex items-center justify-center">
                                <span class="material-symbols-outlined text-6xl text-primary/60">query_stats</span>
                            </div>
                            <p class="text-center text-sm font-medium text-slate-600 dark:text-slate-300"><?php echo htmlspecialchars($uploadedFile); ?></p>
                        </div>

                        <div class="flex-1 space-y-0">
                            <?php
                            $steps = [
                                'Validating Data Structure',
                                'Cleaning & Normalizing',
                                'Computing RFM Models',
                                'Segmenting Customer Base',
                                'Generating Actionable Insights',
                            ];
                            foreach ($steps as $index => $stepLabel):
                            ?>
                            <div id="step<?php echo $index + 1; ?>" class="relative pl-10 <?php echo $index < count($steps) - 1 ? 'pb-8' : ''; ?> group" data-status="pending">
                                <div class="absolute left-0 top-0 flex h-full w-10 justify-center">
                                    <div class="step-line h-full w-0.5 bg-slate-200 dark:bg-slate-700 <?php echo $index === count($steps) - 1 ? 'hidden' : ''; ?>"></div>
                                </div>
                                <div class="step-icon absolute left-0 top-0 flex size-8 -ml-4 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800 text-slate-400 ring-4 ring-white dark:ring-[#1a222e]">
                                    <span class="material-symbols-outlined text-[10px] filled">circle</span>
                                </div>
                                <div class="step-content flex flex-col gap-1 opacity-60">
                                    <h3 class="text-base font-medium"><?php echo htmlspecialchars($stepLabel); ?></h3>
                                    <p class="step-text text-sm text-slate-500 dark:text-slate-400">Pending</p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="md:col-span-2 bg-blue-50 dark:bg-blue-900/10 border border-blue-100 dark:border-blue-800/30 rounded-xl p-5 flex gap-4 items-start">
                        <div class="bg-blue-100 dark:bg-blue-800/30 p-2 rounded-lg shrink-0">
                            <span class="material-symbols-outlined">lightbulb</span>
                        </div>
                        <div>
                            <h4 class="text-sm font-bold mb-1">Current Job</h4>
                            <p class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed">Job ID: <span class="font-mono"><?php echo htmlspecialchars($jobId); ?></span></p>
                            <p class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed mt-1">File: <?php echo htmlspecialchars($uploadedFile); ?></p>
                        </div>
                    </div>
                    <div class="flex flex-col justify-center">
                        <button id="viewResultsBtn" onclick="goToResults()" class="w-full h-full min-h-[60px] flex items-center justify-center gap-2 rounded-xl bg-slate-200 dark:bg-slate-800 text-slate-400 dark:text-slate-500 font-bold text-base cursor-not-allowed transition-all" disabled>
                            <span>View Results</span>
                        </button>
                    </div>
                </div>

                <div id="jobNarrationCard" class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-[#1a222e] p-5 shadow-sm">
                    <div class="flex items-start gap-4">
                        <div id="jobNarrationIcon" class="mt-0.5 flex h-10 w-10 items-center justify-center rounded-full bg-blue-100 text-blue-700">
                            <span class="material-symbols-outlined animate-spin">progress_activity</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p id="jobNarrationTitle" class="text-sm font-bold text-primary dark:text-white">Analysis is being prepared</p>
                            <p id="jobNarrationMessage" class="mt-1 text-sm text-slate-500 dark:text-slate-400">We are waiting for the backend to start the segmentation job.</p>
                            <div id="jobNarrationMeta" class="mt-3 hidden rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-300"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const steps = [
            { id: 'step1', activeText: 'Checking uploaded file structure...', completeText: 'Validation complete' },
            { id: 'step2', activeText: 'Preparing transactions for analysis...', completeText: 'Data cleaning complete' },
            { id: 'step3', activeText: 'Calculating recency, frequency, and monetary metrics...', completeText: 'RFM metrics computed' },
            { id: 'step4', activeText: 'Running clustering and segment assignment...', completeText: 'Customer segmentation complete' },
            { id: 'step5', activeText: 'Preparing results and recommendations...', completeText: 'Analysis complete!' }
        ];
        let pollAttempts = 0;

        function setNarration(type, title, message, meta = '') {
            const icon = document.getElementById('jobNarrationIcon');
            const titleEl = document.getElementById('jobNarrationTitle');
            const messageEl = document.getElementById('jobNarrationMessage');
            const metaEl = document.getElementById('jobNarrationMeta');
            const config = {
                info: ['bg-blue-100 text-blue-700', 'progress_activity', true],
                success: ['bg-green-100 text-green-700', 'check_circle', false],
                warning: ['bg-amber-100 text-amber-700', 'warning', false],
                error: ['bg-red-100 text-red-700', 'error', false]
            }[type] || ['bg-blue-100 text-blue-700', 'progress_activity', true];

            icon.className = `mt-0.5 flex h-10 w-10 items-center justify-center rounded-full ${config[0]}`;
            icon.innerHTML = `<span class="material-symbols-outlined ${config[2] ? 'animate-spin' : ''}">${config[1]}</span>`;
            titleEl.textContent = title;
            messageEl.textContent = message;

            if (meta) {
                metaEl.textContent = meta;
                metaEl.classList.remove('hidden');
            } else {
                metaEl.textContent = '';
                metaEl.classList.add('hidden');
            }
        }

        function updateStep(stepIndex, status) {
            const step = document.getElementById(steps[stepIndex].id);
            const icon = step.querySelector('.step-icon');
            const content = step.querySelector('.step-content');
            const text = step.querySelector('.step-text');
            const line = step.querySelector('.step-line');

            if (status === 'active') {
                icon.className = 'step-icon absolute left-0 top-0 flex size-8 -ml-4 items-center justify-center rounded-full bg-primary text-white ring-4 ring-white dark:ring-[#1a222e] shadow-md shadow-primary/20';
                icon.innerHTML = '<span class="material-symbols-outlined text-lg animate-spin">progress_activity</span>';
                content.classList.remove('opacity-60');
                text.className = 'step-text text-sm text-primary/80 dark:text-blue-300 font-medium';
                text.textContent = steps[stepIndex].activeText;
            } else if (status === 'completed') {
                icon.className = 'step-icon absolute left-0 top-0 flex size-8 -ml-4 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400 ring-4 ring-white dark:ring-[#1a222e]';
                icon.innerHTML = '<span class="material-symbols-outlined text-lg">check</span>';
                content.classList.remove('opacity-60');
                text.className = 'step-text text-sm text-slate-500 dark:text-slate-400';
                text.textContent = steps[stepIndex].completeText;
                if (line) line.classList.add('bg-primary/20');
            }
        }

        function updateProgress(percent, message) {
            document.getElementById('progressBar').style.width = percent + '%';
            document.getElementById('progressPercent').textContent = Math.round(percent) + '%';
            document.getElementById('timeRemaining').textContent = message;
        }

        function enableResultsButton() {
            const btn = document.getElementById('viewResultsBtn');
            btn.disabled = false;
            btn.className = 'w-full h-full min-h-[60px] flex items-center justify-center gap-2 rounded-xl bg-primary hover:bg-primary/90 text-white shadow-lg shadow-primary/30 font-bold text-base transition-all cursor-pointer';
            btn.innerHTML = '<span>View Results</span><span class="material-symbols-outlined">arrow_forward</span>';
        }

        function goToResults() {
            window.location.href = 'analytics.php?job_id=' + encodeURIComponent(jobId);
        }

        function applyJobState(status) {
            const statusConfig = {
                pending: { progress: 15, activeStep: 0, message: 'Your file has been received and queued for analysis.', narration: ['info', 'Job queued', 'Your dataset is in line for processing. We will move through validation and segmentation automatically.', 'This stage usually lasts only a few moments.'] },
                processing: { progress: 70, activeStep: 3, message: 'Analysis is running on the backend now.', narration: ['info', 'Segmentation is running', 'We are cleaning the data, computing RFM features, and generating your customer segments now.', 'Keep this page open if you want to watch progress update live.'] },
                completed: { progress: 100, activeStep: 4, message: 'Analysis finished successfully.', narration: ['success', 'Analysis complete', 'Your results are ready. You can now open the analytics dashboard for this job.', 'The report and segment outputs have been generated.'] },
                cancelled: { progress: 100, activeStep: 0, message: 'This analysis job was cancelled.', narration: ['warning', 'Analysis cancelled', 'This job was cancelled before completion.', 'Start a new upload when you are ready to try again.'] }
            };
            const config = statusConfig[status] || statusConfig.pending;

            steps.forEach((step, index) => {
                if (status === 'completed' || index < config.activeStep) {
                    updateStep(index, 'completed');
                } else if (index === config.activeStep) {
                    updateStep(index, 'active');
                }
            });

            updateProgress(config.progress, config.message);
            setNarration(...config.narration);
        }

        const jobId = <?php echo json_encode($jobId); ?>;

        async function pollJobStatus() {
            try {
                const response = await fetch(`api/process.php?action=status&job_id=${encodeURIComponent(jobId)}`);
                const data = await response.json();
                if (!data.success) {
                    throw new Error(data.error || 'Failed to get job status');
                }

                if (data.status === 'failed') {
                    updateProgress(100, 'Analysis failed.');
                    setNarration(
                        'error',
                        'Analysis failed',
                        data.error_message || 'The backend reported a failure while processing this dataset.',
                        'You can return to Upload Data, adjust the file, and submit a new run.'
                    );
                    return;
                }

                if (data.status === 'cancelled') {
                    updateProgress(100, 'Analysis cancelled.');
                    setNarration(
                        'warning',
                        'Analysis cancelled',
                        data.error_message || 'This analysis job was cancelled.',
                        'No further processing will happen for this job.'
                    );
                    return;
                }

                pollAttempts = 0;
                applyJobState(data.status);

                if (data.status === 'completed') {
                    enableResultsButton();
                    return;
                }

                setTimeout(pollJobStatus, 1500);
            } catch (error) {
                console.error(error);
                pollAttempts += 1;
                updateProgress(5, 'Waiting for the backend to respond...');
                setNarration(
                    pollAttempts > 2 ? 'warning' : 'info',
                    pollAttempts > 2 ? 'Still trying to reach the backend' : 'Connecting to the backend',
                    pollAttempts > 2
                        ? 'The status API is taking longer than expected, but we are still polling automatically.'
                        : 'We are checking the current job status now.',
                    pollAttempts > 2 ? 'If this persists, the backend may be restarting or the job queue may be busy.' : ''
                );
                setTimeout(pollJobStatus, 3000);
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            applyJobState('pending');
            pollJobStatus();
        });
    </script>
</body>
</html>
