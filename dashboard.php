<?php 
session_start();
include "includes/functions.php";

// Cookie config
define('COOKIE_NAME', 'user_secret_key');
define('COOKIE_EXPIRY', 365 * 24 * 60 * 60);

$secretKeyFromURL = $_GET['secretkey'] ?? null;
$secretKeyFromCookie = $_COOKIE[COOKIE_NAME] ?? null;
$secretKeyToValidate = $secretKeyFromURL ?: $secretKeyFromCookie;

$hasSecretKey = false;
$currentSecretKey = null;
$autoOpenModal = false;
$user_id = null;

if ($secretKeyToValidate) {
    $user = fetchOne("SELECT user_id, secret_key FROM users WHERE secret_key = ?", [$secretKeyToValidate]);

    if ($user) {
        // Set sessions
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['secret_key'] = $user['secret_key'];
        
        $user_id = $user['user_id'];
        $hasSecretKey = true;
        $currentSecretKey = $secretKeyToValidate;
        
        // Set cookie if not already set or if it's different
        if (!$secretKeyFromCookie || $secretKeyFromCookie !== $secretKeyToValidate) {
            setcookie(COOKIE_NAME, $secretKeyToValidate, time() + COOKIE_EXPIRY, '/', '', isset($_SERVER['HTTPS']), true);
        }
        
        // Check if website_id is in URL
        if (isset($_GET['website_id'])) {
            $requestedWebsiteId = $_GET['website_id'];
            
            // Validate this website_id belongs to the user
            $websiteCheck = fetchOne(
                "SELECT website_id FROM website WHERE website_id = ? AND user_id = ?", 
                [$requestedWebsiteId, $user_id]
            );
            
            if ($websiteCheck) {
                // Valid website_id - set it in session
                $_SESSION['website_id'] = $requestedWebsiteId;
            } else {
                // Invalid website_id - get their first website and redirect
                $defaultWebsite = fetchOne(
                    "SELECT website_id FROM website WHERE user_id = ? ORDER BY created_at ASC LIMIT 1", 
                    [$user_id]
                );
                
                if ($defaultWebsite) {
                    $_SESSION['website_id'] = $defaultWebsite['website_id'];
                    
                    // Build redirect URL with correct website_id
                    $urlParts = parse_url($_SERVER['REQUEST_URI']);
                    $basePath = $urlParts['path'];
                    $queryParams = [];
                    
                    if (isset($urlParts['query'])) {
                        parse_str($urlParts['query'], $queryParams);
                    }
                    
                    $queryParams['website_id'] = $defaultWebsite['website_id'];
                    unset($queryParams['secretkey']); // Remove secretkey if present
                    
                    header("Location: " . $basePath . '?' . http_build_query($queryParams));
                    exit;
                } else {
                    // User has no websites - let them through to create one
                    $_SESSION['website_id'] = null;
                }
            }
        } else {
            // NO website_id in URL - try to redirect with website_id
            
            // Try to use session website_id first (if valid)
            if (isset($_SESSION['website_id'])) {
                $sessionWebsiteCheck = fetchOne(
                    "SELECT website_id FROM website WHERE website_id = ? AND user_id = ?", 
                    [$_SESSION['website_id'], $user_id]
                );
                
                if ($sessionWebsiteCheck) {
                    // Session website_id is valid - redirect with it
                    $redirectWebsiteId = $_SESSION['website_id'];
                } else {
                    // Session website_id invalid - get default
                    $defaultWebsite = fetchOne(
                        "SELECT website_id FROM website WHERE user_id = ? ORDER BY created_at ASC LIMIT 1", 
                        [$user_id]
                    );
                    $redirectWebsiteId = $defaultWebsite['website_id'] ?? null;
                    $_SESSION['website_id'] = $redirectWebsiteId;
                }
            } else {
                // No session website_id - get default
                $defaultWebsite = fetchOne(
                    "SELECT website_id FROM website WHERE user_id = ? ORDER BY created_at ASC LIMIT 1", 
                    [$user_id]
                );
                $redirectWebsiteId = $defaultWebsite['website_id'] ?? null;
                $_SESSION['website_id'] = $redirectWebsiteId;
            }
            
            // Only redirect if user has websites
            if ($redirectWebsiteId) {
                $urlParts = parse_url($_SERVER['REQUEST_URI']);
                $basePath = $urlParts['path'];
                $queryParams = [];
                
                if (isset($urlParts['query'])) {
                    parse_str($urlParts['query'], $queryParams);
                }
                
                $queryParams['website_id'] = $redirectWebsiteId;
                unset($queryParams['secretkey']); // Remove secretkey if present
                
                header("Location: " . $basePath . '?' . http_build_query($queryParams));
                exit;
            }
            // If $redirectWebsiteId is null, user has no websites - just let them through
        }
        
        // Handle secretkey in URL - clean redirect (ONLY if secretkey is in URL)
        if ($secretKeyFromURL) {
            $urlParts = parse_url($_SERVER['REQUEST_URI']);
            $basePath = $urlParts['path'];
            $queryParams = [];
            
            if (isset($urlParts['query'])) {
                parse_str($urlParts['query'], $queryParams);
            }
            
            unset($queryParams['secretkey']);
            
            // Only add website_id if user has one
            if ($_SESSION['website_id']) {
                $queryParams['website_id'] = $_SESSION['website_id'];
            }
            
            $redirectUrl = $basePath;
            if (!empty($queryParams)) {
                $redirectUrl .= '?' . http_build_query($queryParams);
            }
            
            header("Location: " . $redirectUrl);
            exit;
        }
        
    } else {
        // Invalid secret key
        session_unset();
        setcookie(COOKIE_NAME, '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
        $autoOpenModal = true;
    }
} else {
    // No secret key at all
    session_unset();
    $autoOpenModal = true;
}

$website_id = $_SESSION['website_id'] ?? null;
$url = env('PHANTOMTRACK_URL');
include "header.php";
?>
<body data-theme="dark">
    
    <div class="dashboard-container">
      <!-- Sidebar Overlay -->
      
      
     
     <?php include "nav.php"; ?>
     
     
      <!-- Main Content -->
      <main class="main-content" id="mainContent">
     <?php include "main-content.php"; ?>
        
        <!-- Stats Cards -->
<?php include "stat-cards.php"; ?>
        
        <!-- Filter Buttons & Charts -->
        
        <?php include "charts.php"; ?>
        
        <!-- Data Cards -->
        <?php include "data-cards.php"; ?>
        
          <!-- Top Referrers -->
          
          <?php include "top-referrer.php"; ?>
       
          
          <!-- Country Breakdown -->
         <?php include "country-tier.php"; ?>
          
          <!-- Device Types -->
          <?php require "device-type.php"; ?>
          
          <!-- Browsers -->
          <?php require "browser.php"; ?>
          
            <!-- Ai Review -->
          
          <?php require "ai-review.php"; ?>
          
          <!-- Universal Modal -->
  				
  				<?php require "universal-modal.php"; ?>
  
  
  
  <!--secret-key--modal-->
          <?php include "mod.php"; ?>
        </div>
      </main>
    </div>
    
    <script src="assets/js/chart.min.js"></script>
    <script src="assets/js/script.js"></script>
    
    <script>
    // Open modal IMMEDIATELY when clicked, BEFORE data loads
    document.body.addEventListener('htmx:beforeRequest', function(event) {
      if (event.detail.target.id === 'modal-content') {
        const triggerElement = event.detail.elt;
        const modalTitle = triggerElement.getAttribute('data-modal-title') || 'Details';
        const modalSize = triggerElement.getAttribute('data-modal-size') || 'default';
        
        // Set title
        document.getElementById('modal-title').textContent = modalTitle;
        
        // Set size
        const modalContainer = document.querySelector('.modal-container');
        modalContainer.className = 'modal-container ' + (modalSize !== 'default' ? modalSize : '');
        
        // Show loading spinner in modal
        document.getElementById('modal-content').innerHTML = `
          <div class="spinner-container">
            <i class="fas fa-spinner fa-spin"></i>
            <span>Loading...</span>
          </div>
        `;
        
        // OPEN MODAL IMMEDIATELY
        document.getElementById('universal-modal').classList.add('active');
      }
    });

    function closeModal() {
      document.getElementById('universal-modal').classList.remove('active');
    }

    // Close on Escape key
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') closeModal();
    });
    
    // Handle errors gracefully
    document.body.addEventListener('htmx:responseError', function(event) {
      if (event.detail.target.id === 'modal-content') {
        document.getElementById('modal-content').innerHTML = `
          <div style="color: #dc2626; text-align: center; padding: 40px;">
            <i class="fas fa-exclamation-circle" style="font-size: 48px; margin-bottom: 16px; display: block;"></i>
            <p style="font-size: 18px; font-weight: 600; margin-bottom: 8px;">Error loading data</p>
            <p style="font-size: 14px; color: #6b7280;">Please try again later</p>
          </div>
        `;
      }
    });
  </script>
    
</body>
</html>

