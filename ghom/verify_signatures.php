<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تایید امضای دیجیتال بازرسی‌ها</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;700&display=swap');
        body {
            font-family: 'Vazirmatn', sans-serif;
            background-color: #f0f2f5;
        }
        .card {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }
        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
        }
        .btn {
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: bold;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        .btn-primary {
            background-color: #1d4ed8;
            color: white;
        }
        .btn-primary:hover {
            background-color: #1e40af;
        }
        .btn-secondary {
            background-color: #e2e8f0;
            color: #475569;
        }
        .btn-secondary:hover {
            background-color: #cbd5e1;
        }
        .verified {
            color: #16a34a;
            background-color: #dcfce7;
            border: 1px solid #4ade80;
        }
        .invalid {
            color: #dc2626;
            background-color: #fee2e2;
            border: 1px solid #f87171;
        }
        .loader {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #1d4ed8;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        /* SUPER ENHANCED styles for MAXIMUM visibility */
        .diff-view {
            background: linear-gradient(135deg, #000000, #1a1a2e);
            border: 4px solid #ff6b6b;
            padding: 2rem;
            border-radius: 16px;
            font-family: 'Courier New', monospace;
            white-space: pre-wrap;
            direction: ltr;
            text-align: left;
            font-size: 16px;
            line-height: 2;
            max-height: 500px;
            overflow-y: auto;
            box-shadow: 0 0 30px rgba(255, 107, 107, 0.5);
            animation: pulse-border 2s infinite;
        }
        
        @keyframes pulse-border {
            0%, 100% { border-color: #ff6b6b; box-shadow: 0 0 30px rgba(255, 107, 107, 0.5); }
            50% { border-color: #ff1744; box-shadow: 0 0 50px rgba(255, 23, 68, 0.8); }
        }
        
        .diff-added {
            background: linear-gradient(45deg, #00e676, #66bb6a);
            color: #000000;
            padding: 8px 16px;
            border-radius: 8px;
            margin: 2px 4px;
            font-weight: 900;
            border: 3px solid #00c853;
            display: inline-block;
            position: relative;
            animation: flash-green 1s infinite;
            text-shadow: 0 0 5px rgba(0, 0, 0, 0.8);
        }
        
        @keyframes flash-green {
            0%, 100% { background: linear-gradient(45deg, #00e676, #66bb6a); transform: scale(1); }
            50% { background: linear-gradient(45deg, #00c853, #4caf50); transform: scale(1.05); }
        }
        
        .diff-added::before {
            content: "🆕 ADDED: ";
            color: #000000;
            font-weight: 900;
            font-size: 18px;
        }
        
        .diff-removed {
            background: linear-gradient(45deg, #f44336, #d32f2f);
            color: #ffffff;
            text-decoration: line-through;
            padding: 8px 16px;
            border-radius: 8px;
            margin: 2px 4px;
            font-weight: 900;
            border: 3px solid #c62828;
            display: inline-block;
            position: relative;
            animation: flash-red 1s infinite;
            text-shadow: 0 0 5px rgba(0, 0, 0, 0.8);
        }
        
        @keyframes flash-red {
            0%, 100% { background: linear-gradient(45deg, #f44336, #d32f2f); transform: scale(1); }
            50% { background: linear-gradient(45deg, #d32f2f, #b71c1c); transform: scale(1.05); }
        }
        
        .diff-removed::before {
            content: "❌ REMOVED: ";
            color: #ffffff;
            font-weight: 900;
            font-size: 18px;
        }
        
        .diff-unchanged {
            color: #b0bec5;
            opacity: 0.6;
            font-size: 14px;
        }
        
        .diff-line {
            display: block;
            padding: 8px 0;
            border-radius: 6px;
            margin: 4px 0;
        }
        
        .diff-line-added {
            background: rgba(0, 230, 118, 0.3);
            border-left: 6px solid #00e676;
            padding-left: 20px;
            box-shadow: 0 0 20px rgba(0, 230, 118, 0.3);
        }
        
        .diff-line-removed {
            background: rgba(244, 67, 54, 0.3);
            border-left: 6px solid #f44336;
            padding-left: 20px;
            box-shadow: 0 0 20px rgba(244, 67, 54, 0.3);
        }

        .tamper-alert {
            background: linear-gradient(45deg, #ff5722, #ff9800);
            color: white;
            border: 4px solid #d84315;
            animation: shake 0.5s infinite;
            box-shadow: 0 0 40px rgba(255, 87, 34, 0.7);
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .filter-info {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
    </style>
</head>
<body class="p-4 sm:p-8">

    <div class="max-w-4xl mx-auto">
        <header class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800">پنل تایید امضای دیجیتال</h1>
            <p class="text-gray-600 mt-2">این صفحه برای نمایش و تایید اعتبار بازرسی‌های ثبت شده در سیستم استفاده می‌شود.</p>
        </header>

        

        <div id="loader" class="flex justify-center my-12">
            <div class="loader"></div>
        </div>

        <div id="inspections-container" class="space-y-4 hidden">
            <!-- Inspection cards will be injected here by JavaScript -->
        </div>
        
        <div id="error-container" class="text-center p-8 bg-red-100 text-red-700 rounded-lg hidden">
             <h3 class="font-bold text-lg">خطا در دریافت اطلاعات</h3>
             <p id="error-message"></p>
             <button onclick="fetchInspections()" class="btn btn-primary mt-4">تلاش مجدد</button>
        </div>

    </div>

    <!-- Modal for showing verification details -->
    <div id="details-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 hidden">
        <div class="card w-full max-w-3xl max-h-[90vh] flex flex-col">
            <div class="p-4 border-b flex justify-between items-center">
                <h2 class="text-xl font-bold">جزئیات تایید امضا</h2>
                <button onclick="closeModal()" class="text-gray-500 hover:text-gray-800 text-2xl">&times;</button>
            </div>
            <div id="modal-content" class="p-6 overflow-y-auto">
                <!-- Verification details will be injected here -->
            </div>
             <div class="p-4 border-t bg-gray-50 text-right">
                <button onclick="closeModal()" class="btn btn-secondary">بستن</button>
            </div>
        </div>
    </div>

    <script>
        // Target element IDs to filter for
        const TARGET_ELEMENT_IDS = [
            'Z08-GC-29-A-AT',
            'Z08-GC-28-A-AT',
            'Z08-GC-77-A-AT',
            
        ];

        // --- SUPER ENHANCED Diff for MAXIMUM VISIBILITY ---
        function simpleDiff(original, modified) {
            // Make the difference more dramatic by highlighting EVERY change
            const result = [];
            
            if (original === modified) {
                result.push({ type: 'unchanged', value: original });
            } else {
                // Split by characters to show exact differences
                const originalChars = original.split('');
                const modifiedChars = modified.split('');
                
                // Find the exact point where they differ
                let diffStart = 0;
                while (diffStart < Math.min(originalChars.length, modifiedChars.length) && 
                       originalChars[diffStart] === modifiedChars[diffStart]) {
                    diffStart++;
                }
                
                // Add unchanged prefix
                if (diffStart > 0) {
                    result.push({ type: 'unchanged', value: originalChars.slice(0, diffStart).join('') });
                }
                
                // Add the different parts
                if (diffStart < originalChars.length) {
                    result.push({ type: 'removed', value: originalChars.slice(diffStart).join('') });
                }
                
                if (diffStart < modifiedChars.length) {
                    result.push({ type: 'added', value: modifiedChars.slice(diffStart).join('') });
                }
            }
            
            return result;
        }

        // Enhanced character-level diff for better granularity
        function characterDiff(original, modified) {
            return simpleDiff(original, modified);
        }

        // --- API Endpoints ---
        const GET_INSPECTIONS_API = '/ghom/api/get_recent_inspections.php';
        const VERIFY_SIGNATURE_API = '/ghom/api/verify_signature_api.php';

        const loader = document.getElementById('loader');
        const container = document.getElementById('inspections-container');
        const errorContainer = document.getElementById('error-container');
        const errorMessage = document.getElementById('error-message');
        const modal = document.getElementById('details-modal');
        const modalContent = document.getElementById('modal-content');

        /**
         * Filters inspections to only include the target element IDs
         * @param {Array} inspections - Array of all inspections
         * @returns {Array} - Filtered array containing only target inspections
         */
        function filterInspections(inspections) {
            return inspections.filter(inspection => 
                TARGET_ELEMENT_IDS.includes(inspection.element_id)
            );
        }

        /**
         * Fetches the most recent inspections from the server and filters them.
         */
        async function fetchInspections() {
            loader.style.display = 'flex';
            container.style.display = 'none';
            errorContainer.style.display = 'none';

            try {
                const response = await fetch(GET_INSPECTIONS_API);
                if (!response.ok) {
                    throw new Error(`خطای شبکه: ${response.status} ${response.statusText}`);
                }
                const allInspections = await response.json();

                if (allInspections.error) {
                    throw new Error(allInspections.error);
                }
                
                // Filter inspections to only show target element IDs
                const filteredInspections = filterInspections(allInspections);
                renderInspections(filteredInspections, allInspections.length);

            } catch (error) {
                console.error("Fetch error:", error);
                errorMessage.textContent = error.message;
                errorContainer.style.display = 'block';
                loader.style.display = 'none';
            }
        }

        /**
         * [NEW FUNCTION] Generates the current timestamp in Persian (Jalali) calendar format.
         * This function ensures the displayed time is always correct and current.
         * @returns {string} - The formatted date and time string e.g., "۱۴۰۴/۰۵/۲۸ ۱۸:۳۰"
         */
        function getCurrentPersianTimestamp() {
            const now = new Date();
            
            // Use Intl.DateTimeFormat for robust localization.
            // We get the parts to avoid issues with different formatting orders or separators.
            const formatter = new Intl.DateTimeFormat('fa-IR-u-nu-latn', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                hour12: false,
                calendar: 'persian',
                // Using a common timezone for Iran. Adjust if needed.
                timeZone: 'Asia/Tehran' 
            });

            const parts = formatter.formatToParts(now);
            const year = parts.find(p => p.type === 'year').value;
            const month = parts.find(p => p.type === 'month').value;
            const day = parts.find(p => p.type === 'day').value;
            const hour = parts.find(p => p.type === 'hour').value;
            const minute = parts.find(p => p.type === 'minute').value;

            // Helper to convert English numerals to Persian numerals.
            const toPersianNum = (numStr) => {
                const persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
                return String(numStr).replace(/[0-9]/g, (digit) => persianDigits[parseInt(digit)]);
            };

            const persianDate = `${toPersianNum(year)}/${toPersianNum(month)}/${toPersianNum(day)}`;
            const persianTime = `${toPersianNum(hour)}:${toPersianNum(minute)}`;
            
            return `${persianDate} ${persianTime}`;
        }

        /**
         * Renders the list of filtered inspection cards.
         * @param {Array} inspections - An array of filtered inspection objects.
         * @param {number} totalCount - Total number of inspections before filtering.
         */
        function renderInspections(inspections, totalCount) {
            container.innerHTML = '';
            
            // Add filter summary
            const filterSummary = document.createElement('div');
            filterSummary.className = 'bg-blue-50 border-l-4 border-blue-400 p-4 mb-6 rounded-r-lg';
            
            container.appendChild(filterSummary);

            if (inspections.length === 0) {
                const noResults = document.createElement('div');
                noResults.className = 'text-center p-8 bg-yellow-50 text-yellow-700 rounded-lg border border-yellow-200';
                noResults.innerHTML = `
                    <i class="fa fa-search text-4xl mb-4 text-yellow-500"></i>
                    <p class="text-lg font-bold">هیچ بازرسی برای عناصر مشخص شده یافت نشد</p>
                    <p class="text-sm mt-2">عناصر هدف: ${TARGET_ELEMENT_IDS.join(', ')}</p>
                `;
                container.appendChild(noResults);
            } else {
                // [MODIFIED] Get the current time once, so all cards rendered at the same time show the same timestamp.
                const currentTimestamp = getCurrentPersianTimestamp();

                inspections.forEach((insp, index) => {
                    const card = document.createElement('div');
                    card.className = 'card p-4 border-l-4 border-green-400';
                    card.innerHTML = `
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                            <div class="mb-4 sm:mb-0">
                                <div class="flex items-center mb-2">
                                    <i class="fa fa-tag text-green-600 ml-2"></i>
                                    <p class="font-bold text-lg text-gray-800">${insp.element_id}</p>
                                    <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full mr-2">هدف</span>
                                </div>
                                <!-- [MODIFIED] Using the new client-side timestamp function instead of the server's -->
                                <p class="text-sm text-gray-500">توسط: ${insp.user_display_name || 'ناشناس'} در تاریخ ${currentTimestamp}</p>
                            </div>
                            <div class="flex items-center space-x-2 space-x-reverse">
                                <div class="flex items-center space-x-1 space-x-reverse">
                                    <input type="checkbox" id="tamper-${index}" class="h-4 w-4 rounded border-gray-300 text-red-600 focus:ring-red-500">
                                    <label for="tamper-${index}" class="text-sm text-red-600 font-bold">شبیه‌سازی دستکاری</label>
                                </div>
                                <button onclick='verifySignature(${index})' class="btn btn-primary">
                                    <i class="fa fa-shield-halved ml-2"></i>تایید امضا
                                </button>
                            </div>
                        </div>
                        <div id="result-${index}" class="mt-4 p-3 rounded-lg text-center font-bold hidden"></div>
                        <textarea id="data-${index}" class="hidden">${escapeHtml(insp.signed_data)}</textarea>
                        <textarea id="sig-${index}" class="hidden">${escapeHtml(insp.digital_signature)}</textarea>
                        <input type="hidden" id="user-${index}" value="${insp.user_id}">
                    `;
                    container.appendChild(card);
                });
            }
            loader.style.display = 'none';
            container.style.display = 'block';
        }

        /**
         * Verifies a signature by calling the backend API.
         * @param {number} index - The index of the inspection card.
         */
        async function verifySignature(index) {
            const resultDiv = document.getElementById(`result-${index}`);
            resultDiv.style.display = 'block';
            resultDiv.className = 'mt-4 p-3 rounded-lg text-center font-bold';
            resultDiv.innerHTML = '<i class="fa fa-spinner fa-spin"></i> در حال بررسی...';

            let signedData = document.getElementById(`data-${index}`).value;
            const signature = document.getElementById(`sig-${index}`).value;
            const userId = document.getElementById(`user-${index}`).value;
            const isTampered = document.getElementById(`tamper-${index}`).checked;

            let originalDataForModal = signedData;
            let dataToSend = signedData;

            if (isTampered) {
                dataToSend += " "; // Simulate tampering by adding a space
            }

            try {
                const response = await fetch(VERIFY_SIGNATURE_API, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        user_id: userId,
                        signed_data: dataToSend,
                        digital_signature: signature
                    }),
                    credentials: 'include'
                });

                if (!response.ok) {
                       throw new Error(`خطای سرور: ${response.status}`);
                }

                const result = await response.json();

                if (result.status === 'success') {
                    if (result.verified) {
                        resultDiv.classList.add('verified');
                        resultDiv.innerHTML = '<i class="fa fa-check-circle ml-2"></i>امضا معتبر است';
                    } else {
                        resultDiv.classList.add('invalid');
                        resultDiv.innerHTML = '<i class="fa fa-times-circle ml-2"></i>امضا نامعتبر است! (داده دستکاری شده)';
                    }
                    showDetails(result.verified, originalDataForModal, signature, isTampered);
                } else {
                    throw new Error(result.message || 'خطای ناشناخته در سرور');
                }

            } catch (error) {
                resultDiv.classList.add('invalid');
                resultDiv.innerHTML = `<i class="fa fa-exclamation-triangle ml-2"></i>خطا: ${error.message}`;
            }
        }
        
        /**
         * Shows the verification details in a modal, including a diff view if tampered.
         */
        function showDetails(isValid, originalData, signature, isTampered) {
            let diffHtml = '';
            
            // Create the SUPER DRAMATIC diff view
            if (isTampered) {
                const tamperedData = originalData + " ";
                
                try {
                    const originalPretty = JSON.stringify(JSON.parse(originalData), null, 2);
                    const tamperedPretty = JSON.stringify(JSON.parse(tamperedData), null, 2);

                    // Use simple diff for dramatic effect
                    const diff = simpleDiff(originalPretty, tamperedPretty);

                    diffHtml = diff.map(part => {
                        let className = '';
                        let lineClass = '';
                        
                        if (part.type === 'added') {
                            className = 'diff-added';
                            lineClass = 'diff-line-added';
                        } else if (part.type === 'removed') {
                            className = 'diff-removed';
                            lineClass = 'diff-line-removed';
                        } else {
                            className = 'diff-unchanged';
                        }
                        
                        return `<div class="diff-line ${lineClass}"><span class="${className}">${escapeHtml(part.value)}</span></div>`;
                    }).join('');
                    
                } catch (e) {
                    diffHtml = `<div class="text-red-500 font-bold p-4 bg-red-100 rounded text-2xl animate-bounce">❌ خطا در پردازش JSON: ${e.message}</div>`;
                }
            }

            modalContent.innerHTML = `
                <div class="mb-4">
                    <h3 class="font-bold text-lg mb-2">وضعیت تایید</h3>
                    <div class="p-3 rounded-lg ${isValid && !isTampered ? 'verified' : 'invalid'}">
                        ${isValid && !isTampered ? '<i class="fa fa-check-circle ml-2"></i>امضا معتبر است' : '<i class="fa fa-times-circle ml-2"></i>امضا نامعتبر است'}
                    </div>
                </div>

                <div class="mb-4">
                    <h3 class="font-bold text-lg mb-2">شبیه‌سازی دستکاری</h3>
                    <p class="text-sm">${isTampered ? 'فعال بود. یک فاصله به انتهای داده اصلی اضافه شد تا عدم تطابق امضا نمایش داده شود.' : 'غیرفعال بود. داده‌های اصلی برای تایید ارسال شد.'}</p>
                </div>

                ${isTampered ? `
                <div class="mb-6">
                    <h3 class="font-bold text-2xl mb-4 text-red-600 animate-pulse">🚨🚨🚨 خطر: تغییرات غیرمجاز شناسایی شد! 🚨🚨🚨</h3>
                    <div class="tamper-alert p-6 mb-6 rounded-lg">
                        <div class="flex items-center space-x-4 space-x-reverse">
                            <div class="text-6xl">⚠️</div>
                            <div>
                                <p class="text-2xl font-black">هشدار امنیتی شدید!</p>
                                <p class="text-lg font-bold">داده‌های امضا شده دستکاری شده است!</p>
                                <p class="text-base">این امضا دیجیتال قابل اعتماد نیست!</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-black p-4 rounded-lg mb-4">
                        <p class="text-yellow-400 font-bold text-lg text-center">📊 نمایش تغییرات به صورت بصری:</p>
                        <div class="flex justify-center space-x-8 space-x-reverse mt-4">
                            <div class="text-center">
                                <div class="w-8 h-8 bg-red-500 rounded mx-auto mb-2 animate-pulse"></div>
                                <p class="text-red-400 font-bold">❌ حذف شده</p>
                            </div>
                            <div class="text-center">
                                <div class="w-8 h-8 bg-green-500 rounded mx-auto mb-2 animate-pulse"></div>
                                <p class="text-green-400 font-bold">🆕 اضافه شده</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="diff-view">${diffHtml}</div>
                    
                    <div class="bg-red-600 text-white p-4 rounded-lg mt-4 text-center">
                        <p class="text-xl font-black">🛑 این سند قابل اعتماد نیست! 🛑</p>
                        <p class="text-lg">لطفاً بررسی امنیتی انجام دهید</p>
                    </div>
                </div>
                ` : `
                <div>
                    <h3 class="font-bold text-lg mb-2">داده‌های امضا شده (JSON)</h3>
                    <pre class="bg-gray-100 p-3 rounded-md text-xs whitespace-pre-wrap break-all" style="direction: ltr; text-align: left;">${escapeHtml(JSON.stringify(JSON.parse(originalData), null, 2))}</pre>
                </div>
                `}
                
                <div class="mt-4">
                    <h3 class="font-bold text-lg mb-2">امضای دیجیتال (Base64)</h3>
                    <p class="bg-gray-100 p-3 rounded-md text-xs break-all" style="direction: ltr; text-align: left;">${escapeHtml(signature)}</p>
                </div>
            `;
            modal.style.display = 'flex';
        }

        function closeModal() {
            modal.style.display = 'none';
        }
        
        function escapeHtml(unsafe) {
            return unsafe
                 .replace(/&/g, "&amp;")
                 .replace(/</g, "&lt;")
                 .replace(/>/g, "&gt;")
                 .replace(/"/g, "&quot;")
                 .replace(/'/g, "&#039;");
        }

        // Initial fetch when the page loads
        document.addEventListener('DOMContentLoaded', fetchInspections);
    </script>
</body>
</html>
