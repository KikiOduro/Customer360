/**
 * Customer360 - Upload Module
 * Handles file upload and job creation
 */

const upload = {
    selectedFile: null,
    
    /**
     * Render upload page
     */
    renderUploadPage() {
        return `
            <div class="dashboard-header">
                <h1>📤 Upload Transaction Data</h1>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <div class="upload-zone" id="upload-zone">
                        <input type="file" id="file-input" accept=".csv">
                        <div class="upload-icon">📁</div>
                        <h3>Drag and drop your CSV file here</h3>
                        <p>or click to browse files</p>
                        <p class="form-hint mt-md">Supported format: CSV (max 50MB)</p>
                    </div>
                    
                    <div id="file-preview" class="hidden mt-lg">
                        <div class="flex justify-between items-center mb-md">
                            <div class="flex items-center gap-md">
                                <span style="font-size: 2rem;">📄</span>
                                <div>
                                    <h4 id="file-name"></h4>
                                    <p class="form-hint" id="file-size"></p>
                                </div>
                            </div>
                            <button class="btn btn-secondary btn-sm" id="remove-file">Remove</button>
                        </div>
                    </div>
                    
                    <div class="upload-options">
                        <h4>Analysis Options</h4>
                        
                        <div class="form-group">
                            <label class="form-label" for="clustering-method">Clustering Algorithm</label>
                            <select id="clustering-method" class="form-select">
                                <option value="kmeans">K-Means (Recommended)</option>
                                <option value="gmm">Gaussian Mixture Model (GMM)</option>
                                <option value="hierarchical">Hierarchical Clustering</option>
                            </select>
                            <p class="form-hint">K-Means works best for most datasets</p>
                        </div>
                        
                        <div class="form-group">
                            <label class="flex items-center gap-sm">
                                <input type="checkbox" id="include-comparison">
                                <span>Run all methods for comparison</span>
                            </label>
                            <p class="form-hint">Compare K-Means, GMM, and Hierarchical results</p>
                        </div>
                    </div>
                    
                    <div class="mt-lg">
                        <button class="btn btn-primary btn-lg" id="start-analysis" disabled>
                            🚀 Start Analysis
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="card mt-lg">
                <div class="card-header">
                    <h3 class="card-title">📋 Required CSV Format</h3>
                </div>
                <div class="card-body">
                    <p>Your CSV file should contain the following columns:</p>
                    <table class="data-table mt-md">
                        <thead>
                            <tr>
                                <th>Column</th>
                                <th>Description</th>
                                <th>Example</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>customer_id</strong></td>
                                <td>Unique customer identifier</td>
                                <td>CUST001, 12345</td>
                            </tr>
                            <tr>
                                <td><strong>invoice_date</strong></td>
                                <td>Date of transaction</td>
                                <td>2025-01-15, 15/01/2025</td>
                            </tr>
                            <tr>
                                <td><strong>invoice_id</strong></td>
                                <td>Transaction/invoice number</td>
                                <td>INV001, TXN12345</td>
                            </tr>
                            <tr>
                                <td><strong>amount</strong></td>
                                <td>Transaction amount</td>
                                <td>150.00, 2500</td>
                            </tr>
                            <tr>
                                <td>product (optional)</td>
                                <td>Product name</td>
                                <td>Widget A</td>
                            </tr>
                            <tr>
                                <td>category (optional)</td>
                                <td>Product category</td>
                                <td>Electronics</td>
                            </tr>
                        </tbody>
                    </table>
                    <p class="form-hint mt-md">
                        Column names are flexible - we'll try to auto-detect them. 
                        Common variations like "date", "total", "cust_id" are also supported.
                    </p>
                </div>
            </div>
        `;
    },

    /**
     * Initialize upload page handlers
     */
    initUploadPage() {
        const uploadZone = document.getElementById('upload-zone');
        const fileInput = document.getElementById('file-input');
        const filePreview = document.getElementById('file-preview');
        const removeFileBtn = document.getElementById('remove-file');
        const startAnalysisBtn = document.getElementById('start-analysis');

        if (!uploadZone) return;

        // Click to upload
        uploadZone.addEventListener('click', () => {
            fileInput.click();
        });

        // File input change
        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                this.handleFileSelect(e.target.files[0]);
            }
        });

        // Drag and drop
        uploadZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadZone.classList.add('drag-over');
        });

        uploadZone.addEventListener('dragleave', () => {
            uploadZone.classList.remove('drag-over');
        });

        uploadZone.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadZone.classList.remove('drag-over');
            
            if (e.dataTransfer.files.length > 0) {
                this.handleFileSelect(e.dataTransfer.files[0]);
            }
        });

        // Remove file
        removeFileBtn.addEventListener('click', () => {
            this.selectedFile = null;
            fileInput.value = '';
            filePreview.classList.add('hidden');
            uploadZone.classList.remove('hidden');
            startAnalysisBtn.disabled = true;
        });

        // Start analysis
        startAnalysisBtn.addEventListener('click', () => {
            this.startAnalysis();
        });
    },

    /**
     * Handle file selection
     */
    handleFileSelect(file) {
        // Validate file type
        if (!file.name.toLowerCase().endsWith('.csv')) {
            app.showToast('Please upload a CSV file', 'error');
            return;
        }

        // Validate file size (50MB max)
        if (file.size > 50 * 1024 * 1024) {
            app.showToast('File size exceeds 50MB limit', 'error');
            return;
        }

        this.selectedFile = file;

        // Update UI
        document.getElementById('file-name').textContent = file.name;
        document.getElementById('file-size').textContent = this.formatFileSize(file.size);
        document.getElementById('file-preview').classList.remove('hidden');
        document.getElementById('upload-zone').classList.add('hidden');
        document.getElementById('start-analysis').disabled = false;
    },

    /**
     * Format file size for display
     */
    formatFileSize(bytes) {
        if (bytes < 1024) return bytes + ' bytes';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    },

    /**
     * Start the analysis
     */
    async startAnalysis() {
        if (!this.selectedFile) {
            app.showToast('Please select a file first', 'error');
            return;
        }

        const clusteringMethod = document.getElementById('clustering-method').value;
        const includeComparison = document.getElementById('include-comparison').checked;

        const formData = new FormData();
        formData.append('file', this.selectedFile);
        formData.append('clustering_method', clusteringMethod);
        formData.append('include_comparison', includeComparison);

        try {
            app.showLoading('Uploading and starting analysis...');
            
            const result = await api.postForm('/jobs/upload', formData);
            
            app.showToast('Analysis started!', 'success');
            
            // Navigate to results page with polling
            window.location.hash = `#/results/${result.job_id}`;
            
        } catch (error) {
            app.showToast(error.message, 'error');
        } finally {
            app.hideLoading();
        }
    }
};

// Freeze the upload object
Object.freeze(upload);
