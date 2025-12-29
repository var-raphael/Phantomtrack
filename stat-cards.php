<style>
.fa-spin {
    color: #6366F1;
    font-size: 32px;
}
.htmx-indicator {
    display: none;
}
.htmx-request .htmx-indicator {
    display: inline-block;
}
.htmx-request.htmx-indicator {
    display: inline-block;
}
</style>

<div class="stats-grid">
    <!-- Total Unique Visitors -->
    <div class="stat-card">
        <span id="spinner-visitors" class="htmx-indicator">
            <i class="fas fa-spinner fa-spin"></i>
        </span>
        <div 
            hx-get="api/statistics?type=unique" 
            hx-trigger="load" 
            hx-swap="innerHTML"
            hx-indicator="#spinner-visitors">
            
            <div class="stat-header">
                <span class="stat-label">Total Unique Visitors</span>
                <div class="stat-icon icon-purple">
                    <i class="fas fa-users"></i>
                </div>
            </div>
            
            <div class="stat-value">
                <span>--</span>
            </div>
            
            <div class="stat-subtext">Loading...</div>
        </div>
    </div>

    <!-- Total Page Views -->
    <div class="stat-card">
        <span id="spinner-pageviews" class="htmx-indicator">
            <i class="fas fa-spinner fa-spin"></i>
        </span>
        <div 
            hx-get="api/statistics?type=pageviews" 
            hx-trigger="load, every 30s" 
            hx-swap="innerHTML"
            hx-indicator="#spinner-pageviews">
            
            <div class="stat-header">
                <span class="stat-label">Total Page Views</span>
                <div class="stat-icon icon-cyan">
                    <i class="fas fa-eye"></i>
                </div>
            </div>
            
            <div class="stat-value">
                <span>--</span>
            </div>
            
            <div class="stat-subtext">Loading...</div>
        </div>
    </div>

    <!-- Live Visitors -->
    <div class="stat-card">
        <span id="spinner-live" class="htmx-indicator">
            <i class="fas fa-spinner fa-spin"></i>
        </span>
        <div 
            hx-get="api/statistics?type=live" 
            hx-trigger="load, every 30s"           
            hx-swap="innerHTML"
            hx-indicator="#spinner-live">
            
            <div class="stat-header">
                <span class="stat-label">Live Visitors</span>
                <div class="stat-icon icon-green">
                    <i class="fas fa-circle"></i>
                </div>                      
            </div>
            
            <div class="stat-value">
                <span>--</span>
            </div>
            
            <div class="stat-subtext">Loading...</div>
        </div>
    </div>

    <!-- Monthly Usage -->
    <div class="stat-card">
        <span id="spinner-usage" class="htmx-indicator">
            <i class="fas fa-spinner fa-spin"></i>
        </span>
        <div 
            hx-get="api/statistics?type=usage" 
            hx-trigger="load" 
            hx-swap="innerHTML"
            hx-indicator="#spinner-usage">
            
            <div class="stat-header">
                <span class="stat-label">Monthly Usage</span>
                <div class="stat-icon icon-orange">
                    <i class="fas fa-chart-pie"></i>
                </div>
            </div>
            
            <div class="stat-value">
                <span>--</span>
            </div>
            
            <div class="stat-subtext">Loading...</div>
        </div>
    </div>

    <!-- Average Time on Page -->
    <div class="stat-card">
        <span id="spinner-timeonpage" class="htmx-indicator">
            <i class="fas fa-spinner fa-spin"></i>
        </span>
        <div 
            hx-get="api/statistics?type=time-on-page" 
            hx-trigger="load" 
            hx-swap="innerHTML"
            hx-indicator="#spinner-timeonpage">
            
            <div class="stat-header">
                <span class="stat-label">Average Time on Page</span>
                <div class="stat-icon icon-purple">
                    <i class="fas fa-stopwatch"></i>
                </div>
            </div>
            
            <div class="stat-value">
                <span>--</span>
            </div>
            
            <div class="stat-subtext">Loading...</div>
        </div>
    </div>

    <!-- Custom Event -->
    <?php require "custom-event.php"; ?>

    <!-- Total Time on Site -->
    <div class="stat-card">
        <span id="spinner-timeonsite" class="htmx-indicator">
            <i class="fas fa-spinner fa-spin"></i>
        </span>
        <div 
            hx-get="api/statistics?type=time-on-site" 
            hx-trigger="load" 
            hx-swap="innerHTML"
            hx-indicator="#spinner-timeonsite">
            
            <div class="stat-header">
                <span class="stat-label">Total Time on Site</span>
                <div class="stat-icon icon-cyan">
                    <i class="fas fa-hourglass-half"></i>
                </div>
            </div>
            
            <div class="stat-value">
                <span>--</span>
            </div>
            
            <div class="stat-subtext">Loading...</div>
        </div>
    </div>

    <!-- Average Bounce Rate -->
    <div class="stat-card">
        <span id="spinner-bounce" class="htmx-indicator">
            <i class="fas fa-spinner fa-spin"></i>
        </span>
        <div 
            hx-get="api/statistics?type=bounce" 
            hx-trigger="load" 
            hx-swap="innerHTML"
            hx-indicator="#spinner-bounce">
            
            <div class="stat-header">
                <span class="stat-label">Average Bounce Rate</span>
                <div class="stat-icon icon-orange">
                    <i class="fas fa-sign-out-alt"></i>
                </div>
            </div>
            
            <div class="stat-value">
                <span>--</span>
            </div>
            
            <div class="stat-subtext">Loading...</div>
        </div>
    </div>

    <!-- Average Retention Rate -->
    <div class="stat-card">
        <span id="spinner-retention" class="htmx-indicator">
            <i class="fas fa-spinner fa-spin"></i>
        </span>
        <div 
            hx-get="api/statistics?type=retention" 
            hx-trigger="load" 
            hx-swap="innerHTML"
            hx-indicator="#spinner-retention">
            
            <div class="stat-header">
                <span class="stat-label">Average Retention Rate</span>
                <div class="stat-icon icon-green">
                    <i class="fas fa-redo-alt"></i>
                </div>
            </div>
            
            <div class="stat-value">
                <span>--</span>
            </div>
            
            <div class="stat-subtext">Loading...</div>
        </div>
    </div>
</div>
