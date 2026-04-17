<?php
// /public_html/ghom/contractor_opening_request.php
// This file allows contractors to submit opening requests for panels and confirm opened panels.
require_once __DIR__ . '/../../sercon/bootstrap.php';
secureSession();
if (!isLoggedIn()) {
    header('Location: /login.php?msg=login_required');
    exit();
}
// Allow contractors, admins, and superusers
if (!in_array($_SESSION['role'], ['admin', 'superuser', 'cat', 'car', 'coa', 'crs'])) {
    http_response_code(403);
    require 'Access_Denied.php';
    exit;
}
$pageTitle = "درخواست بازگشایی پانل";
require_once __DIR__ . '/header.php';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <title><?php echo escapeHtml($pageTitle); ?></title>
    <link rel="stylesheet" href="/ghom/assets/css/jalalidatepicker.min.css" />
    <style>
        /* Add some basic styles for the new layout */
        body {
            font-family: "Samim", sans-serif;
            background-color: #f4f7f6;
            direction: rtl;
        }

        .main-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            padding: 20px;
            max-width: 1600px;
            margin: auto;
        }

        .svg-wrapper {
            flex: 3;
            min-width: 600px;
        }

        .side-panel {
            flex: 1;
            min-width: 320px;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        #svgContainer {
            width: 100%;
            height: 70vh;
            border: 1px solid #ccc;
            background: #e9ecef;
        }

        .mode-switcher {
            text-align: center;
            margin-bottom: 20px;
        }

        .mode-switcher button {
            padding: 10px 20px;
            font-size: 16px;
            cursor: pointer;
            border: 1px solid #ccc;
            background: #f0f0f0;
        }

        .mode-switcher button.active {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        .submit-btn {
            width: 100%;
            padding: 12px;
            font-size: 16px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .element-selected {
            stroke: #ffc107 !important;
            stroke-width: 3px !important;
        }

        .highlight-approved {
            fill: rgba(25, 135, 84, 0.8) !important;
            stroke: #fff !important;
            stroke-width: 2px !important;
        }

        .faded-out {
            opacity: 0.2;
            pointer-events: none;
        }
    </style>
</head>

<body>
    <div class="main-container">
        <div class="side-panel" id="side-panel">
        </div>
        <div class="svg-wrapper">
            <div class="mode-switcher">
                <button id="request-mode-btn" class="active">۱. ثبت درخواست بازگشایی</button>
                <button id="confirm-mode-btn">۲. تایید پانل‌های بازگشایی شده</button>
            </div>
            <div id="svgContainer"></div>
        </div>
    </div>

    <script src="/ghom/assets/js/jalalidatepicker.min.js"></script>
    <script type="module" src="/ghom/assets/js/shared_svg_logic.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // --- STATE ---
            let currentMode = 'request'; // 'request' or 'confirm'
            let selectedElements = new Map();

            // --- UI ELEMENTS ---
            const sidePanel = document.getElementById('side-panel');
            const requestModeBtn = document.getElementById('request-mode-btn');
            const confirmModeBtn = document.getElementById('confirm-mode-btn');
            const svgContainer = document.getElementById('svgContainer');

            // --- FUNCTIONS ---

            function renderSidePanel() {
                const selectionCount = selectedElements.size;
                let panelHTML = '';

                if (currentMode === 'request') {
                    panelHTML = `
                        <h3>ثبت درخواست بازگشایی</h3>
                        <p><strong>${selectionCount}</strong> پانل انتخاب شده</p>
                        <hr>
                        <div class="form-group">
                            <label for="request-date">تاریخ درخواست:</label>
                            <input type="text" id="request-date" data-jdp readonly>
                        </div>
                        <div class="form-group">
                            <label for="request-notes">یادداشت (اختیاری):</label>
                            <textarea id="request-notes" rows="4"></textarea>
                        </div>
                        <button id="submit-request-btn" class="submit-btn">ارسال درخواست</button>
                    `;
                } else { // confirm mode
                    panelHTML = `
                        <h3>تایید بازگشایی</h3>
                        <p>پانل هایی که بازگشایی آنها توسط مشاور تایید شده، انتخاب کنید.</p>
                        <p><strong>${selectionCount}</strong> پانل انتخاب شده</p>
                        <hr>
                        <button id="submit-confirm-btn" class="submit-btn" style="background-color: #0d6efd;">تایید بازگشایی پانل‌های انتخابی</button>
                    `;
                }

                sidePanel.innerHTML = panelHTML;
                jalaliDatepicker.startWatch(); // Re-initialize date picker

                // Add event listeners for the new buttons
                if (currentMode === 'request') {
                    document.getElementById('submit-request-btn').addEventListener('click', submitOpeningRequest);
                } else {
                    document.getElementById('submit-confirm-btn').addEventListener('click', confirmPanelsOpened);
                }
            }

            function setMode(mode) {
                currentMode = mode;
                selectedElements.clear(); // Clear selection when changing mode

                requestModeBtn.classList.toggle('active', mode === 'request');
                confirmModeBtn.classList.toggle('active', mode === 'confirm');

                renderSidePanel();
                recolorSVG();
            }

            function recolorSVG() {
                const allPanels = svgContainer.querySelectorAll('.interactive-element');
                if (currentMode === 'request') {
                    allPanels.forEach(el => el.classList.remove('faded-out', 'highlight-approved'));
                } else { // confirm mode
                    allPanels.forEach(el => {
                        // This uses the status from the dataset, which is loaded with the SVG
                        const status = window.currentPlanDbData[el.id]?.status;
                        if (status === 'Opening Approved') {
                            el.classList.remove('faded-out');
                            el.classList.add('highlight-approved');
                        } else {
                            el.classList.add('faded-out');
                            el.classList.remove('highlight-approved');
                        }
                    });
                }
            }

            async function submitOpeningRequest() {
                const element_ids = Array.from(selectedElements.keys());
                if (element_ids.length === 0) {
                    alert('لطفا ابتدا پانل‌های مورد نظر را انتخاب کنید.');
                    return;
                }
                const payload = {
                    element_ids: element_ids,
                    date: document.getElementById('request-date').value,
                    notes: document.getElementById('request-notes').value,
                };

                try {
                    const response = await fetch('api/submit_opening_request.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(payload)
                    });
                    const result = await response.json();
                    alert(result.message);
                    if (result.status === 'success') {
                        location.reload(); // Reload to see new statuses
                    }
                } catch (error) {
                    alert('خطا در ارسال درخواست.');
                }
            }

            async function confirmPanelsOpened() {
                const element_ids = Array.from(selectedElements.keys());
                if (element_ids.length === 0) {
                    alert('لطفا پانل‌هایی که بازگشایی شده‌اند را انتخاب کنید.');
                    return;
                }
                const payload = {
                    element_ids: element_ids
                };

                try {
                    const response = await fetch('api/confirm_panels_opened.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(payload)
                    });
                    const result = await response.json();
                    alert(result.message);
                    if (result.status === 'success') {
                        location.reload();
                    }
                } catch (error) {
                    alert('خطا در ثبت تایید بازگشایی.');
                }
            }

            // --- EVENT LISTENERS ---
            requestModeBtn.addEventListener('click', () => setMode('request'));
            confirmModeBtn.addEventListener('click', () => setMode('confirm'));

            // Listen for selection changes from your main SVG logic file
            document.addEventListener('selectionChanged', (e) => {
                selectedElements = e.detail.selectedElements;
                renderSidePanel();
            });

            // --- INITIALIZATION ---
            // Assuming your shared_svg_logic.js will load the SVG
            // Once it's loaded, we can set the initial state
            const observer = new MutationObserver((mutations, obs) => {
                if (document.querySelector("#svgContainer svg")) {
                    setMode('request'); // Set initial mode
                    obs.disconnect(); // Stop observing
                }
            });
            observer.observe(svgContainer, {
                childList: true
            });
        });
    </script>
</body>

</html>