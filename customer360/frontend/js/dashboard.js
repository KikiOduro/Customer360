/**
 * Customer360 - Dashboard Module
 * Handles jobs list, results display, and reporting
 */

const dashboard = {
    currentJobId: null,
    pollingInterval: null,

    /**
     * Render main dashboard (jobs list)
     */
    async renderDashboard() {
        try {
            const jobs = await api.get('/jobs/');
            
            if (jobs.length === 0) {
                return `
                    <div class="dashboard-header">
                        <h1>📊 Dashboard</h1>
                        <a href="#/upload" class="btn btn-primary">
                            📤 Upload Data
                        </a>
                    </div>
                    
                    <div class="card">
                        <div class="card-body">
                            <div class="empty-state">
                                <div class="empty-icon">📁</div>
                                <h3>No Analysis Jobs Yet</h3>
                                <p>Upload your customer transaction data to get started with segmentation</p>
                                <a href="#/upload" class="btn btn-primary btn-lg mt-md">
                                    Upload Your First Dataset
                                </a>
                            </div>
                        </div>
                    </div>
                `;
            }

            const jobsHtml = jobs.map(job => `
                <div class="job-item">
                    <div class="job-info">
                        <span class="file-icon">📄</span>
                        <div class="job-details">
                            <h4>${job.original_filename}</h4>
                            <p>
                                ${job.num_customers ? `${job.num_customers.toLocaleString()} customers • ` : ''}
                                ${job.total_revenue ? `GH₵${job.total_revenue.toLocaleString()}` : ''}
                                ${!job.num_customers && !job.total_revenue ? new Date(job.created_at).toLocaleDateString() : ''}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-md">
                        <span class="status-badge status-${job.status}">${job.status}</span>
                        <div class="job-actions">
                            ${job.status === 'completed' ? `
                                <a href="#/results/${job.job_id}" class="btn btn-primary btn-sm">View Results</a>
                                <button class="btn btn-secondary btn-sm" onclick="dashboard.downloadReport('${job.job_id}')">📄 PDF</button>
                            ` : ''}
                            ${job.status === 'failed' ? `
                                <button class="btn btn-secondary btn-sm" onclick="dashboard.deleteJob('${job.job_id}')">Delete</button>
                            ` : ''}
                            ${job.status === 'processing' || job.status === 'pending' ? `
                                <a href="#/results/${job.job_id}" class="btn btn-secondary btn-sm">Check Status</a>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `).join('');

            return `
                <div class="dashboard-header">
                    <h1>📊 Dashboard</h1>
                    <a href="#/upload" class="btn btn-primary">
                        📤 Upload New Data
                    </a>
                </div>
                
                <div class="jobs-list">
                    ${jobsHtml}
                </div>
            `;
        } catch (error) {
            return `
                <div class="card">
                    <div class="card-body">
                        <div class="empty-state">
                            <div class="empty-icon">❌</div>
                            <h3>Error Loading Dashboard</h3>
                            <p>${error.message}</p>
                            <button class="btn btn-primary mt-md" onclick="app.navigate('dashboard')">
                                Try Again
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }
    },

    /**
     * Render results page for a specific job
     */
    async renderResultsPage(jobId) {
        this.currentJobId = jobId;
        
        try {
            // First check job status
            const status = await api.get(`/jobs/status/${jobId}`);
            
            if (status.status === 'pending' || status.status === 'processing') {
                this.startPolling(jobId);
                return this.renderProcessingState(status);
            }
            
            if (status.status === 'failed') {
                return this.renderFailedState(status);
            }
            
            // Job is completed, get results
            const results = await api.get(`/jobs/results/${jobId}`);
            return this.renderCompletedResults(results);
            
        } catch (error) {
            return `
                <div class="card">
                    <div class="card-body">
                        <div class="empty-state">
                            <div class="empty-icon">❌</div>
                            <h3>Error Loading Results</h3>
                            <p>${error.message}</p>
                            <a href="#/dashboard" class="btn btn-primary mt-md">
                                Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            `;
        }
    },

    /**
     * Render processing state
     */
    renderProcessingState(status) {
        return `
            <div class="dashboard-header">
                <h1>⏳ Analysis in Progress</h1>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <div class="empty-state">
                        <div class="spinner" style="margin: 0 auto;"></div>
                        <h3 class="mt-lg">Processing Your Data</h3>
                        <p>This may take a few moments depending on dataset size...</p>
                        <p class="form-hint mt-md">Status: ${status.status}</p>
                    </div>
                </div>
            </div>
        `;
    },

    /**
     * Render failed state
     */
    renderFailedState(status) {
        return `
            <div class="dashboard-header">
                <h1>❌ Analysis Failed</h1>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <div class="empty-state">
                        <div class="empty-icon">⚠️</div>
                        <h3>Something Went Wrong</h3>
                        <p>${status.error_message || 'An unknown error occurred during analysis'}</p>
                        <div class="flex gap-md justify-center mt-lg">
                            <a href="#/upload" class="btn btn-primary">Try Again</a>
                            <a href="#/dashboard" class="btn btn-secondary">Back to Dashboard</a>
                        </div>
                    </div>
                </div>
            </div>
        `;
    },

    /**
     * Render completed results
     */
    renderCompletedResults(results) {
        const meta = results.meta || {};
        const segments = results.segments || [];
        const preprocessing = results.preprocessing || {};
        const dateRange = preprocessing.date_range || {};

        // Stats cards
        const statsHtml = `
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">👥</div>
                    <div class="stat-value">${(meta.num_customers || 0).toLocaleString()}</div>
                    <div class="stat-label">Total Customers</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🧾</div>
                    <div class="stat-value">${(meta.num_transactions || 0).toLocaleString()}</div>
                    <div class="stat-label">Total Transactions</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">💰</div>
                    <div class="stat-value">GH₵${(meta.total_revenue || 0).toLocaleString()}</div>
                    <div class="stat-label">Total Revenue</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🎯</div>
                    <div class="stat-value">${meta.num_clusters || 0}</div>
                    <div class="stat-label">Customer Segments</div>
                </div>
            </div>
        `;

        // Segments cards
        const segmentsHtml = segments.map((seg, idx) => {
            const colors = ['#2563eb', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'];
            const bgColor = colors[idx % colors.length];
            
            return `
                <div class="segment-card">
                    <div class="segment-card-header" style="background: linear-gradient(135deg, ${bgColor}, ${bgColor}dd);">
                        <h3>${seg.segment_label}</h3>
                        <div class="segment-percentage">${seg.percentage}%</div>
                        <div style="font-size: 0.875rem; opacity: 0.9;">${seg.num_customers.toLocaleString()} customers</div>
                    </div>
                    <div class="segment-card-body">
                        <div class="segment-metrics">
                            <div class="segment-metric">
                                <div class="metric-value">${seg.avg_recency.toFixed(0)}</div>
                                <div class="metric-label">Avg Recency (days)</div>
                            </div>
                            <div class="segment-metric">
                                <div class="metric-value">${seg.avg_frequency.toFixed(1)}</div>
                                <div class="metric-label">Avg Frequency</div>
                            </div>
                            <div class="segment-metric">
                                <div class="metric-value">GH₵${seg.avg_monetary.toFixed(0)}</div>
                                <div class="metric-label">Avg Monetary</div>
                            </div>
                        </div>
                        <div class="segment-actions">
                            <h4>Recommended Actions</h4>
                            <ul>
                                ${seg.recommended_actions.slice(0, 3).map(action => `<li>${action}</li>`).join('')}
                            </ul>
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        // Cluster size chart (simple bar representation)
        const clusterSizesHtml = segments.map((seg, idx) => {
            const colors = ['#2563eb', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'];
            const bgColor = colors[idx % colors.length];
            
            return `
                <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.5rem;">
                    <div style="width: 120px; font-size: 0.875rem;">${seg.segment_label}</div>
                    <div style="flex: 1; background: #f3f4f6; border-radius: 4px; height: 24px; overflow: hidden;">
                        <div style="width: ${seg.percentage}%; background: ${bgColor}; height: 100%; display: flex; align-items: center; justify-content: flex-end; padding-right: 8px; color: white; font-size: 0.75rem; font-weight: 500;">
                            ${seg.percentage}%
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        return `
            <div class="dashboard-header">
                <h1>📊 Analysis Results</h1>
                <div class="flex gap-md">
                    <button class="btn btn-success" onclick="dashboard.downloadReport('${meta.job_id}')">
                        📄 Download PDF Report
                    </button>
                    <button class="btn btn-secondary" onclick="dashboard.downloadCSV('${meta.job_id}')">
                        📥 Download CSV
                    </button>
                </div>
            </div>
            
            ${statsHtml}
            
            <div class="card mb-xl">
                <div class="card-header">
                    <h3 class="card-title">📈 Segment Distribution</h3>
                </div>
                <div class="card-body">
                    ${clusterSizesHtml}
                </div>
            </div>
            
            <div class="card mb-xl">
                <div class="card-header">
                    <h3 class="card-title">📊 Analysis Details</h3>
                </div>
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                        <div>
                            <strong>Clustering Method:</strong> ${meta.clustering_method?.toUpperCase() || 'K-Means'}
                        </div>
                        <div>
                            <strong>Silhouette Score:</strong> ${(meta.silhouette_score || 0).toFixed(3)}
                        </div>
                        <div>
                            <strong>Date Range:</strong> ${dateRange.start?.substring(0, 10) || 'N/A'} to ${dateRange.end?.substring(0, 10) || 'N/A'}
                        </div>
                        <div>
                            <strong>Analysis Time:</strong> ${(meta.duration_seconds || 0).toFixed(1)}s
                        </div>
                    </div>
                </div>
            </div>
            
            <h2 class="mb-lg">🎯 Customer Segments</h2>
            <div class="segments-grid">
                ${segmentsHtml}
            </div>
            
            <div class="mt-xl">
                <a href="#/dashboard" class="btn btn-secondary">← Back to Dashboard</a>
            </div>
        `;
    },

    /**
     * Start polling for job status
     */
    startPolling(jobId) {
        this.stopPolling();
        
        this.pollingInterval = setInterval(async () => {
            try {
                const status = await api.get(`/jobs/status/${jobId}`);
                
                if (status.status === 'completed' || status.status === 'failed') {
                    this.stopPolling();
                    // Reload the page
                    app.navigate(`results/${jobId}`);
                }
            } catch (error) {
                console.error('Polling error:', error);
            }
        }, 3000);
    },

    /**
     * Stop polling
     */
    stopPolling() {
        if (this.pollingInterval) {
            clearInterval(this.pollingInterval);
            this.pollingInterval = null;
        }
    },

    /**
     * Download PDF report
     */
    async downloadReport(jobId) {
        try {
            app.showLoading('Generating PDF report...');
            await api.downloadFile(`/jobs/report/${jobId}`, `customer360_report_${jobId.substring(0, 8)}.pdf`);
            app.showToast('Report downloaded!', 'success');
        } catch (error) {
            app.showToast(error.message, 'error');
        } finally {
            app.hideLoading();
        }
    },

    /**
     * Download customers CSV
     */
    async downloadCSV(jobId) {
        try {
            app.showLoading('Downloading CSV...');
            await api.downloadFile(`/jobs/download/${jobId}/customers`, `customers_segmented_${jobId.substring(0, 8)}.csv`);
            app.showToast('CSV downloaded!', 'success');
        } catch (error) {
            app.showToast(error.message, 'error');
        } finally {
            app.hideLoading();
        }
    },

    /**
     * Delete a job
     */
    async deleteJob(jobId) {
        if (!confirm('Are you sure you want to delete this job?')) {
            return;
        }

        try {
            await api.delete(`/jobs/${jobId}`);
            app.showToast('Job deleted', 'success');
            app.navigate('dashboard');
        } catch (error) {
            app.showToast(error.message, 'error');
        }
    }
};

// Make downloadReport and deleteJob accessible globally
window.dashboard = dashboard;
