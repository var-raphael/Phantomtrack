<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Documentation - Phantom Track</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/font-awesome/icons/css/all.min.css">
    <style>
        .docs-container {
            display: flex;
            min-height: 100vh;
            padding-top: 70px;
        }

        .sidebar {
            width: 280px;
            background: var(--card);
            backdrop-filter: blur(10px);
            border-right: 1px solid var(--border);
            position: fixed;
            left: 0;
            top: 70px;
            bottom: 0;
            overflow-y: auto;
            padding: 24px;
            transition: transform 0.3s ease;
        }

        .sidebar-toggle {
            display: none;
            position: fixed;
            left: 20px;
            bottom: 20px;
            z-index: 999;
            background: linear-gradient(135deg, var(--accent1), var(--accent2));
            color: #FFFFFF;
            border: none;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            cursor: pointer;
            box-shadow: 0 4px 20px rgba(99, 102, 241, 0.4);
        }

        .nav-section {
            margin-bottom: 24px;
        }

        .nav-title {
            color: var(--text);
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
            opacity: 0.6;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            color: var(--text);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.2s ease;
            margin-bottom: 4px;
            font-size: 14px;
        }

        .nav-item:hover {
            background: var(--hover);
            color: var(--accent1);
        }

        .nav-item.active {
            background: linear-gradient(135deg, var(--accent1), var(--accent2));
            color: #FFFFFF;
        }

        .nav-item i {
            font-size: 16px;
            width: 20px;
        }

        .docs-content {
            flex: 1;
            margin-left: 280px;
            padding: 40px;
            max-width: 900px;
        }

        .docs-header {
            margin-bottom: 40px;
        }

        .docs-header h1 {
            font-size: 48px;
            margin-bottom: 16px;
        }

        .docs-header p {
            font-size: 18px;
            color: var(--text);
            opacity: 0.8;
            line-height: 1.6;
        }

        .search-box {
            width: 100%;
            background: var(--card);
            border: 2px solid var(--border);
            color: var(--text);
            padding: 16px 20px 16px 52px;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .search-box:focus {
            outline: none;
            border-color: var(--accent1);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }

        .search-wrapper {
            position: relative;
            margin-bottom: 40px;
            display: flex;
            align-items: center;
        }

        .search-icon {
            position: absolute;
            left: 20px;
            color: var(--text);
            opacity: 0.5;
            font-size: 18px;
            pointer-events: none;
        }

        .quick-start-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .quick-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 24px;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .quick-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(99, 102, 241, 0.2);
            border-color: var(--accent1);
        }

        .quick-card-icon {
            font-size: 32px;
            background: linear-gradient(135deg, var(--accent1), var(--accent2));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 12px;
        }

        .quick-card h3 {
            color: var(--text);
            font-size: 18px;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .quick-card p {
            color: var(--text);
            opacity: 0.7;
            font-size: 14px;
            line-height: 1.5;
        }

        .section-title {
            color: var(--text);
            font-size: 24px;
            font-weight: 600;
            margin: 40px 0 20px 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .section-title i {
            color: var(--accent1);
        }

        @media (max-width: 968px) {
            .sidebar {
                transform: translateX(-100%);
                z-index: 1000;
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .sidebar-toggle {
                display: block;
            }

            .docs-content {
                margin-left: 0;
                padding: 24px;
            }

            .docs-header h1 {
                font-size: 36px;
            }
        }
    </style>
</head>
<body data-theme="dark">

    <!-- Header -->
    <div class="header">
        <div class="header-content">
            <h1><i class="fas fa-book"></i> Documentation</h1>
            <button class="theme-toggle" onclick="toggleTheme()" aria-label="Toggle theme">
                <span id="themeIcon"></span>
            </button>
        </div>
    </div>

    <!-- Sidebar Toggle Button (Mobile) -->
    <button class="sidebar-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <div class="docs-container">
        <!-- Sidebar Navigation -->
        <nav class="sidebar" id="sidebar">
            <div class="nav-section">
                <div class="nav-title">Getting Started</div>
                <a href="#introduction" class="nav-item active">
                    <i class="fas fa-home"></i>
                    <span>Introduction</span>
                </a>
                <a href="#quickstart" class="nav-item">
                    <i class="fas fa-rocket"></i>
                    <span>Quick Start</span>
                </a>
                <a href="#authentication" class="nav-item">
                    <i class="fas fa-key"></i>
                    <span>Authentication</span>
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-title">API Reference</div>
                <a href="#endpoints" class="nav-item">
                    <i class="fas fa-plug"></i>
                    <span>Endpoints</span>
                </a>
                <a href="#requests" class="nav-item">
                    <i class="fas fa-paper-plane"></i>
                    <span>Making Requests</span>
                </a>
                <a href="#responses" class="nav-item">
                    <i class="fas fa-reply"></i>
                    <span>Responses</span>
                </a>
                <a href="#errors" class="nav-item">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>Error Handling</span>
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-title">Guides</div>
                <a href="#webhooks" class="nav-item">
                    <i class="fas fa-webhook"></i>
                    <span>Webhooks</span>
                </a>
                <a href="#rate-limits" class="nav-item">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Rate Limits</span>
                </a>
                <a href="#best-practices" class="nav-item">
                    <i class="fas fa-star"></i>
                    <span>Best Practices</span>
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-title">Resources</div>
                <a href="#sdks" class="nav-item">
                    <i class="fas fa-code"></i>
                    <span>SDKs & Libraries</span>
                </a>
                <a href="#changelog" class="nav-item">
                    <i class="fas fa-history"></i>
                    <span>Changelog</span>
                </a>
                <a href="#support" class="nav-item">
                    <i class="fas fa-question-circle"></i>
                    <span>Support</span>
                </a>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="docs-content">
            <div class="docs-header">
                <h1>Welcome to Phantom Track</h1>
                <p>Everything you need to integrate and use Phantom Track API. Get started with our comprehensive guides, API references, and code examples.</p>
            </div>

            <div class="search-wrapper">
                <i class="fas fa-search search-icon"></i>
                <input type="text" class="search-box" placeholder="Search documentation...">
            </div>

            <h2 class="section-title"><i class="fas fa-bolt"></i> Quick Start</h2>
            <div class="quick-start-grid">
                <div class="quick-card">
                    <div class="quick-card-icon">
                        <i class="fas fa-play-circle"></i>
                    </div>
                    <h3>5-Minute Tutorial</h3>
                    <p>Get up and running with your first API call in just 5 minutes</p>
                </div>

                <div class="quick-card">
                    <div class="quick-card-icon">
                        <i class="fas fa-key"></i>
                    </div>
                    <h3>API Keys</h3>
                    <p>Learn how to generate and manage your API keys securely</p>
                </div>

                <div class="quick-card">
                    <div class="quick-card-icon">
                        <i class="fas fa-code"></i>
                    </div>
                    <h3>Code Examples</h3>
                    <p>Browse through code samples in multiple programming languages</p>
                </div>

                <div class="quick-card">
                    <div class="quick-card-icon">
                        <i class="fas fa-book-open"></i>
                    </div>
                    <h3>API Reference</h3>
                    <p>Complete reference for all available endpoints and parameters</p>
                </div>
            </div>

            <h2 class="section-title"><i class="fas fa-list"></i> Popular Topics</h2>
            <div class="card-solid" style="padding: 0; overflow: hidden;">
                <a href="#authentication" class="nav-item" style="padding: 16px 24px; margin: 0; border-radius: 0; border-bottom: 1px solid var(--border);">
                    <i class="fas fa-shield-alt"></i>
                    <span>Authentication & Security</span>
                </a>
                <a href="#webhooks" class="nav-item" style="padding: 16px 24px; margin: 0; border-radius: 0; border-bottom: 1px solid var(--border);">
                    <i class="fas fa-bell"></i>
                    <span>Setting up Webhooks</span>
                </a>
                <a href="#rate-limits" class="nav-item" style="padding: 16px 24px; margin: 0; border-radius: 0; border-bottom: 1px solid var(--border);">
                    <i class="fas fa-clock"></i>
                    <span>Understanding Rate Limits</span>
                </a>
                <a href="#errors" class="nav-item" style="padding: 16px 24px; margin: 0; border-radius: 0;">
                    <i class="fas fa-bug"></i>
                    <span>Error Codes & Troubleshooting</span>
                </a>
            </div>

            <h2 class="section-title"><i class="fas fa-headset"></i> Need Help?</h2>
            <div class="card-solid">
                <p style="color: var(--text); opacity: 0.8; line-height: 1.8; margin-bottom: 16px;">
                    Can't find what you're looking for? Our support team is here to help you succeed.
                </p>
                <div class="btn-group">
                    <button class="btn" onclick="window.open('mailto:support@phantomtrack.com', '_blank')">
                        <i class="fas fa-envelope"></i> Contact Support
                    </button>
                    <button class="btn-outline" onclick="alert('Opening community forum...')">
                        <i class="fas fa-comments"></i> Community Forum
                    </button>
                </div>
            </div>
        </main>
    </div>

    <script src="assets/js/toogle.js"></script>
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.querySelector('.sidebar-toggle');
            
            if (window.innerWidth <= 968) {
                if (!sidebar.contains(event.target) && !toggle.contains(event.target)) {
                    sidebar.classList.remove('active');
                }
            }
        });

        // Handle nav item clicks
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', function(e) {
                document.querySelectorAll('.nav-item').forEach(i => i.classList.remove('active'));
                this.classList.add('active');
                
                // Close sidebar on mobile after click
                if (window.innerWidth <= 968) {
                    document.getElementById('sidebar').classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>