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
#spinner-browser {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  color: #6366F1;
  font-size: 32px;
  z-index: 10;
}


</style>



<div class="data-card">
            <div class="data-header">
              <h3 class="data-title">Browsers</h3>
              <button 
      class="view-all-btn"
      hx-get="api/browsers?type=browsers-detailed&limit=all"
      hx-target="#modal-content"
      hx-swap="innerHTML"
      data-modal-title="Browsers">
      View All <i class="fas fa-chevron-right"></i>
    </button>
            </div>
            <div class="data-list" 
       id="browsers-list"
       hx-get="api/browsers?type=browsers"
       hx-trigger="load"
       hx-indicator="#spinner-browser"
       hx-swap="innerHTML">
       
       
       <span id="spinner-browser" class="htmx-indicator">
      <i class="fas fa-spinner fa-spin"></i>
    </span>
  </div>
          </div>
          