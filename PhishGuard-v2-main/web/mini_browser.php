<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once '../config.php';

$pageTitle    = 'Mini Browser';
$pageSubtitle = 'Preview links safely inside PhishGuard';
$userName     = $_SESSION['full_name'] ?? 'User';
$userInitial  = strtoupper(substr($userName, 0, 1));

$initialUrl  = trim($_GET['url'] ?? '');
$scanResult  = strtoupper(trim($_GET['result'] ?? 'SAFE'));
$scanExplain = trim($_GET['explanation'] ?? '');

if (!in_array($scanResult, ['SAFE', 'SUSPICIOUS', 'DANGEROUS'], true)) {
    $scanResult = 'SAFE';
}

if ($scanExplain === '') {
    if ($scanResult === 'SUSPICIOUS') {
        $scanExplain = 'This URL matches known suspicious patterns. Proceed with caution.';
    } elseif ($scanResult === 'DANGEROUS') {
        $scanExplain = 'This URL is on the PhishGuard blacklist and is dangerous to open.';
    } else {
        $scanExplain = 'No threats found in the PhishGuard database.';
    }
}

$returnTo = trim($_GET['from'] ?? '');
if ($returnTo === '' || !preg_match('/^[a-z0-9_\-]+\.php(\?.*)?$/i', $returnTo)) {
    $returnTo = 'scanner.php';
}

$host = '';
if ($initialUrl !== '' && filter_var($initialUrl, FILTER_VALIDATE_URL)) {
    $host = parse_url($initialUrl, PHP_URL_HOST) ?: '';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mini Browser | PhishGuard</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .pg-mb-wrap {
            display: flex;
            flex-direction: column;
            height: calc(100vh - 200px);
            min-height: 560px;
            border: 1px solid rgba(148, 163, 184, 0.14);
            border-radius: 18px;
            background: #0f1b30;
            overflow: hidden;
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.35);
        }

        .pg-mb-bar {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 14px;
            background: linear-gradient(180deg, #0d1830 0%, #0a1424 100%);
            border-bottom: 1px solid rgba(148, 163, 184, 0.14);
        }

        .pg-mb-navbtn {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(148, 163, 184, 0.18);
            background: rgba(15, 27, 48, 0.6);
            color: #cbd5e1;
            border-radius: 10px;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.15s ease;
            flex-shrink: 0;
            text-decoration: none;
        }

        .pg-mb-navbtn:hover:not(:disabled) {
            background: rgba(56, 189, 248, 0.10);
            color: #38bdf8;
            border-color: rgba(56, 189, 248, 0.35);
            transform: translateY(-1px);
        }

        .pg-mb-navbtn:disabled {
            opacity: 0.35;
            cursor: not-allowed;
        }

        .pg-mb-url {
            flex: 1;
            position: relative;
            min-width: 0;
        }

        .pg-mb-url .pg-mb-lock {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #22c55e;
            font-size: 12px;
            pointer-events: none;
            transition: color 0.2s ease;
        }

        .pg-mb-url.warn .pg-mb-lock {
            color: #f59e0b;
        }

        .pg-mb-url.danger .pg-mb-lock {
            color: #ef4444;
        }

        .pg-mb-url input {
            width: 100%;
            height: 42px;
            padding: 0 14px 0 38px;
            border-radius: 11px;
            border: 1px solid rgba(148, 163, 184, 0.18);
            background: rgba(2, 6, 23, 0.6);
            color: #f8fafc;
            font-size: 13px;
            font-family: 'Consolas', 'Monaco', monospace;
            outline: none;
            transition: all 0.15s ease;
        }

        .pg-mb-url input::placeholder {
            color: #475569;
            font-family: 'Segoe UI', sans-serif;
        }

        .pg-mb-url input:focus {
            border-color: rgba(56, 189, 248, 0.55);
            box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.10);
            background: rgba(2, 6, 23, 0.85);
        }

        .pg-mb-url.danger input:focus {
            border-color: rgba(239, 68, 68, 0.55);
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.12);
        }

        .pg-mb-go {
            height: 42px;
            padding: 0 20px;
            border-radius: 11px;
            border: 0;
            background: linear-gradient(135deg, #2563eb, #38bdf8);
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
            transition: all 0.15s ease;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.28);
            letter-spacing: 0.3px;
        }

        .pg-mb-go:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.42);
        }

        .pg-mb-timer {
            display: none;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 10px;
            background: rgba(245, 158, 11, 0.10);
            border: 1px solid rgba(245, 158, 11, 0.25);
            color: #fbbf24;
            font-size: 11px;
            font-weight: 700;
            flex-shrink: 0;
            white-space: nowrap;
        }

        .pg-mb-timer.show {
            display: inline-flex;
        }

        .pg-mb-timer.danger {
            background: rgba(239, 68, 68, 0.10);
            border-color: rgba(239, 68, 68, 0.28);
            color: #fca5a5;
        }

        .pg-mb-timer-num {
            font-family: 'Consolas', monospace;
            font-size: 14px;
            min-width: 24px;
            text-align: center;
            display: inline-block;
        }

        .pg-mb-banner {
            display: none;
            align-items: flex-start;
            gap: 10px;
            padding: 11px 16px;
            border-bottom: 1px solid rgba(148, 163, 184, 0.10);
            font-size: 12px;
            line-height: 1.55;
        }

        .pg-mb-banner.show {
            display: flex;
        }

        .pg-mb-banner.suspicious {
            background: rgba(245, 158, 11, 0.08);
            border-bottom-color: rgba(245, 158, 11, 0.18);
            color: #fcd34d;
        }

        .pg-mb-banner.danger {
            background: rgba(239, 68, 68, 0.08);
            border-bottom-color: rgba(239, 68, 68, 0.18);
            color: #fca5a5;
        }

        .pg-mb-banner.safe {
            background: rgba(34, 197, 94, 0.08);
            border-bottom-color: rgba(34, 197, 94, 0.18);
            color: #86efac;
        }

        .pg-mb-banner i {
            font-size: 14px;
            flex-shrink: 0;
            margin-top: 1px;
        }

        .pg-mb-banner.suspicious i { color: #f59e0b; }
        .pg-mb-banner.danger i     { color: #ef4444; }
        .pg-mb-banner.safe i       { color: #22c55e; }

        .pg-mb-banner strong {
            font-weight: 700;
            color: inherit;
        }

        .pg-mb-banner span {
            color: inherit;
            opacity: 0.92;
        }

        .pg-mb-frame-area {
            flex: 1;
            position: relative;
            background: #ffffff;
            overflow: hidden;
        }

        .pg-mb-frame {
            width: 100%;
            height: 100%;
            border: 0;
            display: block;
        }

        .pg-mb-frame-loading {
            position: absolute;
            inset: 0;
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 14px;
            background: #0f1b30;
            color: #94a3b8;
            font-size: 13px;
            z-index: 3;
        }

        .pg-mb-frame-loading.show {
            display: flex;
        }

        .pg-mb-frame-loading .pg-mb-spinner {
            width: 38px;
            height: 38px;
            border: 3px solid rgba(56, 189, 248, 0.2);
            border-top-color: #38bdf8;
            border-radius: 50%;
            animation: pgMbSpin 0.8s linear infinite;
        }

        @keyframes pgMbSpin {
            to { transform: rotate(360deg); }
        }

        .pg-mb-embed-blocked {
            position: absolute;
            inset: 0;
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 16px;
            padding: 40px 24px;
            text-align: center;
            background:
                radial-gradient(circle at 50% 0%, rgba(56, 189, 248, 0.06), transparent 60%),
                rgba(15, 27, 48, 0.98);
            z-index: 4;
        }

        .pg-mb-embed-blocked.show {
            display: flex;
        }

        .pg-mb-embed-icon {
            width: 84px;
            height: 84px;
            border-radius: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 34px;
            background: rgba(56, 189, 248, 0.10);
            border: 1px solid rgba(56, 189, 248, 0.28);
            color: #38bdf8;
            box-shadow: 0 0 40px rgba(56, 189, 248, 0.15);
        }

        .pg-mb-embed-blocked h3 {
            margin: 0;
            color: #f8fafc;
            font-size: 19px;
            font-weight: 800;
        }

        .pg-mb-embed-blocked p {
            margin: 0;
            max-width: 540px;
            color: #94a3b8;
            font-size: 13px;
            line-height: 1.65;
        }

        .pg-mb-embed-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
            margin-top: 6px;
        }

        .pg-mb-embed-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 20px;
            border-radius: 11px;
            border: 0;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.15s ease;
            letter-spacing: 0.3px;
        }

        .pg-mb-embed-btn.primary {
            background: linear-gradient(135deg, #2563eb, #38bdf8);
            color: #fff;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.28);
        }

        .pg-mb-embed-btn.primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.42);
            color: #fff;
        }

        .pg-mb-embed-btn.secondary {
            background: rgba(148, 163, 184, 0.10);
            color: #cbd5e1;
            border: 1px solid rgba(148, 163, 184, 0.22);
        }

        .pg-mb-embed-btn.secondary:hover {
            background: rgba(148, 163, 184, 0.18);
            color: #f8fafc;
            transform: translateY(-1px);
        }

        .pg-mb-blocked {
            position: absolute;
            inset: 0;
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 16px;
            padding: 40px 24px;
            text-align: center;
            background:
                radial-gradient(circle at 50% 0%, rgba(239, 68, 68, 0.08), transparent 60%),
                rgba(15, 27, 48, 0.95);
            z-index: 2;
        }

        .pg-mb-blocked.show {
            display: flex;
        }

        .pg-mb-blocked-icon {
            width: 84px;
            height: 84px;
            border-radius: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 34px;
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.30);
            color: #ef4444;
            box-shadow: 0 0 40px rgba(239, 68, 68, 0.18);
            animation: pgMbPulse 2s ease-in-out infinite;
        }

        @keyframes pgMbPulse {
            0%, 100% { transform: scale(1); }
            50%      { transform: scale(1.05); }
        }

        .pg-mb-blocked h3 {
            margin: 0;
            color: #f8fafc;
            font-size: 19px;
            font-weight: 800;
        }

        .pg-mb-blocked p {
            margin: 0;
            max-width: 500px;
            color: #94a3b8;
            font-size: 13px;
            line-height: 1.65;
        }

        .pg-mb-blocked .pg-mb-blocked-reason {
            margin-top: 4px;
            padding: 10px 16px;
            border-radius: 10px;
            background: rgba(239, 68, 68, 0.10);
            border: 1px solid rgba(239, 68, 68, 0.25);
            color: #fca5a5;
            font-size: 12px;
            font-weight: 600;
            max-width: 500px;
        }

        .pg-mb-feedback {
            position: absolute;
            right: 16px;
            bottom: 16px;
            z-index: 6;
            max-width: 360px;
            width: calc(100% - 32px);
            padding: 14px 16px;
            border-radius: 14px;
            background: linear-gradient(180deg, #101c33 0%, #0a1424 100%);
            border: 1px solid rgba(148, 163, 184, 0.22);
            box-shadow: 0 14px 40px rgba(0, 0, 0, 0.5);
            color: #cbd5e1;
            font-size: 12px;
            animation: pgFbSlideUp 0.35s ease;
        }

        @keyframes pgFbSlideUp {
            from { opacity: 0; transform: translateY(12px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .pg-mb-feedback-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 700;
            color: #f8fafc;
            font-size: 13px;
            margin-bottom: 10px;
        }

        .pg-mb-feedback-title i {
            color: #38bdf8;
        }

        .pg-mb-feedback-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 10px;
        }

        .pg-mb-feedback-btn {
            flex: 1;
            min-width: 130px;
            padding: 9px 12px;
            border-radius: 9px;
            border: 1px solid transparent;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.15s ease;
        }

        .pg-mb-feedback-btn.safe {
            background: rgba(34, 197, 94, 0.12);
            border-color: rgba(34, 197, 94, 0.35);
            color: #86efac;
        }

        .pg-mb-feedback-btn.safe:hover,
        .pg-mb-feedback-btn.safe.active {
            background: rgba(34, 197, 94, 0.25);
            color: #bbf7d0;
        }

        .pg-mb-feedback-btn.danger {
            background: rgba(239, 68, 68, 0.12);
            border-color: rgba(239, 68, 68, 0.35);
            color: #fca5a5;
        }

        .pg-mb-feedback-btn.danger:hover,
        .pg-mb-feedback-btn.danger.active {
            background: rgba(239, 68, 68, 0.25);
            color: #fecaca;
        }

        .pg-mb-feedback-note {
            width: 100%;
            min-height: 52px;
            padding: 8px 10px;
            border-radius: 8px;
            border: 1px solid rgba(148, 163, 184, 0.22);
            background: rgba(2, 6, 23, 0.6);
            color: #f8fafc;
            font-size: 11px;
            font-family: 'Segoe UI', sans-serif;
            resize: vertical;
            outline: none;
            margin-bottom: 10px;
        }

        .pg-mb-feedback-note:focus {
            border-color: rgba(56, 189, 248, 0.55);
        }

        .pg-mb-feedback-actions {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
        }

        .pg-mb-feedback-submit {
            padding: 8px 14px;
            border-radius: 8px;
            border: 0;
            background: linear-gradient(135deg, #2563eb, #38bdf8);
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.15s ease;
        }

        .pg-mb-feedback-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.4);
        }

        .pg-mb-feedback-submit:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .pg-mb-feedback-cancel {
            padding: 8px 14px;
            border-radius: 8px;
            border: 1px solid rgba(148, 163, 184, 0.22);
            background: transparent;
            color: #94a3b8;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .pg-mb-feedback-cancel:hover {
            color: #f8fafc;
            border-color: rgba(148, 163, 184, 0.4);
        }

        .pg-mb-feedback-thanks {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px;
            color: #86efac;
            font-weight: 700;
        }

        .pg-mb-feedback-thanks i {
            font-size: 16px;
        }

        .pg-mb-suggestions {
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            right: 0;
            z-index: 500;
            max-height: 300px;
            overflow-y: auto;
            padding: 6px;
            background: #0f1b30;
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: 14px;
            box-shadow: 0 22px 50px rgba(0, 0, 0, 0.55);
            display: none;
        }

        .pg-mb-suggestions.show {
            display: block;
        }

        @media (max-width: 768px) {
            .pg-mb-wrap {
                height: calc(100vh - 180px);
                min-height: 480px;
                border-radius: 14px;
            }
            .pg-mb-bar {
                padding: 10px;
                gap: 8px;
            }
            .pg-mb-navbtn {
                width: 32px;
                height: 32px;
                font-size: 12px;
            }
            .pg-mb-url input {
                height: 38px;
                font-size: 12px;
                padding: 0 12px 0 34px;
            }
            .pg-mb-go {
                height: 38px;
                padding: 0 14px;
                font-size: 11px;
            }
            .pg-mb-go span {
                display: none;
            }
            .pg-mb-timer {
                padding: 4px 9px;
                font-size: 10px;
            }
            .pg-mb-timer-num {
                font-size: 12px;
            }
            .pg-mb-embed-blocked h3 {
                font-size: 16px;
            }
            .pg-mb-embed-blocked p {
                font-size: 12px;
            }
            .pg-mb-embed-icon {
                width: 68px;
                height: 68px;
                font-size: 28px;
            }
            .pg-mb-feedback {
                left: 12px;
                right: 12px;
                bottom: 12px;
                max-width: none;
                padding: 12px;
            }
        }
    </style>
</head>

<body>

<div class="pg-layout">

    <?php require_once 'includes/sidebar.php'; ?>

    <main class="pg-main">

        <?php require_once 'includes/topbar.php'; ?>

        <div class="pg-content">

            <div class="pg-section-head" style="margin-bottom:14px;">
                <div>
                    <h2>Mini Browser</h2>
                    <p>Preview links safely inside PhishGuard.</p>
                </div>
                <a href="<?= htmlspecialchars($returnTo) ?>" class="pg-btn" style="text-decoration:none;">
                    <i class="fa-solid fa-arrow-left"></i>
                    Back to Scanner
                </a>
            </div>

            <div class="pg-mb-wrap">

                <div class="pg-mb-bar">

                    <button type="button" class="pg-mb-navbtn" id="mbBack" title="Back">
                        <i class="fa-solid fa-arrow-left"></i>
                    </button>

                    <button type="button" class="pg-mb-navbtn" id="mbForward" title="Forward">
                        <i class="fa-solid fa-arrow-right"></i>
                    </button>

                    <button type="button" class="pg-mb-navbtn" id="mbReload" title="Reload">
                        <i class="fa-solid fa-rotate-right"></i>
                    </button>

                    <div class="pg-mb-url" id="mbUrlWrap">
                        <i class="fa-solid fa-lock pg-mb-lock" id="mbLock"></i>
                        <input
                            type="text"
                            id="mbUrl"
                            placeholder="Type a URL and press Go"
                            autocomplete="off"
                            spellcheck="false"
                            value="<?= htmlspecialchars($initialUrl) ?>"
                        >
                        <div class="pg-mb-suggestions" id="mbSuggestions"></div>
                    </div>

                    <div class="pg-mb-timer" id="mbTimer">
                        <i class="fa-solid fa-hourglass-half"></i>
                        <span>Closing in</span>
                        <span class="pg-mb-timer-num" id="mbTimerNum">20</span>
                        <span>s</span>
                    </div>

                    <button type="button" class="pg-mb-go" id="mbGo">
                        <i class="fa-solid fa-arrow-right"></i>
                        <span>Go</span>
                    </button>

                </div>

                <div class="pg-mb-banner" id="mbBanner">
                    <i class="fa-solid fa-circle-info"></i>
                    <div>
                        <strong id="mbBannerTitle"></strong>
                        <span id="mbBannerText"></span>
                    </div>
                </div>

                <div class="pg-mb-frame-area">

                    <div class="pg-mb-frame-loading" id="mbLoading">
                        <div class="pg-mb-spinner"></div>
                        <span>Loading preview...</span>
                    </div>

                    <div class="pg-mb-embed-blocked" id="mbEmbedBlocked">
                        <div class="pg-mb-embed-icon">
                            <i class="fa-solid fa-shield-halved"></i>
                        </div>
                        <h3>Preview unavailable for this site</h3>
                        <p>
                            This website blocks embedding inside other pages for security reasons
                            (X-Frame-Options or Content-Security-Policy). The scan result is still valid —
                            you can safely open the site in a new tab if you wish to view it.
                        </p>
                        <div class="pg-mb-embed-actions">
                            <a
                                href="#"
                                id="mbOpenNewTab"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="pg-mb-embed-btn primary"
                            >
                                <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                Open in New Tab
                            </a>
                            <button
                                type="button"
                                class="pg-mb-embed-btn secondary"
                                id="mbBackFromBlock"
                            >
                                <i class="fa-solid fa-arrow-left"></i>
                                Back to Scanner
                            </button>
                        </div>
                    </div>

                    <div class="pg-mb-blocked" id="mbBlocked">
                        <div class="pg-mb-blocked-icon">
                            <i class="fa-solid fa-shield-halved"></i>
                        </div>
                        <h3>Ready to browse safely</h3>
                        <p>
                            Type a URL in the bar above. PhishGuard will check it against
                            the blacklist first. Dangerous sites are blocked automatically.
                        </p>
                        <div class="pg-mb-blocked-reason" id="mbBlockedReason" style="display:none;"></div>
                    </div>

                    <iframe
                        id="mbFrame"
                        class="pg-mb-frame"
                        sandbox="allow-same-origin allow-scripts allow-popups allow-forms"
                        referrerpolicy="no-referrer"
                        style="display:none;"
                    ></iframe>

                    <div class="pg-mb-feedback" id="mbFeedback" style="display:none;">
                        <div class="pg-mb-feedback-inner">
                            <div class="pg-mb-feedback-title">
                                <i class="fa-solid fa-circle-question"></i>
                                Was this result accurate?
                            </div>
                            <div class="pg-mb-feedback-buttons">
                                <button type="button" class="pg-mb-feedback-btn safe" id="fbMarkSafe">
                                    <i class="fa-solid fa-check"></i>
                                    Mark as Safe
                                </button>
                                <button type="button" class="pg-mb-feedback-btn danger" id="fbMarkDangerous">
                                    <i class="fa-solid fa-triangle-exclamation"></i>
                                    Still Dangerous
                                </button>
                            </div>
                            <textarea
                                id="fbNote"
                                class="pg-mb-feedback-note"
                                placeholder="Optional: Tell us why (helps us improve detection)"
                                maxlength="1000"
                            ></textarea>
                            <div class="pg-mb-feedback-actions">
                                <button type="button" class="pg-mb-feedback-submit" id="fbSubmit">
                                    <i class="fa-solid fa-paper-plane"></i>
                                    Submit
                                </button>
                                <button type="button" class="pg-mb-feedback-cancel" id="fbCancel">
                                    Cancel
                                </button>
                            </div>
                            <div class="pg-mb-feedback-thanks" id="fbThanks" style="display:none;">
                                <i class="fa-solid fa-circle-check"></i>
                                <span>Thank you for your feedback!</span>
                            </div>
                        </div>
                    </div>

                </div>

            </div>

        </div>

    </main>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const input            = document.getElementById('mbUrl');
    const frame            = document.getElementById('mbFrame');
    const blocked          = document.getElementById('mbBlocked');
    const blockedReason    = document.getElementById('mbBlockedReason');
    const embedBlocked     = document.getElementById('mbEmbedBlocked');
    const openNewTabBtn    = document.getElementById('mbOpenNewTab');
    const backFromBlockBtn = document.getElementById('mbBackFromBlock');
    const loading          = document.getElementById('mbLoading');
    const suggestions      = document.getElementById('mbSuggestions');
    const backBtn          = document.getElementById('mbBack');
    const fwdBtn           = document.getElementById('mbForward');
    const reloadBtn        = document.getElementById('mbReload');
    const urlWrap          = document.getElementById('mbUrlWrap');
    const lockIcon         = document.getElementById('mbLock');
    const timerEl          = document.getElementById('mbTimer');
    const timerNum         = document.getElementById('mbTimerNum');
    const banner           = document.getElementById('mbBanner');
    const bannerTitle      = document.getElementById('mbBannerTitle');
    const bannerText       = document.getElementById('mbBannerText');

    const feedbackBox      = document.getElementById('mbFeedback');
    const fbMarkSafe       = document.getElementById('fbMarkSafe');
    const fbMarkDangerous  = document.getElementById('fbMarkDangerous');
    const fbNote           = document.getElementById('fbNote');
    const fbSubmit         = document.getElementById('fbSubmit');
    const fbCancel         = document.getElementById('fbCancel');
    const fbThanks         = document.getElementById('fbThanks');

    const returnTo    = <?= json_encode($returnTo) ?>;
    const initialUrl  = <?= json_encode($initialUrl) ?>;
    const scanResult  = <?= json_encode($scanResult) ?>;
    const scanExplain = <?= json_encode($scanExplain) ?>;

    let typingTimer       = null;
    let countdownInterval = null;
    let loadTimeout       = null;
    let currentUrl        = '';
    let feedbackShown     = false;
    let selectedVerdict   = null;

    function normalizeUrl(value) {
        value = value.trim();
        if (value === '') return '';
        if (!/^https?:\/\//i.test(value)) {
            value = 'https://' + value;
        }
        return value;
    }

    function setLockState(state) {
        urlWrap.classList.remove('warn', 'danger');
        lockIcon.classList.remove('fa-lock', 'fa-triangle-exclamation');

        if (state === 'danger') {
            urlWrap.classList.add('danger');
            lockIcon.classList.add('fa-triangle-exclamation');
        } else if (state === 'warn') {
            urlWrap.classList.add('warn');
            lockIcon.classList.add('fa-triangle-exclamation');
        } else {
            lockIcon.classList.add('fa-lock');
        }
    }

    function showBanner(level, title, text) {
        banner.classList.remove('show', 'suspicious', 'danger', 'safe');

        if (!level) return;

        banner.classList.add('show');

        if (level === 'danger') {
            banner.classList.add('danger');
        } else if (level === 'suspicious') {
            banner.classList.add('suspicious');
        } else if (level === 'safe') {
            banner.classList.add('safe');
        }

        const icon = banner.querySelector('i');

        if (level === 'danger') {
            icon.className = 'fa-solid fa-circle-exclamation';
        } else if (level === 'suspicious') {
            icon.className = 'fa-solid fa-triangle-exclamation';
        } else {
            icon.className = 'fa-solid fa-circle-check';
        }

        bannerTitle.textContent = title || '';
        bannerText.textContent  = text ? ' ' + text : '';
    }

    function startTimer(seconds, level) {
        if (countdownInterval) {
            clearInterval(countdownInterval);
            countdownInterval = null;
        }

        if (!seconds || seconds <= 0) {
            timerEl.classList.remove('show', 'danger');
            return;
        }

        timerEl.classList.add('show');
        timerEl.classList.toggle('danger', level === 'danger');

        let remaining = seconds;
        timerNum.textContent = remaining;

        countdownInterval = setInterval(function () {
            remaining--;

            if (remaining <= 0) {
                clearInterval(countdownInterval);
                countdownInterval = null;
                window.location.href = returnTo;
                return;
            }

            timerNum.textContent = remaining;
        }, 1000);
    }

    function clearLoadTimeout() {
        if (loadTimeout) {
            clearTimeout(loadTimeout);
            loadTimeout = null;
        }
    }

    function showBlocked(title, message, reason) {
        frame.style.display = 'none';
        frame.removeAttribute('src');
        loading.classList.remove('show');
        embedBlocked.classList.remove('show');
        blocked.classList.add('show');

        blocked.querySelector('h3').textContent = title;
        blocked.querySelector('p').textContent  = message;

        const icon = blocked.querySelector('.pg-mb-blocked-icon');
        icon.innerHTML = '<i class="fa-solid fa-ban"></i>';

        if (reason && reason.trim() !== '') {
            blockedReason.style.display = 'block';
            blockedReason.textContent = '⚠ ' + reason;
        } else {
            blockedReason.style.display = 'none';
            blockedReason.textContent = '';
        }
    }

    function showEmbedBlocked(url) {
        frame.style.display = 'none';
        loading.classList.remove('show');
        blocked.classList.remove('show');
        embedBlocked.classList.add('show');

        openNewTabBtn.href = url;
        openNewTabBtn.setAttribute('data-url', url);
    }

    function hideAllOverlays() {
        blocked.classList.remove('show');
        embedBlocked.classList.remove('show');
    }

    function watchIframeLoad(url) {
        clearLoadTimeout();

        loadTimeout = setTimeout(function () {
            if (currentUrl === url) {
                showEmbedBlocked(url);
            }
        }, 9000);
    }

    function showFeedbackBox() {
        if (!feedbackBox || feedbackShown) return;
        feedbackBox.style.display = 'block';
        feedbackShown = true;
    }

    function setSelectedVerdict(verdict) {
        selectedVerdict = verdict;
        if (fbMarkSafe)      fbMarkSafe.classList.toggle('active', verdict === 'SAFE');
        if (fbMarkDangerous) fbMarkDangerous.classList.toggle('active', verdict === 'DANGEROUS');
    }

    function loadUrl(url, result, explanation) {
        if (!url) return;

        input.value = url;
        currentUrl = url;

        result = (result || 'SAFE').toUpperCase();
        explanation = explanation || '';

        hideAllOverlays();
        clearLoadTimeout();

        if (feedbackBox) {
            feedbackBox.style.display = 'none';
            feedbackShown = false;
            selectedVerdict = null;

            if (fbMarkSafe)      fbMarkSafe.classList.remove('active');
            if (fbMarkDangerous) fbMarkDangerous.classList.remove('active');
            if (fbNote)          fbNote.value = '';
            if (fbThanks)        fbThanks.style.display = 'none';
            if (fbSubmit) {
                fbSubmit.style.display = 'inline-flex';
                fbSubmit.disabled = false;
                fbSubmit.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Submit';
            }
            if (fbCancel)        fbCancel.style.display = 'inline-flex';
            if (fbMarkSafe)      fbMarkSafe.style.display = 'inline-flex';
            if (fbMarkDangerous) fbMarkDangerous.style.display = 'inline-flex';
            if (fbNote)          fbNote.style.display = 'block';
        }

        if (result === 'DANGEROUS') {
            setLockState('danger');
            showBanner('danger', 'Dangerous URL — Auto-blocked.', explanation);
            showBlocked(
                'Blocked — This URL is DANGEROUS',
                'PhishGuard automatically blocked this URL for your safety. The preview was not loaded.',
                explanation
            );
            startTimer(20, 'danger');
            setTimeout(showFeedbackBox, 3000);
            return;
        }

        fetch('../api/check_url.php?url=' + encodeURIComponent(url))
            .then(r => r.json())
            .then(data => {

                if (data.blocked) {
                    setLockState('danger');
                    const reason = 'Category: ' + (data.category || 'Unknown') + ' — ' + (data.notes || '');
                    showBanner('danger', 'Dangerous URL — Auto-blocked.', reason);
                    showBlocked(
                        'Blocked — This URL is on the PhishGuard blacklist',
                        'PhishGuard automatically blocked this URL for your safety. The preview was not loaded.',
                        reason
                    );
                    startTimer(20, 'danger');
                    setTimeout(showFeedbackBox, 3000);
                    return;
                }

                if (result === 'SUSPICIOUS') {
                    setLockState('warn');
                    showBanner(
                        'suspicious',
                        'Suspicious URL — ' + (explanation || 'Proceed with caution.'),
                        'The preview below shows what this site contains. Auto-closing in 20 seconds.'
                    );
                    startTimer(20, 'suspicious');
                    setTimeout(showFeedbackBox, 3000);
                } else {
                    setLockState('safe');
                    showBanner('safe', 'Safe URL — No threats found.', 'Preview loaded below.');
                }

                blocked.classList.remove('show');
                loading.classList.add('show');
                frame.style.display = 'block';
                frame.src = url;

                watchIframeLoad(url);
            })
            .catch(() => {
                if (result === 'SUSPICIOUS') {
                    setLockState('warn');
                    showBanner(
                        'suspicious',
                        'Suspicious URL — ' + (explanation || 'Proceed with caution.'),
                        'The preview below shows what this site contains. Auto-closing in 20 seconds.'
                    );
                    startTimer(20, 'suspicious');
                    setTimeout(showFeedbackBox, 3000);
                } else {
                    setLockState('safe');
                    showBanner('safe', 'Safe URL — No threats found.', 'Preview loaded below.');
                }

                blocked.classList.remove('show');
                loading.classList.add('show');
                frame.style.display = 'block';
                frame.src = url;

                watchIframeLoad(url);
            });
    }

    frame.addEventListener('load', function () {
        try {
            const doc = frame.contentDocument || frame.contentWindow.document;

            if (!doc || doc.body === null) {
                return;
            }

            const bodyText = (doc.body.innerText || '').trim();

            if (bodyText.indexOf('took too long to respond') !== -1 ||
                bodyText.indexOf('ERR_') !== -1 ||
                bodyText.indexOf('This site can') !== -1 ||
                bodyText.indexOf('server IP address could not be found') !== -1) {
                return;
            }

            clearLoadTimeout();
            loading.classList.remove('show');
        } catch (e) {
            clearLoadTimeout();
            loading.classList.remove('show');
        }
    });

    document.getElementById('mbGo').addEventListener('click', function () {
        loadUrl(normalizeUrl(input.value), 'SAFE', '');
    });

    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            loadUrl(normalizeUrl(input.value), 'SAFE', '');
            suggestions.classList.remove('show');
        }
    });

    backBtn.addEventListener('click', function () {
        try { frame.contentWindow.history.back(); } catch (err) {}
    });

    fwdBtn.addEventListener('click', function () {
        try { frame.contentWindow.history.forward(); } catch (err) {}
    });

    reloadBtn.addEventListener('click', function () {
        if (frame.src) {
            loading.classList.add('show');
            embedBlocked.classList.remove('show');
            frame.src = frame.src;
            watchIframeLoad(currentUrl);
        }
    });

    input.addEventListener('input', function () {

        const query = input.value.trim();

        clearTimeout(typingTimer);

        if (query.length < 3) {
            suggestions.classList.remove('show');
            return;
        }

        typingTimer = setTimeout(function () {
            fetch('../api/url_preview.php?q=' + encodeURIComponent(query))
                .then(r => r.json())
                .then(data => {
                    if (!data.success) {
                        suggestions.classList.remove('show');
                        return;
                    }
                    suggestions.innerHTML = data.html;
                    suggestions.classList.add('show');
                })
                .catch(() => {
                    suggestions.classList.remove('show');
                });
        }, 350);
    });

    document.addEventListener('click', function (e) {
        if (!suggestions.contains(e.target) && e.target !== input) {
            suggestions.classList.remove('show');
        }
    });

    suggestions.addEventListener('click', function (e) {
        const item = e.target.closest('.pg-mb-item');
        if (!item) return;

        const url = item.dataset.url;

        input.value = url;
        suggestions.classList.remove('show');

        loadUrl(url, 'SAFE', '');
    });

    if (openNewTabBtn) {
        openNewTabBtn.addEventListener('click', function (e) {
            const url = openNewTabBtn.getAttribute('data-url') || openNewTabBtn.href;
            if (!url || url === '#') {
                e.preventDefault();
            }
        });
    }

    if (backFromBlockBtn) {
        backFromBlockBtn.addEventListener('click', function () {
            window.location.href = returnTo;
        });
    }

    if (fbMarkSafe) {
        fbMarkSafe.addEventListener('click', function () {
            setSelectedVerdict('SAFE');
        });
    }

    if (fbMarkDangerous) {
        fbMarkDangerous.addEventListener('click', function () {
            setSelectedVerdict('DANGEROUS');
        });
    }

    if (fbCancel) {
        fbCancel.addEventListener('click', function () {
            if (feedbackBox) feedbackBox.style.display = 'none';
        });
    }

    if (fbSubmit) {
        fbSubmit.addEventListener('click', function () {

            if (!selectedVerdict) {
                alert('Please choose "Mark as Safe" or "Still Dangerous" first.');
                return;
            }

            fbSubmit.disabled = true;
            fbSubmit.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Sending...';

            const formData = new FormData();
            formData.append('url', currentUrl);
            formData.append('detected_result', scanResult);
            formData.append('detected_score', '0');
            formData.append('user_verdict', selectedVerdict);
            formData.append('user_note', fbNote ? fbNote.value : '');

            fetch('../api/feedback.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        fbThanks.style.display = 'flex';
                        fbSubmit.style.display = 'none';
                        fbCancel.style.display = 'none';
                        fbMarkSafe.style.display = 'none';
                        fbMarkDangerous.style.display = 'none';
                        if (fbNote) fbNote.style.display = 'none';
                    } else {
                        alert('Error: ' + (data.error || 'Could not submit feedback.'));
                        fbSubmit.disabled = false;
                        fbSubmit.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Submit';
                    }
                })
                .catch(() => {
                    alert('Network error. Please try again.');
                    fbSubmit.disabled = false;
                    fbSubmit.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Submit';
                });
        });
    }

    if (initialUrl && initialUrl.trim() !== '') {
        loadUrl(normalizeUrl(initialUrl), scanResult, scanExplain);
    }

});
</script>

</body>
</html>