<?php 
session_start();
include "includes/functions.php";

if(isset($_GET["track_id"])) {
	$track_id = clean($_GET["track_id"]);
 
 $checkTrackId = fetchOne("SELECT website_id FROM website WHERE track_id = ?", [$track_id]);
 
 if($checkTrackId){
    $_SESSION["website_id"] = $checkTrackId["website_id"];
} else {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Error</title>
        <style>
            body {
                margin: 0;
                padding: 0;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
            }
            .error-container {
                background: white;
                padding: 40px 50px;
                border-radius: 12px;
                box-shadow: 0 10px 40px rgba(0,0,0,0.2);
                text-align: center;
                max-width: 400px;
            }
            .error-icon {
                font-size: 48px;
                color: #ef4444;
                margin-bottom: 20px;
            }
            h1 {
                color: #1f2937;
                font-size: 24px;
                margin: 0 0 10px 0;
            }
            p {
                color: #6b7280;
                font-size: 16px;
                margin: 0;
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-icon">⚠️</div>
            <h1>Track ID Not Recognized</h1>
            <p>The tracking ID you provided could not be found in our system.</p>
        </div>
    </body>
    </html>
    <?php
    exit;
}
} else {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Error</title>
        <style>
            body {
                margin: 0;
                padding: 0;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
            }
            .error-container {
                background: white;
                padding: 40px 50px;
                border-radius: 12px;
                box-shadow: 0 10px 40px rgba(0,0,0,0.2);
                text-align: center;
                max-width: 400px;
            }
            .error-icon {
                font-size: 48px;
                color: #ef4444;
                margin-bottom: 20px;
            }
            h1 {
                color: #1f2937;
                font-size: 24px;
                margin: 0 0 10px 0;
            }
            p {
                color: #6b7280;
                font-size: 16px;
                margin: 0;
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-icon">❌</div>
            <h1>Track ID Not Found</h1>
            <p>No tracking ID was provided in the request.</p>
        </div>
    </body>
    </html>
    <?php
    exit;
}
if(isset($_GET["theme"])) {
$theme = clean($_GET["theme"]);
}else{
$theme = "blue";
}
include "embed-header.php";
?>
<body data-theme="dark">
    
    <div class="dashboard-container">
  
     <div class="w3-top" style="display: flex; justify-content: flex-end;">
  <button class="theme-toggle" onclick="toggleTheme()" aria-label="Toggle theme">
    <i id="themeIcon" class="fas fa-moon"></i>
  </button>
</div>
     
      <!-- Main Content -->
      <main class="main-content" id="mainContent">
    
        
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
    
    // Handle errors gracefully abeg i no want headache
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