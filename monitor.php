<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Monitoring Eksekusi Nomor</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        :root {
            --primary: #4361ee;
            --success: #06d6a0;
            --danger: #ef476f;
            --warning: #ffd166;
            --info: #118ab2;
            --dark: #073b4c;
            --light: #f8f9fa;
            --gray: #6c757d;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding: 20px;
            color: var(--dark);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Header */
        .header {
            text-align: center;
            margin-bottom: 40px;
            animation: fadeInDown 1s ease;
        }

        .header h1 {
            font-size: 2.8rem;
            color: var(--primary);
            margin-bottom: 10px;
            background: linear-gradient(to right, var(--primary), var(--info));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
        }

        .header p {
            color: var(--gray);
            font-size: 1.1rem;
            max-width: 600px;
            margin: 0 auto;
        }

        /* Stats Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
            animation: fadeInUp 1s ease 0.3s both;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: var(--shadow);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .stat-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 8px;
            height: 100%;
        }

        .stat-card.total::before { background: var(--primary); }
        .stat-card.success::before { background: var(--success); }
        .stat-card.failed::before { background: var(--danger); }
        .stat-card.retry::before { background: var(--warning); }
        .stat-card.remaining::before { background: var(--info); }

        .stat-icon {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            color: white;
        }

        .stat-icon.total { background: var(--primary); }
        .stat-icon.success { background: var(--success); }
        .stat-icon.failed { background: var(--danger); }
        .stat-icon.retry { background: var(--warning); }
        .stat-icon.remaining { background: var(--info); }

        .stat-content {
            text-align: right;
        }

        .stat-number {
            font-size: 3rem;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 1rem;
            color: var(--gray);
            font-weight: 500;
        }

        /* Progress Bar */
        .progress-container {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: var(--shadow);
            margin-bottom: 40px;
            animation: fadeInUp 1s ease 0.5s both;
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            align-items: center;
        }

        .progress-header h3 {
            color: var(--primary);
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .progress-percentage {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
        }

        .progress-bar {
            height: 25px;
            background: #e9ecef;
            border-radius: 12px;
            overflow: hidden;
            position: relative;
            margin-bottom: 15px;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(to right, var(--success), var(--primary));
            border-radius: 12px;
            transition: width 1s ease;
            position: relative;
            overflow: hidden;
            width: 0%;
        }

        .progress-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            bottom: 0;
            right: 0;
            background-image: linear-gradient(
                -45deg, 
                rgba(255, 255, 255, 0.2) 25%, 
                transparent 25%, 
                transparent 50%, 
                rgba(255, 255, 255, 0.2) 50%, 
                rgba(255, 255, 255, 0.2) 75%, 
                transparent 75%, 
                transparent
            );
            z-index: 1;
            background-size: 50px 50px;
            animation: move 2s linear infinite;
        }

        .progress-stats {
            display: flex;
            justify-content: space-between;
            color: var(--gray);
            font-size: 0.9rem;
        }

        /* Tabs */
        .tabs {
            display: flex;
            background: white;
            border-radius: 15px;
            overflow: hidden;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
            animation: fadeInUp 1s ease 0.7s both;
        }

        .tab {
            flex: 1;
            padding: 18px;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
            font-weight: 600;
            font-size: 1.1rem;
            position: relative;
            color: var(--gray);
        }

        .tab.active {
            color: var(--primary);
            background: rgba(67, 97, 238, 0.05);
        }

        .tab.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: var(--primary);
            border-radius: 2px;
        }

        .tab:hover:not(.active) {
            background: rgba(0, 0, 0, 0.02);
        }

        /* Data Tables */
        .data-container {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow);
            animation: fadeInUp 1s ease 0.9s both;
            margin-bottom: 40px;
        }

        .table-container {
            overflow-x: auto;
            max-height: 500px;
            overflow-y: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }

        thead {
            background: linear-gradient(to right, var(--primary), var(--info));
            color: white;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        th {
            padding: 20px 15px;
            text-align: left;
            font-weight: 600;
            font-size: 1.1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        tbody tr {
            border-bottom: 1px solid #eee;
            transition: var(--transition);
        }

        tbody tr:hover {
            background: rgba(67, 97, 238, 0.03);
            transform: scale(1.002);
        }

        td {
            padding: 18px 15px;
            color: var(--dark);
        }

        .status-badge {
            padding: 8px 15px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
        }

        .status-success { background: rgba(6, 214, 160, 0.1); color: var(--success); }
        .status-failed { background: rgba(239, 71, 111, 0.1); color: var(--danger); }
        .status-retry { background: rgba(255, 209, 102, 0.1); color: #b08900; }
        .status-pending { background: rgba(17, 138, 178, 0.1); color: var(--info); }

        .msisdn {
            font-family: 'Courier New', monospace;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .paket-name {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* Control Panel */
        .control-panel {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: var(--shadow);
            margin-bottom: 40px;
            animation: fadeInUp 1s ease 1.1s both;
        }

        .control-panel h2 {
            color: var(--primary);
            margin-bottom: 25px;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .control-buttons {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 15px 30px;
            border-radius: 12px;
            border: none;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 10px;
            justify-content: center;
            flex: 1;
            min-width: 200px;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: #3a56d4;
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(67, 97, 238, 0.3);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #05c08e;
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(6, 214, 160, 0.3);
        }

        .btn-warning {
            background: var(--warning);
            color: #333;
        }

        .btn-warning:hover {
            background: #e6bc5c;
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(255, 209, 102, 0.3);
        }

        /* Animations */
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes move {
            0% { background-position: 0 0; }
            100% { background-position: 50px 50px; }
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .pulse {
            animation: pulse 2s infinite;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header h1 {
                font-size: 2.2rem;
            }
            
            .stat-card {
                padding: 20px;
            }
            
            .stat-number {
                font-size: 2.5rem;
            }
            
            .stat-icon {
                width: 60px;
                height: 60px;
                font-size: 25px;
            }
            
            .tabs {
                flex-direction: column;
            }
            
            .btn {
                min-width: 100%;
            }
            
            th, td {
                padding: 15px 10px;
            }
            
            .progress-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .progress-percentage {
                font-size: 1.8rem;
            }
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--gray);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 1.8rem;
            margin-bottom: 10px;
            color: var(--dark);
        }

        /* Loading */
        .loading {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 60px;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid rgba(67, 97, 238, 0.1);
            border-radius: 50%;
            border-top-color: var(--primary);
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Footer */
        .footer {
            text-align: center;
            padding: 20px;
            color: var(--gray);
            font-size: 0.9rem;
            animation: fadeInUp 1s ease 1.3s both;
        }

        .last-update {
            display: inline-block;
            background: rgba(0, 0, 0, 0.05);
            padding: 8px 15px;
            border-radius: 50px;
            margin-top: 10px;
        }

        /* Toast Notification */
        .toast {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            display: flex;
            align-items: center;
            gap: 15px;
            z-index: 1000;
            transform: translateY(100px);
            opacity: 0;
            transition: var(--transition);
            max-width: 400px;
        }

        .toast.show {
            transform: translateY(0);
            opacity: 1;
        }

        .toast-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
        }

        .toast-icon.success { background: var(--success); }
        .toast-icon.warning { background: var(--warning); }
        .toast-icon.error { background: var(--danger); }

        .toast-content h4 {
            margin-bottom: 5px;
            color: var(--dark);
        }

        .toast-content p {
            color: var(--gray);
            font-size: 0.9rem;
        }

        /* Status Indicator */
        .status-indicator {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
        }

        .status-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }

        .status-dot.online { background: var(--success); animation: pulse 2s infinite; }
        .status-dot.offline { background: var(--danger); }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1><i class="fas fa-broadcast-tower"></i> Dashboard Monitoring Eksekusi Nomor</h1>
            <p>Monitor real-time status eksekusi 19.000+ nomor dengan visualisasi data lengkap</p>
        </div>

        <!-- Stats Cards -->
        <div class="stats-container">
            <div class="stat-card total">
                <div class="stat-icon total">
                    <i class="fas fa-list-ol"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number" id="totalCount">0</div>
                    <div class="stat-label">Total Nomor</div>
                </div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-icon success">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number" id="successCount">0</div>
                    <div class="stat-label">Sukses</div>
                </div>
            </div>
            
            <div class="stat-card failed">
                <div class="stat-icon failed">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number" id="failedCount">0</div>
                    <div class="stat-label">Gagal</div>
                </div>
            </div>
            
            <div class="stat-card retry">
                <div class="stat-icon retry">
                    <i class="fas fa-redo-alt"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number" id="retryCount">0</div>
                    <div class="stat-label">Akan Retry</div>
                </div>
            </div>
            
            <div class="stat-card remaining">
                <div class="stat-icon remaining">
                    <i class="fas fa-hourglass-half"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number" id="remainingCount">0</div>
                    <div class="stat-label">Belum Diproses</div>
                </div>
            </div>
        </div>

        <!-- Progress Bar -->
        <div class="progress-container">
            <div class="progress-header">
                <h3><i class="fas fa-chart-line"></i> Progress Eksekusi</h3>
                <div class="progress-percentage" id="progressPercentage">0%</div>
            </div>
            <div class="progress-bar">
                <div class="progress-fill" id="progressFill"></div>
            </div>
            <div class="progress-stats">
                <span>Diproses: <span id="processedCount">0</span> nomor</span>
                <span>Sisa: <span id="remainingProgressCount">0</span> nomor</span>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <div class="tab active" data-tab="success">
                <i class="fas fa-check-circle"></i> Data Sukses
            </div>
            <div class="tab" data-tab="failed">
                <i class="fas fa-times-circle"></i> Data Gagal
            </div>
            <div class="tab" data-tab="retry">
                <i class="fas fa-redo-alt"></i> Data Retry
            </div>
            <div class="tab" data-tab="all">
                <i class="fas fa-table"></i> Semua Data
            </div>
        </div>

        <!-- Data Tables -->
        <div class="data-container">
            <div class="table-container">
                <table id="dataTable">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>MSISDN</th>
                            <th>Paket</th>
                            <th>Status</th>
                            <th>Transaction ID</th>
                            <th>Amount</th>
                            <th>Timestamp</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <!-- Data akan dimuat dinamis -->
                    </tbody>
                </table>
            </div>
            
            <!-- Empty State -->
            <div id="emptyState" class="empty-state" style="display: none;">
                <i class="fas fa-database"></i>
                <h3>Tidak ada data</h3>
                <p>Data akan muncul setelah eksekusi dimulai</p>
            </div>
            
            <!-- Loading -->
            <div id="loading" class="loading">
                <div class="spinner"></div>
            </div>
        </div>

        <!-- Control Panel -->
        <div class="control-panel">
            <h2><i class="fas fa-sliders-h"></i> Kontrol Dashboard</h2>
            <div class="control-buttons">
                <button class="btn btn-primary" id="refreshBtn">
                    <i class="fas fa-sync-alt"></i> Refresh Data
                </button>
                <button class="btn btn-success" id="startAutoBtn">
                    <i class="fas fa-play"></i> Auto Refresh (10s)
                </button>
                <button class="btn btn-warning" id="stopAutoBtn" style="display: none;">
                    <i class="fas fa-pause"></i> Stop Auto Refresh
                </button>
                <div class="status-indicator">
                    <div class="status-dot offline" id="statusDot"></div>
                    <span id="statusText">Offline</span>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>Dashboard Monitoring Eksekusi Nomor | Real-time Monitoring System</p>
            <div class="last-update">
                <i class="fas fa-clock"></i> Terakhir diperbarui: <span id="lastUpdate">Belum pernah</span>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div class="toast" id="toast">
        <div class="toast-icon success" id="toastIcon">
            <i class="fas fa-check"></i>
        </div>
        <div class="toast-content">
            <h4 id="toastTitle">Sukses</h4>
            <p id="toastMessage">Data berhasil diperbarui</p>
        </div>
    </div>

    <script>
        // Konfigurasi
        const FILES = {
            data: 'data.json',
            success: 'success.json',
            failed: 'failed.json',
            retry: 'retry.json'
        };
        
        const REFRESH_INTERVAL = 10000; // 10 detik
        let autoRefreshInterval = null;
        let currentTab = 'success';
        let isAutoRefresh = false;

        // DOM Elements
        const totalCountEl = document.getElementById('totalCount');
        const successCountEl = document.getElementById('successCount');
        const failedCountEl = document.getElementById('failedCount');
        const retryCountEl = document.getElementById('retryCount');
        const remainingCountEl = document.getElementById('remainingCount');
        const progressFillEl = document.getElementById('progressFill');
        const progressPercentageEl = document.getElementById('progressPercentage');
        const processedCountEl = document.getElementById('processedCount');
        const remainingProgressCountEl = document.getElementById('remainingProgressCount');
        const tableBodyEl = document.getElementById('tableBody');
        const emptyStateEl = document.getElementById('emptyState');
        const loadingEl = document.getElementById('loading');
        const lastUpdateEl = document.getElementById('lastUpdate');
        const tabs = document.querySelectorAll('.tab');
        const refreshBtn = document.getElementById('refreshBtn');
        const startAutoBtn = document.getElementById('startAutoBtn');
        const stopAutoBtn = document.getElementById('stopAutoBtn');
        const statusDot = document.getElementById('statusDot');
        const statusText = document.getElementById('statusText');
        const toast = document.getElementById('toast');
        const toastIcon = document.getElementById('toastIcon');
        const toastTitle = document.getElementById('toastTitle');
        const toastMessage = document.getElementById('toastMessage');

        // Format number dengan titik
        function formatNumber(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }

        // Format tanggal
        function formatDate(dateString) {
            if (!dateString) return '-';
            try {
                const date = new Date(dateString);
                return date.toLocaleString('id-ID');
            } catch (e) {
                return dateString;
            }
        }

        // Tampilkan toast notification
        function showToast(type, title, message) {
            // Update toast content
            toastIcon.className = 'toast-icon ' + type;
            toastTitle.textContent = title;
            toastMessage.textContent = message;
            
            // Update icon
            const icon = toastIcon.querySelector('i');
            icon.className = 'fas ' + (
                type === 'success' ? 'fa-check' :
                type === 'warning' ? 'fa-exclamation-triangle' :
                'fa-exclamation-circle'
            );
            
            // Show toast
            toast.classList.add('show');
            
            // Hide after 5 seconds
            setTimeout(() => {
                toast.classList.remove('show');
            }, 5000);
        }

        // Update status indicator
        function updateStatusIndicator(connected) {
            if (connected) {
                statusDot.className = 'status-dot online';
                statusText.textContent = 'Online - Auto Refresh Aktif';
                statusText.style.color = 'var(--success)';
            } else {
                statusDot.className = 'status-dot offline';
                statusText.textContent = 'Offline - Manual Mode';
                statusText.style.color = 'var(--danger)';
            }
        }

        // Fetch data dari file JSON
        async function fetchJSON(file) {
            try {
                const response = await fetch(file + '?t=' + Date.now());
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                const data = await response.json();
                return Array.isArray(data) ? data : [];
            } catch (error) {
                console.error(`Error fetching ${file}:`, error);
                return [];
            }
        }

        // Load semua data
        async function loadAllData() {
            try {
                loadingEl.style.display = 'flex';
                emptyStateEl.style.display = 'none';
                
                // Fetch semua data secara parallel
                const [dataJson, successJson, failedJson, retryJson] = await Promise.all([
                    fetchJSON(FILES.data),
                    fetchJSON(FILES.success),
                    fetchJSON(FILES.failed),
                    fetchJSON(FILES.retry)
                ]);
                
                console.log('Data loaded:', {
                    data: dataJson.length,
                    success: successJson.length,
                    failed: failedJson.length,
                    retry: retryJson.length
                });
                
                // Hitung statistik
                const success = successJson.length;
                const failed = failedJson.length;
                const retry = retryJson.length;
                const remaining = dataJson.length;
                const processed = success + failed + retry;
                const total = processed + remaining;
                
                // Update stat cards
                totalCountEl.textContent = formatNumber(total);
                successCountEl.textContent = formatNumber(success);
                failedCountEl.textContent = formatNumber(failed);
                retryCountEl.textContent = formatNumber(retry);
                remainingCountEl.textContent = formatNumber(remaining);
                processedCountEl.textContent = formatNumber(processed);
                remainingProgressCountEl.textContent = formatNumber(remaining);
                
                // Update progress bar
                const percentage = total > 0 ? Math.round((processed / total) * 100) : 0;
                progressFillEl.style.width = `${percentage}%`;
                progressPercentageEl.textContent = `${percentage}%`;
                
                // Update tabel berdasarkan tab aktif
                updateTable(currentTab, {
                    data: dataJson,
                    success: successJson,
                    failed: failedJson,
                    retry: retryJson
                });
                
                // Update last update time
                const now = new Date();
                lastUpdateEl.textContent = now.toLocaleString('id-ID');
                
                // Tampilkan toast sukses
                showToast('success', 'Data Diperbarui', 
                    `${formatNumber(success)} sukses, ${formatNumber(failed)} gagal, ${formatNumber(retry)} akan retry`);
                
            } catch (error) {
                console.error('Error loading data:', error);
                showToast('error', 'Error', 'Gagal memuat data. Coba lagi.');
                emptyStateEl.style.display = 'block';
                emptyStateEl.innerHTML = `
                    <i class="fas fa-exclamation-triangle"></i>
                    <h3>Gagal Memuat Data</h3>
                    <p>${error.message}</p>
                `;
            } finally {
                loadingEl.style.display = 'none';
            }
        }

        // Update tabel berdasarkan tab
        function updateTable(tab, data) {
            const tableData = getTableData(tab, data);
            
            if (tableData.length === 0) {
                tableBodyEl.innerHTML = '';
                emptyStateEl.style.display = 'block';
                emptyStateEl.innerHTML = `
                    <i class="fas fa-database"></i>
                    <h3>Tidak ada data</h3>
                    <p>Tidak ada data untuk tab "${getTabName(tab)}"</p>
                `;
                return;
            }
            
            emptyStateEl.style.display = 'none';
            
            // Build table rows
            let html = '';
            tableData.forEach((item, index) => {
                const status = getStatus(item, tab);
                const statusClass = getStatusClass(status);
                const statusText = getStatusText(status);
                const msisdn = item.msisdn || '-';
                const paket = item.nama_paket || item.product || '-';
                const transactionId = item.transaction_id || '-';
                const amount = item.amount ? `Rp ${item.amount}` : '-';
                const timestamp = item.timestamp || '-';
                
                html += `
                    <tr>
                        <td>${index + 1}</td>
                        <td class="msisdn">${msisdn}</td>
                        <td class="paket-name" title="${paket}">${paket}</td>
                        <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                        <td>${transactionId}</td>
                        <td>${amount}</td>
                        <td>${formatDate(timestamp)}</td>
                    </tr>
                `;
            });
            
            tableBodyEl.innerHTML = html;
        }

        // Helper functions untuk tabel
        function getTableData(tab, data) {
            switch(tab) {
                case 'success': return data.success;
                case 'failed': return data.failed;
                case 'retry': return data.retry;
                case 'all': return [
                    ...data.success.map(d => ({...d, status: 'success'})),
                    ...data.failed.map(d => ({...d, status: 'failed'})),
                    ...data.retry.map(d => ({...d, status: 'retry'})),
                    ...data.data.map(d => ({...d, status: 'pending'}))
                ];
                default: return [];
            }
        }

        function getTabName(tab) {
            const names = {
                'success': 'Sukses',
                'failed': 'Gagal',
                'retry': 'Retry',
                'all': 'Semua'
            };
            return names[tab] || tab;
        }

        function getStatus(item, tab) {
            if (tab === 'success') return 'success';
            if (tab === 'failed') return 'failed';
            if (tab === 'retry') return 'retry';
            if (item.success) return 'success';
            if (item.http_code) return 'failed';
            if (item.nama_paket && !item.transaction_id && !item.http_code) return 'pending';
            return 'unknown';
        }

        function getStatusClass(status) {
            const classes = {
                'success': 'status-success',
                'failed': 'status-failed',
                'retry': 'status-retry',
                'pending': 'status-pending'
            };
            return classes[status] || 'status-pending';
        }

        function getStatusText(status) {
            const texts = {
                'success': 'Sukses',
                'failed': 'Gagal',
                'retry': 'Akan Retry',
                'pending': 'Belum Diproses'
            };
            return texts[status] || status;
        }

        // Event Listeners
        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                // Update active tab
                tabs.forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                currentTab = tab.dataset.tab;
                
                // Reload data untuk tab baru
                loadAllData();
            });
        });

        refreshBtn.addEventListener('click', () => {
            refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memuat...';
            loadAllData().finally(() => {
                refreshBtn.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh Data';
            });
        });

        startAutoBtn.addEventListener('click', () => {
            isAutoRefresh = true;
            startAutoBtn.style.display = 'none';
            stopAutoBtn.style.display = 'flex';
            updateStatusIndicator(true);
            
            autoRefreshInterval = setInterval(() => {
                loadAllData();
            }, REFRESH_INTERVAL);
            
            showToast('success', 'Auto Refresh Aktif', 'Data akan diperbarui setiap 10 detik');
        });

        stopAutoBtn.addEventListener('click', () => {
            isAutoRefresh = false;
            startAutoBtn.style.display = 'flex';
            stopAutoBtn.style.display = 'none';
            updateStatusIndicator(false);
            
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
                autoRefreshInterval = null;
            }
            
            showToast('warning', 'Auto Refresh Dimatikan', 'Data hanya akan diperbarui manual');
        });

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            // Load initial data
            loadAllData();
            
            // Update status indicator
            updateStatusIndicator(false);
            
            // Show welcome message
            setTimeout(() => {
                showToast('success', 'Dashboard Siap', 'Sistem monitoring berhasil dimuat');
            }, 1000);
        });

        // Handle page visibility change
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                console.log('Tab tidak aktif, pause auto refresh jika aktif');
            } else if (isAutoRefresh) {
                console.log('Tab aktif kembali, melanjutkan auto refresh');
                loadAllData();
            }
        });
    </script>
</body>
</html>
