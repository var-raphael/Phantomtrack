<?php
session_start();
include "../includes/functions.php";
$website_id = $_SESSION["website_id"] ?? 1;

echo <<<HTML
<div class="custom-events-modal">
    <div class="modal-tabs">
        <button class="tab-btn active" onclick="switchTab('overview')">
            <i class="fas fa-list"></i> Overview
        </button>
        <button class="tab-btn" onclick="switchTab('timeline')">
            <i class="fas fa-clock"></i> Timeline
        </button>
    </div>
    
    <div id="tab-overview" class="tab-content active" 
         hx-get="api/custom-events?type=overview&limit=20" 
         hx-trigger="load"
         hx-swap="innerHTML">
        <div class="loading-spinner">
            <i class="fas fa-spinner fa-spin"></i> Loading events...
        </div>
    </div>
    
    <div id="tab-timeline" class="tab-content" 
         hx-get="api/custom-events?type=timeline" 
         hx-trigger="load"
         hx-swap="innerHTML">
        <div class="loading-spinner">
            <i class="fas fa-spinner fa-spin"></i> Loading timeline...
        </div>
    </div>
    
    <div id="event-details-modal" class="details-container"></div>
</div>

<script>
function switchTab(tab) {
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    
    event.target.closest('.tab-btn').classList.add('active');
    document.getElementById('tab-' + tab).classList.add('active');
}
</script>

<style>
/* ============================================================================
   CUSTOM EVENTS STYLES
   Theme-aware with reduced padding
   ============================================================================ */

.custom-events-modal {
    width: 100%;
    max-height: 70vh;
    overflow-y: auto;
}

/* Modal Tabs */
.modal-tabs {
    display: flex;
    gap: 6px;
    margin-bottom: 16px;
    border-bottom: 2px solid var(--border);
    padding-bottom: 0;
}

.tab-btn {
    padding: 8px 16px;
    background: none;
    border: none;
    border-bottom: 2px solid transparent;
    cursor: pointer;
    font-size: 13px;
    font-weight: 500;
    color: var(--text);
    opacity: 0.6;
    transition: all 0.2s ease;
    margin-bottom: -2px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.tab-btn i {
    font-size: 12px;
}

.tab-btn:hover {
    opacity: 0.8;
}

.tab-btn.active {
    color: var(--accent1);
    opacity: 1;
    border-bottom-color: var(--accent1);
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

/* Event Item */
.event-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 8px;
    margin-bottom: 8px;
    transition: all 0.2s ease;
    cursor: pointer;
}

.event-item:hover {
    background: var(--hover);
    transform: translateX(2px);
}

.event-icon {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #818cf8 0%, #6366f1 100%);
    border-radius: 6px;
    flex-shrink: 0;
}

.event-icon i {
    color: #fff;
    font-size: 16px;
}

.event-info {
    flex: 1;
    min-width: 0;
}

.event-name {
    font-weight: 600;
    font-size: 13px;
    color: var(--text);
    display: block;
    margin-bottom: 2px;
}

.event-time {
    font-size: 11px;
    color: var(--text);
    opacity: 0.6;
}

.event-stats {
    display: flex;
    gap: 20px;
    flex-shrink: 0;
}


/* Timeline Styles */
.timeline-date {
    font-weight: 600;
    font-size: 11px;
    color: var(--text);
    opacity: 0.6;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin: 16px 0 10px;
    padding-bottom: 6px;
    border-bottom: 1px solid var(--border);
}

.timeline-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 12px;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 6px;
    margin-bottom: 6px;
}

.timeline-name {
    font-size: 12px;
    color: var(--text);
    font-weight: 500;
}

.timeline-count {
    font-size: 12px;
    font-weight: 700;
    color: var(--accent1);
    background: var(--hover);
    padding: 4px 10px;
    border-radius: 12px;
}

/* Details Modal */
.details-container {
    margin-top: 16px;
    padding-top: 16px;
    border-top: 2px solid var(--border);
}

.details-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.details-header h3 {
    margin: 0;
    font-size: 16px;
    color: var(--text);
    font-weight: 700;
}

.close-btn {
    background: var(--card);
    border: 1px solid var(--border);
    width: 28px;
    height: 28px;
    border-radius: 6px;
    font-size: 18px;
    cursor: pointer;
    color: var(--text);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.close-btn:hover {
    background: var(--hover);
}

.details-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.detail-card {
    padding: 12px;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 8px;
}

.detail-row {
    display: flex;
    gap: 10px;
    margin-bottom: 6px;
}

.detail-label {
    font-size: 11px;
    font-weight: 600;
    color: var(--text);
    opacity: 0.6;
    min-width: 50px;
}

.detail-value {
    font-size: 11px;
    color: var(--text);
    flex: 1;
    word-break: break-all;
}

.properties-row {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin: 10px 0;
}

.property-tag {
    display: inline-block;
    padding: 5px 10px;
    background: var(--hover);
    color: var(--accent1);
    border: 1px solid var(--border);
    border-radius: 6px;
    font-size: 10px;
    font-weight: 500;
}

.detail-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid var(--border);
}

.meta-badge {
    font-size: 10px;
    color: var(--text);
    opacity: 0.7;
    background: var(--card);
    padding: 4px 8px;
    border-radius: 12px;
    border: 1px solid var(--border);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 40px 16px;
    color: var(--text);
    opacity: 0.6;
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 12px;
    opacity: 0.3;
    color: var(--accent1);
}

.empty-state p {
    font-size: 14px;
    font-weight: 600;
    margin: 10px 0 6px;
    color: var(--text);
}

.empty-state small {
    font-size: 12px;
    opacity: 0.7;
}

/* Loading Spinner */
.loading-spinner {
    text-align: center;
    padding: 32px 16px;
    color: var(--text);
    opacity: 0.6;
}

.loading-spinner i {
    margin-right: 6px;
    color: var(--accent1);
}

/* Responsive */
@media (max-width: 640px) {
    .event-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
        padding: 12px;
    }
    
    .event-stats {
        width: 100%;
        justify-content: space-around;
        padding-top: 10px;
        border-top: 1px solid var(--border);
    }
    
    .stat-item {
        text-align: center;
    }
}
</style>
HTML;
?>