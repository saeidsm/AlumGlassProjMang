<?php
require_once __DIR__ . '/../../sercon/bootstrap.php';
secureSession();
if (!isLoggedIn()) {
    header('Location: /login.php');
    exit();
}
$pageTitle = "داشبورد وظایف و تقویم";
$pdo = getProjectDBConnection('pardis');
// Get all unique Blocks
$all_blocks = $pdo->query("SELECT DISTINCT block FROM elements WHERE block IS NOT NULL AND block != '' ORDER BY block")->fetchAll(PDO::FETCH_COLUMN);
// Get all unique Zone Names
$all_zones = $pdo->query("SELECT DISTINCT zone_name FROM elements WHERE zone_name IS NOT NULL AND zone_name != '' ORDER BY zone_name")->fetchAll(PDO::FETCH_COLUMN);
// Get all unique Element Types
$all_types = $pdo->query("SELECT DISTINCT element_type FROM elements WHERE element_type IS NOT NULL AND element_type != '' ORDER BY element_type")->fetchAll(PDO::FETCH_COLUMN);
// Get all unique Contractors
$all_contractors = $pdo->query("SELECT DISTINCT contractor FROM elements WHERE contractor IS NOT NULL AND contractor != '' ORDER BY contractor")->fetchAll(PDO::FETCH_COLUMN);
// Get all possible Statuses from the ENUM definition in the 'notifications' table
$status_query = $pdo->query("SHOW COLUMNS FROM notifications LIKE 'status'");
preg_match("/^enum\(\'(.*)\'\)$/", $status_query->fetch(PDO::FETCH_ASSOC)['Type'], $matches);
$all_statuses = explode("','", $matches[1]);
// --- END: Fetch all data for filters ---
require_once __DIR__ . '/header_pardis.php';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تقویم کاری و اعلان‌ها</title>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/main.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
    @font-face {
  font-family: "Samim";
  src: url("/pardis/assets/fonts/Samim-FD.woff2") format("woff2"),
    url("/pardis/assets/fonts/Samim-FD.woff") format("woff"),
    url("/pardis/assets/fonts/Samim-FD.ttf") format("truetype");
}
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}
body {
  font-family: "Samim", "Tahoma", sans-serif;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  min-height: 100vh;
  padding: 10px;
}
.dashboard-container {
  width: 100%;
  max-width: none;
  margin: 0;
  display: grid;
  grid-template-columns: 400px 1fr;
  gap: 15px;
  height: calc(100vh - 20px);
}
.notifications-section {
  background: rgba(255, 255, 255, 0.95);
  -webkit-backdrop-filter: blur(10px);
  backdrop-filter: blur(10px);
  border-radius: 20px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
  overflow: hidden;
  border: 1px solid rgba(255, 255, 255, 0.2);
  display: flex;
  flex-direction: column;
}
.notifications-header {
  background: linear-gradient(135deg, #4f46e5, #7c3aed);
  color: white;
  padding: 20px;
  text-align: center;
  position: relative;
  overflow: hidden;
  flex-shrink: 0;
}
.notifications-header::before {
  content: "";
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="2" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="40" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="40" cy="80" r="1.5" fill="rgba(255,255,255,0.1)"/></svg>');
  animation: float 20s infinite linear;
}
@keyframes float {
  0% {
    transform: translateY(0px) rotate(0deg);
  }
  100% {
    transform: translateY(-100px) rotate(360deg);
  }
}
.notifications-header h2 {
  font-size: 1.3rem;
  font-weight: 600;
  margin: 0;
  position: relative;
  z-index: 1;
}
.notifications-header .icon {
  font-size: 1.8rem;
  margin-bottom: 8px;
  position: relative;
  z-index: 1;
}
.notifications-content {
  flex: 1;
  overflow-y: auto;
  overflow-x: hidden;
  padding: 0;
}
.notifications-content::-webkit-scrollbar {
  width: 8px;
}
.notifications-content::-webkit-scrollbar-track {
  background: #f1f1f1;
}
.notifications-content::-webkit-scrollbar-thumb {
  background: #c1c1c1;
  border-radius: 4px;
}
.notifications-content::-webkit-scrollbar-thumb:hover {
  background: #a8a8a8;
}
.accordion-item {
  border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}
.accordion-header {
  background: linear-gradient(135deg, #f8fafc, #e2e8f0);
  padding: 15px 20px;
  cursor: pointer;
  display: flex;
  justify-content: space-between;
  align-items: center;
  transition: all 0.3s ease;
  font-weight: 500;
  color: #334155;
}
.accordion-header:hover {
  background: linear-gradient(135deg, #e2e8f0, #cbd5e1);
  transform: translateX(-2px);
}
.accordion-header.active {
  background: linear-gradient(135deg, #4f46e5, #7c3aed);
  color: white;
}
.accordion-header .chevron {
  transition: transform 0.3s ease;
  font-size: 0.9rem;
}
.accordion-header.active .chevron {
  transform: rotate(180deg);
}
.accordion-content {
  max-height: 0;
  overflow: hidden;
  transition: max-height 0.3s ease;
  background: white;
}
.accordion-content.active {
  max-height: 2000vh; /* A large value that content won't exceed */
}
.zone-section {
  border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}
.zone-header {
  background: #f1f5f9;
  padding: 10px 20px;
  font-weight: 500;
  color: #475569;
  font-size: 0.9rem;
}
.notification-item {
  padding: 12px 20px;
  border-bottom: 1px solid rgba(0, 0, 0, 0.05);
  transition: all 0.2s ease;
  position: relative;
}
.notification-item:hover {
  background: #f8fafc;
  transform: translateX(-3px);
}
.notification-item:last-child {
  border-bottom: none;
}
.notification-badge {
  display: inline-block;
  padding: 3px 8px;
  border-radius: 15px;
  font-size: 0.7rem;
  font-weight: 500;
  margin-bottom: 6px;
}
.notification-badge.new {
  background: linear-gradient(135deg, #ef4444, #dc2626);
  color: white;
  animation: pulse 2s infinite;
}
.notification-badge.type-danger {
  background: linear-gradient(135deg, #ef4444, #dc2626);
  color: white;
}
.notification-badge.type-warning {
  background: linear-gradient(135deg, #f59e0b, #d97706);
  color: white;
}
.notification-badge.type-success {
  background: linear-gradient(135deg, #10b981, #059669);
  color: white;
}
@keyframes pulse {
  0% {
    transform: scale(1);
  }
  50% {
    transform: scale(1.05);
  }
  100% {
    transform: scale(1);
  }
}
.notification-link {
  color: #1e40af;
  text-decoration: none;
  font-weight: 500;
  line-height: 1.4;
  display: block;
  margin-bottom: 4px;
  font-size: 0.85rem;
}
.notification-link:hover {
  color: #1d4ed8;
}
.notification-time {
  color: #64748b;
  font-size: 0.75rem;
}
.calendar-section {
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(10px);
  border-radius: 20px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
  overflow: hidden;
  border: 1px solid rgba(255, 255, 255, 0.2);
  display: flex;
  flex-direction: column;
}
.filters-panel {
  background: linear-gradient(135deg, #f8fafc, #e2e8f0);
  padding: 15px 20px;
  border-bottom: 1px solid rgba(0, 0, 0, 0.1);
  flex-shrink: 0;
}
.filters-content {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  flex-wrap: wrap;
  align-items: flex-end; /* Aligns button and filters to the bottom */
  gap: 15px;
}
.filter-group {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 120px;
}
.filter-label {
  font-size: 0.75rem;
  font-weight: 500;
  color: #475569;
}
.filter-select {
  padding: 8px 12px;
  border: 2px solid #e2e8f0;
  border-radius: 10px;
  background: white;
  font-family: inherit;
  font-size: 0.85rem;
  transition: all 0.3s ease;
  min-width: 120px;
}
.filter-select:focus {
  outline: none;
  border-color: #4f46e5;
  box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
}
.filter-reset-btn {
  background: linear-gradient(135deg, #ef4444, #dc2626);
  color: white;
  border: none;
  padding: 8px 16px;
  border-radius: 10px;
  font-family: inherit;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.85rem;
}
.filter-reset-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(239, 68, 68, 0.3);
}
.calendar-container {
  flex: 1;
  padding: 20px;
  overflow: auto;
  width: 100%;
} /* Mobile Toggle Button */
.mobile-toggle {
  display: none;
  position: fixed;
  top: 20px;
  right: 20px;
  z-index: 1000;
  background: linear-gradient(135deg, #4f46e5, #7c3aed);
  color: white;
  border: none;
  border-radius: 50%;
  width: 50px;
  height: 50px;
  font-size: 1.2rem;
  cursor: pointer;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
  transition: all 0.3s ease;
}
.mobile-toggle:hover {
  transform: scale(1.05);
} /* FullCalendar Customization */
.fc {
  font-family: "Samim", "Tahoma", sans-serif;
  height: 100% !important;
  width: 100% !important;
}
.fc-view-harness {
  width: 100% !important;
}
.fc-header-toolbar {
  margin-bottom: 15px;
  padding: 12px;
  background: linear-gradient(135deg, #f8fafc, #e2e8f0);
  border-radius: 10px;
  flex-wrap: wrap;
  gap: 8px;
}
.fc-toolbar-chunk {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 4px;
}
.fc-button-primary {
  background: linear-gradient(135deg, #4f46e5, #7c3aed);
  border: none;
  border-radius: 6px;
  padding: 6px 12px;
  font-weight: 500;
  transition: all 0.3s ease;
  font-size: 0.85rem;
  white-space: nowrap;
}
.fc-button-primary:hover {
  transform: translateY(-1px);
  box-shadow: 0 8px 20px rgba(79, 70, 229, 0.3);
}
.fc-button-primary:not(:disabled):active,
.fc-button-primary:not(:disabled).fc-button-active {
  background: linear-gradient(135deg, #3730a3, #5b21b6);
}
.fc-daygrid-day {
  transition: all 0.2s ease;
  min-height: 120px !important;
}
.fc-daygrid-day:hover {
  background: rgba(79, 70, 229, 0.05);
}
.fc-event {
  border: none;
  border-radius: 6px;
  padding: 4px 8px;
  font-weight: 500;
  font-size: 0.8rem;
  transition: all 0.2s ease;
  cursor: pointer;
  margin: 1px 0;
  min-height: 22px;
  line-height: 1.2;
  word-wrap: break-word;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.fc-event:hover {
  transform: scale(1.02);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  z-index: 1000;
  white-space: normal;
  min-height: auto;
}
.fc-event-title {
  font-size: 0.8rem;
  line-height: 1.2;
}
.fc-daygrid-event-harness {
  margin-bottom: 2px;
}
.fc-event.color-info {
  background: linear-gradient(135deg, #3b82f6, #2563eb) !important;
  color: white !important;
}
.fc-event.color-warning {
  background: linear-gradient(135deg, #f59e0b, #d97706) !important;
  color: white !important;
}
.fc-event.color-success {
  background: linear-gradient(135deg, #10b981, #059669) !important;
  color: white !important;
}
.fc-event.color-danger {
  background: linear-gradient(135deg, #ef4444, #dc2626) !important;
  color: white !important;
}
.fc-daygrid-day-events {
  margin-top: 2px;
}
.fc-more-link {
  background: #4f46e5;
  color: white;
  border-radius: 4px;
  padding: 2px 6px;
  font-size: 0.7rem;
}
.loading-spinner {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 30px;
  color: #64748b;
}
.spinner {
  width: 30px;
  height: 30px;
  border: 3px solid #e2e8f0;
  border-top: 3px solid #4f46e5;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-left: 12px;
}
@keyframes spin {
  0% {
    transform: rotate(0deg);
  }
  100% {
    transform: rotate(360deg);
  }
}
.empty-state {
  text-align: center;
  padding: 30px 15px;
  color: #64748b;
}
.empty-state i {
  font-size: 2.5rem;
  margin-bottom: 12px;
  opacity: 0.5;
} /* Responsive Design */
@media (max-width: 1600px) {
  .dashboard-container {
    grid-template-columns: 350px 1fr;
  }
}
@media (max-width: 1200px) {
  body {
    padding: 5px;
  }
  .dashboard-container {
    grid-template-columns: 320px 1fr;
    gap: 10px;
    height: calc(100vh - 10px);
  }
  .filters-panel {
    padding: 12px 15px;
    gap: 10px;
  }
  .filter-group {
    min-width: 110px;
  }
  .fc-header-toolbar {
    padding: 8px;
  }
  .fc-button-primary {
    padding: 4px 8px;
    font-size: 0.8rem;
  }
} /* Mobile Styles */
@media (max-width: 768px) {
  body {
    padding: 5px;
    overflow-x: hidden;
  }
  .mobile-toggle {
    display: block;
  }
  .dashboard-container {
    grid-template-columns: 1fr;
    height: auto;
    gap: 10px;
    min-height: calc(100vh - 10px);
  }
  .notifications-section {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 999;
    transform: translateX(-100%);
    transition: transform 0.3s ease;
    border-radius: 0;
    max-height: 100vh;
  }
  .notifications-section.active {
    transform: translateX(0);
  }
  .calendar-section {
    margin-top: 10px;
    height: calc(100vh - 20px);
    display: flex;
    flex-direction: column;
  } /* Mobile filters - make collapsible */
  .filters-panel {
    padding: 8px 15px !important;
    display: block !important;
    max-height: 50px;
    overflow: hidden;
    transition: max-height 0.3s ease;
  }
  .filters-panel.expanded {
    max-height: 300px;
    padding: 15px !important;
  }
  .filters-toggle {
    display: flex !important; /* Show only on mobile */
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    font-weight: 500;
    color: #475569;
    margin-bottom: 10px;
  }
  .filters-content {
    display: flex !important;
    flex-direction: column !important;
    gap: 8px !important;
    opacity: 0;
    transition: opacity 0.3s ease;
  }
  .filters-panel.expanded .filters-content {
    opacity: 1;
  }
  .filter-group {
    width: 100% !important;
    min-width: auto !important;
  }
  .filter-select {
    width: 100% !important;
    min-width: auto !important;
  }
  .calendar-container {
    flex: 1;
    padding: 10px;
    overflow: hidden;
    height: 100%;
  }
  .fc {
    height: 100% !important;
  }
  .fc-view-harness {
    height: 100% !important;
  }
  .fc-header-toolbar {
    padding: 8px;
    margin-bottom: 10px;
    flex-wrap: wrap;
    justify-content: center;
  }
  .fc-toolbar-chunk {
    justify-content: center;
    width: auto;
    margin: 2px;
    flex-wrap: wrap;
  }
  .fc-button-primary {
    padding: 6px 8px;
    font-size: 0.75rem;
    margin: 1px;
    white-space: nowrap;
  }
  .fc-daygrid-day {
    min-height: 80px !important;
  }
  .fc-event {
    font-size: 0.7rem;
    padding: 2px 6px;
    min-height: 18px;
    margin: 1px 0;
  }
  .fc-event-title {
    font-size: 0.7rem;
    line-height: 1.1;
  }
  .fc-col-header-cell {
    font-size: 0.8rem;
    padding: 8px 4px;
  }
  .fc-daygrid-day-number {
    font-size: 0.9rem;
    padding: 4px;
  }
  .notifications-header {
    padding: 15px;
  }
  .notifications-header h2 {
    font-size: 1.1rem;
  }
  .accordion-header {
    padding: 12px 15px;
    font-size: 0.9rem;
  }
  .notification-item {
    padding: 10px 15px;
  }
  .zone-header {
    padding: 8px 15px;
    font-size: 0.85rem;
  }
} /* Extra small mobile devices */
@media (max-width: 768px) {
  body {
    padding: 5px;
    overflow-x: hidden;
  }
  .mobile-toggle {
    display: block;
  }
  .dashboard-container {
    grid-template-columns: 1fr;
    height: auto;
    gap: 10px;
    min-height: calc(100vh - 10px);
  }
  .notifications-section {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 999;
    transform: translateX(-100%);
    transition: transform 0.3s ease;
    border-radius: 0;
    max-height: 100vh;
  }
  .notifications-section.active {
    transform: translateX(0);
  }
  .calendar-section {
    margin-top: 10px;
    height: calc(100vh - 20px);
    display: flex;
    flex-direction: column;
  } /* Mobile-specific filter styles */
  .filters-panel {
    background: linear-gradient(135deg, #f8fafc, #e2e8f0);
    padding: 8px 15px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.1);
    display: block; /* Change from flex to block on mobile */
    position: relative;
    flex-shrink: 0;
    max-height: 50px; /* Collapsed by default on mobile */
    overflow: hidden;
    transition: max-height 0.3s ease;
    gap: 0; /* Remove gap on mobile */
  }
  .filters-panel.expanded {
    max-height: 300px;
    padding: 15px;
  }
  .filters-toggle {
    display: flex; /* Show on mobile */
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    font-weight: 500;
    color: #475569;
    margin-bottom: 10px;
  }
  .filters-content {
    display: flex;
    flex-direction: column; /* Stack vertically on mobile */
    gap: 8px;
    opacity: 0;
    transition: opacity 0.3s ease;
    width: 100%;
  }
  .filters-panel.expanded .filters-content {
    opacity: 1;
  }
  .filter-group {
    width: 100%;
    min-width: auto;
  }
  .filter-select {
    min-width: auto;
    width: 100%;
    padding: 8px 12px;
    font-size: 0.9rem;
  }
  .calendar-container {
    flex: 1;
    padding: 10px;
    overflow: hidden;
    height: 100%;
  }
  .fc {
    height: 100% !important;
  }
  .fc-view-harness {
    height: 100% !important;
  }
  .fc-header-toolbar {
    padding: 8px;
    margin-bottom: 10px;
    flex-wrap: wrap;
    justify-content: center;
  }
  .fc-toolbar-chunk {
    justify-content: center;
    width: auto;
    margin: 2px;
    flex-wrap: wrap;
  }
  .fc-button-primary {
    padding: 6px 8px;
    font-size: 0.75rem;
    margin: 1px;
    white-space: nowrap;
  }
  .fc-daygrid-day {
    min-height: 80px !important;
  }
  .fc-event {
    font-size: 0.7rem;
    padding: 2px 6px;
    min-height: 18px;
    margin: 1px 0;
  }
  .fc-event-title {
    font-size: 0.7rem;
    line-height: 1.1;
  }
  .fc-col-header-cell {
    font-size: 0.8rem;
    padding: 8px 4px;
  }
  .fc-daygrid-day-number {
    font-size: 0.9rem;
    padding: 4px;
  }
  .notifications-header {
    padding: 15px;
  }
  .notifications-header h2 {
    font-size: 1.1rem;
  }
  .accordion-header {
    padding: 12px 15px;
    font-size: 0.9rem;
  }
  .notification-item {
    padding: 10px 15px;
  }
  .zone-header {
    padding: 8px 15px;
    font-size: 0.85rem;
  }
} /* Extra small mobile devices */
@media (max-width: 480px) {
  .calendar-section {
    height: calc(100vh - 15px);
  }
  .filters-panel {
    padding: 6px 10px;
    max-height: 45px;
  }
  .filters-panel.expanded {
    padding: 12px 10px;
  }
  .fc-header-toolbar {
    flex-direction: column;
    align-items: center;
    gap: 6px;
    padding: 6px;
  }
  .fc-toolbar-chunk {
    width: auto;
    margin-bottom: 3px;
  }
  .fc-button-primary {
    padding: 4px 6px;
    font-size: 0.7rem;
    margin: 1px;
  }
  .fc-daygrid-day {
    min-height: 70px !important;
  }
  .fc-event {
    font-size: 0.65rem;
    padding: 1px 4px;
    min-height: 16px;
  }
  .filter-select {
    padding: 6px 8px;
    font-size: 0.8rem;
  }
  .calendar-container {
    padding: 8px;
  }
} /* Landscape mobile orientation */
@media (max-width: 768px) and (orientation: landscape) {
  .calendar-section {
    height: calc(100vh - 15px);
  }
  .fc-daygrid-day {
    min-height: 60px !important;
  }
  .filters-panel {
    max-height: 40px;
  }
}
.notification-tabs {
  display: flex;
  background-color: #e2e8f0;
  padding: 5px;
  flex-shrink: 0;
}
.notification-tab-button {
  flex: 1;
  padding: 12px 10px;
  background: transparent;
  border: none;
  font-family: "Samim", sans-serif;
  font-size: 0.9rem;
  font-weight: 500;
  color: #64748b;
  cursor: pointer;
  border-radius: 15px;
  transition: all 0.3s ease;
  position: relative;
}
.notification-tab-button.active {
  background: white;
  color: #4f46e5;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}
.notification-tab-content {
  display: none;
  flex: 1;
  overflow-y: auto;
}
.notification-tab-content.active {
  display: block;
}
.notification-count-badge {
  background-color: #ef4444;
  color: white;
  border-radius: 50%;
  padding: 1px 6px;
  font-size: 0.7rem;
  margin-right: 5px;
  vertical-align: middle;
}
.task-actions {
  margin-top: 8px;
  text-align: left;
}
.task-action-btn {
  background: #10b981;
  color: white;
  border: none;
  padding: 4px 10px;
  font-size: 0.75rem;
  border-radius: 8px;
  cursor: pointer;
}
.filter-toggle-btn {
  background: linear-gradient(135deg, #10b981, #059669);
  color: white;
  border: none;
  padding: 8px 12px;
  border-radius: 10px;
  font-family: inherit;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.85rem;
  min-width: 120px;
}

.filter-toggle-btn[data-state="hide"] {
  background: linear-gradient(135deg, #ef4444, #dc2626);
}

.filter-toggle-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3);
}

.filter-toggle-btn[data-state="hide"]:hover {
  box-shadow: 0 8px 20px rgba(239, 68, 68, 0.3);
}
</style>
</head>
<body> <!-- Mobile Toggle Button --> 
    <button class="mobile-toggle" id="mobile-toggle" title="نمایش اعلان‌ها"> <i class="fa fa-bell" aria-hidden="true"></i>
    </button>
    <div class="dashboard-container"> <!-- Notifications Panel -->
        <div class="notifications-section" id="notifications-section">
            <div class="notifications-header">
                <div class="icon"><i class="fa fa-tasks"></i></div>
                <h2>مرکز وظایف و اعلان‌ها</h2>
            </div> <!-- --- NEW: Tabs for Tasks and Archive --- -->
            <div class="notification-tabs">
                <button class="notification-tab-button active" data-tab="tasks">
                    <span id="task-count-badge" class="notification-count-badge" style="display:none;"></span>
                    کارهای فعال
                </button>
                <button class="notification-tab-button" data-tab="notifications">
                    <span id="notification-count-badge" class="notification-count-badge" style="display:none;"></span>
                    اعلان‌ها
                </button>
                <button class="notification-tab-button" data-tab="archive">
                    بایگانی
                </button>
            </div>
            <div id="notifications-content" class="notification-tab-content">
              <div class="notifications-content">
                  <div id="notifications-accordion">
                      <div class="loading-spinner" id="notifications-loading">
                          <span>در حال بارگذاری اعلان‌ها...</span>
                          <div class="spinner"></div>
                      </div>
                  </div>
              </div>
          </div>
            <div id="tasks-content" class="notification-tab-content active">
                <div class="notifications-content">
                    <div id="tasks-accordion">
                        <div class="loading-spinner" id="task-loading"><span>در حال بارگذاری وظایف...</span>
                            <div class="spinner"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div id="archive-content" class="notification-tab-content">
                <div class="notifications-content">
                    <div id="archive-accordion">
                        <div class="loading-spinner" id="archive-loading"><span>در حال بارگذاری بایگانی...</span>
                            <div class="spinner"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div> <!-- Calendar Panel -->
        <div class="calendar-section">
            <div class="filters-panel" id="filters-panel"> <!-- START OF FIX: Mobile toggle button added back -->
                <div class="filters-toggle" id="filters-toggle"> <span><i class="fa fa-filter"></i> فیلترها</span> <i
                        class="fa fa-chevron-down" id="filters-chevron"></i> </div>
                <div class="filters-content" id="filters-content"> <!-- --- NEW: More detailed filters --- -->
                    <div class="filter-group"> <label class="filter-label">بلوک</label> <select class="filter-select"
                            id="filter-block">
                            <option value="">همه بلوک‌ها</option>
                        </select> </div>
                    <div class="filter-group"> <label class="filter-label">زون</label> <select class="filter-select"
                            id="filter-zone">
                            <option value="">همه زون‌ها</option>
                        </select> </div>
                    <div class="filter-group"> <label class="filter-label">نوع المان</label> <select
                            class="filter-select" id="filter-type">
                            <option value="">همه انواع</option>
                        </select> </div>
                    <div class="filter-group"> <label class="filter-label">پیمانکار</label> <select
                            class="filter-select" id="filter-contractor">
                            <option value="">همه پیمانکاران</option>
                        </select> </div>
                    <div class="filter-group"> <label class="filter-label">وضعیت وظیفه</label> <select
                            class="filter-select" id="filter-status">
                            <option value="">همه وضعیت‌ها</option>
                        </select> </div>
                   <div class="filter-group"> 
                    <label class="filter-label">نمایش وظایف</label> 
                    <button class="filter-toggle-btn" id="filter-my-actions" data-state="show">
                      <i class="fa fa-eye"></i> مخفی کردن وظایف خودم
                    </button> 
                  </div>
                    <div class="filter-group" style="justify-content: flex-end;"> <button class="filter-reset-btn"
                            id="filter-reset"><i class="fa fa-refresh"></i> پاک کردن</button> </div>
                </div>
            </div>
            <div class="calendar-container">
                <div id="calendar"></div>
            </div>
        </div>
    </div>
<script>
        document.addEventListener("DOMContentLoaded", function () {
  // ===================================================================
  // 1. DOM Elements and Global State Variables
  // ===================================================================
 
  // Filter elements
  const filterElements = {
    block: document.getElementById("filter-block"),
    zone: document.getElementById("filter-zone"),
    type: document.getElementById("filter-type"),
    contractor: document.getElementById("filter-contractor"),
    status: document.getElementById("filter-status"),
    reset: document.getElementById("filter-reset"),
    // Removed direction filter as per fix
  };
  const calendarEl = document.getElementById("calendar");
  const accordionContainer = document.getElementById("notifications-accordion");
  const taskAccordion = document.getElementById("tasks-accordion");
  const archiveAccordion = document.getElementById("archive-accordion");
  const taskCountBadge = document.getElementById("task-count-badge");
  const notificationsAccordion = document.getElementById("notifications-accordion");
  const notificationCountBadge = document.getElementById("notification-count-badge");
  
  // Mobile elements
  const mobileToggle = document.getElementById("mobile-toggle");
  const notificationsSection = document.getElementById("notifications-section");
  const filtersPanel = document.getElementById("filters-panel");
  const filtersToggle = document.getElementById("filters-toggle");
  const filtersChevron = document.getElementById("filters-chevron");
  
  // State management
  let allEvents = [];
  const FILTERS_KEY = "calendarFilters";
  const ACCORDION_KEY = "activeAccordionBlock";
  const SCROLL_KEY = "notificationScrollPos";
  // ===================================================================
  // 2. Core Functions
  // ===================================================================

    /**
     * Marks notifications as read and updates header badge
     */
  function markNotificationsAsRead() {
  fetch("/pardis/api/mark_notifications_as_read.php", {
    method: "POST",
  })
    .then((res) => res.json())
    .then((data) => {
      if (data.status === "success") {
        console.log(`${data.marked_count || 0} notifications marked as read.`);
        
        // Update ALL badge references immediately
        const headerBadge = window.parent?.document.getElementById("notification-badge");
        const localNotificationBadge = document.getElementById("notification-count-badge");
        
        if (headerBadge && data.marked_count > 0) {
          const currentCount = parseInt(headerBadge.textContent || 0);
          const newCount = Math.max(0, currentCount - data.marked_count);
          headerBadge.textContent = newCount;
          if (newCount === 0) {
            headerBadge.style.display = "none";
          }
        }
        
        if (localNotificationBadge) {
          localNotificationBadge.style.display = "none";
        }
        
        // CRITICAL: Store timestamp to prevent badge reappearing on refresh
        localStorage.setItem('notifications_last_read', Date.now().toString());
      }
    })
    .catch((err) =>
      console.error("Error marking notifications as read:", err)
    );
}
  /**
             * Render notification panels (tasks or archive)
             */
  function renderNotificationPanel(container, groupedData, isTaskPanel) {
    container.innerHTML = "";
    if (!groupedData || Object.keys(groupedData).length === 0) {
      const message = isTaskPanel
        ? "هیچ کار فعالی برای شما تعریف نشده است."
        : "هیچ اعلانی در بایگانی وجود ندارد.";
      container.innerHTML = `<div class="empty-state"><i class="fa ${
        isTaskPanel ? "fa-check-circle" : "fa-bell-slash"
      }"></i><p>${message}</p></div>`;
      return;
    }
    for (const blockName in groupedData) {
      const zones = groupedData[blockName];
      const accordionItem = document.createElement("div");
      accordionItem.className = "accordion-item";
      const header = document.createElement("div");
      header.className = "accordion-header";
      header.dataset.block = blockName;
      header.innerHTML = `<span><i class="fa fa-building"></i> بلوک: ${blockName}</span><i class="fa fa-chevron-down chevron"></i>`;
      const content = document.createElement("div");
      content.className = "accordion-content";
      for (const zoneName in zones) {
        const notifications = zones[zoneName];
        const zoneSection = document.createElement("div");
        zoneSection.className = "zone-section";
        zoneSection.innerHTML = `<div class="zone-header"><i class="fa fa-map-marker-alt"></i> زون: ${zoneName}</div>`;
        notifications.forEach((n) => {
          const itemDiv = document.createElement("div");
          itemDiv.className = "notification-item";
          itemDiv.dataset.taskId = n.notification_id;
          const isUnread = n.status === "pending";
          const badgeClass = isUnread
            ? "notification-badge new"
            : `notification-badge type-${n.type}`;
          const badgeText = isUnread
            ? "جدید"
            : n.status === "completed"
            ? "تکمیل شده"
            : "مشاهده شده";
          const date = new Date(n.created_at);
          const formattedDate =
            date.toLocaleDateString("fa-IR") +
            " " +
            date.toLocaleTimeString("fa-IR", {
              hour: "2-digit",
              minute: "2-digit",
            });
          let actionsHTML = "";
          if (isTaskPanel && n.status !== "completed") {
            actionsHTML = `<div class="task-actions"><button class="task-action-btn" data-task-id="${n.notification_id}">اتمام وظیفه</button></div>`;
          }
          itemDiv.innerHTML = `
<div class="${badgeClass}">${badgeText}</div>
<a href="${n.link}" class="notification-link" target="_blank">${n.message}</a>
<div class="notification-time"><i class="fa fa-clock"></i> ${formattedDate}</div>
${actionsHTML}`;
          zoneSection.appendChild(itemDiv);
        });
        content.appendChild(zoneSection);
      }
      accordionItem.appendChild(header);
      accordionItem.appendChild(content);
      container.appendChild(accordionItem);
      header.addEventListener("click", () => {
        header.classList.toggle("active");

        content.classList.toggle("active");
      });
    }
  }

      /**
     * Populate dynamic filters from server response
     */
   function populateDynamicFilters(filters) {
    const populateSelect = (selectEl, options, prefix = "") => {
      if (!selectEl || !options) return;
      const currentValue = selectEl.value;
      selectEl.innerHTML = `<option value="">همه ${prefix}</option>`;
      options.forEach((opt) => {
        selectEl.innerHTML += `<option value="${opt}" ${
          opt == currentValue ? "selected" : ""
        }>${opt}</option>`;
      });
    };
    
    populateSelect(filterElements.block, filters.blocks, "بلوک‌ها");
    populateSelect(filterElements.zone, filters.zones, "زون‌ها");  
    populateSelect(filterElements.type, filters.types, "انواع");
    populateSelect(filterElements.contractor, filters.contractors, "پیمانکاران");
    populateSelect(filterElements.status, filters.statuses, "وضعیت‌ها");
    
    // REMOVED: All direction filter logic since we're using toggle button now
  }

  /**
             * Initialize mobile filters functionality
             */
  function initMobileFilters() {
    if (filtersToggle && window.innerWidth <= 768) {
      filtersToggle.style.display = "flex";

      filtersToggle.addEventListener("click", () => {
        filtersPanel.classList.toggle("expanded");

        if (filtersChevron) {
          filtersChevron.style.transform = filtersPanel.classList.contains(
            "expanded"
          )
            ? "rotate(180deg)"
            : "rotate(0deg)";
        }
      });

      // Auto-collapse filters on mobile after selection

      document.querySelectorAll(".filter-select").forEach((select) => {
        select.addEventListener("change", () => {
          setTimeout(() => {
            if (filtersPanel.classList.contains("expanded")) {
              filtersPanel.classList.remove("expanded");

              if (filtersChevron)
                filtersChevron.style.transform = "rotate(0deg)";
            }
          }, 300);
        });
      });
    } else if (filtersToggle) {
      filtersToggle.style.display = "none";

      filtersPanel.classList.remove("expanded");
    }
  }

  // ===================================================================

  // 3. LocalStorage State Management

  // ===================================================================

  const saveFilters = () => {
  const toggleBtn = document.getElementById("filter-my-actions");
  const filterState = {
    zone: filterElements.zone?.value || "",
    type: filterElements.type?.value || "",
    block: filterElements.block?.value || "",
    status: filterElements.status?.value || "",
    contractor: filterElements.contractor?.value || "",
    hideMyActions: toggleBtn ? toggleBtn.dataset.state : "show" // Save toggle state
  };

  localStorage.setItem(FILTERS_KEY, JSON.stringify(filterState));
};

  const saveAccordionState = () => {
    const activeHeader = accordionContainer?.querySelector(
      ".accordion-header.active"
    );

    if (activeHeader) {
      localStorage.setItem(ACCORDION_KEY, activeHeader.dataset.block);
    }
  };

  const debounce = (func, delay) => {
    let timeout;
    return function (...args) {
      clearTimeout(timeout);
      timeout = setTimeout(() => func.apply(this, args), delay);
    };
  };

  const saveScrollPosition = debounce(() => {
    const scroller = document.querySelector(".notifications-content");
    if (scroller) {
      localStorage.setItem(SCROLL_KEY, scroller.scrollTop);
    }
  }, 100);

  const restoreState = () => {
  // Restore filters
  const savedFilters = JSON.parse(localStorage.getItem(FILTERS_KEY) || "{}");
  if (savedFilters && Object.keys(savedFilters).length > 0) {
    Object.keys(savedFilters).forEach((key) => {
      if (key === 'hideMyActions') {
        // Restore toggle button state
        const toggleBtn = document.getElementById("filter-my-actions");
        if (toggleBtn && savedFilters[key]) {
          toggleBtn.dataset.state = savedFilters[key];
          if (savedFilters[key] === "hide") {
            toggleBtn.innerHTML = '<i class="fa fa-eye-slash"></i> نمایش وظایف خودم';
          }
        }
      } else if (filterElements[key]) {
        filterElements[key].value = savedFilters[key] || "";
      }
    });
    calendar.refetchEvents();
  }
    // Restore accordion and scroll position
    const savedBlock = localStorage.getItem(ACCORDION_KEY);
    const savedScroll = localStorage.getItem(SCROLL_KEY);
    if (accordionContainer) {
      const observer = new MutationObserver((mutations, obs) => {
        const accordionHeaders =
          accordionContainer.querySelectorAll(".accordion-header");
        if (accordionHeaders.length > 0) {
          let headerToOpen = savedBlock
            ? accordionContainer.querySelector(
                `.accordion-header[data-block="${savedBlock}"]`
              )
            : accordionHeaders[0];
          if (headerToOpen && !headerToOpen.classList.contains("active")) {
            headerToOpen.click();
          }
          if (savedScroll) {
            const scroller = document.querySelector(".notifications-content");
            if (scroller) {
              scroller.scrollTop = savedScroll;
            }
          }
          obs.disconnect();
        }
      });
      observer.observe(accordionContainer, {
        childList: true,
        subtree: true,
      });
    }
  };
  // ===================================================================
  // 4. Calendar Initialization
  // ===================================================================
  const calendar = new FullCalendar.Calendar(calendarEl, {
    locale: "fa",
    direction: "rtl",
    firstDay: 6,
    height: "100%",
    aspectRatio: window.innerWidth <= 768 ? 1.0 : 1.35,
    headerToolbar: {
      left: "prev,next",
      center: "title",
      right: "dayGridMonth,timeGridWeek,listWeek",
    },
    buttonText: {
      today: "امروز",
      month: "ماه",
      week: "هفته",
      day: "روز",
      list: "لیست",
    },
    dayMaxEvents: window.innerWidth <= 768 ? 3 : 4,
    moreLinkClick: "popover",
    eventDisplay: "block",
    eventMaxStack: 3,
    events: (fetchInfo, successCallback, failureCallback) => {
      const myActionsToggle = document.getElementById("filter-my-actions");
      const hideMyActions = myActionsToggle && myActionsToggle.dataset.state === "hide";
      
      const params = new URLSearchParams({
        start: fetchInfo.startStr,
        end: fetchInfo.endStr,
        block: filterElements.block.value,
        zone: filterElements.zone.value,
        type: filterElements.type.value,
        contractor: filterElements.contractor.value,
        status: filterElements.status.value,
        hide_my_actions: hideMyActions ? '1' : '0'
      });

      fetch(`/pardis/api/get_calendar_events.php?${params}`)
        .then((res) => {
          if (!res.ok) {
            throw new Error(`HTTP error! status: ${res.status}`);
          }
          return res.json();
        })
        .then((data) => {
          if (data && data.events && data.filters) {
            const uniqueEvents = [];
            const seenEventIds = new Set();
            
            data.events.forEach(event => {
              if (!seenEventIds.has(event.id)) {
                seenEventIds.add(event.id);
                
                const processedEvent = {
                  id: event.id,
                  title: event.title,
                  start: event.start,
                  end: event.end,
                  color: event.color || '#4f46e5',
                  extendedProps: event.extendedProps || {
                    related_link: event.related_link,
                    element_type: event.element_type,
                    zone_name: event.zone_name,
                    block: event.block,
                    contractor: event.contractor,
                    task_status: event.task_status,
                    direction: event.direction
                  }
                };
                
                uniqueEvents.push(processedEvent);
              }
            });
            
            populateDynamicFilters(data.filters);
            successCallback(uniqueEvents);
            
            console.log(`Loaded ${uniqueEvents.length} unique events`);
          } else if (data && data.error) {
            console.error("API returned error:", data.error);
            failureCallback(new Error(data.error));
          } else {
            throw new Error("Invalid data structure from API. Check PHP error logs.");
          }
        })
        .catch((err) => {
          console.error("Error fetching calendar events:", err);
          failureCallback(err);
        });
    },
    eventClick: function (info) {
      info.jsEvent.preventDefault();
      if (info.event.extendedProps.related_link) {
        window.open(info.event.extendedProps.related_link, "_blank");
      }
    },
    eventDidMount: function (info) {
      // Add hover effects
      info.el.addEventListener("mouseenter", function () {
        this.style.transform = "scale(1.02)";
        this.style.zIndex = "1000";
        if (window.innerWidth > 768) {
          this.style.whiteSpace = "normal";
          this.style.minHeight = "auto";
        }
      });
      info.el.addEventListener("mouseleave", function () {
        this.style.transform = "scale(1)";
        this.style.zIndex = "auto";
        this.style.whiteSpace = "nowrap";
        this.style.minHeight = window.innerWidth <= 768 ? "16px" : "22px";
      });
    },
    windowResize: function () {
      const isMobile = window.innerWidth <= 768;
      calendar.setOption("aspectRatio", isMobile ? 1.0 : 1.35);
      calendar.setOption("dayMaxEvents", isMobile ? 3 : 4);
      calendar.setOption("headerToolbar", {
        left: "prev,next",
        center: "title",
        right: "dayGridMonth,timeGridWeek,listWeek",
      });
    },
  });
  calendar.render();
  // ===================================================================
  // 5. Event Listeners Setup
  // ===================================================================
  document.querySelectorAll(".filter-select").forEach((sel) => {
    sel.addEventListener("change", () => {
      saveFilters();
      calendar.refetchEvents();
    });
  });

  filterElements.reset.addEventListener("click", () => {
    document.querySelectorAll(".filter-select").forEach((sel) => (sel.value = ""));
    
    // Reset toggle button to default state
    const toggleBtn = document.getElementById("filter-my-actions");
    if (toggleBtn) {
      toggleBtn.dataset.state = "show";
      toggleBtn.innerHTML = '<i class="fa fa-eye"></i> مخفی کردن وظایف خودم';
    }
    
    localStorage.removeItem(FILTERS_KEY);
    calendar.refetchEvents();
  });
 document.getElementById("filter-my-actions")?.addEventListener("click", function() {
    const currentState = this.dataset.state;
    
    if (currentState === "show") {
      this.dataset.state = "hide";
      this.innerHTML = '<i class="fa fa-eye-slash"></i> نمایش وظایف خودم';
    } else {
      this.dataset.state = "show";
      this.innerHTML = '<i class="fa fa-eye"></i> مخفی کردن وظایف خودم';
    }
    
    calendar.refetchEvents();
  });
  // Mobile toggle functionality
  if (mobileToggle && notificationsSection) {
    mobileToggle.addEventListener("click", function () {
      notificationsSection.classList.toggle("active");
      const icon = mobileToggle.querySelector("i");
      if (notificationsSection.classList.contains("active")) {
        icon.className = "fa fa-times";
      } else {
        icon.className = "fa fa-bell";
      }
    });
    // Close notifications when clicking outside on mobile
    document.addEventListener("click", function (e) {
      if (
        window.innerWidth <= 768 &&
        !notificationsSection.contains(e.target) &&
        !mobileToggle.contains(e.target) &&
        notificationsSection.classList.contains("active")
      ) {
        notificationsSection.classList.remove("active");

        mobileToggle.querySelector("i").className = "fa fa-bell";
      }
    });
  }
  // Filter functionality
  // ===================================================================
  // 6. Load Notifications
  // ===================================================================
  console.log("Starting to fetch notifications...");

fetch("/pardis/api/get_notifications.php")
  .then((res) => {
    console.log("Response status:", res.status);
    return res.text();
  })
  .then((text) => {
    console.log("Raw response:", text);
    let data;
    try {
      data = JSON.parse(text);
    } catch (e) {
      console.error("JSON parse error:", e);
      return;
    }

    console.log("Parsed data:", data);

    // CRITICAL FIX: Check localStorage to see if notifications were already read
    const lastReadTime = localStorage.getItem('notifications_last_read');
    const wasAlreadyRead = lastReadTime && (Date.now() - parseInt(lastReadTime)) < 3600000; // 1 hour grace period
    
    console.log("Was already read recently:", wasAlreadyRead);

    // Update task count badge (this one should always show the count)
    if (taskCountBadge) {
      const totalTasks = data.active_tasks
        ? Object.values(data.active_tasks).reduce(
            (sum, block) =>
              sum + Object.values(block).reduce((s, zone) => s + zone.length, 0),
            0
          )
        : 0;
      taskCountBadge.textContent = totalTasks;
      taskCountBadge.style.display = totalTasks > 0 ? "inline-block" : "none";
    }

    // CRITICAL FIX: Don't show notification badge if recently read
    if (notificationCountBadge) {
      if (wasAlreadyRead) {
        notificationCountBadge.style.display = "none";
        console.log("Hiding notification badge - was recently read");
      } else {
        const totalNotifications = data.notifications
          ? Object.values(data.notifications).reduce(
              (sum, block) =>
                sum + Object.values(block).reduce((s, zone) => s + zone.length, 0),
              0
            )
          : 0;
        if (totalNotifications > 0) {
          notificationCountBadge.textContent = totalNotifications;
          notificationCountBadge.style.display = "inline-block";
          console.log("Showing notification badge with count:", totalNotifications);
        } else {
          notificationCountBadge.style.display = "none";
        }
      }
    }

    // Render all panels
    renderNotificationPanel(taskAccordion, data.active_tasks, true);
    renderNotificationPanel(notificationsAccordion, data.notifications, false);
    renderNotificationPanel(archiveAccordion, data.archived_notifications, false);

    // CRITICAL FIX: Auto-mark as read and store timestamp
    if (data.total_unread > 0 && !wasAlreadyRead) {
      console.log("Auto-marking notifications as read on page load");
      markNotificationsAsRead();
    }
  })
  .catch((error) => {
    console.error("Fetch error:", error);
  });
// Helper function to count actually unread notifications
function countUnreadNotifications(notifications) {
  if (!notifications) return 0;
  
  let unreadCount = 0;
  Object.values(notifications).forEach(block => {
    Object.values(block).forEach(zone => {
      zone.forEach(notification => {
        // Count notifications with status 'pending' as unread
        if (notification.status === 'pending') {
          unreadCount++;
        }
      });
    });
  });
  
  return unreadCount;
}
  // مدیریت تب‌های اعلان

  document.querySelectorAll(".notification-tab-button").forEach((button) => {
    button.addEventListener("click", () => {
      document
        .querySelectorAll(".notification-tab-button, .notification-tab-content")
        .forEach((el) => el.classList.remove("active"));

      button.classList.add("active");

      document
        .getElementById(button.dataset.tab + "-content")
        .classList.add("active");

      // NEW: Mark notifications as read when opening notifications tab
      if (button.dataset.tab === "notifications") {
        markNotificationsAsReadForTab();
      }
    });
});

function markNotificationsAsReadForTab() {
  const localNotificationBadge = document.getElementById("notification-count-badge");
  
  fetch("/pardis/api/mark_notifications_as_read.php", {
    method: "POST",
  })
    .then((res) => res.json())
    .then((data) => {
      if (data.status === "success") {
        console.log(`${data.marked_count || 0} notifications marked as read from tab click.`);
        
        // Hide badge immediately
        if (localNotificationBadge) {
          localNotificationBadge.style.display = "none";
        }
        
        // Update parent frame badge
        const headerBadge = window.parent?.document.getElementById("notification-badge");
        if (headerBadge && data.marked_count > 0) {
          const currentCount = parseInt(headerBadge.textContent || 0);
          const newCount = Math.max(0, currentCount - data.marked_count);
          headerBadge.textContent = newCount;
          if (newCount === 0) {
            headerBadge.style.display = "none";
          }
        }
        
        // CRITICAL: Store timestamp
        localStorage.setItem('notifications_last_read', Date.now().toString());
      }
    })
    .catch((err) => {
      console.error("Error marking notifications as read:", err);
    });
}
  // --- مدیریت کلیک روی دکمه "اتمام وظیفه" (این کد را جداگانه قرار می‌دهیم) ---

  // We use event delegation on a parent container for efficiency

  document
    .getElementById("tasks-accordion")
    ?.addEventListener("click", function (e) {
      if (e.target.classList.contains("task-action-btn")) {
        const taskId = e.target.dataset.taskId;
        const button = e.target;
        button.disabled = true;
        button.textContent = "...";
        fetch("/pardis/api/complete_task.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
          },
          body: JSON.stringify({
            task_id: taskId,
          }),
        })
          .then((res) => res.json())
          .then((response) => {
            if (response.status === "success") {
              const taskItem = button.closest(".notification-item");
              if (taskItem) {
                // انیمیشن محو شدن برای حذف آیتم
                taskItem.style.transition = "opacity 0.5s, max-height 0.5s";
                taskItem.style.opacity = "0";
                taskItem.style.maxHeight = "0px";
                taskItem.style.padding = "0";
                setTimeout(() => taskItem.remove(), 500);
              }
              // به‌روزرسانی شمارنده وظایف
              if (taskCountBadge) {
                const currentCount = parseInt(taskCountBadge.textContent || 0);
                const newCount = Math.max(0, currentCount - 1);
                taskCountBadge.textContent = newCount;
                if (newCount === 0) taskCountBadge.style.display = "none";
              }
            } else {
              alert(
                "خطا در تکمیل وظیفه: " + (response.message || "خطای نامشخص")
              );
              button.disabled = false;
              button.textContent = "اتمام وظیفه";
            }
          })
          .catch((err) => {
            console.error("Error completing task:", err);
            alert("خطا در اتصال به سرور");
            button.disabled = false;
            button.textContent = "اتمام وظیفه";
          });
      }
    });
  // ===================================================================
  // 7. Window Resize and Mobile Handling
  // ===================================================================
  window.addEventListener("resize", function () {
    const isMobile = window.innerWidth <= 768;
    if (!isMobile) {
      // Reset mobile states on desktop
      if (filtersPanel) {
        filtersPanel.classList.remove("expanded");
      }
      if (filtersChevron) {
        filtersChevron.style.transform = "rotate(0deg)";
      }
      if (
        notificationsSection &&
        notificationsSection.classList.contains("active")
      ) {
        notificationsSection.classList.remove("active");

        if (mobileToggle) {
          const icon = mobileToggle.querySelector("i");

          if (icon) icon.className = "fa fa-bell";
        }
      }
    }
    initMobileFilters();
  });
  // ===================================================================
  // 8. Initialize Everything
  // ===================================================================
      restoreState();
        if (filtersToggle)
          filtersToggle.addEventListener("click", () =>
            filtersPanel.classList.toggle("expanded")
          );
      });
</script>

</body>
</html>