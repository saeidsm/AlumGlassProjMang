/* Extracted from pardis/daily_reports.php during Phase 2C.
 * Concatenates 1 inline <script> block(s).
 */

const projectData = {
            "پروژه دانشگاه خاتم پردیس": {
            "ساختمان کتابخانه": [
            'کرتین وال طبقه سوم', 'پهنه بتنی غرب و جنوب', 'ویندووال طبقه 1', 'ویندووال طبقه 2',
            'ویندووال طبقه همکف', 'اسکای لایت', 'کاور بتنی ستونها', 'هندریل',
            'نقشه‌های ساخت فلاشینگ، دودبند و سمنت بورد'
            ],
            "ساختمان دانشکده کشاورزی": [
            'ویندووال طبقه 3 غرب', 'کرتین وال طبقه 1 و 2 غرب', 'کرتین‌‌وال طبقه 2 و 3 غرب',
            'ویندووال طبقه اول شمال بین محور B~F', 'کرتین‌وال طبقه دوم و سوم شمال بین محور C~F',
            'ویندووال طبقه دوم شمال بین محور B~C', 'ویندووال طبقه سوم شمال بین محور A~B',
            'کرتین‌وال طبقه 1و2 شمال بین محور A~B', 'ویندووال طبقه همکف شرق',
            'ویندووال طبقه اول شرق بین محور 11 تا 18', 'کرتین وال طبقه 2 و 3 شرق بین محورهای 12 تا 17',
            'کرتین‌وال طبقه 1 تا 3 شرق بین محورهای 11 تا 12', 'کرتین‌وال طبقه 1 تا 3 شرق بین محورهای 7 تا 8',
            'کرتین‌وال طبقه 1 تا 2 شرق بین محورهای 2 تا 7', 'کرتین‌وال طبقه همکف تا سوم شرق میانی',
            'ویندووال طبقه اول جنوب', 'ویندووال طبقه دوم جنوب', 'ویندووال طبقه سوم جنوب',
            'نمای بتنی غرب', 'نمای آجری غرب', 'پنل‌های بتنی شرق', 'پنل‌های آجری شرق',
            'پنل‌های آجری شمال', 'پنل‌های بتنی شمال', 'پنل‌های بتنی جنوب', 'پنل‌های آجری جنوب',
            'نمای کلمپ ویو', 'هندریل', 'ویندووال داخلی طبقه 3', 'اسکای لایت'
            ],
            "هر دو ساختمان": [], // Empty array will trigger custom input
            "عمومی": [] // Empty array will trigger custom input
            }

            // You can add more projects here in the future
            };

 document.addEventListener('DOMContentLoaded', function() {
        // Initialize Jalali DatePicker - THIS IS THE PART YOU WERE LOOKING FOR
        jalaliDatepicker.startWatch({
            persianDigits: true, autoShow: true, autoHide: true, hideAfterChange: true,
            date: true, time: false, zIndex: 2000
        });

        // Add the first activity row automatically
        addActivity();
loadPendingTasks();
      loadPreviousDayPlan();  
 checkSubmissionStatus(); 
  checkMissedSubmissions();
        loadReportsList();
        loadTaskCounts();
        calculateWorkHours();
        
        // Add event listeners for filters and tabs
        document.getElementById('filterDate')?.addEventListener('change', loadReportsList);
        document.getElementById('filterRole')?.addEventListener('change', loadReportsList);
        document.getElementById('dashboard-tab')?.addEventListener('shown.bs.tab', loadDashboardData);
    });

function updateProjectLocations() {
    const projectSelect = document.getElementById('projectBuilding');
    const locationSelect = document.getElementById('locationSelect');
    const selectedProject = projectSelect.value;

    locationSelect.innerHTML = '<option value="">انتخاب کنید...</option>';

    if (selectedProject && projectLocations[selectedProject]) {
        const options = projectLocations[selectedProject].options;
        options.forEach(option => {
            const opt = document.createElement('option');
            opt.value = option;
            opt.textContent = option;
            locationSelect.appendChild(opt);
        });

        if (options.length === 0) {
            document.getElementById('customLocationCheck').checked = true;
            toggleCustomLocation();
        }
    }
}

function loadPreviousDayPlan() {
    fetch('daily_report_api.php?action=get_previous_day_plan')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.tasks && data.tasks.length > 0) {
                showPlannedTasksModal(data.tasks);
            }
        })
        .catch(error => console.error('Error loading previous day plan:', error));
}

/**
 * Creates and displays the modal for the "Tomorrow's Plan" tasks.
 */
function showPlannedTasksModal(tasks) {
    const modalHtml = `
        <div class="modal fade" id="plannedTasksModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-info text-white">
                        <h5 class="modal-title"><i class="bi bi-calendar-check"></i> برنامه شما از روز قبل</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>این موارد را از برنامه دیروز خود ثبت کرده‌اید. موارد مورد نظر را برای افزودن به گزارش امروز انتخاب کنید:</p>
                        <ul class="list-group" id="plannedTasksList">
                            ${tasks.map((task, index) => `
                                <li class="list-group-item">
                                    <input class="form-check-input me-2" type="checkbox" value="" id="planned_task_${index}">
                                    <label class="form-check-label" for="planned_task_${index}">
                                        ${task}
                                    </label>
                                </li>
                            `).join('')}
                        </ul>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">نادیده گرفتن</button>
                        <button type="button" class="btn btn-primary" onclick="addSelectedPlannedTasks()">افزودن به گزارش</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Ensure no old modal exists before adding a new one
    const existingModal = document.getElementById('plannedTasksModal');
    if (existingModal) existingModal.remove();

    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const modalEl = new bootstrap.Modal(document.getElementById('plannedTasksModal'));
    modalEl.show();
}
function addSelectedPlannedTasks() {
    const checkboxes = document.querySelectorAll('#plannedTasksList input[type="checkbox"]:checked');
    
    checkboxes.forEach(checkbox => {
        const taskDescription = checkbox.nextElementSibling.textContent.trim();
        
        // Call the existing function to create a new activity block
        addActivity(); 
        
        // Get the description input of the newly created activity and fill it
        const newActivityDescriptionInput = document.querySelector(`#activity-${activityCount} input[name*="[description]"]`);
        if (newActivityDescriptionInput) {
            newActivityDescriptionInput.value = taskDescription;
        }
    });

    // Hide the modal after adding the tasks
    const modalInstance = bootstrap.Modal.getInstance(document.getElementById('plannedTasksModal'));
    if (modalInstance) {
        modalInstance.hide();
    }
}
                                        function toggleCustomLocation() {
                                            const checkbox = document.getElementById('customLocationCheck');
                                            const customInput = document.getElementById('customLocation');
                                            const locationSelect = document.getElementById('locationSelect');

                                            if (checkbox.checked) {
                                                customInput.style.display = 'block';
                                                customInput.required = true;
                                                locationSelect.required = false;
                                                locationSelect.disabled = true;
                                            } else {
                                                customInput.style.display = 'none';
                                                customInput.required = false;
                                                locationSelect.required = true;
                                                locationSelect.disabled = false;
                                            }
                                        }

                                        // Set default project on load
                                        document.addEventListener('DOMContentLoaded', function() {
    const projectSelect = document.getElementById('projectName');
    const buildingSelect = document.getElementById('buildingName');
    const partSelect = document.getElementById('buildingPart');
    const customPartCheck = document.getElementById('customPartCheck');
    const customPartInput = document.getElementById('customBuildingPart');

    // Only proceed if elements exist
    if (buildingSelect && partSelect) {
        // Set initial disabled states
        buildingSelect.disabled = true;
        partSelect.disabled = true;
    }

    // Add event listeners only if elements exist
    if (projectSelect) {
        // Add default option
        projectSelect.options[0] = new Option('انتخاب پروژه...', '');
        
        // Add project options
        for (const projectName in projectData) {
            projectSelect.options[projectSelect.options.length] = new Option(projectName, projectName);
        }
        
        // Set default value
        const defaultProject = "پروژه دانشگاه خاتم پردیس";
        projectSelect.value = defaultProject;

        // Event listener for project change
        projectSelect.addEventListener('change', function() {
            if (buildingSelect && partSelect) {
                const selectedProject = this.value;
                buildingSelect.innerHTML = '<option value="">انتخاب ساختمان...</option>';
                partSelect.innerHTML = '<option value="">ابتدا ساختمان را انتخاب کنید...</option>';
                buildingSelect.disabled = !selectedProject;
                partSelect.disabled = true;

                if (selectedProject && projectData[selectedProject]) {
                    const buildings = projectData[selectedProject];
                    for (const buildingName in buildings) {
                        buildingSelect.options[buildingSelect.options.length] = new Option(buildingName, buildingName);
                    }
                }
            }
        });
    }

    if (buildingSelect) {
        buildingSelect.addEventListener('change', function() {
            // Building change handler code...
        });
    }

    if (customPartCheck) {
        customPartCheck.addEventListener('change', toggleCustomPart);
    }
});
