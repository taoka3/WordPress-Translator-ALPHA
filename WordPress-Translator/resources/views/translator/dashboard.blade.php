<!DOCTYPE html>
<html lang="ja" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>WordPress 英訳管理システム | WP Translation Studio</title>
    <meta name="description" content="LMStudioと連携したWordPress記事・カテゴリー・タグのインテリジェント英訳管理システム。ソースコード保持、リアルタイム進捗監視、中断・再開に対応。">

    <!-- Fonts: Outfit & JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&family=Outfit:wght@300;400;500;600;700&family=Noto+Sans+JP:wght@400;500;700&display=swap" rel="stylesheet">
    <!-- Lucide Icons CDN -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        :root {
            --bg-base: #090d16;
            --bg-surface: #0f172a;
            --bg-card: rgba(30, 41, 59, 0.7);
            --bg-card-hover: rgba(51, 65, 85, 0.8);
            --border-glass: rgba(255, 255, 255, 0.08);
            --border-highlight: rgba(99, 102, 241, 0.3);
            
            --primary: #6366f1;
            --primary-glow: rgba(99, 102, 241, 0.4);
            --primary-hover: #4f46e5;
            --secondary: #06b6d4;
            --accent-green: #10b981;
            --accent-yellow: #f59e0b;
            --accent-red: #ef4444;
            
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --text-dim: #64748b;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Outfit', 'Noto Sans JP', sans-serif;
            background-color: var(--bg-base);
            color: var(--text-main);
            min-height: 100vh;
            line-height: 1.6;
            overflow-x: hidden;
            background-image: 
                radial-gradient(at 0% 0%, rgba(99, 102, 241, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 0%, rgba(6, 182, 212, 0.12) 0px, transparent 50%),
                radial-gradient(at 50% 100%, rgba(16, 185, 129, 0.08) 0px, transparent 50%);
            background-attachment: fixed;
        }

        /* Glassmorphism Classes */
        .glass-panel {
            background: var(--bg-card);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--border-glass);
            border-radius: 16px;
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
        }

        .glass-card {
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border-glass);
            border-radius: 12px;
            transition: all 0.25s ease;
        }

        .glass-card:hover {
            border-color: var(--border-highlight);
            box-shadow: 0 4px 20px rgba(99, 102, 241, 0.15);
        }

        /* Container Layout */
        .app-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 24px 20px 60px;
        }

        /* Header */
        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 28px;
            margin-bottom: 24px;
        }

        .logo-group {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .logo-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: linear-gradient(135deg, #6366f1, #06b6d4);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 20px var(--primary-glow);
        }

        .logo-title {
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            background: linear-gradient(90deg, #ffffff, #94a3b8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .logo-sub {
            font-size: 0.8rem;
            color: var(--secondary);
            font-weight: 500;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            font-size: 0.9rem;
            font-weight: 600;
            border-radius: 10px;
            border: 1px solid transparent;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
            color: white;
            font-family: inherit;
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            box-shadow: 0 4px 14px var(--primary-glow);
        }
        .btn-primary:hover {
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.6);
            transform: translateY(-1px);
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669);
            box-shadow: 0 4px 14px rgba(16, 185, 129, 0.35);
        }
        .btn-success:hover {
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.5);
            transform: translateY(-1px);
        }

        .btn-warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            box-shadow: 0 4px 14px rgba(245, 158, 11, 0.35);
            color: #fff;
        }
        .btn-warning:hover {
            box-shadow: 0 6px 20px rgba(245, 158, 11, 0.5);
            transform: translateY(-1px);
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            box-shadow: 0 4px 14px rgba(239, 68, 68, 0.35);
        }
        .btn-danger:hover {
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.5);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: rgba(30, 41, 59, 0.8);
            border-color: var(--border-glass);
            color: var(--text-main);
        }
        .btn-secondary:hover {
            background: rgba(51, 65, 85, 0.9);
            border-color: rgba(255, 255, 255, 0.15);
        }

        /* Status Badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 0.02em;
        }
        .status-idle { background: rgba(148, 163, 184, 0.15); color: #94a3b8; border: 1px solid rgba(148, 163, 184, 0.3); }
        .status-running { background: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid rgba(52, 211, 153, 0.4); box-shadow: 0 0 12px rgba(16, 185, 129, 0.25); }
        .status-paused { background: rgba(245, 158, 11, 0.2); color: #fbbf24; border: 1px solid rgba(251, 191, 36, 0.4); }
        .status-stopped { background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid rgba(248, 113, 113, 0.4); }

        .pulse-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: currentColor;
            animation: pulse-ring 1.5s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        @keyframes pulse-ring {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(0.85); }
        }

        /* Grid Layouts */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--card-accent, var(--primary));
        }

        .stat-label {
            font-size: 0.82rem;
            font-weight: 500;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            letter-spacing: -0.03em;
            color: #fff;
        }

        .stat-sub {
            font-size: 0.8rem;
            color: var(--text-dim);
        }

        /* Progress Bar */
        .progress-section {
            padding: 24px 28px;
            margin-bottom: 24px;
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .progress-track {
            height: 14px;
            background: rgba(15, 23, 42, 0.8);
            border-radius: 9999px;
            overflow: hidden;
            border: 1px solid var(--border-glass);
            position: relative;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #6366f1, #06b6d4, #10b981);
            background-size: 200% 100%;
            animation: gradient-shift 3s ease infinite;
            border-radius: 9999px;
            transition: width 0.4s ease;
            box-shadow: 0 0 16px rgba(99, 102, 241, 0.5);
        }

        @keyframes gradient-shift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* Runner Control Bar */
        .controls-bar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 18px 24px;
            margin-bottom: 24px;
        }

        .controls-group {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        /* Main Dashboard Two-Column Section */
        .dashboard-split {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }

        @media (max-width: 992px) {
            .dashboard-split {
                grid-template-columns: 1fr;
            }
        }

        /* Active Item Live Card */
        .live-preview-card {
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 16px;
            min-height: 280px;
        }

        .section-title {
            font-size: 1.05rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-main);
        }

        .live-item-badge {
            font-size: 0.75rem;
            padding: 2px 8px;
            background: rgba(99, 102, 241, 0.2);
            color: #a5b4fc;
            border-radius: 6px;
        }

        .code-guard-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 0.75rem;
            padding: 4px 10px;
            border-radius: 6px;
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        /* Log Terminal Console */
        .log-terminal {
            background: #050811;
            border: 1px solid var(--border-glass);
            border-radius: 12px;
            padding: 16px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.82rem;
            height: 240px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .log-line {
            display: flex;
            gap: 10px;
            line-height: 1.5;
            word-break: break-all;
        }

        .log-time {
            color: var(--text-dim);
            white-space: nowrap;
        }

        .log-level-info { color: #60a5fa; }
        .log-level-success { color: #34d399; }
        .log-level-warning { color: #fbbf24; }
        .log-level-error { color: #f87171; }

        /* Tabs Navigation */
        .tabs-header {
            display: flex;
            gap: 8px;
            border-bottom: 1px solid var(--border-glass);
            padding: 0 24px;
            margin-bottom: 20px;
            overflow-x: auto;
        }

        .tab-btn {
            padding: 14px 18px;
            background: transparent;
            border: none;
            color: var(--text-muted);
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            font-family: inherit;
            white-space: nowrap;
        }

        .tab-btn:hover {
            color: var(--text-main);
        }

        .tab-btn.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }

        /* Filter Controls */
        .filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            padding: 0 24px 16px;
            align-items: center;
            justify-content: space-between;
        }

        .search-box {
            position: relative;
            flex: 1;
            min-width: 240px;
            max-width: 400px;
        }

        .search-box input {
            width: 100%;
            padding: 9px 12px 9px 36px;
            border-radius: 8px;
            background: rgba(15, 23, 42, 0.7);
            border: 1px solid var(--border-glass);
            color: #fff;
            font-size: 0.88rem;
            font-family: inherit;
            outline: none;
        }
        .search-box input:focus {
            border-color: var(--primary);
        }

        .search-icon {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-dim);
            pointer-events: none;
        }

        .select-filter {
            padding: 9px 14px;
            border-radius: 8px;
            background: rgba(15, 23, 42, 0.7);
            border: 1px solid var(--border-glass);
            color: #fff;
            font-size: 0.88rem;
            font-family: inherit;
            outline: none;
            cursor: pointer;
        }

        /* Data Table */
        .table-container {
            padding: 0 24px 24px;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            text-align: left;
        }

        th {
            padding: 12px 14px;
            color: var(--text-muted);
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid var(--border-glass);
        }

        td {
            padding: 14px;
            border-bottom: 1px solid var(--border-glass);
            font-size: 0.88rem;
            vertical-align: middle;
        }

        tbody tr:hover {
            background: rgba(51, 65, 85, 0.25);
        }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(8px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 20px;
        }

        .modal-overlay.open {
            display: flex;
        }

        .modal-content {
            background: #0f172a;
            border: 1px solid var(--border-glass);
            border-radius: 16px;
            width: 100%;
            max-width: 900px;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.6);
            overflow: hidden;
        }

        .modal-header {
            padding: 20px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--border-glass);
        }

        .modal-body {
            padding: 24px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid var(--border-glass);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            background: rgba(15, 23, 42, 0.5);
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-muted);
        }

        .form-input, .form-textarea {
            width: 100%;
            padding: 10px 14px;
            border-radius: 8px;
            background: rgba(30, 41, 59, 0.5);
            border: 1px solid var(--border-glass);
            color: #fff;
            font-size: 0.9rem;
            font-family: inherit;
            outline: none;
        }
        .form-textarea {
            font-family: 'JetBrains Mono', monospace;
            min-height: 160px;
            resize: vertical;
        }
        .form-input:focus, .form-textarea:focus {
            border-color: var(--primary);
        }

        /* Notification Toast */
        .toast {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 14px 20px;
            border-radius: 10px;
            background: #1e293b;
            color: white;
            border: 1px solid var(--border-glass);
            box-shadow: 0 10px 25px rgba(0,0,0,0.4);
            display: flex;
            align-items: center;
            gap: 10px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 2000;
        }
        .toast.show {
            transform: translateY(0);
            opacity: 1;
        }

        /* Spin Animation */
        .spin {
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>

<div class="app-container">

    <!-- Top Navigation Header -->
    <header class="glass-panel">
        <div class="logo-group">
            <div class="logo-icon">
                <i data-lucide="languages" style="color: white; width: 24px; height: 24px;"></i>
            </div>
            <div>
                <h1 class="logo-title">WordPress 英訳スタジオ</h1>
                <div class="logo-sub">LMStudio& MySQL</div>
            </div>
        </div>

        <div class="header-actions">
            <div id="runnerStatusBadge" class="status-badge status-idle">
                <span class="pulse-dot"></span>
                <span id="runnerStatusText">待機中 (Idle)</span>
            </div>

            <button id="btnSyncWp" class="btn btn-secondary" title="WordPress MySQLから最新の記事・タグ・カテゴリを再読込">
                <i data-lucide="refresh-cw" id="syncIcon" style="width: 16px; height: 16px;"></i>
                <span>WPデータ同期</span>
            </button>

            <button id="btnBatchAlphaNumeric" class="btn btn-secondary" title="英字・数字・記号のみの項目をLM Studioを介さずenカラムへ即時反映">
                <i data-lucide="zap" id="zapIcon" style="width: 16px; height: 16px; color: #fbbf24;"></i>
                <span>英数記号を直接反映</span>
            </button>

            <button id="btnOpenSettings" class="btn btn-secondary" title="LMStudio及び動作設定">
                <i data-lucide="settings" style="width: 16px; height: 16px;"></i>
                <span>設定</span>
            </button>
        </div>
    </header>

    <!-- Key Metrics Stats Cards -->
    <div class="stats-grid">
        <div class="glass-panel stat-card" style="--card-accent: #6366f1;">
            <div class="stat-label">
                <span>総翻訳キュー</span>
                <i data-lucide="layers" style="width: 18px; height: 18px; color: #6366f1;"></i>
            </div>
            <div class="stat-value" id="statTotal">{{ number_format($stats['total']) }}</div>
            <div class="stat-sub">記事・カテゴリ・タグ対象</div>
        </div>

        <div class="glass-panel stat-card" style="--card-accent: #10b981;">
            <div class="stat-label">
                <span>英訳完了</span>
                <i data-lucide="check-circle-2" style="width: 18px; height: 18px; color: #10b981;"></i>
            </div>
            <div class="stat-value" id="statCompleted" style="color: #34d399;">{{ number_format($stats['completed']) }}</div>
            <div class="stat-sub" id="statPercent">{{ $stats['percentage'] }}% 完了済み</div>
        </div>

        <div class="glass-panel stat-card" style="--card-accent: #f59e0b;">
            <div class="stat-label">
                <span>未翻訳・待機中</span>
                <i data-lucide="clock" style="width: 18px; height: 18px; color: #f59e0b;"></i>
            </div>
            <div class="stat-value" id="statPending" style="color: #fbbf24;">{{ number_format($stats['pending']) }}</div>
            <div class="stat-sub">順次キュー処理中</div>
        </div>

        <div class="glass-panel stat-card" style="--card-accent: #ef4444;">
            <div class="stat-label">
                <span>エラー・要再試行</span>
                <i data-lucide="alert-triangle" style="width: 18px; height: 18px; color: #ef4444;"></i>
            </div>
            <div class="stat-value" id="statFailed" style="color: #f87171;">{{ number_format($stats['failed']) }}</div>
            <div class="stat-sub">自動リトライ対応</div>
        </div>
    </div>

    <!-- Progress Overview Panel -->
    <div class="glass-panel progress-section">
        <div class="progress-header">
            <div>
                <span style="font-weight: 600; font-size: 1.05rem;">全体進捗率</span>
                <span style="font-size: 0.85rem; color: var(--text-muted); margin-left: 8px;">
                    (記事: <strong id="progressPosts" style="color: #fff;">{{ $stats['breakdown']['posts']['completed'] }}/{{ $stats['breakdown']['posts']['total'] }}</strong> | 
                     カテゴリ: <strong id="progressCategories" style="color: #fff;">{{ $stats['breakdown']['categories']['completed'] }}/{{ $stats['breakdown']['categories']['total'] }}</strong> | 
                     タグ: <strong id="progressTags" style="color: #fff;">{{ $stats['breakdown']['tags']['completed'] }}/{{ $stats['breakdown']['tags']['total'] }}</strong>)
                </span>
            </div>
            <span id="progressBarLabel" style="font-weight: 700; font-size: 1.2rem; color: var(--secondary);">{{ $stats['percentage'] }}%</span>
        </div>
        <div class="progress-track">
            <div id="progressBarFill" class="progress-fill" style="width: {{ $stats['percentage'] }}%;"></div>
        </div>
    </div>

    <!-- Execution Controls Bar -->
    <div class="glass-panel controls-bar">
        <div class="controls-group">
            <button id="btnStartRunner" class="btn btn-success">
                <i data-lucide="play" style="width: 18px; height: 18px;"></i>
                <span>自動英訳を開始 / 再開</span>
            </button>

            <button id="btnPauseRunner" class="btn btn-warning" disabled>
                <i data-lucide="pause" style="width: 18px; height: 18px;"></i>
                <span>一時停止</span>
            </button>

            <button id="btnStopRunner" class="btn btn-danger" disabled>
                <i data-lucide="square" style="width: 18px; height: 18px;"></i>
                <span>停止</span>
            </button>

            <button id="btnStepRunner" class="btn btn-secondary" title="現在のキューから1件だけテスト翻訳を実行">
                <i data-lucide="fast-forward" style="width: 18px; height: 18px;"></i>
                <span>1件だけ実行</span>
            </button>
        </div>

        <div class="controls-group">
            <div class="code-guard-badge" title="Rule 9準拠: pre/codeタグやGutenbergコードブロックは置換保護され翻訳後も完全維持されます">
                <i data-lucide="shield-check" style="width: 14px; height: 14px;"></i>
                <span>ソースコード保護機能 (Rule 9) 有効</span>
            </div>
            <div style="font-size: 0.85rem; color: var(--text-dim);">
                モデル: <span id="currentModelBadge" style="color: #a5b4fc; font-weight: 500;">{{ $currentModel }}</span>
            </div>
        </div>
    </div>

    <!-- Split Section: Active Item Preview & Live Console Logs -->
    <div class="dashboard-split">
        <!-- Live Item Translation Preview -->
        <div class="glass-panel live-preview-card">
            <div class="section-title">
                <i data-lucide="activity" style="color: var(--secondary); width: 20px; height: 20px;"></i>
                <span>現在処理中のアイテム</span>
                <span id="activeItemBadge" class="live-item-badge" style="display: none;">実行中</span>
            </div>

            <div id="activeItemContainer" style="display: flex; flex-direction: column; gap: 12px; justify-content: center; flex: 1;">
                <div id="activeItemPlaceholder" style="text-align: center; color: var(--text-dim); padding: 40px 0;">
                    <i data-lucide="coffee" style="width: 40px; height: 40px; margin-bottom: 8px; opacity: 0.5;"></i>
                    <p>現在アイドル状態です。「自動英訳を開始」をクリックしてください</p>
                </div>

                <div id="activeItemContent" style="display: none; flex-direction: column; gap: 12px;">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <span id="activeItemType" style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: var(--primary);">POST #0</span>
                        <span id="activeItemCodeCount" class="code-guard-badge">コードブロック: 0箇所</span>
                    </div>
                    <div>
                        <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 2px;">日本語タイトル:</div>
                        <div id="activeItemTitleJa" style="font-weight: 600; color: #fff; font-size: 0.95rem;">-</div>
                    </div>
                    <div>
                        <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 2px;">英訳タイトル (生成結果):</div>
                        <div id="activeItemTitleEn" style="font-weight: 600; color: #34d399; font-size: 0.95rem;">-</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Real-time Console Log Terminal -->
        <div class="glass-panel live-preview-card">
            <div class="section-title" style="justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <i data-lucide="terminal" style="color: #6366f1; width: 20px; height: 20px;"></i>
                    <span>リアルタイム実行ログ</span>
                </div>
                <button id="btnClearLogs" style="background: none; border: none; color: var(--text-dim); cursor: pointer; font-size: 0.8rem;">クリア</button>
            </div>

            <div id="logTerminal" class="log-terminal">
                <div class="log-line">
                    <span class="log-time">System</span>
                    <span class="log-level-info">システム初期化完了。LMStudioと接続準備完了。</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Items Management Table Panel -->
    <div class="glass-panel">
        <!-- Tabs -->
        <div class="tabs-header">
            <button class="tab-btn active" data-type="all">
                <i data-lucide="list" style="width: 16px; height: 16px;"></i>
                <span>すべて (<span id="tabCountAll">{{ $stats['total'] }}</span>)</span>
            </button>
            <button class="tab-btn" data-type="post">
                <i data-lucide="file-text" style="width: 16px; height: 16px;"></i>
                <span>記事投稿 (<span id="tabCountPost">{{ $stats['breakdown']['posts']['total'] }}</span>)</span>
            </button>
            <button class="tab-btn" data-type="category">
                <i data-lucide="folder" style="width: 16px; height: 16px;"></i>
                <span>カテゴリー (<span id="tabCountCategory">{{ $stats['breakdown']['categories']['total'] }}</span>)</span>
            </button>
            <button class="tab-btn" data-type="tag">
                <i data-lucide="tag" style="width: 16px; height: 16px;"></i>
                <span>タグ (<span id="tabCountTag">{{ $stats['breakdown']['tags']['total'] }}</span>)</span>
            </button>
        </div>

        <!-- Filter Controls -->
        <div class="filter-bar">
            <div class="search-box">
                <i data-lucide="search" class="search-icon" style="width: 16px; height: 16px;"></i>
                <input type="text" id="searchInput" placeholder="タイトルやIDで検索...">
            </div>

            <div style="display: flex; gap: 10px; align-items: center;">
                <select id="statusFilter" class="select-filter">
                    <option value="all">すべてのステータス</option>
                    <option value="pending">未翻訳 (Pending)</option>
                    <option value="completed">翻訳完了 (Completed)</option>
                    <option value="failed">エラー (Failed)</option>
                </select>

                <button id="btnRefreshList" class="btn btn-secondary" style="padding: 9px 12px;">
                    <i data-lucide="rotate-cw" style="width: 16px; height: 16px;"></i>
                </button>
            </div>
        </div>

        <!-- Data Table -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th style="width: 80px;">種別</th>
                        <th style="width: 90px;">WP ID</th>
                        <th>日本語 (オリジナル)</th>
                        <th>英訳結果 (English)</th>
                        <th style="width: 110px;">状態</th>
                        <th style="width: 140px;">操作</th>
                    </tr>
                </thead>
                <tbody id="itemsTableBody">
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--text-dim); padding: 40px 0;">
                            データを読み込み中...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination Controls -->
        <div id="paginationContainer" style="display: flex; align-items: center; justify-content: space-between; padding: 16px 24px; border-top: 1px solid var(--border-glass);">
            <div id="paginationInfo" style="font-size: 0.85rem; color: var(--text-dim);">0 件中 0 件表示</div>
            <div style="display: flex; gap: 8px;">
                <button id="btnPrevPage" class="btn btn-secondary" style="padding: 6px 12px;" disabled>前へ</button>
                <button id="btnNextPage" class="btn btn-secondary" style="padding: 6px 12px;" disabled>次へ</button>
            </div>
        </div>
    </div>

</div>

<!-- Item Detail & Edit Modal -->
<div id="itemModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span id="modalItemTypeBadge" class="live-item-badge">POST</span>
                <h3 id="modalTitle" style="font-size: 1.15rem; font-weight: 600;">詳細確認・英訳編集</h3>
            </div>
            <button id="btnCloseModal" style="background: none; border: none; color: var(--text-dim); cursor: pointer;">
                <i data-lucide="x" style="width: 20px; height: 20px;"></i>
            </button>
        </div>

        <div class="modal-body">
            <input type="hidden" id="modalItemId">

            <div class="form-group">
                <label class="form-label">日本語タイトル / 名称 (原文):</label>
                <input type="text" id="modalTitleJa" class="form-input" readonly style="background: rgba(15,23,42,0.4);">
            </div>

            <div class="form-group">
                <label class="form-label">英訳タイトル / 名称 (English):</label>
                <input type="text" id="modalTitleEn" class="form-input" placeholder="英訳タイトルを入力...">
            </div>

            <div id="modalContentGroup" class="form-group">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                    <label class="form-label">英訳本文 (WordPress投稿本文):</label>
                    <span id="modalCodeBlocksCount" class="code-guard-badge">保護コード: 0箇所</span>
                </div>
                <textarea id="modalContentEn" class="form-textarea" placeholder="英訳本文 (HTML/Gutenbergブロック)..."></textarea>
            </div>

            <div id="modalErrorContainer" style="display: none; background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 8px; padding: 12px; color: #f87171; font-size: 0.85rem;">
                <strong>前回のエラー:</strong> <span id="modalErrorText"></span>
            </div>
        </div>

        <div class="modal-footer">
            <button id="btnModalTranslate" class="btn btn-primary">
                <i data-lucide="sparkles" style="width: 16px; height: 16px;"></i>
                <span>今すぐAI再翻訳</span>
            </button>
            <button id="btnModalSave" class="btn btn-success">
                <i data-lucide="save" style="width: 16px; height: 16px;"></i>
                <span>保存してWordPressに反映</span>
            </button>
            <button id="btnModalClose" class="btn btn-secondary">閉じる</button>
        </div>
    </div>
</div>

<!-- Settings Modal -->
<div id="settingsModal" class="modal-overlay">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3 style="font-size: 1.15rem; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                <i data-lucide="sliders" style="width: 20px; height: 20px; color: var(--primary);"></i>
                <span>システム設定</span>
            </h3>
            <button id="btnCloseSettingsModal" style="background: none; border: none; color: var(--text-dim); cursor: pointer;">
                <i data-lucide="x" style="width: 20px; height: 20px;"></i>
            </button>
        </div>

        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">LMStudio サーバー URL (Rule 4):</label>
                <div style="display: flex; gap: 8px;">
                    <input type="text" id="settingLmStudioUrl" class="form-input" value="{{ $lmStudioUrl }}">
                    <button id="btnTestLmStudio" class="btn btn-secondary" style="white-space: nowrap;">接続テスト</button>
                </div>
                <span id="lmStudioTestResult" style="font-size: 0.8rem; margin-top: 4px;"></span>
            </div>

            <div class="form-group">
                <label class="form-label">使用AIモデル:</label>
                <select id="settingModel" class="select-filter" style="width: 100%;">
                    @foreach($models as $m)
                        <option value="{{ $m }}" {{ $m === $currentModel ? 'selected' : '' }}>{{ $m }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">リクエスト間ウェイト (秒):</label>
                <input type="number" id="settingDelay" class="form-input" value="0.5" step="0.1" min="0" max="10">
                <span style="font-size: 0.78rem; color: var(--text-dim);">LMStudioの負荷を抑えるためのインターバル</span>
            </div>

            <div class="form-group">
                <label class="form-label">英訳対象 (Rule 8):</label>
                <div style="display: flex; gap: 16px; margin-top: 6px;">
                    <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                        <input type="checkbox" id="targetPost" checked> 記事 (Posts)
                    </label>
                    <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                        <input type="checkbox" id="targetCategory" checked> カテゴリー
                    </label>
                    <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                        <input type="checkbox" id="targetTag" checked> タグ
                    </label>
                </div>
            </div>
        </div>

        <div class="modal-footer">
            <button id="btnSaveSettings" class="btn btn-primary">設定を保存</button>
            <button id="btnCancelSettings" class="btn btn-secondary">キャンセル</button>
        </div>
    </div>
</div>

<!-- Toast Notification -->
<div id="toastNotification" class="toast">
    <i data-lucide="info" id="toastIcon" style="width: 20px; height: 20px;"></i>
    <span id="toastMessage">通知メッセージ</span>
</div>

<script>
    // Application State
    const App = {
        csrfToken: document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
        runnerState: '{{ $stats["runner_state"] }}',
        activeFilterType: 'all',
        activeFilterStatus: 'all',
        searchQuery: '',
        currentPage: 1,
        runnerEventSource: null,
        workerLoopRunning: false,
        lastLogId: null,

        init() {
            lucide.createIcons();
            this.bindEvents();
            this.updateRunnerUI(this.runnerState);
            this.loadItems();
            this.startStatusPoller();
            this.pollLogs();

            // If queue is completely empty on first run, auto-prompt sync
            const total = parseInt(document.getElementById('statTotal').innerText.replace(/,/g, ''));
            if (total === 0) {
                this.syncFromWp(true);
            }
        },

        bindEvents() {
            // Controls
            document.getElementById('btnStartRunner').addEventListener('click', () => this.startRunner());
            document.getElementById('btnPauseRunner').addEventListener('click', () => this.pauseRunner());
            document.getElementById('btnStopRunner').addEventListener('click', () => this.stopRunner());
            document.getElementById('btnStepRunner').addEventListener('click', () => this.processSingleStep());
            document.getElementById('btnSyncWp').addEventListener('click', () => this.syncFromWp());
            document.getElementById('btnBatchAlphaNumeric').addEventListener('click', () => this.syncAlphaNumericDirect());

            // Tab filtering
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                    const target = e.currentTarget;
                    target.classList.add('active');
                    this.activeFilterType = target.getAttribute('data-type');
                    this.currentPage = 1;
                    this.loadItems();
                });
            });

            // Status filter
            document.getElementById('statusFilter').addEventListener('change', (e) => {
                this.activeFilterStatus = e.target.value;
                this.currentPage = 1;
                this.loadItems();
            });

            // Search with debounce
            let searchTimeout;
            document.getElementById('searchInput').addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.searchQuery = e.target.value.trim();
                    this.currentPage = 1;
                    this.loadItems();
                }, 350);
            });

            document.getElementById('btnRefreshList').addEventListener('click', () => this.loadItems());
            document.getElementById('btnPrevPage').addEventListener('click', () => {
                if (this.currentPage > 1) {
                    this.currentPage--;
                    this.loadItems();
                }
            });
            document.getElementById('btnNextPage').addEventListener('click', () => {
                this.currentPage++;
                this.loadItems();
            });

            // Settings Modal
            document.getElementById('btnOpenSettings').addEventListener('click', () => this.openSettingsModal());
            document.getElementById('btnCloseSettingsModal').addEventListener('click', () => this.closeSettingsModal());
            document.getElementById('btnCancelSettings').addEventListener('click', () => this.closeSettingsModal());
            document.getElementById('btnSaveSettings').addEventListener('click', () => this.saveSettings());
            document.getElementById('btnTestLmStudio').addEventListener('click', () => this.testLmStudio());

            // Edit Item Modal
            document.getElementById('btnCloseModal').addEventListener('click', () => this.closeItemModal());
            document.getElementById('btnModalClose').addEventListener('click', () => this.closeItemModal());
            document.getElementById('btnModalSave').addEventListener('click', () => this.saveItemModal());
            document.getElementById('btnModalTranslate').addEventListener('click', () => this.translateCurrentModalItem());

            // Clear logs
            document.getElementById('btnClearLogs').addEventListener('click', () => {
                document.getElementById('logTerminal').innerHTML = '';
            });
        },

        showToast(message, type = 'info') {
            const toast = document.getElementById('toastNotification');
            const msgEl = document.getElementById('toastMessage');
            const iconEl = document.getElementById('toastIcon');

            msgEl.innerText = message;
            if (type === 'success') {
                toast.style.borderColor = '#10b981';
                iconEl.setAttribute('data-lucide', 'check-circle-2');
                iconEl.style.color = '#34d399';
            } else if (type === 'error') {
                toast.style.borderColor = '#ef4444';
                iconEl.setAttribute('data-lucide', 'alert-circle');
                iconEl.style.color = '#f87171';
            } else {
                toast.style.borderColor = 'rgba(255,255,255,0.15)';
                iconEl.setAttribute('data-lucide', 'info');
                iconEl.style.color = '#60a5fa';
            }
            lucide.createIcons();

            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 3500);
        },

        addLog(message, level = 'info') {
            const term = document.getElementById('logTerminal');
            const now = new Date();
            const timeStr = now.toTimeString().split(' ')[0];

            const line = document.createElement('div');
            line.className = 'log-line';
            line.innerHTML = `
                <span class="log-time">${timeStr}</span>
                <span class="log-level-${level}">${this.escapeHtml(message)}</span>
            `;
            term.appendChild(line);
            term.scrollTop = term.scrollHeight;
        },

        escapeHtml(str) {
            if (!str) return '';
            return str.replace(/[&<>'"]/g, 
                tag => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[tag] || tag)
            );
        },

        updateRunnerUI(state) {
            this.runnerState = state;
            const badge = document.getElementById('runnerStatusBadge');
            const badgeText = document.getElementById('runnerStatusText');
            const btnStart = document.getElementById('btnStartRunner');
            const btnPause = document.getElementById('btnPauseRunner');
            const btnStop = document.getElementById('btnStopRunner');
            const btnStep = document.getElementById('btnStepRunner');

            badge.className = 'status-badge';

            if (state === 'running') {
                badge.classList.add('status-running');
                badgeText.innerText = '英訳実行中 (Running)';
                btnStart.disabled = true;
                btnPause.disabled = false;
                btnStop.disabled = false;
                btnStep.disabled = true;
                document.getElementById('activeItemBadge').style.display = 'inline-block';
            } else if (state === 'paused') {
                badge.classList.add('status-paused');
                badgeText.innerText = '一時停止中 (Paused)';
                btnStart.disabled = false;
                btnPause.disabled = true;
                btnStop.disabled = false;
                btnStep.disabled = false;
                document.getElementById('activeItemBadge').style.display = 'none';
            } else if (state === 'stopped') {
                badge.classList.add('status-stopped');
                badgeText.innerText = '停止中 (Stopped)';
                btnStart.disabled = false;
                btnPause.disabled = true;
                btnStop.disabled = true;
                btnStep.disabled = false;
                document.getElementById('activeItemBadge').style.display = 'none';
            } else {
                badge.classList.add('status-idle');
                badgeText.innerText = '待機中 (Idle)';
                btnStart.disabled = false;
                btnPause.disabled = true;
                btnStop.disabled = true;
                btnStep.disabled = false;
                document.getElementById('activeItemBadge').style.display = 'none';
            }
        },

        async syncFromWp(silent = false) {
            const icon = document.getElementById('syncIcon');
            icon.classList.add('spin');
            if (!silent) this.addLog('WordPress MySQLデータベースからデータを同期しています...', 'info');

            try {
                const res = await fetch('/api/sync', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.csrfToken, 'Content-Type': 'application/json' }
                });
                const data = await res.json();
                if (data.success) {
                    const directCount = data.result.alphanumeric_direct_updated || 0;
                    const directInfo = directCount > 0 ? ` (英数記号直接反映: ${directCount}件)` : '';
                    this.showToast(`同期完了: 記事 ${data.result.posts_synced}件, カテゴリ ${data.result.categories_synced}件, タグ ${data.result.tags_synced}件${directInfo}`, 'success');
                    this.addLog(`WordPressデータ同期成功 (総件数: ${data.result.total_in_db}件)${directInfo}`, 'success');
                    this.updateStatsUI(data.stats);
                    this.loadItems();
                } else {
                    this.showToast(data.error || '同期エラーが発生しました', 'error');
                }
            } catch (err) {
                this.showToast('WordPress同期通信エラー: ' + err.message, 'error');
            } finally {
                const btnSync = document.getElementById('btnSyncWp');
                if (btnSync) {
                    btnSync.disabled = false;
                    btnSync.querySelectorAll('svg, i').forEach(el => el.classList.remove('spin'));
                }
                const syncIcon = document.getElementById('syncIcon');
                if (syncIcon) syncIcon.classList.remove('spin');
            }
        },

        async syncAlphaNumericDirect() {
            const btn = document.getElementById('btnBatchAlphaNumeric');
            if (btn) {
                btn.disabled = true;
                btn.querySelectorAll('svg, i').forEach(el => el.classList.add('spin'));
            }

            this.addLog('英字・数字・記号のみの項目をLM Studioを介さずenカラムへ直接更新中...', 'info');

            try {
                const res = await fetch('/api/sync-alphanumeric', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': this.csrfToken,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    }
                });

                if (!res.ok) {
                    let errMsg = `サーバーエラー (${res.status})`;
                    try {
                        const errData = await res.json();
                        errMsg = errData.message || errData.error || errMsg;
                    } catch (e) {
                        const text = await res.text();
                        if (text) errMsg += ': ' + text.substring(0, 120);
                    }
                    throw new Error(errMsg);
                }

                const data = await res.json();
                if (data.success) {
                    const total = data.result.total;
                    this.showToast(`英数記号の項目 ${total}件 をLM Studioを介さずenカラムへ直接更新しました！`, 'success');
                    this.addLog(`英数記号の直接反映完了: 合計 ${total}件 (カテゴリ: ${data.result.categories}件, タグ: ${data.result.tags}件, 記事: ${data.result.posts}件)`, 'success');
                    if (data.stats) this.updateStatsUI(data.stats);
                    this.loadItems();
                } else {
                    this.showToast(data.error || '英数記号の直接更新に失敗しました', 'error');
                    this.addLog(`[エラー] ${data.error}`, 'error');
                }
            } catch (err) {
                this.showToast('通信エラー: ' + err.message, 'error');
                this.addLog(`[通信エラー] ${err.message}`, 'error');
            } finally {
                const btnBatch = document.getElementById('btnBatchAlphaNumeric');
                if (btnBatch) {
                    btnBatch.disabled = false;
                    btnBatch.querySelectorAll('svg, i').forEach(el => el.classList.remove('spin'));
                }
                const zapIcon = document.getElementById('zapIcon');
                if (zapIcon) zapIcon.classList.remove('spin');
            }
        },

        async fetchJson(url, options = {}) {
            const headers = Object.assign({
                'X-CSRF-TOKEN': this.csrfToken,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }, options.headers || {});

            const res = await fetch(url, Object.assign({}, options, { headers }));
            const text = await res.text();

            try {
                return JSON.parse(text);
            } catch (parseErr) {
                // Server or reverse proxy returned HTML (e.g. 504 Gateway Timeout during long LLM generation)
                if (res.status === 504 || res.status === 502 || res.status === 524) {
                    return {
                        status: 'busy',
                        success: false,
                        message: `長文処理のためサーバー応答待機中 (HTTP ${res.status})。バックグラウンド処理を継続しています...`
                    };
                }
                const titleMatch = text.match(/<title>(.*?)<\/title>/i);
                const summary = titleMatch ? titleMatch[1] : `HTTP ${res.status}`;
                throw new Error(`サーバー応答エラー (${summary})`);
            }
        },

        async startRunner() {
            try {
                const data = await this.fetchJson('/api/runner/start', { method: 'POST' });
                if (data.success) {
                    this.updateRunnerUI('running');
                    if (data.stats) this.updateStatsUI(data.stats);
                    this.addLog('英訳ランナーを開始しました。LMStudioへリクエスト送信中...', 'info');
                    this.runWorkerLoop();
                }
            } catch (err) {
                this.showToast('ランナー開始エラー: ' + err.message, 'error');
            }
        },

        async pauseRunner() {
            try {
                const data = await this.fetchJson('/api/runner/pause', { method: 'POST' });
                if (data.success) {
                    this.updateRunnerUI('paused');
                    this.addLog('英訳処理を一時停止しました。再開ボタンでいつでも継続できます。', 'warning');
                }
            } catch (err) {
                this.showToast('一時停止エラー: ' + err.message, 'error');
            }
        },

        async stopRunner() {
            try {
                const data = await this.fetchJson('/api/runner/stop', { method: 'POST' });
                if (data.success) {
                    this.updateRunnerUI('stopped');
                    this.addLog('英訳処理を停止しました。', 'warning');
                }
            } catch (err) {
                this.showToast('停止エラー: ' + err.message, 'error');
            }
        },

        async processSingleStep() {
            this.addLog('1件テスト翻訳ステップを実行中...', 'info');
            try {
                while (true) {
                    const data = await this.fetchJson('/api/runner/step', { method: 'POST' });
                    if (data.stats) this.updateStatsUI(data.stats);

                    if (data.status === 'partial' && data.item) {
                        const done = data.progress?.done || 0;
                        const total = data.progress?.total || 0;
                        this.addLog(`[分割翻訳中] #${data.item.source_id} (${data.item.item_type}) 「${data.item.title_ja}」 (${done}/${total} パート完了)...`, 'info');
                        this.displayActiveItem(data.item, `${done}/${total} パート完了`);
                        continue;
                    } else if (data.status === 'busy') {
                        await new Promise(r => setTimeout(r, 2000));
                        continue;
                    } else if (data.status === 'success' && data.item) {
                        this.showToast(`翻訳成功: #${data.item.source_id} 「${data.item.title_en}」`, 'success');
                        this.addLog(`[SUCCESS] #${data.item.source_id} (${data.item.item_type}) 「${data.item.title_ja}」 -> 「${data.item.title_en}」`, 'success');
                        this.displayActiveItem(data.item);
                    } else if (data.status === 'completed') {
                        this.showToast('すべての英訳が完了しています！', 'success');
                        this.addLog('すべての英訳が完了しています', 'info');
                    } else {
                        this.showToast(data.message || 'エラーが発生しました', 'error');
                        this.addLog(`[ERROR] ${data.message}`, 'error');
                    }
                    break;
                }
                this.loadItems();
            } catch (err) {
                this.showToast('ステップ実行エラー: ' + err.message, 'error');
            }
        },

        // Client-driven asynchronous robust worker loop
        async runWorkerLoop() {
            if (this.workerLoopRunning) return;
            this.workerLoopRunning = true;

            while (this.runnerState === 'running') {
                try {
                    const data = await this.fetchJson('/api/runner/step', { method: 'POST' });

                    if (data.stats) {
                        this.updateStatsUI(data.stats);
                    }

                    if (data.status === 'partial' && data.item) {
                        const done = data.progress?.done || 0;
                        const total = data.progress?.total || 0;
                        this.addLog(`[分割翻訳中] #${data.item.source_id} (${data.item.item_type}) 「${data.item.title_ja}」 (${done}/${total} パート完了)...`, 'info');
                        this.displayActiveItem(data.item, `${done}/${total} パート完了`);
                        continue;
                    } else if (data.status === 'busy') {
                        if (data.item) {
                            this.displayActiveItem(data.item, 'LM Studio 処理待機中...');
                        }
                        await new Promise(r => setTimeout(r, 2000));
                        continue;
                    } else if (data.status === 'success' && data.item) {
                        this.addLog(`[翻訳完了] #${data.item.source_id} (${data.item.item_type}) 「${data.item.title_ja}」 -> 「${data.item.title_en}」`, 'success');
                        this.displayActiveItem(data.item);
                        this.loadItems(true); // silent refresh
                    } else if (data.status === 'error') {
                        this.addLog(`[エラー] ${data.message}`, 'error');
                    } else if (data.status === 'completed') {
                        this.addLog('祝！すべての対象の英訳が完了しました！', 'success');
                        this.showToast('すべての英訳が完了しました！', 'success');
                        this.updateRunnerUI('idle');
                        break;
                    } else if (data.status === 'paused' || data.status === 'stopped') {
                        this.updateRunnerUI(data.status);
                        break;
                    }

                    // Respect delay setting
                    const delaySec = parseFloat(document.getElementById('settingDelay')?.value || '0.5');
                    if (delaySec > 0) {
                        await new Promise(r => setTimeout(r, delaySec * 1000));
                    }
                } catch (err) {
                    this.addLog('通信エラー発生。3秒後に再試行します: ' + err.message, 'warning');
                    await new Promise(r => setTimeout(r, 3000));
                }
            }

            this.workerLoopRunning = false;
        },

        displayActiveItem(item, progressNote = null) {
            document.getElementById('activeItemPlaceholder').style.display = 'none';
            const content = document.getElementById('activeItemContent');
            content.style.display = 'flex';

            const label = progressNote ? `${item.item_type.toUpperCase()} #${item.source_id} (${progressNote})` : `${item.item_type.toUpperCase()} #${item.source_id}`;
            document.getElementById('activeItemType').innerText = label;
            document.getElementById('activeItemCodeCount').innerText = `保護コード: ${item.code_blocks_count || 0}箇所`;
            document.getElementById('activeItemTitleJa').innerText = item.title_ja || '(タイトルなし)';
            document.getElementById('activeItemTitleEn').innerText = item.title_en || '(翻訳中...)';
        },

        updateStatsUI(stats) {
            document.getElementById('statTotal').innerText = Number(stats.total).toLocaleString();
            document.getElementById('statCompleted').innerText = Number(stats.completed).toLocaleString();
            document.getElementById('statPending').innerText = Number(stats.pending).toLocaleString();
            document.getElementById('statFailed').innerText = Number(stats.failed).toLocaleString();

            const pct = `${stats.percentage}%`;
            document.getElementById('statPercent').innerText = `${pct} 完了済み`;
            document.getElementById('progressBarLabel').innerText = pct;
            document.getElementById('progressBarFill').style.width = pct;

            if (stats.breakdown) {
                document.getElementById('progressPosts').innerText = `${stats.breakdown.posts.completed}/${stats.breakdown.posts.total}`;
                document.getElementById('progressCategories').innerText = `${stats.breakdown.categories.completed}/${stats.breakdown.categories.total}`;
                document.getElementById('progressTags').innerText = `${stats.breakdown.tags.completed}/${stats.breakdown.tags.total}`;

                document.getElementById('tabCountAll').innerText = stats.total;
                document.getElementById('tabCountPost').innerText = stats.breakdown.posts.total;
                document.getElementById('tabCountCategory').innerText = stats.breakdown.categories.total;
                document.getElementById('tabCountTag').innerText = stats.breakdown.tags.total;
            }

            if (stats.runner_state && stats.runner_state !== this.runnerState) {
                this.updateRunnerUI(stats.runner_state);
            }
        },

        async loadItems(silent = false) {
            const tbody = document.getElementById('itemsTableBody');
            if (!silent) {
                tbody.innerHTML = `<tr><td colspan="6" style="text-align: center; color: var(--text-dim); padding: 30px 0;"><i data-lucide="loader" class="spin" style="width: 20px; height: 20px; display: inline-block;"></i> 読み込み中...</td></tr>`;
                lucide.createIcons();
            }

            const params = new URLSearchParams({
                type: this.activeFilterType,
                status: this.activeFilterStatus,
                search: this.searchQuery,
                page: this.currentPage
            });

            try {
                const res = await fetch(`/api/items?${params.toString()}`);
                const data = await res.json();

                if (!data.data || data.data.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="6" style="text-align: center; color: var(--text-dim); padding: 40px 0;">該当するデータがありません</td></tr>`;
                    document.getElementById('paginationInfo').innerText = '0 件中 0 件表示';
                    document.getElementById('btnPrevPage').disabled = true;
                    document.getElementById('btnNextPage').disabled = true;
                    return;
                }

                let html = '';
                data.data.forEach(item => {
                    const typeBadgeClass = item.item_type === 'post' ? 'background: rgba(99,102,241,0.2); color: #a5b4fc;' : (item.item_type === 'category' ? 'background: rgba(6,182,212,0.2); color: #67e8f9;' : 'background: rgba(245,158,11,0.2); color: #fcd34d;');
                    const typeLabel = item.item_type === 'post' ? '記事' : (item.item_type === 'category' ? 'カテゴリ' : 'タグ');

                    let statusBadge = '';
                    if (item.status === 'completed') {
                        statusBadge = '<span style="color: #34d399; font-weight: 600; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 4px;"><i data-lucide="check" style="width:14px;height:14px;"></i>完了</span>';
                    } else if (item.status === 'failed') {
                        statusBadge = '<span style="color: #f87171; font-weight: 600; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 4px;"><i data-lucide="alert-circle" style="width:14px;height:14px;"></i>エラー</span>';
                    } else if (item.status === 'translating') {
                        statusBadge = '<span style="color: #60a5fa; font-weight: 600; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 4px;"><i data-lucide="loader" class="spin" style="width:14px;height:14px;"></i>翻訳中</span>';
                    } else {
                        statusBadge = '<span style="color: #94a3b8; font-size: 0.8rem;">未翻訳</span>';
                    }

                    const titleJa = this.escapeHtml(item.title_ja || '(無題)');
                    const titleEn = item.title_en ? this.escapeHtml(item.title_en) : '<span style="color: var(--text-dim); font-style: italic;">未英訳</span>';

                    html += `
                        <tr data-id="${item.id}">
                            <td><span style="font-size: 0.75rem; font-weight: 700; padding: 2px 8px; border-radius: 6px; ${typeBadgeClass}">${typeLabel}</span></td>
                            <td style="font-family: 'JetBrains Mono', monospace; font-size: 0.82rem; color: var(--text-dim);">#${item.source_id}</td>
                            <td>
                                <div style="font-weight: 500; color: #fff; max-width: 320px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="${titleJa}">
                                    ${titleJa}
                                </div>
                            </td>
                            <td>
                                <div style="color: #34d399; max-width: 340px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="${this.escapeHtml(item.title_en || '')}">
                                    ${titleEn}
                                </div>
                            </td>
                            <td>${statusBadge}</td>
                            <td>
                                <div style="display: flex; gap: 6px;">
                                    <button onclick="App.openItemModal(${item.id})" class="btn btn-secondary" style="padding: 5px 9px; font-size: 0.8rem;" title="詳細確認・手動修正">
                                        <i data-lucide="edit-3" style="width: 14px; height: 14px;"></i>
                                    </button>
                                    <button onclick="App.translateSingleItem(${item.id})" class="btn btn-primary" style="padding: 5px 9px; font-size: 0.8rem;" title="AI英訳実行">
                                        <i data-lucide="sparkles" style="width: 14px; height: 14px;"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `;
                });

                tbody.innerHTML = html;
                lucide.createIcons();

                // 処理中でないヘッダーボタンのアイコンの回転を確実にクリア
                const btnBatch = document.getElementById('btnBatchAlphaNumeric');
                if (btnBatch && !btnBatch.disabled) {
                    btnBatch.querySelectorAll('svg, i').forEach(el => el.classList.remove('spin'));
                }
                const btnSync = document.getElementById('btnSyncWp');
                if (btnSync && !btnSync.disabled) {
                    btnSync.querySelectorAll('svg, i').forEach(el => el.classList.remove('spin'));
                }

                // Pagination
                document.getElementById('paginationInfo').innerText = `${data.total} 件中 ${data.from || 0}～${data.to || 0} 件表示 (ページ ${data.current_page}/${data.last_page})`;
                document.getElementById('btnPrevPage').disabled = (data.current_page <= 1);
                document.getElementById('btnNextPage').disabled = (data.current_page >= data.last_page);

            } catch (err) {
                tbody.innerHTML = `<tr><td colspan="6" style="text-align: center; color: #f87171; padding: 20px;">データ取得エラー: ${err.message}</td></tr>`;
            }
        },

        async openItemModal(id) {
            try {
                const res = await fetch(`/api/items/${id}`);
                const item = await res.json();

                document.getElementById('modalItemId').value = item.id;
                document.getElementById('modalItemTypeBadge').innerText = item.item_type.toUpperCase() + ' #' + item.source_id;
                document.getElementById('modalTitleJa').value = item.title_ja || '';
                document.getElementById('modalTitleEn').value = item.title_en || '';

                const contentGroup = document.getElementById('modalContentGroup');
                if (item.item_type === 'post') {
                    contentGroup.style.display = 'flex';
                    document.getElementById('modalContentEn').value = item.content_en || '';
                    document.getElementById('modalCodeBlocksCount').innerText = `保護コード: ${item.code_blocks_count || 0}箇所`;
                } else {
                    contentGroup.style.display = 'none';
                }

                const errBox = document.getElementById('modalErrorContainer');
                if (item.error_message) {
                    errBox.style.display = 'block';
                    document.getElementById('modalErrorText').innerText = item.error_message;
                } else {
                    errBox.style.display = 'none';
                }

                document.getElementById('itemModal').classList.add('open');
            } catch (err) {
                this.showToast('アイテム読込エラー: ' + err.message, 'error');
            }
        },

        closeItemModal() {
            document.getElementById('itemModal').classList.remove('open');
        },

        async saveItemModal() {
            const id = document.getElementById('modalItemId').value;
            const titleEn = document.getElementById('modalTitleEn').value;
            const contentEn = document.getElementById('modalContentEn').value;

            try {
                const res = await fetch(`/api/items/${id}`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.csrfToken, 'Content-Type': 'application/json' },
                    body: JSON.stringify({ title_en: titleEn, content_en: contentEn })
                });
                const data = await res.json();
                if (data.success) {
                    this.showToast('WordPressデータベースに更新反映しました！', 'success');
                    this.closeItemModal();
                    this.loadItems(true);
                }
            } catch (err) {
                this.showToast('保存エラー: ' + err.message, 'error');
            }
        },

        async translateCurrentModalItem() {
            const id = document.getElementById('modalItemId').value;
            const btn = document.getElementById('btnModalTranslate');
            btn.disabled = true;
            btn.innerHTML = `<i data-lucide="loader" class="spin" style="width: 16px; height: 16px;"></i> 翻訳中...`;
            lucide.createIcons();

            try {
                while (true) {
                    const data = await this.fetchJson(`/api/items/${id}/translate`, { method: 'POST' });
                    if (data.status === 'busy') {
                        await new Promise(r => setTimeout(r, 2000));
                        continue;
                    }
                    if (data.success && data.status === 'partial') {
                        const done = data.progress?.done || 0;
                        const total = data.progress?.total || 0;
                        document.getElementById('modalTitleEn').value = data.item.title_en || '';
                        btn.innerHTML = `<i data-lucide="loader" class="spin" style="width: 16px; height: 16px;"></i> 分割翻訳中 (${done}/${total})...`;
                        lucide.createIcons();
                        this.addLog(`[手動分割英訳中] #${data.item.source_id} (${done}/${total} パート完了)...`, 'info');
                        continue;
                    }
                    if (data.success) {
                        document.getElementById('modalTitleEn').value = data.item.title_en || '';
                        if (data.item.item_type === 'post') {
                            document.getElementById('modalContentEn').value = data.item.content_en || '';
                            document.getElementById('modalCodeBlocksCount').innerText = `保護コード: ${data.item.code_blocks_count || 0}箇所`;
                        }
                        document.getElementById('modalErrorContainer').style.display = 'none';
                        this.showToast('AI英訳が完了しWordPressに反映されました！', 'success');
                        this.addLog(`手動単体英訳成功: #${data.item.source_id}`, 'success');
                        if (data.stats) this.updateStatsUI(data.stats);
                        this.loadItems(true);
                    } else {
                        this.showToast('英訳エラー: ' + (data.error || '失敗しました'), 'error');
                    }
                    break;
                }
            } catch (err) {
                this.showToast('英訳通信エラー: ' + err.message, 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = `<i data-lucide="sparkles" style="width: 16px; height: 16px;"></i> 今すぐAI再翻訳`;
                lucide.createIcons();
            }
        },

        async translateSingleItem(id) {
            this.showToast(`アイテム #${id} のAI英訳を開始します...`, 'info');
            try {
                while (true) {
                    const data = await this.fetchJson(`/api/items/${id}/translate`, { method: 'POST' });
                    if (data.status === 'busy') {
                        await new Promise(r => setTimeout(r, 2000));
                        continue;
                    }
                    if (data.success && data.status === 'partial') {
                        const done = data.progress?.done || 0;
                        const total = data.progress?.total || 0;
                        this.addLog(`[分割翻訳中] #${data.item.source_id} (${done}/${total} パート完了)...`, 'info');
                        this.displayActiveItem(data.item, `${done}/${total} パート完了`);
                        continue;
                    }
                    if (data.success) {
                        this.showToast(`英訳完了: #${data.item.source_id} 「${data.item.title_en}」`, 'success');
                        this.addLog(`AI英訳完了: #${data.item.source_id} 「${data.item.title_ja}」 -> 「${data.item.title_en}」`, 'success');
                        this.displayActiveItem(data.item);
                        if (data.stats) this.updateStatsUI(data.stats);
                        this.loadItems(true);
                    } else {
                        this.showToast('英訳エラー: ' + (data.error || '失敗'), 'error');
                    }
                    break;
                }
            } catch (err) {
                this.showToast('通信エラー: ' + err.message, 'error');
            }
        },

        // Settings Modal
        openSettingsModal() {
            document.getElementById('settingsModal').classList.add('open');
        },
        closeSettingsModal() {
            document.getElementById('settingsModal').classList.remove('open');
        },

        async testLmStudio() {
            const url = document.getElementById('settingLmStudioUrl').value;
            const resEl = document.getElementById('lmStudioTestResult');
            resEl.innerText = '接続テスト中...';
            resEl.style.color = 'var(--text-muted)';

            try {
                const res = await fetch('/api/settings/test-lmstudio', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.csrfToken, 'Content-Type': 'application/json' },
                    body: JSON.stringify({ url: url })
                });
                const data = await res.json();
                if (data.success) {
                    resEl.innerText = data.message;
                    resEl.style.color = '#34d399';

                    // Update model dropdown if models returned
                    if (data.models && data.models.length > 0) {
                        const sel = document.getElementById('settingModel');
                        sel.innerHTML = '';
                        data.models.forEach(m => {
                            const opt = document.createElement('option');
                            opt.value = m;
                            opt.innerText = m;
                            sel.appendChild(opt);
                        });
                    }
                } else {
                    resEl.innerText = data.message;
                    resEl.style.color = '#f87171';
                }
            } catch (err) {
                resEl.innerText = '接続失敗: ' + err.message;
                resEl.style.color = '#f87171';
            }
        },

        async saveSettings() {
            const url = document.getElementById('settingLmStudioUrl').value;
            const model = document.getElementById('settingModel').value;
            const delay = document.getElementById('settingDelay').value;

            const targetTypes = [];
            if (document.getElementById('targetPost').checked) targetTypes.push('post');
            if (document.getElementById('targetCategory').checked) targetTypes.push('category');
            if (document.getElementById('targetTag').checked) targetTypes.push('tag');

            try {
                const res = await fetch('/api/settings', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.csrfToken, 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        lmstudio_url: url,
                        lmstudio_model: model,
                        delay_seconds: parseFloat(delay),
                        target_types: targetTypes
                    })
                });
                const data = await res.json();
                if (data.success) {
                    this.showToast('設定を正常に保存しました', 'success');
                    document.getElementById('currentModelBadge').innerText = model;
                    this.closeSettingsModal();
                }
            } catch (err) {
                this.showToast('設定保存エラー: ' + err.message, 'error');
            }
        },

        startStatusPoller() {
            setInterval(async () => {
                if (this.runnerState !== 'running') {
                    try {
                        const res = await fetch('/api/stats');
                        const data = await res.json();
                        this.updateStatsUI(data);
                    } catch (e) {}
                }
            }, 6000);
        },

        async pollLogs() {
            setInterval(async () => {
                try {
                    const url = this.lastLogId ? `/api/logs?since_id=${this.lastLogId}` : '/api/logs';
                    const res = await fetch(url);
                    const logs = await res.json();
                    if (logs && logs.length > 0) {
                        logs.forEach(log => {
                            this.addLog(log.message, log.level);
                            this.lastLogId = Math.max(this.lastLogId || 0, log.id);
                        });
                    }
                } catch (e) {}
            }, 3000);
        }
    };

    // Initialize application on DOM ready
    document.addEventListener('DOMContentLoaded', () => App.init());
</script>

</body>
</html>
