const STORAGE_KEYS = {
  THEME: 'theme',
  FILTER: 'dashboard_filter',
  SIDEBAR_STATE: 'sidebar_state'
};

// Chart instances
let visitorsChart, sourcesChart;

// Initialize app on page load
document.addEventListener('DOMContentLoaded', function() {
  initializeTheme();
  initializeSidebar();
  initializeFilter();
  loadChartsWithSavedFilter(); // Load charts manually
});

// Initialize charts AFTER HTMX loads the data
document.body.addEventListener('htmx:afterSwap', function(event) {
  // Check if this is the chart data being loaded
  if (event.detail.target.querySelector('#chartData')) {
    initializeCharts();
  }
});

// Theme Management
function initializeTheme() {
  const savedTheme = localStorage.getItem(STORAGE_KEYS.THEME) || 'dark';
  document.body.setAttribute('data-theme', savedTheme);
  updateThemeIcon(savedTheme);
}

function toggleTheme() {
  const body = document.body;
  const currentTheme = body.getAttribute('data-theme');
  const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
  
  body.setAttribute('data-theme', newTheme);
  localStorage.setItem(STORAGE_KEYS.THEME, newTheme);
  updateThemeIcon(newTheme);
  
  // Update chart colors
  setTimeout(() => {
    updateChartColors(newTheme);
  }, 100);
}

function updateThemeIcon(theme) {
  const icon = document.getElementById('themeIcon');
  if (icon) {
    icon.className = theme === 'dark' ? 'fas fa-moon' : 'fas fa-sun';
  }
}

// Sidebar Management
function initializeSidebar() {
  const savedState = localStorage.getItem(STORAGE_KEYS.SIDEBAR_STATE);
  if (window.innerWidth > 768 && savedState === 'closed') {
    document.getElementById('sidebar')?.classList.add('closed');
    document.getElementById('mainContent')?.classList.add('expanded');
  }
}

function toggleSidebar() {
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebarOverlay');
  const mainContent = document.getElementById('mainContent');
  
  if (window.innerWidth <= 768) {
    // Mobile behavior
    sidebar?.classList.toggle('open');
    overlay?.classList.toggle('show');
  } else {
    // Desktop behavior
    const isClosed = sidebar?.classList.toggle('closed');
    mainContent?.classList.toggle('expanded');
    localStorage.setItem(STORAGE_KEYS.SIDEBAR_STATE, isClosed ? 'closed' : 'open');
  }
}

function closeSidebar() {
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebarOverlay');
  sidebar?.classList.remove('open');
  overlay?.classList.remove('show');
}

// Filter Management
function initializeFilter() {
  const savedFilter = localStorage.getItem(STORAGE_KEYS.FILTER) || 'today';
  const filterButtons = document.querySelectorAll('.filter-btn');
  
  // Remove all active classes first
  filterButtons.forEach(btn => btn.classList.remove('active'));
  
  // Find and activate the correct button
  filterButtons.forEach(btn => {
    const onclickAttr = btn.getAttribute('onclick');
    if (onclickAttr && onclickAttr.includes(`'${savedFilter}'`)) {
      btn.classList.add('active');
    }
  });
}

function setFilter(btn, period) {
  // Update button states
  document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  
  // Save filter preference
  localStorage.setItem(STORAGE_KEYS.FILTER, period);
  
  console.log('Filter changed to:', period);
  
  // Load chart data with new period
  loadChartData(period);
}

function loadChartsWithSavedFilter() {
  const savedFilter = localStorage.getItem(STORAGE_KEYS.FILTER) || 'today';
  console.log('Loading charts with saved filter:', savedFilter);
  loadChartData(savedFilter);
}

function loadChartData(period) {
  const chartContainer = document.getElementById('chartDataContainer');
  if (chartContainer) {
    console.log('Loading chart data for period:', period);
    
    const newUrl = `api/chart-statistics?chart=chart&range=${period}`;
    
    // Use htmx.ajax to load data
    htmx.ajax('GET', newUrl, {
      target: '#chartDataContainer',
      swap: 'innerHTML'
    });
  } else {
    console.error('Chart container not found! Make sure you have: <div id="chartDataContainer" ...>');
  }
}

// View All
function viewAll(type) {
  console.log('View all:', type);
  // Navigate to detailed view
}

// Chart Management
function getThemeColors() {
  const theme = document.body.getAttribute('data-theme');
  return {
    text: theme === 'dark' ? '#E2E8F0' : '#0F172A',
    grid: theme === 'dark' ? 'rgba(100, 116, 139, 0.1)' : 'rgba(100, 116, 139, 0.2)'
  };
}

function initializeCharts() {
  // Destroy existing charts if they exist
  if (visitorsChart) {
    visitorsChart.destroy();
    visitorsChart = null;
  }
  if (sourcesChart) {
    sourcesChart.destroy();
    sourcesChart = null;
  }
  
  const themeColors = getThemeColors();
  
  // Get chart data from hidden input
  const el = document.getElementById('chartData');
  const chartData = el ? JSON.parse(el.value) : { 
    line: [], 
    lineLabels: [],
    pie: [], 
    pieLabels: [] 
  };
  
  console.log('Initializing charts with data:', chartData);
  
  // Visitors Chart
  const visitorsCtx = document.getElementById('visitorsChart')?.getContext('2d');
  if (visitorsCtx && chartData.line.length > 0) {
    // Use lineLabels from PHP if available, otherwise fallback to generated labels
    let labels = chartData.lineLabels && chartData.lineLabels.length > 0 
      ? chartData.lineLabels 
      : chartData.line.map((_, i) => `Point ${i + 1}`);
    
    visitorsChart = new Chart(visitorsCtx, {
      type: 'line',
      data: {
        labels: labels,
        datasets: [{
          label: 'Visitors',
          data: chartData.line,
          borderColor: '#6366F1',
          backgroundColor: 'rgba(99, 102, 241, 0.1)',
          tension: 0.4,
          fill: true
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
          legend: { display: false }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: { color: themeColors.text },
            grid: { color: themeColors.grid }
          },
          x: {
            ticks: { color: themeColors.text },
            grid: { color: themeColors.grid }
          }
        }
      }
    });
  }
  
  // Traffic Sources Chart with Memorable Colors & Thicker Ring
  const sourcesCtx = document.getElementById('sourcesChart')?.getContext('2d');
  if (sourcesCtx && chartData.pie.length > 0) {
    // Use pieLabels from PHP if available, otherwise fallback to default labels
    const pieLabels = chartData.pieLabels && chartData.pieLabels.length > 0
      ? chartData.pieLabels
      : ['Google', 'Direct', 'Social', 'Referral', 'Email'];
    
    sourcesChart = new Chart(sourcesCtx, {
      type: 'doughnut',
      data: {
        labels: pieLabels,
        datasets: [{
          data: chartData.pie,
          backgroundColor: [
            '#FF6B6B',  // Vibrant Coral Red - Google (urgency, attention-grabbing)
            '#4ECDC4',  // Teal - Direct (trust, clarity, memorable)
            '#FFD93D',  // Golden Yellow - Social (optimism, high retention)
            '#A78BFA',  // Soft Purple - Referral (creativity, premium)
            '#F97316'   // Bold Orange - Email (enthusiasm, warmth)
          ],
          borderWidth: 0,
          hoverOffset: 10,
          hoverBorderWidth: 3,
          hoverBorderColor: '#ffffff'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              padding: 15,
              usePointStyle: true,
              color: themeColors.text,
              font: {
                size: 13,
                weight: '500'
              }
            }
          },
          tooltip: {
            backgroundColor: 'rgba(0, 0, 0, 0.8)',
            padding: 12,
            cornerRadius: 8,
            titleFont: {
              size: 14,
              weight: 'bold'
            },
            bodyFont: {
              size: 13
            }
          }
        },
        cutout: '50%', // Changed from 65% to 50% for a thicker, more prominent ring
        animation: {
          animateRotate: true,
          animateScale: true,
          duration: 1000,
          easing: 'easeInOutQuart'
        }
      }
    });
  }
}

function updateChartColors(theme) {
  const textColor = theme === 'dark' ? '#E2E8F0' : '#0F172A';
  const gridColor = theme === 'dark' ? 'rgba(100, 116, 139, 0.1)' : 'rgba(100, 116, 139, 0.2)';
  
  if (visitorsChart) {
    visitorsChart.options.scales.y.ticks.color = textColor;
    visitorsChart.options.scales.x.ticks.color = textColor;
    visitorsChart.options.scales.y.grid.color = gridColor;
    visitorsChart.options.scales.x.grid.color = gridColor;
    visitorsChart.update();
  }
  
  if (sourcesChart) {
    sourcesChart.options.plugins.legend.labels.color = textColor;
    sourcesChart.update();
  }
}
