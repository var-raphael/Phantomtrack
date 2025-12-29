<style>
.htmx-indicator {
  display: none;
}

/* Show indicator when HTMX is making a request */
.htmx-request .htmx-indicator,
.htmx-request.htmx-indicator {
  display: flex;
  justify-content: center;
  align-items: center;
}

/* Spinner positioning and styling */
#spinner-charts {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  color: #6366F1;
  font-size: 32px;
  z-index: 10;
}

/* Fade out the canvas when loading */
.chart-card.htmx-request canvas {
  opacity: 0.3;
}

.stat-value {
  position: relative;
}
</style>
<div class="filter-section">
          <button class="filter-btn active" onclick="setFilter(this, 'today')">Today</button>
<button class="filter-btn" onclick="setFilter(this, 'week')">Last 7 Days</button>
<button class="filter-btn" onclick="setFilter(this, 'month')">Last 30 Days</button>
<button class="filter-btn" onclick="setFilter(this, '90days')">Last 90 Days</button>
        </div>
        
        <!-- Charts -->
        <div 
  id="chartDataContainer"
  hx-get="api/chart-statistics?chart=chart&range=today"
  hx-swap="innerHTML"
  hx-indicator="#spinner-charts"
></div>

<div class="charts-grid">
  <div class="chart-card" id="visitors-card">
    <h3 class="chart-header">Visitors Over Time</h3>
    <span id="spinner-charts" class="htmx-indicator">
      <i class="fas fa-spinner fa-spin"></i>
    </span>
    <canvas id="visitorsChart"></canvas>
  </div>
  
  <div class="chart-card">
    <h3 class="chart-header">Traffic Sources</h3>
    <span id="spinner-charts" class="htmx-indicator">
      <i class="fas fa-spinner fa-spin"></i>
    </span>
    <canvas id="sourcesChart"></canvas>
  </div>
</div>