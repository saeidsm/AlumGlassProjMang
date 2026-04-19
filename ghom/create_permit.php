<?php
// ghom/create_permit.php
require_once __DIR__ . '/../sercon/bootstrap.php';
requireRole(['admin', 'superuser', 'cat', 'crs']);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>ایجاد مجوز</title>
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/ghom/assets/css/jalalidatepicker.min.css" />
    <style>
        body { font-family: Tahoma; padding: 20px; background: #f8f9fa; }
        /* Fix Datepicker Z-Index */
        jdp-container { z-index: 999999 !important; }
    </style>
</head>
<body>
    <div class="card shadow">
        <div class="card-header bg-warning text-dark">
            <h5>1. ثبت اولیه و دریافت فرم چاپ</h5>
        </div>
        <div class="card-body">
            <form id="permitForm">
                <input type="hidden" name="element_ids" id="element_ids">
                <input type="hidden" name="parts_json" id="parts_json">
                <input type="hidden" name="zone" id="zone">
                <input type="hidden" name="block" id="block">
                <input type="hidden" name="plan_file" id="plan_file">
                
                <div class="alert alert-info">
                    <strong>تعداد المان:</strong> <span id="countDisplay">0</span>
                </div>

                <div class="mb-3">
                    <label class="form-label">تاریخ درخواست (الزامی):</label>
                    <!-- Added autocomplete="off" to prevent browser suggestions hiding picker -->
                    <input type="text" name="permit_date" id="permit_date" class="form-control" data-jdp required placeholder="کلیک کنید..." autocomplete="off">
                </div>

                <div class="mb-3">
                    <label class="form-label">شرح عملیات:</label>
                    <textarea name="notes" id="notes" class="form-control" rows="3" required placeholder="مثال: اصلاح زیرسازی..."></textarea>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2">ثبت و دریافت فرم چاپ 🖨️</button>
            </form>
        </div>
    </div>

    <!-- Ensure correct path to JS -->
    <script src="/ghom/assets/js/jalalidatepicker.min.js"></script>
    <script>
        // Init Date Picker with High Z-Index
        jalaliDatepicker.startWatch({
            zIndex: 9999999,
            hideAfterChange: true,
            autoShow: true
        });

        document.addEventListener("DOMContentLoaded", () => {
            const dataStr = sessionStorage.getItem('tempPermitData');
            if(!dataStr) {
                alert("اطلاعات یافت نشد.");
                window.close();
                return;
            }
            const permitData = JSON.parse(dataStr);
            
            document.getElementById('element_ids').value = permitData.ids.join(',');
            document.getElementById('parts_json').value = JSON.stringify(permitData.parts || []);
            document.getElementById('zone').value = permitData.zone;
            document.getElementById('block').value = permitData.block;
            document.getElementById('plan_file').value = permitData.plan;
            document.getElementById('countDisplay').innerText = permitData.ids.length;
            if(permitData.notes) document.getElementById('notes').value = permitData.notes;
        });

        document.getElementById('permitForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            // Validate Date
            const dateVal = document.getElementById('permit_date').value;
            if(!dateVal) {
                alert("لطفا تاریخ را انتخاب کنید.");
                return;
            }

            const formData = new FormData(e.target);
            const btn = e.target.querySelector('button');
            btn.disabled = true;
            btn.innerText = "در حال ثبت...";

            try {
                const res = await fetch('/ghom/api/save_permit.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await res.json();
                
                if(result.status === 'success') {
                    window.open('/ghom/print_permit.php?id=' + result.permit_id, '_blank');
                    if(window.opener) window.opener.location.reload();
                    window.close();
                } else {
                    alert("خطا: " + result.message);
                    btn.disabled = false;
                    btn.innerText = "ثبت و دریافت فرم چاپ 🖨️";
                }
            } catch (err) {
                console.error(err);
                alert("خطا در ارتباط با سرور");
                btn.disabled = false;
                btn.innerText = "ثبت و دریافت فرم چاپ 🖨️";
            }
        });
    </script>
</body>
</html>