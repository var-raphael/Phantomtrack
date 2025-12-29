<style>

/* Custom Events Styles */
.custom-events-modal {
    width: 100%;
    max-height: 70vh;
    overflow-y: auto;
}

.modal-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    border-bottom: 2px solid #e5e7eb;
}

.tab-btn {
    padding: 10px 20px;
    background: none;
    border: none;
    border-bottom: 2px solid transparent;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    color: #6b7280;
    transition: all 0.3s;
    margin-bottom: -2px;
}

.tab-btn.active {
    color: #6366f1;
    border-bottom-color: #6366f1;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.event-item {
    padding: 16px;
    background: #f9fafb;
    border-radius: 8px;
    margin-bottom: 12px;
    transition: all 0.3s;
}

.event-item:hover {
    background: #f3f4f6;
    transform: translateX(4px);
}

.event-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.event-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.event-icon {
    color: #6366f1;
    font-size: 20px;
}

.event-name {
    display: block;
    font-weight: 600;
    font-size: 14px;
    color: #111827;
}

.event-meta {
    display: block;
    font-size: 12px;
    color: #6b7280;
    margin-top: 2px;
}

.event-stats {
    display: flex;
    gap: 24px;
}

.event-stats .stat {
    text-align: center;
}

.event-stats .stat-value {
    display: block;
    font-size: 18px;
    font-weight: 700;
    color: #111827;
}

.event-stats .stat-label {
    display: block;
    font-size: 11px;
    color: #6b7280;
    text-transform: uppercase;
}

.timeline-date {
    font-weight: 600;
    font-size: 13px;
    color: #6b7280;
    margin: 20px 0 10px;
    padding-bottom: 8px;
    border-bottom: 1px solid #e5e7eb;
}

.timeline-event {
    display: flex;
    justify-content: space-between;
    padding: 10px 16px;
    background: #f9fafb;
    border-radius: 6px;
    margin-bottom: 8px;
}

.timeline-event .event-name {
    font-size: 13px;
    color: #374151;
}

.timeline-event .event-count {
    font-size: 13px;
    font-weight: 600;
    color: #6366f1;
}

.event-details-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 0;
    border-bottom: 2px solid #e5e7eb;
    margin-bottom: 16px;
}

.event-details-header h3 {
    margin: 0;
    font-size: 18px;
    color: #111827;
}

.event-details-header button {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #6b7280;
}

.detail-item {
    padding: 12px;
    background: #f9fafb;
    border-radius: 6px;
    margin-bottom: 10px;
}

.detail-row {
    display: flex;
    gap: 8px;
    margin-bottom: 6px;
}

.detail-label {
    font-size: 12px;
    font-weight: 600;
    color: #6b7280;
    min-width: 60px;
}

.detail-value {
    font-size: 12px;
    color: #374151;
}

.property {
    display: inline-block;
    padding: 4px 8px;
    background: #e0e7ff;
    border-radius: 4px;
    font-size: 11px;
    margin: 4px 4px 4px 0;
}

.detail-meta {
    display: flex;
    gap: 12px;
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px solid #e5e7eb;
}

.detail-meta span {
    font-size: 11px;
    color: #6b7280;
    padding: 2px 8px;
    background: #fff;
    border-radius: 12px;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #6b7280;
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 16px;
    opacity: 0.5;
}

.empty-state p {
    font-size: 16px;
    font-weight: 600;
    margin: 8px 0;
}

.empty-state small {
    font-size: 13px;
    opacity: 0.8;
}

.loading {
    text-align: center;
    padding: 20px;
    color: #6b7280;
}
</style>

<div class="stat-card">
    <span id="spinner-custom-events" class="htmx-indicator">
        <i class="fas fa-spinner fa-spin"></i>
    </span>
    <div 
        hx-get="api/custom-events-card" 
        hx-trigger="load, every 30s" 
        hx-swap="innerHTML swap:0.1s"
        hx-indicator="#spinner-custom-events"
        data-loaded="false"
        hx-on::before-request="if(this.dataset.loaded === 'true') { document.getElementById('spinner-custom-events').style.display = 'none'; }"
        hx-on::after-swap="this.dataset.loaded = 'true';">
        
        <div class="stat-header">
            <span class="stat-label">Custom Events</span>
            <div class="stat-icon icon-purple">
                <i class="fas fa-bolt"></i>
            </div>
        </div>
        
        <div class="stat-value">
            <span>--</span>
        </div>
        
        <div class="stat-subtext">Loading...</div>
    </div>
</div>