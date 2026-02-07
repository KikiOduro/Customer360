<?php
/**
 * Processing/Jobs API Endpoint
 * Handles job status polling and result retrieval
 */
require_once __DIR__ . '/config.php';
session_start();

header('Content-Type: application/json');

// Require authentication
if (!isset($_SESSION['user_id'])) {
    jsonResponse(['error' => 'Unauthorized'], 401);
}

$action = $_GET['action'] ?? 'status';
$jobId = $_GET['job_id'] ?? $_SESSION['current_job']['job_id'] ?? null;

switch ($action) {
    case 'status':
        handleStatus($jobId);
        break;
    case 'results':
        handleResults($jobId);
        break;
    case 'list':
        handleList();
        break;
    case 'report':
        handleReport($jobId);
        break;
    case 'delete':
        handleDelete($jobId);
        break;
    case 'cancel':
        handleCancel($jobId);
        break;
    case 'history':
        handleHistory();
        break;
    default:
        jsonResponse(['error' => 'Invalid action'], 400);
}

/**
 * Get job status
 */
function handleStatus($jobId) {
    if (!$jobId) {
        jsonResponse(['error' => 'No job ID provided'], 400);
    }
    
    // Check if demo mode
    if (isset($_SESSION['current_job']['demo_mode']) && $_SESSION['current_job']['demo_mode']) {
        // Simulate progress for demo
        $createdAt = strtotime($_SESSION['current_job']['created_at'] ?? 'now');
        $elapsed = time() - $createdAt;
        
        // Complete after 10 seconds in demo mode
        if ($elapsed > 10) {
            $_SESSION['current_job']['status'] = 'completed';
            $_SESSION['current_job']['completed_at'] = date('c');
            
            // Generate demo results
            generateDemoResults();
        } elseif ($elapsed > 2) {
            $_SESSION['current_job']['status'] = 'processing';
        }
        
        jsonResponse([
            'success' => true,
            'job_id' => $jobId,
            'status' => $_SESSION['current_job']['status'],
            'created_at' => $_SESSION['current_job']['created_at'],
            'completed_at' => $_SESSION['current_job']['completed_at'] ?? null,
            'progress' => min(100, $elapsed * 10),
            'demo_mode' => true
        ]);
    }
    
    // Real API call
    $token = $_SESSION['auth_token'] ?? null;
    
    if ($token) {
        $result = apiRequest("/jobs/status/$jobId", 'GET', null, $token);
        
        if ($result['success']) {
            $_SESSION['current_job']['status'] = $result['data']['status'];
            if ($result['data']['completed_at']) {
                $_SESSION['current_job']['completed_at'] = $result['data']['completed_at'];
            }
            
            jsonResponse([
                'success' => true,
                'job_id' => $result['data']['job_id'],
                'status' => $result['data']['status'],
                'error_message' => $result['data']['error_message'] ?? null,
                'created_at' => $result['data']['created_at'],
                'completed_at' => $result['data']['completed_at']
            ]);
        }
    }
    
    jsonResponse(['error' => 'Failed to get job status'], 500);
}

/**
 * Get job results
 */
function handleResults($jobId) {
    if (!$jobId) {
        jsonResponse(['error' => 'No job ID provided'], 400);
    }
    
    // Check if demo mode - return demo results from session
    if (isset($_SESSION['demo_results'])) {
        jsonResponse([
            'success' => true,
            'results' => $_SESSION['demo_results'],
            'demo_mode' => true
        ]);
    }
    
    // Real API call
    $token = $_SESSION['auth_token'] ?? null;
    
    if ($token) {
        $result = apiRequest("/jobs/results/$jobId", 'GET', null, $token);
        
        if ($result['success']) {
            // Store in session for analytics page
            $_SESSION['analysis_results'] = $result['data'];
            
            jsonResponse([
                'success' => true,
                'results' => $result['data']
            ]);
        }
    }
    
    jsonResponse(['error' => 'Failed to get results'], 500);
}

/**
 * List all jobs for user
 */
function handleList() {
    $token = $_SESSION['auth_token'] ?? null;
    
    if ($token) {
        $result = apiRequest('/jobs/', 'GET', null, $token);
        
        if ($result['success']) {
            jsonResponse([
                'success' => true,
                'jobs' => $result['data']
            ]);
        }
    }
    
    // Demo mode: return current job only
    if (isset($_SESSION['current_job'])) {
        jsonResponse([
            'success' => true,
            'jobs' => [$_SESSION['current_job']],
            'demo_mode' => true
        ]);
    }
    
    jsonResponse(['success' => true, 'jobs' => []]);
}

/**
 * Get PDF report
 */
function handleReport($jobId) {
    if (!$jobId) {
        jsonResponse(['error' => 'No job ID provided'], 400);
    }
    
    $token = $_SESSION['auth_token'] ?? null;
    
    if ($token) {
        // Redirect to backend PDF endpoint
        header("Location: " . BACKEND_API_URL . "/jobs/report/$jobId");
        exit;
    }
    
    jsonResponse(['error' => 'PDF generation requires backend connection'], 501);
}

/**
 * Generate demo results for testing without backend
 */
function generateDemoResults() {
    $upload = $_SESSION['current_upload'] ?? [];
    $mapping = $_SESSION['column_mapping'] ?? [];
    
    // Calculate demo metrics based on sample data
    $sampleRows = $upload['sample_rows'] ?? [];
    $numRecords = count($sampleRows) * 100; // Estimate full dataset
    
    $totalRevenue = 0;
    foreach ($sampleRows as $row) {
        $amountCol = $mapping['amount'] ?? 'Total_GHS';
        $amount = floatval(str_replace([',', 'GH₵', ' '], '', $row[$amountCol] ?? 0));
        $totalRevenue += $amount;
    }
    $totalRevenue *= 100; // Scale up
    
    $_SESSION['demo_results'] = [
        'job_id' => $_SESSION['current_job']['job_id'],
        'status' => 'completed',
        'num_customers' => round($numRecords * 0.3),
        'num_transactions' => $numRecords,
        'total_revenue' => $totalRevenue,
        'date_range' => [
            'start' => '2023-10-01',
            'end' => '2023-12-31'
        ],
        'clustering_method' => 'kmeans',
        'num_clusters' => 5,
        'silhouette_score' => 0.68,
        'segments' => [
            [
                'cluster_id' => 0,
                'segment_label' => 'Champions',
                'num_customers' => round($numRecords * 0.12),
                'percentage' => 40,
                'avg_recency' => 5,
                'avg_frequency' => 12,
                'avg_monetary' => 2500,
                'total_revenue' => $totalRevenue * 0.4,
                'recommended_actions' => [
                    'Offer exclusive loyalty rewards',
                    'Early access to new products',
                    'Personal thank-you messages'
                ]
            ],
            [
                'cluster_id' => 1,
                'segment_label' => 'Loyalists',
                'num_customers' => round($numRecords * 0.08),
                'percentage' => 25,
                'avg_recency' => 15,
                'avg_frequency' => 8,
                'avg_monetary' => 1200,
                'total_revenue' => $totalRevenue * 0.25,
                'recommended_actions' => [
                    'Upsell premium products',
                    'Referral program incentives',
                    'Birthday/anniversary offers'
                ]
            ],
            [
                'cluster_id' => 2,
                'segment_label' => 'Potential Loyalists',
                'num_customers' => round($numRecords * 0.05),
                'percentage' => 15,
                'avg_recency' => 20,
                'avg_frequency' => 4,
                'avg_monetary' => 800,
                'total_revenue' => $totalRevenue * 0.15,
                'recommended_actions' => [
                    'Membership program offers',
                    'Product recommendations',
                    'Engagement campaigns'
                ]
            ],
            [
                'cluster_id' => 3,
                'segment_label' => 'At Risk',
                'num_customers' => round($numRecords * 0.05),
                'percentage' => 15,
                'avg_recency' => 60,
                'avg_frequency' => 3,
                'avg_monetary' => 600,
                'total_revenue' => $totalRevenue * 0.1,
                'recommended_actions' => [
                    'Win-back email campaign',
                    'Special discount offers',
                    'Feedback survey'
                ]
            ],
            [
                'cluster_id' => 4,
                'segment_label' => 'Hibernating',
                'num_customers' => round($numRecords * 0.02),
                'percentage' => 5,
                'avg_recency' => 120,
                'avg_frequency' => 1,
                'avg_monetary' => 200,
                'total_revenue' => $totalRevenue * 0.05,
                'recommended_actions' => [
                    'Reactivation campaign',
                    'Survey to understand needs',
                    'Updated product showcase'
                ]
            ]
        ],
        'recent_customers' => [
            ['name' => 'Abena Boakye', 'email' => 'abena@example.com', 'segment' => 'Champion', 'last_purchase' => 'Oct 24, 2023', 'status' => 'Active', 'spend' => 2450.00],
            ['name' => 'Kwesi Mensah', 'email' => 'kwesi.m@example.com', 'segment' => 'Loyalist', 'last_purchase' => 'Oct 22, 2023', 'status' => 'Active', 'spend' => 850.00],
            ['name' => 'Yaw Addo', 'email' => 'yaw.addo@example.com', 'segment' => 'At Risk', 'last_purchase' => 'Aug 15, 2023', 'status' => 'Inactive', 'spend' => 1200.00],
            ['name' => 'Esi Koomson', 'email' => 'esi.k@example.com', 'segment' => 'Loyalist', 'last_purchase' => 'Oct 20, 2023', 'status' => 'Active', 'spend' => 560.00],
            ['name' => 'Kofi Asante', 'email' => 'kofi.a@example.com', 'segment' => 'Champion', 'last_purchase' => 'Oct 25, 2023', 'status' => 'Active', 'spend' => 3200.00],
            ['name' => 'Akua Mensah', 'email' => 'akua.m@example.com', 'segment' => 'Potential Loyalist', 'last_purchase' => 'Oct 18, 2023', 'status' => 'Active', 'spend' => 420.00]
        ]
    ];
    
    // Also store for analytics page
    $_SESSION['analysis_results'] = $_SESSION['demo_results'];
}

/**
 * Delete a job/report
 */
function handleDelete($jobId) {
    if (!$jobId) {
        jsonResponse(['error' => 'No job ID provided'], 400);
    }
    
    $token = $_SESSION['auth_token'] ?? null;
    
    if ($token) {
        $result = apiRequest("/jobs/$jobId", 'DELETE', null, $token);
        
        if ($result['success']) {
            // Clear from session if it's the current job
            if (isset($_SESSION['current_job']['job_id']) && $_SESSION['current_job']['job_id'] === $jobId) {
                unset($_SESSION['current_job']);
                unset($_SESSION['analysis_results']);
            }
            
            jsonResponse([
                'success' => true,
                'message' => 'Report deleted successfully'
            ]);
        }
    }
    
    // Demo mode - just pretend it worked
    if (isset($_SESSION['current_job']['job_id']) && $_SESSION['current_job']['job_id'] === $jobId) {
        unset($_SESSION['current_job']);
        unset($_SESSION['demo_results']);
        unset($_SESSION['analysis_results']);
    }
    
    jsonResponse([
        'success' => true,
        'message' => 'Report deleted successfully',
        'demo_mode' => true
    ]);
}

/**
 * Cancel a processing job
 */
function handleCancel($jobId) {
    if (!$jobId) {
        jsonResponse(['error' => 'No job ID provided'], 400);
    }
    
    $token = $_SESSION['auth_token'] ?? null;
    
    if ($token) {
        $result = apiRequest("/jobs/$jobId/cancel", 'POST', null, $token);
        
        if ($result['success']) {
            if (isset($_SESSION['current_job']['job_id']) && $_SESSION['current_job']['job_id'] === $jobId) {
                $_SESSION['current_job']['status'] = 'cancelled';
            }
            
            jsonResponse([
                'success' => true,
                'message' => 'Job cancelled successfully'
            ]);
        }
    }
    
    // Demo mode
    if (isset($_SESSION['current_job']['job_id']) && $_SESSION['current_job']['job_id'] === $jobId) {
        $_SESSION['current_job']['status'] = 'cancelled';
    }
    
    jsonResponse([
        'success' => true,
        'message' => 'Job cancelled successfully',
        'demo_mode' => true
    ]);
}

/**
 * Get job history with pagination
 */
function handleHistory() {
    $page = intval($_GET['page'] ?? 1);
    $perPage = intval($_GET['per_page'] ?? 10);
    $status = $_GET['status'] ?? null;
    $search = $_GET['search'] ?? null;
    
    $token = $_SESSION['auth_token'] ?? null;
    
    if ($token) {
        $queryParams = http_build_query([
            'page' => $page,
            'per_page' => $perPage,
            'status' => $status,
            'search' => $search
        ]);
        
        $result = apiRequest("/jobs/?$queryParams", 'GET', null, $token);
        
        if ($result['success']) {
            jsonResponse([
                'success' => true,
                'jobs' => $result['data']['jobs'] ?? $result['data'],
                'total' => $result['data']['total'] ?? count($result['data']),
                'page' => $page,
                'per_page' => $perPage
            ]);
        }
    }
    
    // Demo mode - generate sample history
    $demoJobs = generateDemoHistory($page, $perPage, $status, $search);
    
    jsonResponse([
        'success' => true,
        'jobs' => $demoJobs['jobs'],
        'total' => $demoJobs['total'],
        'page' => $page,
        'per_page' => $perPage,
        'demo_mode' => true
    ]);
}

/**
 * Generate demo job history for testing
 */
function generateDemoHistory($page = 1, $perPage = 10, $statusFilter = null, $search = null) {
    $allJobs = [
        [
            'job_id' => 'job_001',
            'filename' => 'Q3_Sales_Analysis_Final.csv',
            'created_at' => '2026-02-05T10:30:00Z',
            'completed_at' => '2026-02-05T10:35:00Z',
            'customer_count' => 1240,
            'status' => 'completed',
            'segments_count' => 5,
        ],
        [
            'job_id' => 'job_002',
            'filename' => 'Accra_Customer_Segment_v2.csv',
            'created_at' => '2026-02-03T14:20:00Z',
            'completed_at' => '2026-02-03T14:28:00Z',
            'customer_count' => 856,
            'status' => 'completed',
            'segments_count' => 4,
        ],
        [
            'job_id' => 'job_003',
            'filename' => 'Kumasi_Branch_Leads.csv',
            'created_at' => '2026-02-01T09:15:00Z',
            'completed_at' => null,
            'customer_count' => null,
            'status' => 'failed',
            'error_message' => 'Invalid date format in column 3',
        ],
        [
            'job_id' => 'job_004',
            'filename' => 'Churn_Prediction_Feb.csv',
            'created_at' => '2026-01-28T16:45:00Z',
            'completed_at' => null,
            'customer_count' => 2100,
            'status' => 'processing',
            'progress' => 65,
        ],
        [
            'job_id' => 'job_005',
            'filename' => 'Jan_Performance_Review.csv',
            'created_at' => '2026-01-20T11:00:00Z',
            'completed_at' => '2026-01-20T11:12:00Z',
            'customer_count' => 4320,
            'status' => 'completed',
            'segments_count' => 6,
        ],
        [
            'job_id' => 'job_006',
            'filename' => 'December_Sales_Report.csv',
            'created_at' => '2025-12-28T10:00:00Z',
            'completed_at' => '2025-12-28T10:15:00Z',
            'customer_count' => 2890,
            'status' => 'completed',
            'segments_count' => 5,
        ],
        [
            'job_id' => 'job_007',
            'filename' => 'Takoradi_Customers.csv',
            'created_at' => '2025-12-20T14:30:00Z',
            'completed_at' => '2025-12-20T14:42:00Z',
            'customer_count' => 567,
            'status' => 'completed',
            'segments_count' => 4,
        ],
    ];
    
    // Filter by status
    if ($statusFilter) {
        $allJobs = array_filter($allJobs, function($job) use ($statusFilter) {
            return $job['status'] === $statusFilter;
        });
    }
    
    // Filter by search term
    if ($search) {
        $searchLower = strtolower($search);
        $allJobs = array_filter($allJobs, function($job) use ($searchLower) {
            return strpos(strtolower($job['filename']), $searchLower) !== false;
        });
    }
    
    $total = count($allJobs);
    $offset = ($page - 1) * $perPage;
    $jobs = array_slice(array_values($allJobs), $offset, $perPage);
    
    return [
        'jobs' => $jobs,
        'total' => $total
    ];
}
