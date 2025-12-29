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
#spinner-country {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  color: #6366F1;
  font-size: 32px;
  z-index: 10;
}


.country-flag {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 24px;
}

.country-flag-img {
    display: inline-block;
    border-radius: 2px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.1);
}

.country-flag-emoji {
    font-size: 16px;
    line-height: 1;
}


</style>


<div class="data-card">
            <div class="data-header">
              <h3 class="data-title">Country Tier</h3>
               <button 
      class="view-all-btn"
      hx-get="api/countries?type=countries-all"
      hx-target="#modal-content"
      data-modal-title="	Country Breakdown">
      View All <i class="fas fa-chevron-right"></i>
    </button>
            </div>
            <div class="data-list" 
    				 id="countries-list"
     			 hx-get="api/countries?type=countries&limit=5"
     			 hx-trigger="load"
     			 hx-indicator="#spinner-country"
     			 hx-swap="innerHTML">
     			 
              <span id="spinner-country" class="htmx-indicator">
      <i class="fas fa-spinner fa-spin"></i>
    </span>
            </div>
          </div>