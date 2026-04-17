///ghom/assets/js/ghom_app.js
const USER_ROLE = document.body.dataset.userRole;
//<editor-fold desc="Config and Global Variables">
let currentZoom = 1;
const zoomStep = 0.2;
const minZoom = 0.5;
const maxZoom = 40;
let currentSvgElement = null;
let isPanning = false;
let userPrivateKey = null;
let panStartX = 0,
  panStartY = 0,
  panX = 0,
  panY = 0;
let lastTouchDistance = 0;
let currentPlanFileName = "Plan.svg";
let currentPlanZoneName = "نامشخص";
let currentPlanDefaultContractor = "پیمانکار عمومی";
let currentPlanDefaultBlock = "بلوک عمومی";
// In ghom_app.js, at the top
let selectedElements = new Map();
let currentSvgHeight = 2200,
  currentSvgWidth = 3000;
let visibleStatuses = {
  OK: true,
  Reject: true,
  Repair: true,
  "Awaiting Re-inspection": true,
  "Pre-Inspection Complete": true,
  Pending: true,
};
let currentlyActiveSvgElement = null;
const SVG_BASE_PATH = "/ghom/"; // Use root-relative path
const STATUS_COLORS = {
  "Pre-Inspection Complete": "rgba(255, 140, 0, 0.8)", // Orange
  "Awaiting Re-inspection": "rgba(0, 191, 255, 0.8)", // Deep Sky Blue: Contractor is done, consultant's turn
  OK: "rgba(40, 167, 69, 0.7)", // Green
  Reject: "rgba(220, 53, 69, 0.7)", // Red
  Repair: "rgba(156, 39, 176, 0.7)", // Purple
  Pending: "rgba(108, 117, 125, 0.4)", // Grey
};
let currentPlanDbData = {};
const svgGroupConfig = {
  GFRC: {
    label: "GFRC",
    colors: { v: "rgba(13, 110, 253, 0.7)", h: "rgba(0, 150, 136, 0.75)" },
    defaultVisible: true,
    interactive: true,
    elementType: "GFRC",
  },
  Box_40x80x4: {
    label: "Box_40x80x4",
    defaultVisible: true,
    interactive: true,
    elementType: "Zirsazi",
  },
  Box_40x20: {
    label: "Box_40x20",
    defaultVisible: true,
    interactive: true,
    elementType: "Zirsazi",
  },
  tasme: {
    label: "تسمه",
    defaultVisible: true,
    interactive: true,
    elementType: "Zirsazi",
  },
  nabshi_tooli: {
    label: "نبشی طولی",
    defaultVisible: true,
    interactive: true,
    elementType: "Zirsazi",
  },
  Gasket: {
    label: "Gasket",
    defaultVisible: true,
    interactive: true,
    elementType: "Zirsazi",
  },
  SPACER: {
    label: "فاصله گذار",
    defaultVisible: true,
    interactive: true,
    elementType: "Zirsazi",
  },
  Smoke_Barrier: {
    label: "دودبند",
    defaultVisible: true,
    interactive: true,
    elementType: "Zirsazi",
  },
  uchanel: {
    label: "یو چنل",
    defaultVisible: true,
    interactive: true,
    elementType: "Zirsazi",
  },
  unolite: {
    label: "یونولیت",
    defaultVisible: true,
    interactive: true,
    elementType: "Zirsazi",
  },
  "GFRC-Part6": {
    label: "GFRC - قسمت 6",
    defaultVisible: true,
    interactive: true,
    elementType: "GFRC",
  },
  "GFRC-Part_4": {
    label: "GFRC - قسمت 4",
    defaultVisible: true,
    interactive: true,
    elementType: "GFRC",
  },
  Atieh: {
    label: "بلوک A- رس",
    color: "#0de16d",
    defaultVisible: true,
    interactive: true,
    contractor: "شرکت رس",
    block: "A",
    elementType: "Region",
    contractor_id: "crs",
  },
  org: {
    label: "بلوک - اورژانس A- رس",
    color: "#ebb00d",
    defaultVisible: true,
    interactive: true,
    contractor: "شرکت رس",
    block: "A - اورژانس",
    elementType: "Region",
    contractor_id: "crs",
  },
  rosB: {
    label: "بلوک B-رس",
    color: "#38abee",
    defaultVisible: true,
    interactive: true,
    contractor: "شرکت رس",
    block: "B",
    elementType: "Region",
    contractor_id: "crs",
  },
  rosC: {
    label: "بلوک C-عمران آذرستان",
    color: "#ee3838",
    defaultVisible: true,
    interactive: true,
    contractor: "شرکت عمران آذرستان",
    block: "C",
    elementType: "Region",
    contractor_id: "coa",
  },
  hayatOmran: {
    label: " حیاط عمران آذرستان",
    color: "#eef595da",
    defaultVisible: true,
    interactive: true,
    contractor: "شرکت عمران آذرستان",
    block: "حیاط",
    elementType: "Region",
    contractor_id: "coa",
  },
  hayatRos: {
    label: " حیاط رس",
    color: "#eb0de7da",
    defaultVisible: true,
    interactive: true,
    contractor: "شرکت ساختمانی رس",
    block: "حیاط",
    elementType: "Region",
    contractor_id: "crs",
  },
  handrail: {
    label: "نقشه ندارد",
    color: "rgba(238, 56, 56, 0.3)",
    defaultVisible: true,
    interactive: true,
  },
  "glass_40%": {
    label: "شیشه 40%",
    color: "rgba(173, 216, 230, 0.7)",
    defaultVisible: true,
    interactive: true,
    elementType: "Glass",
  },
  "glass_30%": {
    label: "شیشه 30%",
    color: "rgba(173, 216, 230, 0.7)",
    defaultVisible: true,
    interactive: true,
    elementType: "Glass",
  },
  "glass_50%": {
    label: "شیشه 50%",
    color: "rgba(173, 216, 230, 0.7)",
    defaultVisible: true,
    interactive: true,
    elementType: "Glass",
  },
  glass_opaque: {
    label: "شیشه مات",
    color: "rgba(144, 238, 144, 0.7)",
    defaultVisible: true,
    interactive: true,
    elementType: "Glass",
  },
  "glass_80%": {
    label: "شیشه 80%",
    color: "rgba(255, 255, 102, 0.7)",
    defaultVisible: true,
    interactive: true,
    elementType: "Glass",
  },
  Mullion: {
    label: "مولیون",
    color: "rgba(128, 128, 128, 0.9)",
    defaultVisible: true,
    interactive: true,
    elementType: "Mullion",
  },
  Transom: {
    label: "ترنزوم",
    color: "rgba(169, 169, 169, 0.9)",
    defaultVisible: true,
    interactive: true,
    elementType: "Transom",
  },
  Bazshow: {
    label: "بازشو",
    color: "rgba(169, 169, 169, 0.9)",
    defaultVisible: true,
    interactive: true,
    elementType: "Bazshow",
  },
  GLASS: {
    label: "شیشه",
    color: "#eef595da",
    defaultVisible: true,
    interactive: true,
    elementType: "Glass",
  },
  STONE: {
    label: "سنگ",
    color: "#4c28a1",
    defaultVisible: true,
    interactive: true,
    elementType: "STONE",
  },
  Zirsazi: {
    label: "زیرسازی",
    color: "#2464ee",
    defaultVisible: true,
    interactive: true,
    elementType: "Zirsazi",
  },
  Curtainwall: {
    label: "کرتین وال",
    color: "#4c28a1",
    defaultVisible: true,
    interactive: true,
    elementType: "Curtainwall",
  },
};
const regionToZoneMap = {
  Atieh: [
    {
      label: "زون 1 ( A رس)",
      svgFile: SVG_BASE_PATH + "Zone01AT.svg",
    },
    {
      label: "زون 2 ( A رس)",
      svgFile: SVG_BASE_PATH + "Zone02AT.svg",
    },
    {
      label: "زون 3 ( A رس)",
      svgFile: SVG_BASE_PATH + "Zone03AT.svg",
    },
    {
      label: "زون 4 ( A رس)",
      svgFile: SVG_BASE_PATH + "Zone04AT.svg",
    },
    {
      label: "زون 5 ( A رس)",
      svgFile: SVG_BASE_PATH + "Zone05AT.svg",
    },
    {
      label: "زون 6 ( A رس)",
      svgFile: SVG_BASE_PATH + "Zone06AT.svg",
    },
    {
      label: "زون 7 ( A رس)",
      svgFile: SVG_BASE_PATH + "Zone07AT.svg",
    },
    {
      label: "زون 8 ( A رس)",
      svgFile: SVG_BASE_PATH + "Zone08AT.svg",
    },
    {
      label: "زون 9 ( A رس)",
      svgFile: SVG_BASE_PATH + "Zone09AT.svg",
    },
    {
      label: "زون 10 ( A رس)",
      svgFile: SVG_BASE_PATH + "Zone10AT.svg",
    },
    {
      label: "زون 11 ( A رس)",
      svgFile: SVG_BASE_PATH + "Zone11AT.svg",
    },
    {
      label: "زون 12 ( A رس)",
      svgFile: SVG_BASE_PATH + "Zone12AT.svg",
    },
    {
      label: "زون 13 ( A رس)",
      svgFile: SVG_BASE_PATH + "Zone13AT.svg",
    },
    {
      label: "زون 14 ( A رس)",
      svgFile: SVG_BASE_PATH + "Zone14AT.svg",
    },
    {
      label: "زون 15 ( A رس)",
      svgFile: SVG_BASE_PATH + "Zone15AT.svg",
    },
    {
      label: "زون 16 ( A رس)",
      svgFile: SVG_BASE_PATH + "Zone16AT.svg",
    },
    {
      label: "زون 17 ( A رس)",
      svgFile: SVG_BASE_PATH + "Zone17AT.svg",
    },
    {
      label: "زون 18 ( A رس)",
      svgFile: SVG_BASE_PATH + "Zone18AT.svg",
    },
    {
      label: "زون 19 ( A رس)",
      svgFile: SVG_BASE_PATH + "Zone19AT.svg",
    },
  ],
  org: [
    {
      label: "زون اورژانس ",
      svgFile: SVG_BASE_PATH + "ZoneEmergency.svg",
    },
  ],
  rosB: [
    {
      label: "زون 1 (رس B)",
      svgFile: SVG_BASE_PATH + "Zone01ARJ.svg",
    },
    {
      label: "زون 2 (رس B)",
      svgFile: SVG_BASE_PATH + "Zone02ARJ.svg",
    },
    {
      label: "زون 3 (رس B)",
      svgFile: SVG_BASE_PATH + "Zone03ARJ.svg",
    },
    {
      label: "زون 11 (رس B)",
      svgFile: SVG_BASE_PATH + "Zone11ARJ.svg",
    },
    {
      label: "زون 12 (رس B)",
      svgFile: SVG_BASE_PATH + "Zone12ARJ.svg",
    },
    {
      label: "زون 13 (رس B)",
      svgFile: SVG_BASE_PATH + "Zone13ARJ.svg",
    },
    {
      label: "زون 14 (رس B)",
      svgFile: SVG_BASE_PATH + "Zone14ARJ.svg",
    },
    {
      label: "زون 16 (رس B)",
      svgFile: SVG_BASE_PATH + "Zone16ARJ.svg",
    },
    {
      label: "زون 19 (رس B)",
      svgFile: SVG_BASE_PATH + "Zone19ARJ.svg",
    },
    {
      label: "زون 20 (رس B)",
      svgFile: SVG_BASE_PATH + "Zone20ARJ.svg",
    },
    {
      label: "زون 21 (رس B)",
      svgFile: SVG_BASE_PATH + "Zone21ARJ.svg",
    },
    {
      label: "زون 26 (رس B)",
      svgFile: SVG_BASE_PATH + "Zone26ARJ.svg",
    },
  ],
  rosC: [
    {
      label: "زون 4 (C عمران آذرستان)",
      svgFile: SVG_BASE_PATH + "Zone04ARJ.svg",
    },
    {
      label: "زون 5 (C عمران آذرستان)",
      svgFile: SVG_BASE_PATH + "Zone05ARJ.svg",
    },
    {
      label: "زون 6 (C عمران آذرستان)",
      svgFile: SVG_BASE_PATH + "Zone06ARJ.svg",
    },
    {
      label: "زون 7E (C عمران آذرستان)",
      svgFile: SVG_BASE_PATH + "Zone07EARJ.svg",
    },
    {
      label: "زون 7S (C عمران آذرستان)",
      svgFile: SVG_BASE_PATH + "Zone07SARJ.svg",
    },
    {
      label: "زون 7N (C عمران آذرستان)",
      svgFile: SVG_BASE_PATH + "Zone07NARJ.svg",
    },
    {
      label: "زون 8 (C عمران آذرستان)",
      svgFile: SVG_BASE_PATH + "Zone08ARJ.svg",
    },
    {
      label: "زون 9 (C عمران آذرستان)",
      svgFile: SVG_BASE_PATH + "Zone09ARJ.svg",
    },
    {
      label: "زون 10 (C عمران آذرستان)",
      svgFile: SVG_BASE_PATH + "Zone10ARJ.svg",
    },
    {
      label: "زون 22 (C عمران آذرستان)",
      svgFile: SVG_BASE_PATH + "Zone22ARJ.svg",
    },
    {
      label: "زون 23 (C عمران آذرستان)",
      svgFile: SVG_BASE_PATH + "Zone23ARJ.svg",
    },
    {
      label: "زون 24 (C عمران آذرستان)",
      svgFile: SVG_BASE_PATH + "Zone24ARJ.svg",
    },
  ],
  hayatOmran: [
    {
      label: "زون 15 حیاط عمران آذرستان",
      svgFile: "Zone15ARJ.svg",
    },
    {
      label: "زون 16 حیاط عمران آذرستان",
      svgFile: "Zone16ARJ.svg",
    },
    {
      label: "زون 17 حیاط عمران آذرستان",
      svgFile: "Zone17ARJ.svg",
    },
    {
      label: "زون 18 حیاط عمران آذرستان",
      svgFile: "Zone18ARJ.svg",
    },
  ],
  hayatRos: [
    {
      label: "زون 11 حیاط رس ",
      svgFile: SVG_BASE_PATH + "Zone11ROS.svg",
    },
    {
      label: "زون 12 حیاط رس",
      svgFile: SVG_BASE_PATH + "Zone12ROS.svg",
    },
    {
      label: "زون 13 حیاط رس",
      svgFile: SVG_BASE_PATH + "Zone13ROS.svg",
    },
    {
      label: "زون 14 حیاط رس",
      svgFile: SVG_BASE_PATH + "Zone14ROS.svg",
    },
  ],
};

const planNavigationMappings = [
  {
    type: "textAndCircle",
    regex: /^(\d+|[A-Za-z]+[\d-]*)\s+Zone$/i,
    numberGroupIndex: 1,
    svgFilePattern: SVG_BASE_PATH + "Zone{NUMBER}.svg",
    labelPattern: "Zone {NUMBER}",
    defaultContractor: "پیمانکار پیش‌فرض زون عمومی",
    defaultBlock: "بلوک پیش‌فرض زون عمومی",
  },
  {
    svgFile: SVG_BASE_PATH + "Zone09AT.svg",
    label: "Zone 09",
    defaultContractor: "شرکت رس زون 09 ",
    defaultBlock: "بلوکA  زون 9 ",
  },
  {
    svgFile: SVG_BASE_PATH + "Plan.svg",
    label: "Plan اصلی",
    defaultContractor: "مدیر پیمان ",
    defaultBlock: "پروژه بیمارستان قم ",
  },
];
// Add this new constant to ghom_app.js
const planroles = {
  Atieh: {
    label: "بلوک A- رس",
    color: "#0de16d", // Green
    contractor: "شرکت رس",
    block: "A",
    contractor_id: "crs", // Access for CRS
  },
  org: {
    label: "بلوک - اورژانس A- رس",
    color: "#ebb00d", // Yellow
    contractor: "شرکت رس",
    block: "A - اورژانس",
    contractor_id: "crs", // Access for CRS
  },
  rosB: {
    label: "بلوک B-رس",
    color: "#38abee", // Blue
    contractor: "شرکت رس",
    block: "B",
    contractor_id: "crs", // Access for CRS
  },
  rosC: {
    label: "بلوک C-عمران آذرستان",
    color: "#ee3838", // Red
    contractor: "شرکت عمران آذرستان",
    block: "C",
    contractor_id: "coa", // Access for COA
  },
  hayatOmran: {
    label: " حیاط عمران آذرستان",
    color: "#eef595da", // Light Yellow
    contractor: "شرکت عمران آذرستان",
    block: "حیاط",
    contractor_id: "coa", // Access for COA
  },
  hayatRos: {
    label: " حیاط رس",
    color: "#eb0de7da", // Purple
    contractor: "شرکت ساختمانی رس",
    block: "حیاط",
    contractor_id: "crs", // Access for CRS (Assuming Ros Yard belongs to CRS)
  },
};
// In ghom_app.js

/**
 * Toggles the selection state of an SVG element.
 */
function toggleSelection(element) {
  const uniqueId = element.id;
  if (!uniqueId) return;

  if (selectedElements.has(uniqueId)) {
    selectedElements.delete(uniqueId);
    element.classList.remove("element-selected");
  } else {
    selectedElements.set(uniqueId, {
      element_id: uniqueId,
      element_type: element.dataset.elementType,
    });
    element.classList.add("element-selected");
  }
  updateBatchUiControls();
}

/**
 * Clears all current selections.
 */
function clearAllSelections() {
  document
    .querySelectorAll(".element-selected")
    .forEach((el) => el.classList.remove("element-selected"));
  selectedElements.clear();
  updateBatchUiControls();
}

/**
 * Updates the visibility and count of the batch selection UI bar.
 */
function updateBatchUiControls() {
  const bar = document.getElementById("batch-selection-bar");
  const countSpan = document.getElementById("selection-count");
  if (!bar || !countSpan) return;

  const count = selectedElements.size;
  countSpan.textContent = count;

  if (count > 0) {
    bar.style.display = "block";
  } else {
    bar.style.display = "none";
  }
}

// Add this CSS to your stylesheet for the selection highlight
/*
.element-selected {
    stroke: #0d6efd !important;
    stroke-width: 5px !important;
    stroke-opacity: 0.8 !important;
}
*/
//</editor-fold>
function showZoneSelectionMenu(regionKey, event) {
  closeAllForms();
  const zones = regionToZoneMap[regionKey];

  if (!zones || zones.length === 0) {
    console.warn(`No zones found for region key: ${regionKey}`);
    return;
  }

  const menu = document.createElement("div");
  menu.id = "zoneSelectionMenu";

  const regionConfig = svgGroupConfig[regionKey];
  const regionLabel = regionConfig ? regionConfig.label : "انتخاب زون";
  const title = document.createElement("h4");
  title.className = "zone-menu-title";
  title.textContent = `زون‌های موجود برای: ${regionLabel}`;
  menu.appendChild(title);

  const buttonGrid = document.createElement("div");
  buttonGrid.className = "zone-menu-grid";

  zones.forEach((zone) => {
    const menuItem = document.createElement("button");
    menuItem.textContent = zone.label;
    menuItem.onclick = (e) => {
      e.stopPropagation();
      loadAndDisplaySVG(zone.svgFile);
      closeZoneSelectionMenu();
    };
    buttonGrid.appendChild(menuItem);
  });
  menu.appendChild(buttonGrid);

  const closeButton = document.createElement("button");
  closeButton.textContent = "بستن منو";
  closeButton.className = "close-menu-btn";
  closeButton.onclick = (e) => {
    e.stopPropagation();
    closeZoneSelectionMenu();
  };
  menu.appendChild(closeButton);

  document.body.appendChild(menu);

  // --- NEW POSITIONING LOGIC ---
  // Position the menu based on the actual mouse click coordinates.
  let menuLeft = event.clientX;
  let menuTop = event.clientY + 10;

  menu.style.top = `${menuTop}px`;
  menu.style.left = `${menuLeft}px`;

  // This ensures the menu doesn't render off-screen.
  const menuRect = menu.getBoundingClientRect();
  const viewportWidth = window.innerWidth;

  if (menuLeft + menuRect.width > viewportWidth) {
    menu.style.left = `${viewportWidth - menuRect.width - 20}px`; // Move it left
  }
  // --- END NEW LOGIC ---

  setTimeout(
    () =>
      document.addEventListener("click", closeZoneMenuOnClickOutside, {
        once: true,
      }),
    0
  );
}
/**
 * Updates the information bar at the top with the current plan's details.
 * @param {string} zoneName - The name of the current zone/plan.
 * @param {string} contractor - The contractor for the zone.
 * @param {string} block - The block for the zone.
 */
function updateCurrentZoneInfo(zoneName, contractor, block) {
  const infoContainer = document.getElementById("currentZoneInfo");
  const zoneNameDisplay = document.getElementById("zoneNameDisplay");
  const contractorDisplay = document.getElementById("zoneContractorDisplay");
  const blockDisplay = document.getElementById("zoneBlockDisplay");
  const existingLink = infoContainer.querySelector(".edit-plan-link");
  if (existingLink) existingLink.remove();

  // If we don't have a zone name (i.e., we are on the main plan), hide the bar.
  if (!zoneName) {
    infoContainer.style.display = "none";
    return;
  }

  // Otherwise, populate the fields and show the bar.
  zoneNameDisplay.textContent = zoneName || "نامشخص";
  contractorDisplay.textContent = contractor || "نامشخص";
  blockDisplay.textContent = block || "نامشخص";
  infoContainer.style.display = "block"; // Make it visible
}
// NEW FUNCTION: Removes the zone selection menu from the page.
function closeZoneSelectionMenu() {
  const menu = document.getElementById("zoneSelectionMenu");
  if (menu) menu.remove();
  document.removeEventListener("click", closeZoneMenuOnClickOutside);
}

// NEW FUNCTION: Handles clicks outside the zone menu to close it.
function closeZoneMenuOnClickOutside(event) {
  const menu = document.getElementById("zoneSelectionMenu");
  if (menu && !menu.contains(event.target)) {
    closeZoneSelectionMenu();
  }
}

function clearActiveSvgElementHighlight() {
  if (currentlyActiveSvgElement) {
    currentlyActiveSvgElement.classList.remove("svg-element-active");
    currentlyActiveSvgElement = null;
  }
}

function closeAllForms() {
  document
    .querySelectorAll(".form-popup")
    .forEach((form) => (form.style.display = "none"));
  closeGfrcSubPanelMenu();
  closeZoneSelectionMenu(); // ADD THIS LINE
  clearActiveSvgElementHighlight();
}

function closeForm(formId) {
  const formPopup = document.getElementById(formId);
  if (formPopup) {
    formPopup.classList.remove("show");
  }
  if (currentlyActiveSvgElement) {
    currentlyActiveSvgElement.classList.remove("svg-element-active");
    currentlyActiveSvgElement = null;
  }
}

/**
 * Shows a menu of available GFRC parts by fetching the list LIVE from the database.
 * This ensures the menu is always accurate.
 */
async function showGfrcSubPanelMenu(
  clickedElement,
  dynamicContext,
  partNameToOpen = null
) {
  closeGfrcSubPanelMenu();

  if (partNameToOpen) {
    const fullElementId = `${clickedElement.dataset.uniquePanelId}-${partNameToOpen}`;
    openChecklistForm(
      fullElementId,
      dynamicContext.elementType,
      dynamicContext
    );
    return;
  }

  // Show a temporary loading message
  const tempMenu = document.createElement("div");
  tempMenu.id = "gfrcSubPanelMenu";
  tempMenu.innerHTML = `<div style="padding: 10px; color: #555;">در حال بارگذاری بخش‌ها...</div>`;
  document.body.appendChild(tempMenu);
  const rect = clickedElement.getBoundingClientRect();
  tempMenu.style.top = `${rect.bottom + window.scrollY}px`;
  tempMenu.style.left = `${rect.left + window.scrollX}px`;

  try {
    const baseElementId = clickedElement.dataset.uniquePanelId;
    // API call to get the parts for this element
    const response = await fetch(
      `/ghom/api/get_existing_parts.php?element_id=${baseElementId}`
    );
    if (!response.ok) throw new Error("Network response was not ok");

    const partsData = await response.json(); // API now returns [{part_name: "face", status: "OK"}, ...]

    closeGfrcSubPanelMenu();

    if (!Array.isArray(partsData) || partsData.length === 0) {
      alert("هیچ بخشی برای بازرسی این المان ثبت نشده است.");
      return;
    }

    const menu = document.createElement("div");
    menu.id = "gfrcSubPanelMenu";

    // NEW: Status translations for the menu
    const statusTranslations = {
      OK: "✓ تایید",
      Reject: "✗ رد شده",
      Repair: "⚠ نیاز به تعمیر",
      "Awaiting Re-inspection": "⏳ منتظر بازرسی",
      "Pre-Inspection Complete": "آماده",
      "In Progress": "در حال انجام",
      Pending: "معلق",
    };

    partsData.forEach((part) => {
      const partName = part.part_name;
      const partStatus = part.status || "Pending";

      const fullElementId = `${baseElementId}-${partName}`;
      const menuItem = document.createElement("button");

      // NEW: Display part name and its status
      menuItem.innerHTML = `چک لیست: ${partName} <span class="part-status-badge" style="background-color:${
        STATUS_COLORS[partStatus] || "#6c757d"
      }">${statusTranslations[partStatus] || partStatus}</span>`;

      menuItem.onclick = (e) => {
        e.stopPropagation();
        openChecklistForm(
          fullElementId,
          dynamicContext.elementType,
          dynamicContext
        );
        closeGfrcSubPanelMenu();
      };
      menu.appendChild(menuItem);
    });

    const closeButton = document.createElement("button");
    closeButton.textContent = "بستن منو";
    closeButton.className = "close-menu-btn";
    closeButton.onclick = (e) => {
      e.stopPropagation();
      closeGfrcSubPanelMenu();
    };
    menu.appendChild(closeButton);

    document.body.appendChild(menu);
    menu.style.top = `${rect.bottom + window.scrollY}px`;
    menu.style.left = `${rect.left + window.scrollX}px`;

    setTimeout(
      () =>
        document.addEventListener("click", closeGfrcMenuOnClickOutside, {
          once: true,
        }),
      0
    );
  } catch (error) {
    console.error("Failed to fetch GFRC parts:", error);
    closeGfrcSubPanelMenu();
    alert("خطا در دریافت لیست بخش‌های قابل بازرسی.");
  }
}
function closeGfrcSubPanelMenu() {
  const menu = document.getElementById("gfrcSubPanelMenu");
  if (menu) menu.remove();
  document.removeEventListener("click", closeGfrcMenuOnClickOutside);
}

function closeGfrcMenuOnClickOutside(event) {
  const menu = document.getElementById("gfrcSubPanelMenu");
  if (menu && !menu.contains(event.target)) {
    closeGfrcSubPanelMenu();
  }
}

// A helper function to escape HTML characters for security
function escapeHtml(unsafe) {
  if (typeof unsafe !== "string") return "";
  return unsafe
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}
/**
 * Saves a key-value pair to sessionStorage, converting value to JSON string.
 * @param {string} key The key to save under.
 * @param {*} value The value to save (will be stringified).
 */
function saveStateToSession(key, value) {
  try {
    sessionStorage.setItem(key, JSON.stringify(value));
  } catch (e) {
    console.error("Failed to save state to session storage:", e);
  }
}

/**
 * Loads and parses a JSON value from sessionStorage.
 * @param {string} key The key to load.
 * @returns {*} The parsed value or null if not found or invalid.
 */
function loadStateFromSession(key) {
  try {
    const value = sessionStorage.getItem(key);
    return value ? JSON.parse(value) : null;
  } catch (e) {
    console.error("Failed to load state from session storage:", e);
    return null;
  }
}

/**
 * Clears all relevant application state from sessionStorage.
 */
function clearSessionState() {
  sessionStorage.removeItem("lastViewedPlan");
  sessionStorage.removeItem("layerVisibility");
  sessionStorage.removeItem("statusVisibility");
}

/**
 * This is the NEW, database-driven function to open the GFRC checklist.
 * It completely replaces the old one.
 */
function setText(id, text) {
  const el = document.getElementById(id);
  if (el) {
    el.textContent = text || ""; // Use empty string as a fallback for null/undefined
  } else {
    console.error(
      `JavaScript Error: HTML element with ID '${id}' was not found.`
    );
  }
}

/**
 * Opens a dynamic, stage-based checklist form with a professional tabbed UI.
 * This is the definitive version for creating the inspection form.
 */
/**
 * CORRECTED AND FINAL VERSION - This will open the form.
 * It targets the correct HTML elements from the skeleton.
 */
// ===================================================================
// START: ENHANCED MULTI-SHAPE DRAWING MODULE (FIXED FREE DRAWING)
// ===================================================================

let fabricCanvas = null;
let currentDrawingTargetInput = null;
let isDrawing = false;
let drawingStartPoint = null;
let tempShape = null;
let currentTool = "line"; // Default tool
let freeDrawingPath = [];

// Helper function to safely get color from active tool
function getActiveToolColor(defaultColor = "#FF0000") {
  const activeTool = document.querySelector(".drawer-tools .tool-btn.active");
  if (!activeTool) return defaultColor;

  const toolItem = activeTool.closest(".tool-item");
  if (!toolItem) return defaultColor;

  const colorInput = toolItem.querySelector(".tool-color");
  return colorInput ? colorInput.value : defaultColor;
}

// Shape drawing handlers
const shapeHandlers = {
  line: {
    start: handleLineStart,
    move: handleLineMove,
    end: handleLineEnd,
  },
  rectangle: {
    start: handleRectangleStart,
    move: handleRectangleMove,
    end: handleRectangleEnd,
  },
  circle: {
    start: handleCircleStart,
    move: handleCircleMove,
    end: handleCircleEnd,
  },
  freedraw: {
    start: handleFreeDrawStart,
    move: handleFreeDrawMove,
    end: handleFreeDrawEnd,
  },
};

function openLineDrawer(
  targetInputId,
  geometryJson,
  widthCm,
  heightCm,
  itemTitle
) {
  const modal = document.getElementById("crack-drawer-modal");
  currentDrawingTargetInput = document.getElementById(targetInputId);

  if (
    !modal ||
    !currentDrawingTargetInput ||
    !geometryJson ||
    geometryJson === "null" ||
    geometryJson === "undefined"
  ) {
    console.error("Line Drawer: Missing required elements or data.", {
      targetInputId,
      geometryJson,
    });
    alert("خطا: اطلاعات هندسی پنل برای باز کردن ابزار ترسیم وجود ندارد.");
    return;
  }

  document.getElementById(
    "drawer-title"
  ).textContent = `ترسیم برای: ${itemTitle}`;

  const canvasContainer = document.querySelector(".drawer-canvas-container");
  canvasContainer.innerHTML = `
    <div id="ruler-top" class="ruler horizontal"></div>
    <div id="ruler-left" class="ruler vertical"></div>
    <canvas id="crack-canvas"></canvas>
  `;
  const canvasEl = document.getElementById("crack-canvas");

  const geometry = JSON.parse(geometryJson);
  if (!geometry || geometry.length < 2) {
    alert("اطلاعات هندسی پنل نامعتبر است.");
    return;
  }

  const allX = geometry.map((p) => p[0]);
  const allY = geometry.map((p) => p[1]);
  const minX = Math.min(...allX);
  const minY = Math.min(...allY);
  const maxX = Math.max(...allX);
  const maxY = Math.max(...allY);
  const panelUnitWidth = maxX - minX;
  const panelUnitHeight = maxY - minY;

  const availableWidth = window.innerWidth * 0.8 - 250;
  const availableHeight = window.innerHeight * 0.8 - 150;
  let pxPerUnit = 1;
  if (panelUnitWidth > 0 && panelUnitHeight > 0) {
    pxPerUnit = Math.min(
      availableWidth / panelUnitWidth,
      availableHeight / panelUnitHeight
    );
  }
  const finalPxPerUnit = Math.max(0.5, Math.min(pxPerUnit, 20));
  const margin = 50;
  const canvasWidth = panelUnitWidth * finalPxPerUnit + margin * 2;
  const canvasHeight = panelUnitHeight * finalPxPerUnit + margin * 2;

  if (fabricCanvas) fabricCanvas.dispose();
  fabricCanvas = new fabric.Canvas(canvasEl, {
    width: canvasWidth,
    height: canvasHeight,
    backgroundColor: "#ffffff",
    selection: false,
  });
  fabricCanvas.isDrawingMode = false;

  fabricCanvas.customScale = finalPxPerUnit;
  fabricCanvas.customMargin = margin;
  fabricCanvas.customOffsetX = minX;
  fabricCanvas.customOffsetY = minY;

  const panelPoints = geometry.map((p) => ({
    x: (p[0] - minX) * finalPxPerUnit + margin,
    y: (p[1] - minY) * finalPxPerUnit + margin,
  }));
  const panelShape = new fabric.Polygon(panelPoints, {
    fill: "#f8f9fa",
    stroke: "#ced4da",
    strokeWidth: 2,
    selectable: false,
    evented: false,
    fill: new fabric.Pattern({
      source:
        "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAIAAACQkWg2AAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAACJJREFUeNpiZGBg6AGAgwKCBAwEAAFgsf///wcAARDAASQtn9cJUf2dAAAAAElFTkSuQmCC",
      repeat: "repeat",
    }),
  });
  fabricCanvas.add(panelShape);
  fabricCanvas.sendToBack(panelShape);

  // Load existing drawings
  loadExistingDrawings();

  // Set up event handlers
  setupEventHandlers();

  // Setup rulers
  setupRulers(
    panelUnitWidth,
    panelUnitHeight,
    finalPxPerUnit,
    margin,
    minX,
    minY
  );

  // Setup tool buttons
  setupToolButtons();

  modal.style.display = "flex";
}

function loadExistingDrawings() {
  try {
    const existingData = JSON.parse(currentDrawingTargetInput.value);
    if (existingData) {
      // Load lines
      if (existingData.lines) {
        existingData.lines.forEach(loadLine);
      }
      // Load rectangles
      if (existingData.rectangles) {
        existingData.rectangles.forEach(loadRectangle);
      }
      // Load circles
      if (existingData.circles) {
        existingData.circles.forEach(loadCircle);
      }
      // Load free drawings
      if (existingData.freeDrawings) {
        existingData.freeDrawings.forEach(loadFreeDrawing);
      }
    }
  } catch (e) {
    console.warn("Could not parse or load existing drawing data.", e);
  }
}

function loadLine(lineData) {
  const globalCoords = lineData.coords;
  const localX1 = globalCoords[0] - fabricCanvas.customOffsetX;
  const localY1 = globalCoords[1] - fabricCanvas.customOffsetY;
  const localX2 = globalCoords[2] - fabricCanvas.customOffsetX;
  const localY2 = globalCoords[3] - fabricCanvas.customOffsetY;

  const scaledCoords = [
    localX1 * fabricCanvas.customScale + fabricCanvas.customMargin,
    localY1 * fabricCanvas.customScale + fabricCanvas.customMargin,
    localX2 * fabricCanvas.customScale + fabricCanvas.customMargin,
    localY2 * fabricCanvas.customScale + fabricCanvas.customMargin,
  ];

  const line = new fabric.Line(scaledCoords, {
    stroke: lineData.color || "red",
    strokeWidth: 2,
    selectable: false,
    evented: false,
    shapeType: "line",
  });
  fabricCanvas.add(line);
}

function loadRectangle(rectData) {
  const coords = rectData.coords;
  const localCoords = [
    coords[0] - fabricCanvas.customOffsetX,
    coords[1] - fabricCanvas.customOffsetY,
    coords[2] - fabricCanvas.customOffsetX,
    coords[3] - fabricCanvas.customOffsetY,
  ];

  const scaledCoords = [
    localCoords[0] * fabricCanvas.customScale + fabricCanvas.customMargin,
    localCoords[1] * fabricCanvas.customScale + fabricCanvas.customMargin,
    localCoords[2] * fabricCanvas.customScale + fabricCanvas.customMargin,
    localCoords[3] * fabricCanvas.customScale + fabricCanvas.customMargin,
  ];

  const rect = new fabric.Rect({
    left: Math.min(scaledCoords[0], scaledCoords[2]),
    top: Math.min(scaledCoords[1], scaledCoords[3]),
    width: Math.abs(scaledCoords[2] - scaledCoords[0]),
    height: Math.abs(scaledCoords[3] - scaledCoords[1]),
    fill: "transparent",
    stroke: rectData.color || "blue",
    strokeWidth: 2,
    selectable: false,
    evented: false,
    shapeType: "rectangle",
  });
  fabricCanvas.add(rect);
}

function loadCircle(circleData) {
  const coords = circleData.coords;
  const localCoords = [
    coords[0] - fabricCanvas.customOffsetX,
    coords[1] - fabricCanvas.customOffsetY,
    coords[2] - fabricCanvas.customOffsetX,
    coords[3] - fabricCanvas.customOffsetY,
  ];

  const scaledCoords = [
    localCoords[0] * fabricCanvas.customScale + fabricCanvas.customMargin,
    localCoords[1] * fabricCanvas.customScale + fabricCanvas.customMargin,
    localCoords[2] * fabricCanvas.customScale + fabricCanvas.customMargin,
    localCoords[3] * fabricCanvas.customScale + fabricCanvas.customMargin,
  ];

  const centerX = (scaledCoords[0] + scaledCoords[2]) / 2;
  const centerY = (scaledCoords[1] + scaledCoords[3]) / 2;
  const radius =
    Math.sqrt(
      Math.pow(scaledCoords[2] - scaledCoords[0], 2) +
        Math.pow(scaledCoords[3] - scaledCoords[1], 2)
    ) / 2;

  const circle = new fabric.Circle({
    left: centerX - radius,
    top: centerY - radius,
    radius: radius,
    fill: "transparent",
    stroke: circleData.color || "green",
    strokeWidth: 2,
    selectable: false,
    evented: false,
    shapeType: "circle",
  });
  fabricCanvas.add(circle);
}

// FIXED: Free drawing loading function with proper point format handling
function loadFreeDrawing(freeDrawData) {
  if (!freeDrawData.points || freeDrawData.points.length < 2) {
    console.warn("Invalid free drawing data:", freeDrawData);
    return;
  }

  console.log("Loading free drawing with points:", freeDrawData.points);

  // Convert all points to consistent canvas coordinates
  const scaledPoints = freeDrawData.points
    .map((point) => {
      // Handle both array format [x,y] and object format {x,y}
      let x, y;
      if (Array.isArray(point)) {
        x = point[0];
        y = point[1];
      } else if (typeof point === "object" && point !== null) {
        x = point.x;
        y = point.y;
      } else {
        console.warn("Invalid point format:", point);
        return null;
      }

      // Convert from global coordinates to canvas coordinates
      const canvasX =
        (x - fabricCanvas.customOffsetX) * fabricCanvas.customScale +
        fabricCanvas.customMargin;
      const canvasY =
        (y - fabricCanvas.customOffsetY) * fabricCanvas.customScale +
        fabricCanvas.customMargin;

      return { x: canvasX, y: canvasY };
    })
    .filter((point) => point !== null); // Remove any null points

  if (scaledPoints.length < 2) {
    console.warn("Not enough valid points for free drawing");
    return;
  }

  // Create path string
  const pathString =
    "M " + scaledPoints.map((p) => `${p.x},${p.y}`).join(" L ");

  const path = new fabric.Path(pathString, {
    fill: "transparent",
    stroke: freeDrawData.color || "purple",
    strokeWidth: 2,
    selectable: false,
    evented: false,
    shapeType: "freedraw",
  });

  // Store the canvas points for consistent saving later
  path.freeDrawingPoints = scaledPoints;

  console.log(
    "Free drawing loaded successfully with",
    scaledPoints.length,
    "points"
  );
  fabricCanvas.add(path);
}

function setupRulers(
  panelUnitWidth,
  panelUnitHeight,
  finalPxPerUnit,
  margin,
  minX,
  minY
) {
  const rulerTop = document.getElementById("ruler-top");
  const rulerLeft = document.getElementById("ruler-left");
  rulerTop.innerHTML = "";
  rulerLeft.innerHTML = "";

  let tickIntervalUnit =
    finalPxPerUnit > 2 ? 10 : finalPxPerUnit > 0.5 ? 50 : 100;

  // Horizontal Ruler - start from 0
  for (let i = 0; i <= panelUnitWidth; i += tickIntervalUnit) {
    const pixelPos = i * finalPxPerUnit + margin;
    const label = i;
    rulerTop.innerHTML += `<div class="tick" style="left: ${pixelPos}px"></div><div class="label" style="left: ${
      pixelPos + 2
    }px">${label}</div>`;
  }

  // Vertical Ruler - start from 0
  for (let i = 0; i <= panelUnitHeight; i += tickIntervalUnit) {
    const pixelPos = i * finalPxPerUnit + margin;
    const label = i;
    rulerLeft.innerHTML += `<div class="tick" style="top: ${pixelPos}px"></div><div class="label" style="top: ${
      pixelPos + 2
    }px">${label}</div>`;
  }
}

function setupToolButtons() {
  const toolButtons = document.querySelectorAll(".drawer-tools .tool-btn");
  toolButtons.forEach((btn) => {
    btn.addEventListener("click", (e) => {
      // Remove active class from all buttons
      toolButtons.forEach((b) => b.classList.remove("active"));
      // Add active class to clicked button
      btn.classList.add("active");

      // Set current tool based on button data attribute or text
      const toolType =
        btn.getAttribute("data-tool") || btn.textContent.toLowerCase().trim();
      currentTool = toolType;

      console.log("Tool switched to:", currentTool);

      // Handle free drawing mode
      fabricCanvas.isDrawingMode = false; // We handle all drawing manually for consistency
    });
  });

  // Activate the first tool by default
  const firstTool = document.querySelector(".drawer-tools .tool-btn");
  if (firstTool) {
    firstTool.classList.add("active");
    const toolType = firstTool.getAttribute("data-tool") || "line";
    currentTool = toolType;

    // Verify the HTML structure is correct
    const toolItem = firstTool.closest(".tool-item");
    if (!toolItem) {
      console.warn(
        "Warning: Tool buttons are not properly structured within .tool-item containers"
      );
    } else {
      const colorInput = toolItem.querySelector(".tool-color");
      if (!colorInput) {
        console.warn("Warning: Color input not found for tool");
      }
    }
  }
}

// Generic mouse event handlers
function handleCanvasMouseDown(opt) {
  if (opt.target && opt.target.type !== "polygon") return;

  const handler = shapeHandlers[currentTool];
  if (handler && handler.start) {
    handler.start(opt);
  }
}

function setupEventHandlers() {
  fabricCanvas.off("mouse:down").on("mouse:down", handleCanvasMouseDown);
  fabricCanvas.off("mouse:move").on("mouse:move", handleCanvasMouseMove);
  fabricCanvas.off("mouse:up").on("mouse:up", handleCanvasMouseUp);
}

function handleCanvasMouseMove(opt) {
  const handler = shapeHandlers[currentTool];
  if (handler && handler.move) {
    handler.move(opt);
  }
}

function handleCanvasMouseUp(opt) {
  const handler = shapeHandlers[currentTool];
  if (handler && handler.end) {
    handler.end(opt);
  }
}

// Line drawing handlers
function handleLineStart(opt) {
  isDrawing = true;
  const pointer = fabricCanvas.getPointer(opt.e);
  drawingStartPoint = [pointer.x, pointer.y];

  tempShape = new fabric.Line([pointer.x, pointer.y, pointer.x, pointer.y], {
    stroke: "#999999",
    strokeWidth: 1,
    strokeDashArray: [5, 5],
  });
  fabricCanvas.add(tempShape);
}

function handleLineMove(opt) {
  if (!isDrawing || !tempShape) return;
  const pointer = fabricCanvas.getPointer(opt.e);
  tempShape.set({ x2: pointer.x, y2: pointer.y });
  fabricCanvas.renderAll();
}

function handleLineEnd(opt) {
  if (!isDrawing) return;
  isDrawing = false;

  if (tempShape) {
    fabricCanvas.remove(tempShape);
    tempShape = null;
  }

  const pointer = fabricCanvas.getPointer(opt.e);
  const color = getActiveToolColor("#FF0000");

  if (
    Math.abs(pointer.x - drawingStartPoint[0]) > 2 ||
    Math.abs(pointer.y - drawingStartPoint[1]) > 2
  ) {
    const line = new fabric.Line(
      [drawingStartPoint[0], drawingStartPoint[1], pointer.x, pointer.y],
      {
        stroke: color,
        strokeWidth: 2,
        selectable: false,
        evented: false,
        shapeType: "line",
      }
    );
    fabricCanvas.add(line);
  }
}

// Rectangle drawing handlers
function handleRectangleStart(opt) {
  isDrawing = true;
  const pointer = fabricCanvas.getPointer(opt.e);
  drawingStartPoint = [pointer.x, pointer.y];

  tempShape = new fabric.Rect({
    left: pointer.x,
    top: pointer.y,
    width: 0,
    height: 0,
    fill: "transparent",
    stroke: "#999999",
    strokeWidth: 1,
    strokeDashArray: [5, 5],
  });
  fabricCanvas.add(tempShape);
}

function handleRectangleMove(opt) {
  if (!isDrawing || !tempShape) return;
  const pointer = fabricCanvas.getPointer(opt.e);

  const width = pointer.x - drawingStartPoint[0];
  const height = pointer.y - drawingStartPoint[1];

  tempShape.set({
    left: width < 0 ? pointer.x : drawingStartPoint[0],
    top: height < 0 ? pointer.y : drawingStartPoint[1],
    width: Math.abs(width),
    height: Math.abs(height),
  });
  fabricCanvas.renderAll();
}

function handleRectangleEnd(opt) {
  if (!isDrawing) return;
  isDrawing = false;

  if (tempShape) {
    fabricCanvas.remove(tempShape);
    tempShape = null;
  }

  const pointer = fabricCanvas.getPointer(opt.e);
  const color = getActiveToolColor("#0000FF");
  const width = Math.abs(pointer.x - drawingStartPoint[0]);
  const height = Math.abs(pointer.y - drawingStartPoint[1]);

  if (width > 5 && height > 5) {
    const rect = new fabric.Rect({
      left: Math.min(pointer.x, drawingStartPoint[0]),
      top: Math.min(pointer.y, drawingStartPoint[1]),
      width: width,
      height: height,
      fill: "transparent",
      stroke: color,
      strokeWidth: 2,
      selectable: false,
      evented: false,
      shapeType: "rectangle",
    });
    fabricCanvas.add(rect);
  }
}

// Circle drawing handlers
function handleCircleStart(opt) {
  isDrawing = true;
  const pointer = fabricCanvas.getPointer(opt.e);
  drawingStartPoint = [pointer.x, pointer.y];

  tempShape = new fabric.Circle({
    left: pointer.x,
    top: pointer.y,
    radius: 0,
    fill: "transparent",
    stroke: "#999999",
    strokeWidth: 1,
    strokeDashArray: [5, 5],
  });
  fabricCanvas.add(tempShape);
}

function handleCircleMove(opt) {
  if (!isDrawing || !tempShape) return;
  const pointer = fabricCanvas.getPointer(opt.e);

  const radius = Math.sqrt(
    Math.pow(pointer.x - drawingStartPoint[0], 2) +
      Math.pow(pointer.y - drawingStartPoint[1], 2)
  );

  tempShape.set({
    left: drawingStartPoint[0] - radius,
    top: drawingStartPoint[1] - radius,
    radius: radius,
  });
  fabricCanvas.renderAll();
}

function handleCircleEnd(opt) {
  if (!isDrawing) return;
  isDrawing = false;

  if (tempShape) {
    fabricCanvas.remove(tempShape);
    tempShape = null;
  }

  const pointer = fabricCanvas.getPointer(opt.e);
  const color = getActiveToolColor("#00FF00");
  const radius = Math.sqrt(
    Math.pow(pointer.x - drawingStartPoint[0], 2) +
      Math.pow(pointer.y - drawingStartPoint[1], 2)
  );

  if (radius > 5) {
    const circle = new fabric.Circle({
      left: drawingStartPoint[0] - radius,
      top: drawingStartPoint[1] - radius,
      radius: radius,
      fill: "transparent",
      stroke: color,
      strokeWidth: 2,
      selectable: false,
      evented: false,
      shapeType: "circle",
    });
    fabricCanvas.add(circle);
  }
}

// FIXED: Free drawing handlers with consistent point storage
function handleFreeDrawStart(opt) {
  isDrawing = true;
  const pointer = fabricCanvas.getPointer(opt.e);
  freeDrawingPath = [{ x: pointer.x, y: pointer.y }]; // Store as objects for consistency
}

function handleFreeDrawMove(opt) {
  if (!isDrawing) return;
  const pointer = fabricCanvas.getPointer(opt.e);
  freeDrawingPath.push({ x: pointer.x, y: pointer.y });

  // Create temporary path for preview
  if (tempShape) {
    fabricCanvas.remove(tempShape);
  }

  if (freeDrawingPath.length > 1) {
    const pathString =
      "M " + freeDrawingPath.map((p) => `${p.x},${p.y}`).join(" L ");
    tempShape = new fabric.Path(pathString, {
      fill: "transparent",
      stroke: "#999999",
      strokeWidth: 1,
      strokeDashArray: [5, 5],
    });
    fabricCanvas.add(tempShape);
    fabricCanvas.renderAll();
  }
}

function handleFreeDrawEnd(opt) {
  if (!isDrawing) return;
  isDrawing = false;

  if (tempShape) {
    fabricCanvas.remove(tempShape);
    tempShape = null;
  }

  if (freeDrawingPath.length < 2) {
    freeDrawingPath = [];
    return;
  }

  const color = getActiveToolColor("#FF00FF");
  const pathString =
    "M " + freeDrawingPath.map((p) => `${p.x},${p.y}`).join(" L ");

  const path = new fabric.Path(pathString, {
    fill: "transparent",
    stroke: color,
    strokeWidth: 2,
    selectable: false,
    evented: false,
    shapeType: "freedraw",
  });

  // Store the canvas points for consistent saving later
  path.freeDrawingPoints = freeDrawingPath.slice(); // Make a copy

  fabricCanvas.add(path);
  freeDrawingPath = [];
}

// FIXED: Save function with proper free drawing coordinate conversion
document.getElementById("drawer-close-btn").addEventListener("click", () => {
  document.getElementById("crack-drawer-modal").style.display = "none";
});

document.getElementById("drawer-save-btn").addEventListener("click", () => {
  if (fabricCanvas && currentDrawingTargetInput) {
    const scale = fabricCanvas.customScale;
    const margin = fabricCanvas.customMargin;
    const offsetX = fabricCanvas.customOffsetX;
    const offsetY = fabricCanvas.customOffsetY;

    const drawingData = {
      lines: [],
      rectangles: [],
      circles: [],
      freeDrawings: [],
    };

    console.log(
      "Saving drawings. Total objects:",
      fabricCanvas.getObjects().length
    );

    fabricCanvas.getObjects().forEach((obj, index) => {
      if (!obj.shapeType) return; // Skip panel background

      const shapeType = obj.shapeType;
      console.log(`Processing object ${index}: type=${shapeType}`);

      if (shapeType === "line") {
        const localX1 = (obj.x1 - margin) / scale;
        const localY1 = (obj.y1 - margin) / scale;
        const localX2 = (obj.x2 - margin) / scale;
        const localY2 = (obj.y2 - margin) / scale;

        const globalCoords = [
          localX1 + offsetX,
          localY1 + offsetY,
          localX2 + offsetX,
          localY2 + offsetY,
        ];

        drawingData.lines.push({
          coords: globalCoords,
          color: obj.stroke,
        });
      } else if (shapeType === "rectangle") {
        const localX1 = (obj.left - margin) / scale;
        const localY1 = (obj.top - margin) / scale;
        const localX2 = (obj.left + obj.width - margin) / scale;
        const localY2 = (obj.top + obj.height - margin) / scale;

        const globalCoords = [
          localX1 + offsetX,
          localY1 + offsetY,
          localX2 + offsetX,
          localY2 + offsetY,
        ];

        drawingData.rectangles.push({
          coords: globalCoords,
          color: obj.stroke,
        });
      } else if (shapeType === "circle") {
        const centerX = (obj.left + obj.radius - margin) / scale;
        const centerY = (obj.top + obj.radius - margin) / scale;
        const radiusGlobal = obj.radius / scale;

        // Store as bounding box for consistency
        const globalCoords = [
          centerX - radiusGlobal + offsetX,
          centerY - radiusGlobal + offsetY,
          centerX + radiusGlobal + offsetX,
          centerY + radiusGlobal + offsetY,
        ];

        drawingData.circles.push({
          coords: globalCoords,
          color: obj.stroke,
        });
      } else if (shapeType === "freedraw") {
        console.log("Processing free drawing object:", obj);

        const points = [];

        try {
          // Use stored canvas points and convert to global coordinates
          if (obj.freeDrawingPoints && obj.freeDrawingPoints.length > 0) {
            console.log(
              "Using stored freeDrawingPoints:",
              obj.freeDrawingPoints.length,
              "points"
            );
            obj.freeDrawingPoints.forEach((point) => {
              const localX = (point.x - margin) / scale;
              const localY = (point.y - margin) / scale;
              // CONSISTENT: Save as array format [x, y] for consistency with loading
              points.push([localX + offsetX, localY + offsetY]);
            });
          } else {
            console.warn(
              "No freeDrawingPoints found on object, trying to extract from path data"
            );
            // Fallback: extract from path data (less reliable)
            let pathData = obj.path || obj._path;

            if (pathData && Array.isArray(pathData)) {
              for (let i = 0; i < pathData.length; i++) {
                const cmd = pathData[i];
                if (
                  Array.isArray(cmd) &&
                  cmd.length >= 3 &&
                  (cmd[0] === "M" || cmd[0] === "L")
                ) {
                  const canvasX = cmd[1] + (obj.left || 0);
                  const canvasY = cmd[2] + (obj.top || 0);
                  const localX = (canvasX - margin) / scale;
                  const localY = (canvasY - margin) / scale;
                  points.push([localX + offsetX, localY + offsetY]);
                }
              }
            }
          }

          console.log(`Free drawing points converted: ${points.length}`);

          if (points.length > 0) {
            drawingData.freeDrawings.push({
              points: points,
              color: obj.stroke,
            });
          } else {
            console.warn(
              "Could not extract points from free drawing object:",
              obj
            );
          }
        } catch (e) {
          console.error("Error processing free drawing:", e, obj);
        }
      }
    });

    console.log("Final drawing data:", drawingData);

    currentDrawingTargetInput.value = JSON.stringify(drawingData);
    currentDrawingTargetInput.dispatchEvent(
      new Event("input", { bubbles: true })
    );
  }
  document.getElementById("crack-drawer-modal").style.display = "none";
});

// Add clear all drawings function
function clearAllDrawings() {
  if (!fabricCanvas) return;

  // Remove all objects except the panel background
  const objects = fabricCanvas.getObjects();
  objects.forEach((obj) => {
    if (obj.shapeType) {
      // Only remove drawing objects, keep panel background
      fabricCanvas.remove(obj);
    }
  });
  fabricCanvas.renderAll();
}

// ===================================================================
// END: ENHANCED MULTI-SHAPE DRAWING MODULE (FIXED FREE DRAWING)
// ===================================================================
/*
 * Calculates a completion score for multi-stage elements
 * @param {Array} stagesData - Array of stage records for an element
 * @param {Array} templateStages - Array of all possible stages from template
 * @returns {Object} - Contains completion info and color data
 */
function calculateMultiStageStatus(stagesData, templateStages) {
  if (!stagesData || stagesData.length === 0) {
    return {
      overallStatus: "Pending",
      completionScore: 0,
      colorData: STATUS_COLORS["Pending"],
      statusText: "در حال اجرا",
      stagesSummary: "هیچ مرحله‌ای تکمیل نشده",
    };
  }

  let okCount = 0;
  let rejectCount = 0;
  let repairCount = 0;
  let pendingCount = 0;
  let awaitingReinspectionCount = 0;
  let preInspectionCompleteCount = 0;

  const totalStages = templateStages
    ? templateStages.length
    : stagesData.length;

  stagesData.forEach((stage) => {
    const status =
      stage.overall_status || stage.contractor_status || stage.status;

    switch (status) {
      case "OK":
        okCount++;
        break;
      case "Reject":
        rejectCount++;
        break;
      case "Repair":
        repairCount++;
        break;
      case "Awaiting Re-inspection":
        awaitingReinspectionCount++;
        break;
      case "Pre-Inspection Complete":
        preInspectionCompleteCount++;
        break;
      default:
        pendingCount++;
    }
  });

  // Calculate completion score (0-100)
  const completedStages = okCount;
  const completionScore =
    totalStages > 0 ? (completedStages / totalStages) * 100 : 0;

  // Determine overall status and color
  let overallStatus, colorData, statusText;

  if (okCount === totalStages) {
    // All stages completed successfully
    overallStatus = "OK";
    colorData = STATUS_COLORS["OK"];
    statusText = "تمام مراحل تایید شده";
  } else if (rejectCount > 0) {
    // Some stages rejected - critical
    overallStatus = "Reject";
    colorData = STATUS_COLORS["Reject"];
    statusText = `${rejectCount} مرحله رد شده`;
  } else if (awaitingReinspectionCount > 0) {
    // Some stages awaiting re-inspection
    overallStatus = "Awaiting Re-inspection";
    colorData = STATUS_COLORS["Awaiting Re-inspection"];
    statusText = `${awaitingReinspectionCount} مرحله در انتظار بازرسی مجدد`;
  } else if (repairCount > 0) {
    // Some stages need repair
    overallStatus = "Repair";
    colorData = STATUS_COLORS["Repair"];
    statusText = `${repairCount} مرحله نیاز به تعمیر`;
  } else if (preInspectionCompleteCount > 0) {
    // Some stages ready for inspection
    overallStatus = "Pre-Inspection Complete";
    colorData = STATUS_COLORS["Pre-Inspection Complete"];
    statusText = `${preInspectionCompleteCount} مرحله آماده بازرسی`;
  } else {
    // All pending or in progress
    overallStatus = "Pending";
    colorData = STATUS_COLORS["Pending"];
    statusText = "در حال اجرا";
  }

  // Create a spectrum color based on completion percentage
  const spectrumColor = getCompletionSpectrumColor(
    completionScore,
    overallStatus
  );

  return {
    overallStatus,
    completionScore,
    colorData: spectrumColor,
    statusText,
    stagesSummary: `${okCount}/${totalStages} مرحله تکمیل`,
    breakdown: {
      ok: okCount,
      reject: rejectCount,
      repair: repairCount,
      pending: pendingCount,
      awaitingReinspection: awaitingReinspectionCount,
      preInspectionComplete: preInspectionCompleteCount,
      total: totalStages,
    },
  };
}
/**
 * Generates a spectrum color based on completion percentage and status
 * @param {number} completionScore - Completion percentage (0-100)
 * @param {string} overallStatus - Overall status of the element
 * @returns {string} - RGB color string
 */
function getCompletionSpectrumColor(completionScore, overallStatus) {
  // Handle special status colors first
  if (overallStatus === "Reject") {
    return STATUS_COLORS["Reject"];
  }

  if (overallStatus === "OK" && completionScore === 100) {
    return STATUS_COLORS["OK"];
  }

  // Create spectrum from red (0%) through yellow (50%) to green (100%)
  const normalizedScore = Math.max(0, Math.min(100, completionScore));

  let r, g, b;

  if (normalizedScore === 0) {
    // Pure gray for 0% completion
    return "rgba(108, 117, 125, 0.6)";
  } else if (normalizedScore <= 50) {
    // Red to Yellow spectrum (0-50%)
    const ratio = normalizedScore / 50;
    r = 255; // Keep red at maximum
    g = Math.round(140 + 115 * ratio); // 140 (orange-red) to 255 (yellow)
    b = 0;
  } else {
    // Yellow to Green spectrum (50-100%)
    const ratio = (normalizedScore - 50) / 50;
    r = Math.round(255 * (1 - ratio)); // 255 (yellow) to 0 (green)
    g = 255; // Keep green component at maximum
    b = 0;
  }

  // Add some transparency based on completion
  const alpha = 0.4 + (normalizedScore / 100) * 0.4; // 0.4 to 0.8

  return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

/**
 * Renders the full inspection history for a stage, grouped into logical "Inspection Cycles".
 * Fully translated to Persian.
 *
 * @param {Array} historyLog The array of history log events for a stage.
 * @param {Map} allItemsMap A map of {item_id: item_text} for rendering checklist details.
 * @returns {string} The generated HTML for the history display.
 */
function renderHistoryLogHTML(historyLog, allItemsMap) {
  if (!historyLog || !Array.isArray(historyLog) || historyLog.length === 0) {
    return "<p>تاریخچه‌ای برای این مرحله ثبت نشده است.</p>";
  }

  // --- START: Grouping logic ---
  const inspectionCycles = [];
  let currentCycle = [];

  // Reverse the log to process from newest to oldest
  [...historyLog].reverse().forEach((event) => {
    currentCycle.unshift(event); // Add event to the beginning of the current cycle

    // A cycle ends when a Supervisor Action (admin/superuser) is found.
    if (event.role === "admin" || event.role === "superuser") {
      inspectionCycles.push(currentCycle);
      currentCycle = [];
    }
  });
  // Add any remaining events (if the log doesn't end with a supervisor action)
  if (currentCycle.length > 0) {
    inspectionCycles.push(currentCycle);
  }
  // --- END: Grouping logic ---

  // Persian translations
  const statusTranslations = {
    OK: "تایید شده",
    Reject: "رد شده",
    Repair: "نیاز به تعمیر",
    "Awaiting Re-inspection": "تعمیر پایان یافته",
    "Pre-Inspection Complete": "آماده بازرسی",
    Pending: "در حال اجرا",
  };
  const actionTranslations = {
    "Supervisor Action": "اقدام مشاور",
    "Contractor Action": "اقدام پیمانکار",
  };

  const itemTextMap = new Map(Object.entries(allItemsMap));

  const cyclesHTML = inspectionCycles
    .map((cycle, index) => {
      const cycleNumber = inspectionCycles.length - index;

      const eventsHTML = cycle
        .map((event) => {
          let detailsHTML = "";
          if (event.data) {
            const status =
              event.data.overall_status ||
              event.data.contractor_status ||
              "N/A";
            const translatedStatus = statusTranslations[status] || status;
            const notes =
              event.data.notes || event.data.contractor_notes || "بدون یادداشت";
            const attachments =
              event.data.attachments || event.data.contractor_attachments || [];

            const attachmentsHTML =
              attachments.length > 0
                ? `<div class="history-attachments"><strong>پیوست‌ها:</strong><ul>${attachments
                    .map(
                      (file) =>
                        `<li><a href="${escapeHtml(
                          file
                        )}" target="_blank">${escapeHtml(
                          file.split("/").pop()
                        )}</a></li>`
                    )
                    .join("")}</ul></div>`
                : "";

            const checklistHTML = (event.data.checklist_items || [])
              .map((item) => {
                const itemText =
                  itemTextMap.get(String(item.item_id || item.itemId)) ||
                  `مورد #${item.item_id || item.itemId}`;
                return `<div class="history-checklist-item">
                                <span class="history-item-status status-${(
                                  item.status || ""
                                )
                                  .toLowerCase()
                                  .replace(" ", "-")}">${escapeHtml(
                  item.status
                )}</span>
                                <span>- ${escapeHtml(itemText)}</span>
                                ${
                                  item.value
                                    ? `<span class="history-item-value">(${escapeHtml(
                                        item.value
                                      )})</span>`
                                    : ""
                                }
                            </div>`;
              })
              .join("");

            detailsHTML = `
                    <div class="history-note"><strong>وضعیت:</strong> ${escapeHtml(
                      translatedStatus
                    )}</div>
                    <div class="history-note"><strong>یادداشت:</strong> ${escapeHtml(
                      notes
                    )}</div>
                    ${attachmentsHTML}
                    ${
                      checklistHTML
                        ? `<div class="history-checklist"><h4>جزئیات چک‌لیست:</h4>${checklistHTML}</div>`
                        : ""
                    }`;
          }

          return `
            <div class="history-event">
                <div class="history-event-header">
                    <strong>${
                      actionTranslations[event.action] || event.action
                    }</strong>
                    <span>${escapeHtml(
                      event.persian_timestamp
                    )} توسط ${escapeHtml(event.user_display_name)}</span>
                </div>
                ${detailsHTML}
            </div>`;
        })
        .join("");

      return `
        <div class="inspection-cycle-container">
            <h3>سیکل بازرسی #${cycleNumber}</h3>
            ${eventsHTML}
        </div>`;
    })
    .join("");

  return `<div class="history-log-container"><h4>تاریخچه بازرسی‌ها</h4>${cyclesHTML}</div>`;
}
/**
 * Generates a new RSA key pair, stores the private key, and sends the public key to the server.
 */
function generateAndStoreKeys() {
  return new Promise((resolve) => {
    // Show loading message instead of alert
    console.log("Generating new RSA key pair...");

    try {
      forge.pki.rsa.generateKeyPair(
        { bits: 2048, workers: -1 },
        async (err, keypair) => {
          if (err) {
            console.error("Key generation error:", err);
            alert("خطا در ایجاد کلید امضا.");
            resolve(false);
            return;
          }

          try {
            userPrivateKey = keypair.privateKey;
            const privateKeyPem = forge.pki.privateKeyToPem(userPrivateKey);
            const publicKeyPem = forge.pki.publicKeyToPem(keypair.publicKey);

            // Store private key in localStorage
            localStorage.setItem("user_private_key", privateKeyPem);

            // Send public key to server with better error handling
            const response = await fetch("/ghom/api/store_public_key.php", {
              method: "POST",
              headers: {
                "Content-Type": "application/json",
                "X-Requested-With": "XMLHttpRequest",
              },
              body: JSON.stringify({ public_key_pem: publicKeyPem }),
            });

            if (!response.ok) {
              throw new Error(
                `HTTP ${response.status}: ${response.statusText}`
              );
            }

            const data = await response.json();

            if (data.success) {
              console.log(
                "Digital signature keys generated and stored successfully"
              );
              resolve(true);
            } else {
              throw new Error(
                data.message || "Server error storing public key."
              );
            }
          } catch (error) {
            console.error("Store public key error:", error);
            alert("خطا در ذخیره‌سازی کلید عمومی روی سرور: " + error.message);
            resolve(false);
          }
        }
      );
    } catch (e) {
      console.error("Forge library error:", e);
      alert(
        "خطا در کتابخانه رمزنگاری. لطفا اتصال اینترنت خود را بررسی کرده و صفحه را رفرش کنید."
      );
      resolve(false);
    }
  });
}

/**
 * Check and setup digital signature keys with better error handling
 */
async function checkAndSetupKeys() {
  try {
    // Check if private key exists in localStorage
    const privateKeyPem = localStorage.getItem("user_private_key");

    if (privateKeyPem) {
      try {
        // Try to load the existing private key
        userPrivateKey = forge.pki.privateKeyFromPem(privateKeyPem);
        console.log("Existing private key loaded successfully");
        return true;
      } catch (error) {
        console.error("Error loading existing private key:", error);
        localStorage.removeItem("user_private_key");
      }
    }

    // Generate new keys if none exist or if loading failed
    console.log("Generating new keys...");
    const keyGenSuccess = await generateAndStoreKeys();

    if (!keyGenSuccess) {
      console.error("Key generation failed");
      return false;
    }

    // Verify the key was properly stored
    if (!userPrivateKey) {
      console.error("Private key not available after generation");
      return false;
    }

    return true;
  } catch (error) {
    console.error("Error in checkAndSetupKeys:", error);
    return false;
  }
}

/**
 * Signs a string of data using the user's private key.
 * @param {string} dataToSign - The data to be signed.
 * @returns {string} The Base64 encoded signature.
 */
function signData(dataToSign) {
  if (!userPrivateKey) {
    console.error("Private key not available for signing");
    return null;
  }

  try {
    const md = forge.md.sha256.create();
    md.update(dataToSign, "utf8");
    const signature = userPrivateKey.sign(md);
    return forge.util.encode64(signature);
  } catch (error) {
    console.error("Error signing data:", error);
    return null;
  }
}

/**
 * Convert Gregorian date to Persian date format
 */
function formatPersianDate(gregorianDateStr) {
  // If the input is null or empty, return an empty string.
  if (!gregorianDateStr || typeof gregorianDateStr !== "string") {
    return "";
  }

  // If it's already in the correct Persian format, return it.
  if (/^1[34]\d{2}\/\d{2}\/\d{2}/.test(gregorianDateStr)) {
    return gregorianDateStr;
  }

  try {
    const dateObj = new Date(gregorianDateStr);
    if (isNaN(dateObj.getTime())) {
      return gregorianDateStr; // Fallback for invalid date strings
    }

    const gy = dateObj.getFullYear();
    const gm = dateObj.getMonth() + 1; // getMonth() is 0-indexed
    const gd = dateObj.getDate();

    // *** New, reliable conversion algorithm based on day-of-year calculation ***
    const g_d_m = [0, 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    const j_d_m = [0, 31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];

    let jy = gy - 621;
    // Check for Gregorian leap year
    if (gy % 4 === 0 && (gy % 100 !== 0 || gy % 400 === 0)) {
      g_d_m[2] = 29;
    }
    // Check for Jalali leap year (for year `jy`)
    let t = (jy - 1) % 33;
    if ([1, 5, 9, 13, 17, 22, 26, 30].indexOf(t) !== -1) {
      j_d_m[12] = 30; // This is for the previous year's influence on the start of this year
    }

    let g_day_no = 0;
    for (let i = 1; i < gm; i++) {
      g_day_no += g_d_m[i];
    }
    g_day_no += gd;

    // Day of year in Jalali calendar
    let j_day_no;
    const isLeapJalali = j_d_m[12] === 30;
    const marchDayDiff = isLeapJalali ? 79 : 80;

    if (g_day_no > marchDayDiff) {
      j_day_no = g_day_no - 79;
      // Re-check for leap year of the current Jalali year if we are in it
      t = jy % 33;
      if ([1, 5, 9, 13, 17, 22, 26, 30].indexOf(t) !== -1) {
        j_d_m[12] = 30;
      } else {
        j_d_m[12] = 29;
      }
    } else {
      jy--;
      j_day_no = g_day_no + 286 + (isLeapJalali ? 1 : 0);
      // Check leap year for the now-previous Jalali year
      t = jy % 33;
      if ([1, 5, 9, 13, 17, 22, 26, 30].indexOf(t) !== -1) {
        j_d_m[12] = 30;
      } else {
        j_d_m[12] = 29;
      }
    }

    let jm = 1;
    while (j_day_no > j_d_m[jm]) {
      j_day_no -= j_d_m[jm];
      jm++;
    }
    let jd = j_day_no;
    // *** End of algorithm ***

    // Zero-pad the month and day for "YYYY/MM/DD" format.
    const monthStr = String(jm).padStart(2, "0");
    const dayStr = String(jd).padStart(2, "0");

    return `${jy}/${monthStr}/${dayStr}`;
  } catch (e) {
    console.error("Date conversion error:", e, "Input was:", gregorianDateStr);
    return gregorianDateStr;
  }
}
/**
 * =======================================================================================
 * COMPLETE openChecklistForm FUNCTION WITH VALIDATION AND CONFIRMATION - FIXED VERSION
 * This function opens and populates the inspection form with proper error handling
 * =======================================================================================
 */
function openChecklistForm(fullElementId, elementType, dynamicContext) {
  console.log(
    `%cDEBUG: openChecklistForm FINAL CALL. Full Element ID: '${fullElementId}', Type: '${elementType}'`,
    "background: #28a745; color: white; padding: 2px 5px; border-radius: 3px;"
  );

  const formPopup = document.getElementById("universalChecklistForm");

  if (!formPopup) {
    console.error("Critical Error: The form popup container was not found.");
    alert("خطای داخلی: ساختار اصلی فرم یافت نشد.");
    return;
  }

  formPopup.innerHTML = `<div class="form-loader"><h3>در حال بارگذاری...</h3></div>`;
  formPopup.classList.add("show");

  const apiParams = new URLSearchParams({
    element_id: fullElementId,
    element_type: elementType,
  });

  fetch(`/ghom/api/get_element_data.php?${apiParams.toString()}`)
    .then((res) => {
      if (!res.ok)
        throw new Error(`Network response was not ok: ${res.statusText}`);
      return res.json();
    })
    .then(async (data) => {
      if (data.error) throw new Error(data.error);

      console.log("API Response Data:", data);
      console.log("History Data:", data.history); // Add this debug log
      console.log("Template Items for First Stage:", data.template[0]?.items);

      // Debug each stage's history
      if (data.history && Array.isArray(data.history)) {
        data.history.forEach((stageHistory, index) => {
          console.log(`Stage ${index} History:`, stageHistory);
        });
      }

      // Setup digital signature keys BEFORE building the form
      const keysReady = await checkAndSetupKeys();
      if (!keysReady) {
        throw new Error(
          "کلیدهای امضای دیجیتال آماده نیست. لطفا صفحه را رفرش کنید."
        );
      }

      const createLinks = (jsonString) => {
        if (!jsonString) return "<li>هیچ فایلی پیوست نشده است.</li>";
        try {
          const paths = JSON.parse(jsonString);
          if (!Array.isArray(paths) || paths.length === 0)
            return "<li>هیچ فایلی پیوست نشده است.</li>";
          return paths
            .map(
              (p) =>
                `<li><a href="${escapeHtml(p)}" target="_blank">${escapeHtml(
                  p.split("/").pop()
                )}</a></li>`
            )
            .join("");
        } catch (e) {
          return "<li>خطا در نمایش فایل‌ها.</li>";
        }
      };

      const headerHTML = `
        <div class="form-header-new">
            <h3>چک لیست: ${escapeHtml(elementType)}</h3>
            <p>${escapeHtml(fullElementId)}</p>
            <div class="form-meta">
                <span><strong>موقعیت:</strong> ${escapeHtml(
                  dynamicContext.block
                )}, ${escapeHtml(dynamicContext.zoneName)}</span>
                <span><strong>طبقه:</strong> ${escapeHtml(
                  dynamicContext.floorLevel
                )}</span>
            </div>
            <div class="form-meta" style="margin-top: 5px;">
              <span><strong>ابعاد:</strong> <span class="ltr-text">${
                dynamicContext.widthCm
              } x ${dynamicContext.heightCm} cm</span></span>
              <span><strong>مساحت:</strong> <span class="ltr-text">${
                dynamicContext.areaSqm
              } m²</span></span>
            </div>
        </div>`;

      const footerHTML = `
            <div class="form-footer-new">
                <button type="button" class="btn cancel" onclick="closeForm('universalChecklistForm')">بستن</button>
                <button type="button" id="validate-btn" class="btn secondary">بررسی و تایید نهایی</button>
                <button type="submit" form="checklist-form" class="btn save" style="display:none;">ذخیره و امضای دیجیتال</button>
            </div>`;

      let bodyContentHTML =
        '<div class="stage-content-container"><p>مراحل بازرسی برای این المان تعریف نشده است.</p></div>';

      if (data.template && data.template.length > 0) {
        const tabButtons = data.template
          .map(
            (stage, i) =>
              `<button type="button" class="stage-tab-button ${
                i === 0 ? "active" : ""
              }" data-tab="stage-content-${stage.stage_id}" data-stage-id="${
                stage.stage_id
              }">${escapeHtml(stage.stage_name)}</button>`
          )
          .join("");

        const tabContents = data.template
          .map((stage, i) => {
            const history =
              data.history.find((h) => h.stage_id == stage.stage_id) || {};

            console.log(`Building tab for stage ${stage.stage_id}:`, history); // Debug log

            const historyLogHTML = renderHistoryLogHTML(
              history.history_log,
              data.all_items_map
            );

            const items = stage.items
              .map((item_template) => {
                const itemHistory =
                  history.items?.find(
                    (i) => i.item_id == item_template.item_id
                  ) || {};

                console.log(
                  `Item ${item_template.item_id} history:`,
                  itemHistory
                ); // Debug log

                let controlHTML = "";

                if (item_template.requires_drawing == 1) {
                  const targetInputId = `drawing_input_${stage.stage_id}_${item_template.item_id}`;
                  const existingValue = escapeHtml(
                    itemHistory.item_value || "{}"
                  );

                  controlHTML = `
                <input type="hidden" 
                       class="drawing-data-input" 
                       data-item-id="${item_template.item_id}" 
                       id="${targetInputId}" 
                       value='${existingValue}'>
                <button type="button" 
                        class="btn-draw" 
                        onclick="openLineDrawer(
                            '${targetInputId}', 
                            '${escapeHtml(dynamicContext.geometry_json)}', 
                            '${dynamicContext.widthCm}', 
                            '${dynamicContext.heightCm}',
                            '${escapeHtml(item_template.item_text)}'
                        )">
                    ترسیم / ویرایش
                </button>
            `;
                } else {
                  controlHTML = `
                <input type="text" 
                       class="checklist-input" 
                       data-item-id="${item_template.item_id}" 
                       value="${escapeHtml(itemHistory.item_value || "")}" 
                       placeholder="توضیحات...">
            `;
                }

                return `
        <div class="item-row" data-item-id="${item_template.item_id}">
            <label class="item-text">${escapeHtml(
              item_template.item_text
            )}</label>
            <div class="item-controls">
                <div class="status-selector-new">
                     <input type="radio" id="status_ok_${stage.stage_id}_${
                  item_template.item_id
                }" name="status_${stage.stage_id}_${
                  item_template.item_id
                }" value="OK" ${
                  itemHistory.item_status === "OK" ? "checked" : ""
                }>
                     <label for="status_ok_${stage.stage_id}_${
                  item_template.item_id
                }" class="status-icon ok" title="OK">✓</label>
                     <input type="radio" id="status_nok_${stage.stage_id}_${
                  item_template.item_id
                }" name="status_${stage.stage_id}_${
                  item_template.item_id
                }" value="Not OK" ${
                  itemHistory.item_status === "Not OK" ? "checked" : ""
                }>
                     <label for="status_nok_${stage.stage_id}_${
                  item_template.item_id
                }" class="status-icon nok" title="Not OK">✗</label>
                </div>
                ${controlHTML}
            </div>
        </div>`;
              })
              .join("");

            // Format dates properly for Persian display
            const formattedInspectionDate = formatPersianDate(
              history.inspection_date
            );
            const formattedContractorDate = formatPersianDate(
              history.contractor_date
            );

            console.log(`Stage ${stage.stage_id} formatted dates:`, {
              inspection: formattedInspectionDate,
              contractor: formattedContractorDate,
            }); // Debug log

            const stageFooter = `
                        <div class="stage-sections">
                            <fieldset class="consultant-section">
                                <legend>بخش مشاور</legend>
                                <div class="form-group">
                                    <label>وضعیت کلی: <span class="required">*</span></label>
                                    <select name="overall_status" class="validation-required">
                                        <option value="" selected>-- انتخاب کنید --</option>
                                        <option value="OK" ${
                                          history.overall_status === "OK"
                                            ? "selected"
                                            : ""
                                        }>تایید</option>
                                        <option value="Reject" ${
                                          history.overall_status === "Reject"
                                            ? "selected"
                                            : ""
                                        }>رد</option>
                                        <option value="Repair" ${
                                          history.overall_status === "Repair"
                                            ? "selected"
                                            : ""
                                        }>نیاز به تعمیر</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>تاریخ بازرسی: <span class="required">*</span></label>
                                    <input type="text" name="inspection_date" value="${formattedInspectionDate}" data-jdp readonly class="validation-required persian-date">
                                </div>
                                <div class="form-group">
                                    <label>یادداشت:</label>
                                    <textarea name="notes">${
                                      history.notes || ""
                                    }</textarea>
                                </div>
                                <div class="attachments-display-container">
                                    <strong>پیوست‌های موجود:</strong>
                                    <ul class="consultant-attachments-list">${createLinks(
                                      history.attachments
                                    )}</ul>
                                </div>
                                <div class="file-upload-container">
                                    <label>آپلود فایل جدید:</label>
                                    <input type="file" name="attachments[]" multiple>
                                </div>
                            </fieldset>
                            <fieldset class="contractor-section">
                                <legend>بخش پیمانکار</legend>
                                <div class="form-group">
                                    <label>وضعیت: <span class="required">*</span></label>
                                    <select name="contractor_status" class="validation-required">
                                        <option value="" selected>-- انتخاب کنید --</option>
                                        <option value="Pending" ${
                                          history.contractor_status ===
                                          "Pending"
                                            ? "selected"
                                            : ""
                                        }>در حال اجرا</option>
                                        <option value="Pre-Inspection Complete" ${
                                          history.contractor_status ===
                                          "Pre-Inspection Complete"
                                            ? "selected"
                                            : ""
                                        }>آماده</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>تاریخ اعلام: <span class="required">*</span></label>
                                    <input type="text" name="contractor_date" value="${formattedContractorDate}" data-jdp readonly class="validation-required persian-date">
                                </div>
                                <div class="form-group">
                                    <label>توضیحات:</label>
                                    <textarea name="contractor_notes">${
                                      history.contractor_notes || ""
                                    }</textarea>
                                </div>
                                <div class="attachments-display-container">
                                    <strong>پیوست‌های موجود:</strong>
                                    <ul class="contractor-attachments-list">${createLinks(
                                      history.contractor_attachments
                                    )}</ul>
                                </div>
                                <div class="file-upload-container">
                                    <label>آپلود فایل جدید:</label>
                                    <input type="file" name="contractor_attachments[]" multiple>
                                </div>
                            </fieldset>
                        </div>`;

            return `<div id="stage-content-${
              stage.stage_id
            }" class="stage-tab-content ${
              i === 0 ? "active" : ""
            }" data-stage-name="${escapeHtml(
              stage.stage_name
            )}">${historyLogHTML}<div class="checklist-items">${items}</div>${stageFooter}</div>`;
          })
          .join("");

        bodyContentHTML = `
        <input type="hidden" name="elementId" value="${fullElementId}">
         <input type="hidden" name="planFile" value="${escapeHtml(
           dynamicContext.planFile
         )}">
        <div class="stage-tabs-container">${tabButtons}</div>
        <div class="stage-content-container">${tabContents}</div>`;
      }

      const formHTML = `
              <form id="checklist-form" class="form-body-new" novalidate>
                <input type="hidden" name="csrf_token" value="${CSRF_TOKEN}">
                <input type="hidden" name="digital_signature" id="digital-signature">
                <input type="hidden" name="signed_data" id="signed-data">
                ${bodyContentHTML}
            </form>`;

      formPopup.innerHTML = headerHTML + formHTML + footerHTML;

      // Get references to the new elements
      const formElement = document.getElementById("checklist-form");
      const saveButton = formPopup.querySelector(".btn.save");
      const validateButton = formPopup.querySelector("#validate-btn");

      // Clean up any existing event listeners
      if (formElement) {
        const newFormElement = formElement.cloneNode(true);
        formElement.parentNode.replaceChild(newFormElement, formElement);
        const cleanFormElement = document.getElementById("checklist-form");

        // Update button references after cloning
        const newSaveButton = formPopup.querySelector(".btn.save");
        const newValidateButton = formPopup.querySelector("#validate-btn");

        // Keys are already ready at this point
        if (keysReady && userPrivateKey) {
          newSaveButton.textContent = "ذخیره و امضای دیجیتال";
          newValidateButton.disabled = false;
        } else {
          newSaveButton.textContent = "خطا در کلید امضا";
          newValidateButton.textContent = "خطا در کلید امضا";
          newValidateButton.disabled = true;
        }

        // VALIDATION FUNCTION (same as before - no changes needed)
        /**
         * FIXED VERSION: This function now sends the correct data format to match your PHP expectations
         * The key change is in how we structure the stage data before sending
         */
        /**
         * ENHANCED VERSION: Validates and processes ALL tabs with data, not just the active one
         * This replaces the existing validateAllStagesWithPermissions function
         */
        /**
         * ENHANCED VERSION: Validates and processes ALL tabs with data, not just the active one.
         * This function fixes the crash and prepares data for multi-stage submission.
         */
        function validateAllStagesWithPermissions(
          formElement,
          userRole,
          history,
          template
        ) {
          const allTabs = formElement.querySelectorAll(".stage-tab-content");
          const allErrors = [];
          let hasAnyData = false;
          const allStagesData = {}; // This will contain all stages with data

          // Determine permissions (same logic as before, but applied per stage)
          const isSuperuser = userRole === "superuser";
          const isConsultant = userRole === "admin";
          const isContractor = ["cat", "crs", "coa", "crs"].includes(userRole);

          allTabs.forEach((stageEl) => {
            const stageId = stageEl.id.replace("stage-content-", "");
            const stageHistory =
              history.find((h) => String(h.stage_id) === stageId) || {};

            let stageHasData = false;
            const stagePayload = {};

            // Simplified check: if any input has been touched by a permitted user
            // This relies on the setFormState function correctly enabling/disabling sections.

            // Collect consultant data if section is not disabled
            const consultantSection = stageEl.querySelector(
              ".consultant-section"
            );
            if (!consultantSection.disabled) {
              const overallStatus = consultantSection.querySelector(
                '[name="overall_status"]'
              ).value;
              const inspectionDate = consultantSection.querySelector(
                '[name="inspection_date"]'
              ).value;
              const notes =
                consultantSection.querySelector('[name="notes"]').value;

              if (overallStatus) {
                stagePayload.overall_status = overallStatus;
                stageHasData = true;
              }
              if (inspectionDate) {
                stagePayload.inspection_date = inspectionDate;
                stageHasData = true;
              }
              if (notes.trim()) {
                stagePayload.notes = notes;
                stageHasData = true;
              }
            }

            // Collect contractor data if section is not disabled
            const contractorSection = stageEl.querySelector(
              ".contractor-section"
            );
            if (!contractorSection.disabled) {
              const contractorStatus = contractorSection.querySelector(
                '[name="contractor_status"]'
              ).value;
              const contractorDate = contractorSection.querySelector(
                '[name="contractor_date"]'
              ).value;
              const contractorNotes = contractorSection.querySelector(
                '[name="contractor_notes"]'
              ).value;
              if (contractorStatus) {
                stagePayload.contractor_status = contractorStatus;
                stageHasData = true;
              }
              if (contractorDate) {
                stagePayload.contractor_date = contractorDate;
                stageHasData = true;
              }
              if (contractorNotes.trim()) {
                stagePayload.contractor_notes = contractorNotes;
                stageHasData = true;
              }
            }

            // Collect checklist items if enabled
            const checklistItemsEl = stageEl.querySelector(".checklist-items");
            if (checklistItemsEl.style.pointerEvents !== "none") {
              const stageItems = [];
              stageEl.querySelectorAll(".item-row").forEach((itemEl) => {
                const radio = itemEl.querySelector(
                  'input[type="radio"]:checked'
                );
                const textInput = itemEl.querySelector(
                  ".checklist-input, .drawing-data-input"
                );
                // Check if item has changed from its initial state (more robust, but complex)
                // For now, we assume if a value is present, it's intended for submission
                if (radio || (textInput && textInput.value.trim() !== "")) {
                  stageHasData = true;
                  stageItems.push({
                    item_id: textInput.dataset.itemId,
                    status: radio ? radio.value : "Pending", // Default status if none selected but value exists
                    value: textInput.value || "",
                  });
                }
              });
              if (stageItems.length > 0) {
                stagePayload.items = stageItems;
              }
            }

            // Add file uploads to the check for data
            if (
              stageEl.querySelector('input[type="file"]:not(:disabled)').files
                .length > 0
            ) {
              stageHasData = true;
            }

            if (stageHasData) {
              hasAnyData = true;
              allStagesData[stageId] = stagePayload;
            }
          });

          // Basic validation on collected data
          Object.keys(allStagesData).forEach((stageId) => {
            const stageData = allStagesData[stageId];
            const stageName =
              template.find((s) => String(s.stage_id) === stageId)
                ?.stage_name || `مرحله ${stageId}`;

            if (isConsultant || isSuperuser) {
              if (stageData.overall_status && !stageData.inspection_date) {
                allErrors.push(
                  `برای ${stageName}: تاریخ بازرسی الزامی است وقتی وضعیت کلی انتخاب شده.`
                );
              }
            }
            if (isContractor || isSuperuser) {
              if (stageData.contractor_status && !stageData.contractor_date) {
                allErrors.push(
                  `برای ${stageName}: تاریخ اعلام الزامی است وقتی وضعیت پیمانکار انتخاب شده.`
                );
              }
            }
          });

          return {
            isValid: allErrors.length === 0,
            errors: allErrors,
            hasData: hasAnyData,
            allStagesData: allStagesData,
          };
        }

        // ACTUAL SUBMISSION FUNCTION WITH BETTER ERROR HANDLING
        let isSubmitting = false;

        /**
         * Performs the actual data submission to the server.
         */
        async function performActualSubmission(allStagesData) {
          const formPopup = document.getElementById("universalChecklistForm");
          const formElement = document.getElementById("checklist-form");
          const saveButton = formPopup.querySelector(".btn.save");
          const validateButton = formPopup.querySelector("#validate-btn");
          const fullElementId =
            formElement.querySelector('[name="elementId"]').value;
          const planFile = formElement.querySelector('[name="planFile"]').value;

          let isSubmitting = false;
          if (isSubmitting) return;
          isSubmitting = true;

          saveButton.disabled = true;
          validateButton.disabled = true;
          saveButton.textContent = "در حال پردازش...";

          try {
            if (!userPrivateKey) throw new Error("کلید امضا آماده نیست.");

            const dataToSign = JSON.stringify(allStagesData);
            const signature = signData(dataToSign);
            if (!signature) throw new Error("خطا در امضای دیجیتال داده‌ها.");

            console.log("--- CLIENT-SIDE DATA TO SIGN (ALL STAGES) ---");
            console.log(dataToSign);

            const finalFormData = new FormData();
            finalFormData.append("elementId", fullElementId);
            finalFormData.append("planFile", planFile);
            finalFormData.append("csrf_token", CSRF_TOKEN);
            finalFormData.append("stages", dataToSign); // The main payload
            finalFormData.append("signed_data", dataToSign);
            finalFormData.append("digital_signature", signature);

            // IMPORTANT: Gather files from ALL tabs, not just the active one
            formElement
              .querySelectorAll('input[type="file"]')
              .forEach((fileInput) => {
                if (fileInput.name && fileInput.files.length > 0) {
                  for (const file of fileInput.files) {
                    finalFormData.append(fileInput.name, file);
                  }
                }
              });

            saveButton.textContent = "در حال ارسال...";
            const response = await fetch("api/save_inspection.php", {
              method: "POST",
              body: finalFormData,
              credentials: "include",
            });

            const responseData = await response.json();
            if (!response.ok) {
              throw new Error(
                responseData.message || `Server Error: ${response.status}`
              );
            }

            if (responseData.status === "success") {
              alert(responseData.message);
              closeForm("universalChecklistForm");
              if (
                typeof loadAndDisplaySVG === "function" &&
                currentPlanFileName
              ) {
                loadAndDisplaySVG(currentPlanFileName); // Refresh the view
              }
            } else {
              throw new Error(responseData.message || "خطای ناشناخته رخ داد.");
            }
          } catch (error) {
            console.error("Save Error:", error);
            alert("خطا در ذخیره: " + error.message);
          } finally {
            isSubmitting = false;
            saveButton.disabled = false;
            validateButton.disabled = false;
            saveButton.textContent = "ذخیره و امضای دیجیتال";
          }
        }
        // CONFIRMATION MODAL FUNCTION
        function showConfirmationModal(allStagesData, allItemsMap) {
          const modal = document.createElement("div");
          modal.className = "confirmation-modal-overlay";
          modal.style.cssText = `
                position: fixed; top: 0; left: 0; right: 0; bottom: 0;
                background: rgba(0,0,0,0.8); display: flex; align-items: center;
                justify-content: center; z-index: 2000; font-family: 'Vazir', sans-serif;
            `;

          const modalContent = document.createElement("div");
          modalContent.className = "confirmation-modal-content";
          modalContent.style.cssText = `
                background: white; padding: 30px; border-radius: 12px; max-width: 900px;
                max-height: 85vh; overflow-y: auto; box-shadow: 0 15px 35px rgba(0,0,0,0.3);
                direction: rtl; border: 3px solid #007bff;
            `;

          let stagesHTML = "";
          const stageCount = Object.keys(allStagesData).length;
          const stageNameMap = new Map(
            data.template.map((s) => [s.stage_id.toString(), s.stage_name])
          );

          Object.keys(allStagesData).forEach((stageId) => {
            const stageData = allStagesData[stageId];
            const stageName = stageNameMap.get(stageId) || `مرحله ${stageId}`;
            let stageDetailsHTML = "";

            if (stageData.overall_status)
              stageDetailsHTML += `<li><strong>وضعیت کلی:</strong> ${escapeHtml(
                stageData.overall_status
              )}</li>`;
            if (stageData.inspection_date)
              stageDetailsHTML += `<li><strong>تاریخ بازرسی:</strong> ${escapeHtml(
                stageData.inspection_date
              )}</li>`;
            if (stageData.notes)
              stageDetailsHTML += `<li><strong>یادداشت مشاور:</strong> ${escapeHtml(
                stageData.notes
              ).substring(0, 50)}...</li>`;
            if (stageData.contractor_status)
              stageDetailsHTML += `<li><strong>وضعیت پیمانکار:</strong> ${escapeHtml(
                stageData.contractor_status
              )}</li>`;
            if (stageData.contractor_date)
              stageDetailsHTML += `<li><strong>تاریخ اعلام:</strong> ${escapeHtml(
                stageData.contractor_date
              )}</li>`;
            if (stageData.contractor_notes)
              stageDetailsHTML += `<li><strong>توضیحات پیمانکار:</strong> ${escapeHtml(
                stageData.contractor_notes
              ).substring(0, 50)}...</li>`;
            if (stageData.items)
              stageDetailsHTML += `<li><strong>آیتم‌های چک‌لیست:</strong> ${stageData.items.length} مورد تغییر کرده</li>`;

            stagesHTML += `
                    <div style="border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 5px;">
                        <h4 style="margin: 0 0 10px 0; color: #2c3e50;">${escapeHtml(
                          stageName
                        )}</h4>
                        <ul style="margin: 0; padding-right: 20px; font-size: 14px;">${stageDetailsHTML}</ul>
                    </div>
                `;
          });

          modalContent.innerHTML = `
                <h3 style="margin: 0 0 20px 0; color: #2c3e50;">تایید نهایی - ذخیره ${stageCount} مرحله</h3>
                <div style="background: #f8f9fa; padding: 15px; border-radius: 5px;">
                    <p>اطلاعات زیر برای ذخیره و امضای دیجیتال ارسال خواهد شد:</p>
                    ${stagesHTML}
                </div>
                <div style="margin-top: 25px; text-align: center;">
                    <p style="font-weight: bold;">آیا از صحت اطلاعات و ذخیره نهایی اطمینان دارید؟</p>
                    <button id="confirm-save-btn" class="btn save" style="margin-left: 10px; padding: 10px 20px;">تایید و ذخیره</button>
                    <button id="cancel-save-btn" class="btn cancel" style="padding: 10px 20px;">انصراف</button>
                </div>
            `;

          modal.appendChild(modalContent);
          document.body.appendChild(modal);

          modal
            .querySelector("#confirm-save-btn")
            .addEventListener("click", () => {
              document.body.removeChild(modal);
              performActualSubmission(allStagesData);
            });

          const closeModal = () => document.body.removeChild(modal);
          modal
            .querySelector("#cancel-save-btn")
            .addEventListener("click", closeModal);
          modal.addEventListener("click", (e) => {
            if (e.target === modal) closeModal();
          });
        }

        // FORM SUBMIT HANDLER (Hidden, only triggered by confirmation)
        cleanFormElement.addEventListener("submit", function (e) {
          e.preventDefault();
          // This is intentionally left blank as submission is now handled
          // by the validation button and confirmation modal.
        });

        // VALIDATE BUTTON EVENT
        newValidateButton.addEventListener("click", function (e) {
          e.preventDefault();
          console.log("=== VALIDATION STARTING FOR ALL STAGES ===");

          // This function checks permissions for each tab and collects all data.
          const validation = validateAllStagesWithPermissions(
            cleanFormElement,
            USER_ROLE,
            data.history,
            data.template
          );

          console.log("Validation Result:", validation);

          if (!validation.hasData) {
            alert(
              "هیچ داده‌ای برای ذخیره یافت نشد. لطفا حداقل یک فیلد را پر کنید."
            );
            return; // Stop if no data
          }

          if (!validation.isValid) {
            alert(
              "خطاهای زیر باید برطرف شوند:\n\n" + validation.errors.join("\n")
            );
            return; // Stop if there are errors
          }

          // SUCCESS: The validation function has already collected all the data.
          // We now pass its results (`validation.allStagesData`) directly to the confirmation modal.
          // The old, buggy code that tried to collect data again has been removed.
          showConfirmationModal(validation.allStagesData, data.all_items_map);
        });

        // HELPER FUNCTION FOR HIGHLIGHTING ERRORS
        function highlightValidationErrors(formElement, errors) {
          // Clear previous error highlights
          formElement
            .querySelectorAll(".validation-required")
            .forEach((field) => {
              field.classList.remove("error");
            });

          // Highlight fields with errors
          errors.forEach((error) => {
            if (error.includes("وضعیت کلی")) {
              const field = formElement.querySelector(
                '[name="overall_status"]'
              );
              if (field) field.classList.add("error");
            }
            if (error.includes("تاریخ بازرسی")) {
              const field = formElement.querySelector(
                '[name="inspection_date"]'
              );
              if (field) field.classList.add("error");
            }
            if (error.includes("وضعیت") && error.includes("پیمانکار")) {
              const field = formElement.querySelector(
                '[name="contractor_status"]'
              );
              if (field) field.classList.add("error");
            }
            if (error.includes("تاریخ اعلام")) {
              const field = formElement.querySelector(
                '[name="contractor_date"]'
              );
              if (field) field.classList.add("error");
            }
          });
        }

        // INPUT CHANGE HANDLER
        cleanFormElement.addEventListener("input", function (e) {
          const stageContent = e.target.closest(".stage-tab-content");
          if (stageContent) {
            stageContent.dataset.isDirty = "true";
          }
        });

        // FORM SUBMIT HANDLER (Hidden, only triggered by confirmation)
        cleanFormElement.addEventListener("submit", function (e) {
          e.preventDefault();
          // Form submission is now handled through validation button only
        });

        // Setup form state and other initializations
        setFormState(
          formPopup,
          USER_ROLE,
          data.history,
          data.can_edit,
          data.template
        );

        // Initialize Jalali datepicker AFTER form is built and populated
        setTimeout(() => {
          if (typeof jalaliDatepicker !== "undefined") {
            // Destroy any existing datepickers first
            if (window.jalaliDatepicker && window.jalaliDatepicker.destroy) {
              window.jalaliDatepicker.destroy();
            }

            jalaliDatepicker.startWatch({
              selector: "#universalChecklistForm [data-jdp]",
              container: "body",
              zIndex: 1005,
              
              autoSelect: true,
              format: "YYYY/MM/DD",
              showTodayBtn: true,
              showEmptyBtn: false,
              calendar: "persian",
              locale: "fa",
              onSelect: function (unixDate, persianDate, $el) {
                console.log("Date selected:", persianDate);
                $el.value = persianDate;
                // Trigger change event for validation
                $el.dispatchEvent(new Event("change", { bubbles: true }));
              },
            });
          }
        }, 100);

        // Setup stage tabs
        formPopup.querySelectorAll(".stage-tab-button").forEach((button) => {
          button.addEventListener("click", () => {
            formPopup
              .querySelectorAll(".stage-tab-button, .stage-tab-content")
              .forEach((el) => el.classList.remove("active"));
            button.classList.add("active");
            formPopup
              .querySelector(`#${button.dataset.tab}`)
              .classList.add("active");
          });
        });

        // Activate first tab
        const firstTab = formPopup.querySelector(".stage-tab-button");
        if (firstTab) {
          firstTab.click();
        }
      }
    })
    .catch((err) => {
      console.error("DEBUG FAIL: API call failed or form build failed.", err);
      formPopup.innerHTML = `<div class="form-header-new"><h3>خطا</h3></div><div class="form-body-new" style="padding:25px;"><p>خطا در بارگذاری فرم: ${escapeHtml(
        err.message
      )}</p></div><div class="form-footer-new"><button class="btn cancel" onclick="closeForm('universalChecklistForm')">بستن</button></div>`;
    });
}

function openBatchForm() {
  const elements = Array.from(selectedElements.values());
  if (elements.length === 0) {
    return alert("لطفا ابتدا المان‌ها را انتخاب کنید.");
  }

  const firstElementType = elements[0].element_type;
  const allSameType = elements.every(
    (el) => el.element_type === firstElementType
  );
  if (!allSameType) {
    return alert("تمام المان‌های انتخابی باید از یک نوع باشند.");
  }

  // 1. Use the first selected element as a template to generate the form structure.
  const firstElementId = elements[0].element_id;
  const firstElementNode = document.getElementById(firstElementId);
  if (!firstElementNode) {
    return alert("خطا: المان الگو در نقشه یافت نشد.");
  }

  // Create the dynamic context needed by openChecklistForm
  const dynamicContext = {
    elementType: firstElementNode.dataset.elementType,
    planFile: currentPlanFileName,
    block: firstElementNode.dataset.block,
    zoneName: firstElementNode.dataset.zoneName,
    floorLevel: firstElementNode.dataset.floorLevel,
    axisSpan: firstElementNode.dataset.axisSpan,
    widthCm: firstElementNode.dataset.widthCm,
    heightCm: firstElementNode.dataset.heightCm,
    areaSqm: firstElementNode.dataset.areaSqm,
    geometry_json: firstElementNode.dataset.geometry_json,
  };

  // 2. Call your original function. This will open the form and fill it with the first element's data.
  openChecklistForm(firstElementId, firstElementType, dynamicContext);

  // 3. Wait a moment for the form to be built, then modify it for batch use.
  setTimeout(() => {
    modifyFormForBatch(firstElementType);
  }, 500); // 500ms delay ensures the form is fully rendered.
}

/**
 * Modifies the just-opened single form into a batch-ready form.
 */
function modifyFormForBatch(elementType) {
  const formPopup = document.getElementById("universalChecklistForm");
  if (!formPopup) return;

  // A. Update Titles
  const title = formPopup.querySelector(".form-header-new h3");
  const subtitle = formPopup.querySelector(".form-header-new p");
  if (title) title.textContent = `فرم گروهی: ${elementType}`;
  if (subtitle)
    subtitle.textContent = `${selectedElements.size} المان انتخاب شده`;

  // B. Clear All Pre-filled Data
  formPopup
    .querySelectorAll('input[type="text"], textarea')
    .forEach((input) => (input.value = ""));
  formPopup
    .querySelectorAll('input[type="radio"]')
    .forEach((radio) => (radio.checked = false));
  formPopup
    .querySelectorAll("select")
    .forEach((select) => (select.selectedIndex = 0));
  formPopup
    .querySelectorAll(".history-log-container, .attachments-display-container")
    .forEach((el) => (el.innerHTML = ""));

  // C. Re-wire the "Validate" button to submit the batch data
  const validateButton = formPopup.querySelector("#validate-btn");
  if (validateButton) {
    // Clone the button to remove its original event listeners
    const newValidateButton = validateButton.cloneNode(true);
    validateButton.parentNode.replaceChild(newValidateButton, validateButton);

    // Add the new event listener for batch submission
    newValidateButton.textContent = "ثبت برای همه المان ها";
    newValidateButton.addEventListener("click", submitBatchInspection);
  }
}
/**
 * Gathers data from the modified batch form and submits it.
 */
// In ghom_app.js

/**
 * Gathers data from the batch form and submits it WITH THE CORRECT SECURITY TOKEN.
 */
// In ghom_app.js

/**
 * Gathers data from the batch form, CREATES A REAL DIGITAL SIGNATURE, and submits it.
 */
async function submitBatchInspection() {
  const submitBtn = document.querySelector(
    "#universalChecklistForm #validate-btn, #universalChecklistForm #submit-batch-btn"
  );
  const form =
    document.getElementById("checklist-form") ||
    document.getElementById("batch-inspection-form");
  const element_ids = Array.from(selectedElements.keys());

  if (!form) {
    alert("خطای داخلی: فرم یافت نشد.");
    return;
  }

  // Gather data from the form (your existing logic is fine)
  const stagesData = {};
  form.querySelectorAll(".stage-tab-content").forEach((stageEl) => {
    const stageId = stageEl.id
      .replace("stage-content-", "")
      .replace("batch-stage-", "");
    const stagePayload = {};
    const items = [];
    stageEl.querySelectorAll(".item-row").forEach((itemEl) => {
      const radio = itemEl.querySelector('input[type="radio"]:checked');
      const textInput = itemEl.querySelector(".checklist-input");
      if (radio || (textInput && textInput.value.trim() !== "")) {
        items.push({
          item_id: textInput.dataset.itemId,
          status: radio ? radio.value : "Pending",
          value: textInput.value || "",
        });
      }
    });
    if (items.length > 0) stagePayload.items = items;
    const overallStatusInput = stageEl.querySelector(
      `select[name="overall_status"], select[name="batch_overall_status_${stageId}"]`
    );
    const inspectionDateInput = stageEl.querySelector(
      `input[name="inspection_date"], input[name="batch_inspection_date_${stageId}"]`
    );
    const notesInput = stageEl.querySelector(
      `textarea[name="notes"], textarea[name="batch_notes_${stageId}"]`
    );
    if (overallStatusInput && overallStatusInput.value)
      stagePayload.overall_status = overallStatusInput.value;
    if (inspectionDateInput && inspectionDateInput.value)
      stagePayload.inspection_date = inspectionDateInput.value;
    if (notesInput && notesInput.value) stagePayload.notes = notesInput.value;
    if (Object.keys(stagePayload).length > 0)
      stagesData[stageId] = stagePayload;
  });

  if (Object.keys(stagesData).length === 0) {
    return alert("هیچ داده‌ای برای ثبت وارد نشده است.");
  }

  // ===================================================================
  // START: THE CRITICAL FIX IS HERE
  // ===================================================================

  // 1. Prepare the exact data that will be signed and verified on the server.
  const dataToSign = JSON.stringify(stagesData);

  // 2. Generate a real digital signature for that data using your existing function.
  const signature = signData(dataToSign);
  if (!signature) {
    alert("خطا در ایجاد امضای دیجیتال. لطفا دوباره تلاش کنید.");
    return; // Stop if signing fails
  }

  // 3. Build the final payload with the REAL signature and data.
  const payload = {
    element_ids: element_ids,
    stages: JSON.stringify(stagesData),
    csrf_token: CSRF_TOKEN,
    digital_signature: signature, // Use the real signature
    signed_data: dataToSign, // Use the real data that was signed
  };
  // ===================================================================
  // END: THE CRITICAL FIX
  // ===================================================================

  submitBtn.disabled = true;
  submitBtn.textContent = "در حال ارسال...";

  try {
    const response = await fetch("/ghom/api/save_inspection.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: new URLSearchParams(payload),
      credentials: "include", // This is still required
    });
    const result = await response.json();

    if (result.status !== "success") throw new Error(result.message);

    alert(result.message);
    closeForm("universalChecklistForm");
    clearAllSelections();
    loadAndDisplaySVG(currentPlanFileName);
  } catch (error) {
    alert(`خطا در ثبت گروهی: ${error.message}`);
  } finally {
    submitBtn.disabled = false;
    submitBtn.textContent = "ثبت برای همه المان ها";
  }
}
// ===============================================================================
// ADDITIONAL CSS STYLES FOR VALIDATION AND CONFIRMATION MODAL
// Add this CSS to your stylesheet
// ===============================================================================

const validationStyles = `
<style>
.validation-required {
  border: 2px solid #ddd !important;
  transition: border-color 0.3s ease;
}

.validation-required:focus {
  border-color: #007bff !important;
  box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25) !important;
}

.validation-required.error {
  border-color: #dc3545 !important;
  box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25) !important;
}

.required {
  color: #dc3545;
  font-weight: bold;
}

.stage-tab-button {
  position: relative;
  transition: all 0.3s ease;
}

.stage-tab-button.validated::after {
  content: '✓';
  position: absolute;
  top: -5px;
  right: -5px;
  background: #28a745;
  color: white;
  border-radius: 50%;
  width: 20px;
  height: 20px;
  font-size: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.stage-tab-button.has-errors::after {
  content: '!';
  position: absolute;
  top: -5px;
  right: -5px;
  background: #dc3545;
  color: white;
  border-radius: 50%;
  width: 20px;
  height: 20px;
  font-size: 14px;
  font-weight: bold;
  display: flex;
  align-items: center;
  justify-content: center;
}

.confirmation-modal-overlay {
  font-family: 'Vazir', Tahoma, sans-serif;
  direction: rtl;
  text-align: right;
}

.confirmation-modal-content {
  border: 2px solid #007bff;
}

.confirmation-modal-content h3 {
  border-bottom: 2px solid #007bff;
  padding-bottom: 10px;
}

.confirmation-modal-content h4 {
  color: #495057;
  margin: 15px 0 10px 0;
  font-size: 16px;
}

.confirmation-modal-content ul li {
  margin: 5px 0;
  padding: 3px 0;
}

.btn.save {
  background: linear-gradient(45deg, #28a745, #20c997);
  border: none;
  color: white;
  transition: all 0.3s ease;
}

.btn.save:hover:not(:disabled) {
  background: linear-gradient(45deg, #218838, #17a085);
  transform: translateY(-1px);
  box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.btn.save:disabled {
  background: #6c757d;
  cursor: not-allowed;
  transform: none;
  box-shadow: none;
}

.btn.secondary {
  background: linear-gradient(45deg, #007bff, #0056b3);
  border: none;
  color: white;
  transition: all 0.3s ease;
}

.btn.secondary:hover:not(:disabled) {
  background: linear-gradient(45deg, #0056b3, #004085);
  transform: translateY(-1px);
  box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.btn.cancel {
  background: linear-gradient(45deg, #6c757d, #495057);
  border: none;
  color: white;
  transition: all 0.3s ease;
}

.btn.cancel:hover {
  background: linear-gradient(45deg, #5a6268, #343a40);
  transform: translateY(-1px);
  box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.item-row {
  padding: 10px;
  border: 1px solid #e9ecef;
  border-radius: 5px;
  margin-bottom: 10px;
  transition: all 0.3s ease;
}

.item-row:hover {
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  border-color: #007bff;
}

.item-row.incomplete {
  border-color: #ffc107;
  background: #fff3cd;
}

.item-row.complete {
  border-color: #28a745;
  background: #d4edda;
}

.status-selector-new input[type="radio"]:checked + label {
  transform: scale(1.2);
  box-shadow: 0 0 10px rgba(0,0,0,0.3);
}

.form-group label .required {
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0% { opacity: 1; }
  50% { opacity: 0.5; }
  100% { opacity: 1; }
}

.validation-summary {
  background: #f8f9fa;
  border: 1px solid #dee2e6;
  border-radius: 5px;
  padding: 15px;
  margin: 15px 0;
}

.validation-summary.has-errors {
  background: #f8d7da;
  border-color: #f5c6cb;
  color: #721c24;
}

.validation-summary.has-warnings {
  background: #fff3cd;
  border-color: #ffeaa7;
  color: #856404;
}

.validation-summary.all-good {
  background: #d4edda;
  border-color: #c3e6cb;
  color: #155724;
}

.progress-indicator {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #f8f9fa;
  padding: 10px;
  border-radius: 5px;
  margin-bottom: 20px;
  border: 1px solid #dee2e6;
}

.progress-step {
  flex: 1;
  text-align: center;
  padding: 5px;
  border-radius: 3px;
  font-size: 12px;
  font-weight: bold;
  margin: 0 2px;
  transition: all 0.3s ease;
}

.progress-step.completed {
  background: #28a745;
  color: white;
}

.progress-step.current {
  background: #007bff;
  color: white;
}

.progress-step.pending {
  background: #6c757d;
  color: white;
}
  .validation-required.error {
  border: 2px solid #dc3545 !important;
  background-color: #ffe6e6 !important;
}

.validation-required.error:focus {
  box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25) !important;
}

/* Persian date input styling */
.persian-date {
  direction: ltr;
  text-align: center;
  font-family: 'Vazir', 'Tahoma', sans-serif;
}

/* Date picker container styling */
.jalali-datepicker-con {
  z-index: 1010 !important;
  font-family: 'Vazir', 'Tahoma', sans-serif;
}

/* Loading state for keys */
.btn.save:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn.secondary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* Confirmation modal styling improvements */
.confirmation-modal-overlay {
  backdrop-filter: blur(2px);
}

.confirmation-modal-content {
  font-family: 'Vazir', 'Tahoma', sans-serif;
  direction: rtl;
}

.confirmation-modal-content h3 {
  border-bottom: 2px solid #007bff;
  padding-bottom: 10px;
}

.confirmation-modal-content h4 {
  color: #495057;
  margin: 15px 0 10px 0;
  font-size: 16px;
}

.confirmation-modal-content ul {
  background: #f8f9fa;
  padding: 10px;
  border-radius: 5px;
  margin: 5px 0;
}

.confirmation-modal-content li {
  margin: 5px 0;
  padding: 2px 0;
}

/* Better button styling in confirmation modal */
.confirmation-modal-content .btn {
  min-width: 120px;
  transition: all 0.2s ease;
}

.confirmation-modal-content .btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}

/* Form sections disable state */
fieldset:disabled {
  opacity: 0.6;
  pointer-events: none;
}

fieldset:disabled legend {
  color: #6c757d;
}

/* Status indicators for item completion */
.item-row.complete {
  background-color: #d4edda;
  border-left: 4px solid #28a745;
  padding-left: 10px;
  margin: 2px 0;
  transition: all 0.3s ease;
}

.item-row.incomplete {
  background-color: #f8d7da;
  border-left: 4px solid #dc3545;
  padding-left: 10px;
  margin: 2px 0;
  transition: all 0.3s ease;
}

/* Improved form responsiveness */
@media (max-width: 768px) {
  .confirmation-modal-content {
    margin: 20px;
    padding: 20px;
    max-height: 90vh;
  }
  
  .form-meta {
    flex-direction: column;
  }
  
  .form-meta span {
    margin: 2px 0;
  }
}
</style>
`;

// ===============================================================================
// VALIDATION UTILITIES AND HELPER FUNCTIONS
// ===============================================================================

// Function to highlight validation errors on fields
function highlightValidationErrors(formElement, errors) {
  // Clear previous error highlights
  formElement.querySelectorAll(".validation-required").forEach((field) => {
    field.classList.remove("error");
  });

  // Highlight fields with errors
  errors.forEach((error) => {
    if (error.includes("وضعیت کلی")) {
      const field = formElement.querySelector('[name="overall_status"]');
      if (field) field.classList.add("error");
    }
    if (error.includes("تاریخ بازرسی")) {
      const field = formElement.querySelector('[name="inspection_date"]');
      if (field) field.classList.add("error");
    }
    if (error.includes("وضعیت") && error.includes("پیمانکار")) {
      const field = formElement.querySelector('[name="contractor_status"]');
      if (field) field.classList.add("error");
    }
    if (error.includes("تاریخ اعلام")) {
      const field = formElement.querySelector('[name="contractor_date"]');
      if (field) field.classList.add("error");
    }
  });
}

// Function to update progress indicator
function updateProgressIndicator(
  formPopup,
  currentStageId,
  templateData,
  validationResults = {}
) {
  let progressHTML = '<div class="progress-indicator">';

  templateData.forEach((stage, index) => {
    let stepClass = "pending";
    const stageValidation = validationResults[stage.stage_id];

    if (stage.stage_id == currentStageId) {
      stepClass = "current";
    } else if (stageValidation && stageValidation.completed) {
      stepClass = "completed";
    }

    progressHTML += `<div class="progress-step ${stepClass}">${stage.stage_name}</div>`;
  });

  progressHTML += "</div>";

  // Insert progress indicator after form header
  const existingProgress = formPopup.querySelector(".progress-indicator");
  if (existingProgress) {
    existingProgress.outerHTML = progressHTML;
  } else {
    const formHeader = formPopup.querySelector(".form-header-new");
    if (formHeader) {
      formHeader.insertAdjacentHTML("afterend", progressHTML);
    }
  }
}

// Function to update tab indicators based on validation
function updateTabValidationIndicators(formPopup, stageId, validationResult) {
  const tabButton = formPopup.querySelector(`[data-stage-id="${stageId}"]`);
  if (!tabButton) return;

  // Remove existing indicators
  tabButton.classList.remove("validated", "has-errors");

  if (validationResult.isValid && validationResult.hasData) {
    tabButton.classList.add("validated");
  } else if (!validationResult.isValid) {
    tabButton.classList.add("has-errors");
  }
}

// Function to create validation summary
function createValidationSummary(validationResult) {
  let summaryClass = "validation-summary";
  let summaryContent = "";
  let icon = "";

  if (validationResult.errors.length > 0) {
    summaryClass += " has-errors";
    icon = "❌";
    summaryContent = `
      <h4>${icon} خطاهای موجود (${validationResult.errors.length}):</h4>
      <ul>${validationResult.errors
        .map((error) => `<li>${error}</li>`)
        .join("")}</ul>
    `;
  } else if (validationResult.warnings.length > 0) {
    summaryClass += " has-warnings";
    icon = "⚠️";
    summaryContent = `
      <h4>${icon} هشدارها (${validationResult.warnings.length}):</h4>
      <ul>${validationResult.warnings
        .map((warning) => `<li>${warning}</li>`)
        .join("")}</ul>
    `;
  } else {
    summaryClass += " all-good";
    icon = "✅";
    summaryContent = `<h4>${icon} همه چیز آماده است!</h4><p>تمام فیلدهای الزامی پر شده‌اند.</p>`;
  }

  return `<div class="${summaryClass}">${summaryContent}</div>`;
}

// Enhanced validation function with more detailed checks
function validateActiveStageEnhanced(formElement, userRole) {
  const activeTab = formElement.querySelector(".stage-tab-content.active");
  if (!activeTab)
    return {
      isValid: false,
      errors: ["هیچ مرحله فعالی یافت نشد"],
      warnings: [],
      hasData: false,
    };

  const errors = [];
  const warnings = [];
  const info = [];
  let hasData = false;

  // Get stage information
  const stageName = activeTab.dataset.stageName || "مرحله فعلی";
  const stageId = activeTab.id.replace("stage-content-", "");

  // Check checklist items
  const itemRows = activeTab.querySelectorAll(".item-row");
  let checkedItems = 0;
  let totalItems = itemRows.length;

  itemRows.forEach((itemRow, index) => {
    const itemText = itemRow.querySelector(".item-text").textContent.trim();
    const radio = itemRow.querySelector('input[type="radio"]:checked');
    const textInput = itemRow.querySelector(
      ".checklist-input, .drawing-data-input"
    );

    // Update visual indicators
    itemRow.classList.remove("complete", "incomplete");

    if (radio) {
      checkedItems++;
      hasData = true;
      itemRow.classList.add("complete");

      // Check if text input has value when status is "Not OK"
      if (radio.value === "Not OK" && textInput && !textInput.value.trim()) {
        warnings.push(`آیتم "${itemText}" رد شده اما توضیحی ارائه نشده است`);
      }
    } else {
      itemRow.classList.add("incomplete");
      warnings.push(`آیتم شماره ${index + 1}: "${itemText}" انتخاب نشده است`);
    }
  });

  // Summary of checklist completion
  if (totalItems > 0) {
    info.push(`تکمیل چک لیست: ${checkedItems} از ${totalItems} آیتم`);
    if (checkedItems === totalItems) {
      info.push("✅ تمام آیتم‌های چک لیست تکمیل شده‌اند");
    }
  }

  // Role-based validation
  const consultantSection = activeTab.querySelector(".consultant-section");
  const contractorSection = activeTab.querySelector(".contractor-section");

  // Consultant section validation
  if (
    consultantSection &&
    !consultantSection.disabled &&
    (userRole === "admin" || userRole === "superuser")
  ) {
    const overallStatus = consultantSection.querySelector(
      '[name="overall_status"]'
    );
    const inspectionDate = consultantSection.querySelector(
      '[name="inspection_date"]'
    );
    const notes = consultantSection.querySelector('[name="notes"]');

    if (!overallStatus || !overallStatus.value.trim()) {
      errors.push("انتخاب وضعیت کلی در بخش مشاور الزامی است");
    } else {
      hasData = true;
      info.push(`وضعیت کلی: ${overallStatus.value}`);
    }

    if (!inspectionDate || !inspectionDate.value.trim()) {
      errors.push("انتخاب تاریخ بازرسی در بخش مشاور الزامی است");
    } else {
      hasData = true;
      info.push(`تاریخ بازرسی: ${inspectionDate.value}`);
    }

    if (notes && notes.value.trim()) {
      info.push("یادداشت مشاور: موجود");
    }

    // Check file uploads
    const fileInputs = consultantSection.querySelectorAll('input[type="file"]');
    let hasNewFiles = false;
    fileInputs.forEach((input) => {
      if (input.files && input.files.length > 0) {
        hasNewFiles = true;
        info.push(`${input.files.length} فایل جدید برای آپلود انتخاب شده`);
      }
    });
  }

  // Contractor section validation
  if (
    contractorSection &&
    !contractorSection.disabled &&
    (userRole === "cat" ||
      userRole === "crs" ||
      userRole === "coa" ||
      userRole === "crs" ||
      userRole === "superuser")
  ) {
    const contractorStatus = contractorSection.querySelector(
      '[name="contractor_status"]'
    );
    const contractorDate = contractorSection.querySelector(
      '[name="contractor_date"]'
    );
    const contractorNotes = contractorSection.querySelector(
      '[name="contractor_notes"]'
    );

    if (!contractorStatus || !contractorStatus.value.trim()) {
      errors.push("انتخاب وضعیت در بخش پیمانکار الزامی است");
    } else {
      hasData = true;
      info.push(`وضعیت پیمانکار: ${contractorStatus.value}`);
    }

    if (!contractorDate || !contractorDate.value.trim()) {
      errors.push("انتخاب تاریخ اعلام در بخش پیمانکار الزامی است");
    } else {
      hasData = true;
      info.push(`تاریخ اعلام: ${contractorDate.value}`);
    }

    if (contractorNotes && contractorNotes.value.trim()) {
      info.push("توضیحات پیمانکار: موجود");
    }

    // Check contractor file uploads
    const contractorFileInputs =
      contractorSection.querySelectorAll('input[type="file"]');
    contractorFileInputs.forEach((input) => {
      if (input.files && input.files.length > 0) {
        info.push(
          `${input.files.length} فایل جدید پیمانکار برای آپلود انتخاب شده`
        );
      }
    });
  }

  // Overall data check
  if (!hasData) {
    errors.push(
      "هیچ داده‌ای برای ذخیره یافت نشد. لطفا حداقل یک فیلد را پر کنید."
    );
  }

  return {
    isValid: errors.length === 0,
    errors,
    warnings,
    info,
    hasData,
    stageName,
    stageId,
    completionRate:
      totalItems > 0 ? Math.round((checkedItems / totalItems) * 100) : 0,
    totalItems,
    checkedItems,
  };
}

// Inject CSS styles
if (!document.getElementById("validation-styles")) {
  const styleElement = document.createElement("style");
  styleElement.id = "validation-styles";
  styleElement.innerHTML = validationStyles
    .replace("<style>", "")
    .replace("</style>", "");
  document.head.appendChild(styleElement);
}

// ===============================================================================
// KEYBOARD SHORTCUTS AND ACCESSIBILITY FEATURES
// ===============================================================================

// Add keyboard shortcuts for better UX
function addKeyboardShortcuts(formElement, validateButton, saveButton) {
  document.addEventListener("keydown", function (e) {
    // Only work when form is open
    if (
      !document
        .getElementById("universalChecklistForm")
        .classList.contains("show")
    ) {
      return;
    }

    // Ctrl+Enter or Cmd+Enter to validate
    if ((e.ctrlKey || e.metaKey) && e.key === "Enter") {
      e.preventDefault();
      if (!validateButton.disabled) {
        validateButton.click();
      }
    }

    // Ctrl+S or Cmd+S to save (after validation)
    if ((e.ctrlKey || e.metaKey) && e.key === "s") {
      e.preventDefault();
      if (!saveButton.disabled && saveButton.style.display !== "none") {
        saveButton.click();
      }
    }

    // Escape to close form
    if (e.key === "Escape") {
      const modal = document.querySelector(".confirmation-modal-overlay");
      if (modal) {
        modal.click(); // Close confirmation modal
      } else {
        closeForm("universalChecklistForm");
      }
    }

    // Tab navigation between stages (Ctrl+Tab)
    if (e.ctrlKey && e.key === "Tab") {
      e.preventDefault();
      const tabs = formElement.querySelectorAll(".stage-tab-button");
      const activeTab = formElement.querySelector(".stage-tab-button.active");
      const currentIndex = Array.from(tabs).indexOf(activeTab);

      let nextIndex;
      if (e.shiftKey) {
        nextIndex = currentIndex > 0 ? currentIndex - 1 : tabs.length - 1;
      } else {
        nextIndex = currentIndex < tabs.length - 1 ? currentIndex + 1 : 0;
      }

      tabs[nextIndex].click();
    }
  });
}

// ===============================================================================
// AUTO-SAVE FUNCTIONALITY (Optional)
// ===============================================================================

// Auto-save draft data to localStorage (if enabled)
function setupAutoSave(formElement, elementId) {
  const autoSaveKey = `checklist_draft_${elementId}`;
  let autoSaveTimer;

  function saveFormData() {
    try {
      const activeTab = formElement.querySelector(".stage-tab-content.active");
      if (!activeTab) return;

      const formData = {
        timestamp: Date.now(),
        stageId: activeTab.id.replace("stage-content-", ""),
        data: {},
      };

      // Save form field values
      const inputs = activeTab.querySelectorAll("input, select, textarea");
      inputs.forEach((input) => {
        if (input.type === "radio") {
          if (input.checked) {
            formData.data[input.name] = input.value;
          }
        } else if (input.type !== "file") {
          formData.data[input.name || input.id] = input.value;
        }
      });

      localStorage.setItem(autoSaveKey, JSON.stringify(formData));
      console.log("Form data auto-saved");
    } catch (error) {
      console.warn("Auto-save failed:", error);
    }
  }

  function loadFormData() {
    try {
      const savedData = localStorage.getItem(autoSaveKey);
      if (savedData) {
        const data = JSON.parse(savedData);

        // Check if data is recent (less than 24 hours old)
        if (Date.now() - data.timestamp < 24 * 60 * 60 * 1000) {
          const shouldRestore = confirm(
            "یافت شد اطلاعات ذخیره شده قبلی. آیا می‌خواهید آن‌ها را بازیابی کنید؟"
          );

          if (shouldRestore) {
            // Restore form data
            Object.keys(data.data).forEach((key) => {
              const element = formElement.querySelector(
                `[name="${key}"], #${key}`
              );
              if (element) {
                if (element.type === "radio") {
                  const radio = formElement.querySelector(
                    `[name="${key}"][value="${data.data[key]}"]`
                  );
                  if (radio) radio.checked = true;
                } else {
                  element.value = data.data[key];
                }
              }
            });
          }
        }
      }
    } catch (error) {
      console.warn("Failed to load auto-saved data:", error);
    }
  }

  function clearAutoSave() {
    try {
      localStorage.removeItem(autoSaveKey);
      console.log("Auto-save data cleared");
    } catch (error) {
      console.warn("Failed to clear auto-save:", error);
    }
  }

  // Auto-save every 30 seconds
  formElement.addEventListener("input", function () {
    clearTimeout(autoSaveTimer);
    autoSaveTimer = setTimeout(saveFormData, 30000);
  });

  // Load data on form open
  setTimeout(loadFormData, 1000);

  // Clear auto-save on successful submission
  return { clearAutoSave };
}

// ===============================================================================
// ENHANCED CLOSEFORM FUNCTION
// ===============================================================================

// Enhanced closeForm function with cleanup
function closeFormEnhanced(formId) {
  const formPopup = document.getElementById(formId);
  if (formPopup) {
    // Check for unsaved changes
    const isDirty = formPopup.querySelector('[data-is-dirty="true"]');
    if (isDirty) {
      const shouldClose = confirm(
        "تغییرات ذخیره نشده‌ای دارید. آیا مطمئن هستید که می‌خواهید فرم را ببندید؟"
      );
      if (!shouldClose) {
        return false;
      }
    }

    // Reset submission counter
    if (typeof resetSubmissionCounter === "function") {
      resetSubmissionCounter();
    }

    // Remove event listeners
    const cleanFormPopup = formPopup.cloneNode(true);
    formPopup.parentNode.replaceChild(cleanFormPopup, formPopup);

    // Hide the form
    cleanFormPopup.classList.remove("show");

    // Clear content after animation
    setTimeout(() => {
      cleanFormPopup.innerHTML = "";
    }, 500);

    console.log(`Form ${formId} closed and cleaned up`);
    return true;
  }
}

// ===============================================================================
// GLOBAL FORM MANAGEMENT
// ===============================================================================

// Global form state management
window.activeFormInstance = null;

// Prevent multiple form instances
function preventMultipleFormInstances() {
  if (window.activeFormInstance) {
    console.warn("Another form instance is already active");
    return false;
  }
  window.activeFormInstance = true;
  return true;
}

function releaseFormInstance() {
  window.activeFormInstance = null;
}

// ===============================================================================
// UTILITY FUNCTIONS
// ===============================================================================

// Format date for display
function formatDateForDisplay(dateString) {
  if (!dateString) return "";

  // If it's already in Jalali format, return as is
  if (dateString.includes("/") && dateString.split("/").length === 3) {
    return dateString;
  }

  // Convert Gregorian to Jalali if needed
  try {
    const date = new Date(dateString);
    if (typeof gregorian_to_jalali === "function") {
      const jalali = gregorian_to_jalali(
        date.getFullYear(),
        date.getMonth() + 1,
        date.getDate()
      );
      return `${jalali[0]}/${String(jalali[1]).padStart(2, "0")}/${String(
        jalali[2]
      ).padStart(2, "0")}`;
    }
  } catch (e) {
    console.warn("Date conversion failed:", e);
  }

  return dateString;
}

// Validate Jalali date format
function isValidJalaliDate(dateString) {
  if (!dateString) return false;

  const parts = dateString.split("/");
  if (parts.length !== 3) return false;

  const year = parseInt(parts[0]);
  const month = parseInt(parts[1]);
  const day = parseInt(parts[2]);

  return (
    year >= 1300 &&
    year <= 1450 &&
    month >= 1 &&
    month <= 12 &&
    day >= 1 &&
    day <= 31
  );
}

// Create backup of form data before submission
function createFormBackup(formData, elementId) {
  try {
    const backup = {
      timestamp: Date.now(),
      elementId: elementId,
      data: formData,
    };

    localStorage.setItem(
      `form_backup_${elementId}_${Date.now()}`,
      JSON.stringify(backup)
    );

    // Clean old backups (keep only last 5)
    const allKeys = Object.keys(localStorage);
    const backupKeys = allKeys.filter((key) =>
      key.startsWith(`form_backup_${elementId}_`)
    );
    backupKeys.sort();

    while (backupKeys.length > 5) {
      localStorage.removeItem(backupKeys.shift());
    }
  } catch (error) {
    console.warn("Failed to create form backup:", error);
  }
}

// ===============================================================================
// USAGE INSTRUCTIONS AND TIPS
// ===============================================================================

/*
USAGE INSTRUCTIONS FOR THE ENHANCED openChecklistForm:

1. VALIDATION FLOW:
   - Fill out the form normally
   - Click "بررسی و تایید نهایی" to validate
   - Review errors and warnings in the modal
   - Confirm to proceed with submission

2. KEYBOARD SHORTCUTS:
   - Ctrl+Enter: Validate form
   - Ctrl+S: Submit (after validation)
   - Escape: Close form/modal
   - Ctrl+Tab: Navigate between stages

3. VISUAL INDICATORS:
   - Red border: Required fields with errors
   - Green checkmark on tabs: Validated stages
   - Red exclamation on tabs: Stages with errors
   - Progress bar: Shows completion status

4. AUTO-SAVE:
   - Automatically saves draft every 30 seconds
   - Offers to restore on form reopen
   - Cleared after successful submission

5. VALIDATION RULES:
   - All required fields must be filled
   - At least one piece of data must be entered
   - Date fields must be valid Jalali dates
   - File uploads are optional but tracked

6. CONFIRMATION MODAL:
   - Shows summary of all data being submitted
   - Highlights warnings (unchecked items, missing notes)
   - Requires explicit confirmation before submission
   - Shows digital signature status

7. ERROR HANDLING:
   - Network errors with retry options
   - Validation errors with field highlighting
   - Prevents duplicate submissions
   - Graceful fallbacks for missing data

8. ACCESSIBILITY:
   - Screen reader friendly
   - Keyboard navigation support
   - High contrast validation indicators
   - RTL language support

INTEGRATION NOTES:
- Replace your existing openChecklistForm function with this complete version
- Ensure all required CSS classes exist in your stylesheet
- Make sure escapeHtml, USER_ROLE, CSRF_TOKEN are globally available
- Verify setFormState and renderHistoryLogHTML functions exist
- Test jalaliDatepicker integration
- Confirm digital signature functions (signData, checkAndSetupKeys) work correctly

CUSTOMIZATION OPTIONS:
- Modify validation rules in validateActiveStageEnhanced()
- Adjust auto-save interval (currently 30 seconds)
- Change keyboard shortcuts in addKeyboardShortcuts()
- Customize modal styling via CSS
- Add additional progress indicators as needed
*/

/**
 * Sets the editable state of the inspection form based on user role, element type,
 * and the detailed, independent status of each stage (tab).
 *
 * @param {HTMLElement} formPopup The main form container element.
 * @param {string} userRole The role of the currently logged-in user.
 * @param {Array} history An array of all inspection records for the element.
 * @param {boolean} canEditFromServer A master security flag from the server.
 * @param {Array} template The template structure containing all possible stages.
 */
function setFormState(
  formPopup,
  userRole,
  history,
  canEditFromServer,
  template
) {
  const isSuperuser = userRole === "superuser";
  const isConsultant = userRole === "admin";
  const isContractor = ["cat", "crs", "coa", "crs"].includes(userRole);

  const saveButton = formPopup.querySelector(".btn.save");
  let isAnySectionEditable = false;

  // --- Get the element type from the template data ---
  const elementType = template?.[0]?.items?.[0]?.element_type || "Unknown";

  // --- Definitive Workflow Logic based on Element Type ---
  let isConsultantsTurnOverall = false;

  if (elementType === "GFRC") {
    // For GFRC, use the complex, multi-stage workflow logic.
    const allPossibleStageIds = Array.isArray(template)
      ? template.map((stage) => String(stage.stage_id))
      : [];
    const allStagesAreFinalized =
      allPossibleStageIds.length > 0 &&
      allPossibleStageIds.every((stageId) => {
        const stageHistory = history.find(
          (h) => String(h.stage_id) === stageId
        );
        return (
          stageHistory && ["OK", "Reject"].includes(stageHistory.overall_status)
        );
      });
    const processHasStarted = history.length > 0;
    const isAnyAwaitingReinspection = history.some(
      (h) => h.status === "Awaiting Re-inspection"
    );
    isConsultantsTurnOverall =
      (processHasStarted || isAnyAwaitingReinspection) &&
      !allStagesAreFinalized;
  } else {
    // For NON-GFRC elements, the logic is simpler:
    // It's the consultant's turn unless all stages have been finalized.
    const allPossibleStageIds = Array.isArray(template)
      ? template.map((stage) => String(stage.stage_id))
      : [];
    const allStagesAreFinalized =
      allPossibleStageIds.length > 0 &&
      allPossibleStageIds.every((stageId) => {
        const stageHistory = history.find(
          (h) => String(h.stage_id) === stageId
        );
        return (
          stageHistory && ["OK", "Reject"].includes(stageHistory.overall_status)
        );
      });
    isConsultantsTurnOverall = !allStagesAreFinalized;
  }

  // --- Loop through each rendered tab to set its state ---
  formPopup.querySelectorAll(".stage-tab-content").forEach((stageContentEl) => {
    const stageId = stageContentEl.id.replace("stage-content-", "");
    const stageHistory =
      history.find((h) => String(h.stage_id) === stageId) || {};

    const tabButton = formPopup.querySelector(
      `.stage-tab-button[data-tab="stage-content-${stageId}"]`
    );
    const consultantSection = stageContentEl.querySelector(
      ".consultant-section"
    );
    const contractorSection = stageContentEl.querySelector(
      ".contractor-section"
    );
    const checklistItems = stageContentEl.querySelector(".checklist-items");
    if (!consultantSection || !contractorSection || !checklistItems) return;

    // Default state: everything is locked.
    consultantSection.disabled = true;
    contractorSection.disabled = true;
    checklistItems.style.pointerEvents = "none";
    checklistItems.style.opacity = "0.7";

    // --- START OF FIX: Define all variables before use ---
    const rejectionCount = parseInt(stageHistory.repair_rejection_count || 0);
    const stageSpecificConsultantStatus = stageHistory.overall_status;
    const stageSpecificContractorStatus = stageHistory.contractor_status;
    // --- END OF FIX ---

    let isThisTabEditable = false;

    if (isSuperuser) {
      isThisTabEditable = true;
    } else if (isConsultant) {
      if (
        isConsultantsTurnOverall &&
        !["OK", "Reject"].includes(stageSpecificConsultantStatus)
      ) {
        isThisTabEditable = true;
      }
    } else if (isContractor) {
      if (
        stageSpecificConsultantStatus === "Repair" &&
        stageSpecificContractorStatus !== "Awaiting Re-inspection" &&
        rejectionCount < 3
      ) {
        isThisTabEditable = true;
      }
    }

    // Apply editability to the sections of THIS tab.
    if (isThisTabEditable) {
      isAnySectionEditable = true;
      if (isSuperuser || isConsultant) {
        consultantSection.disabled = false;
        checklistItems.style.pointerEvents = "auto";
        checklistItems.style.opacity = "1";
        if (isSuperuser) contractorSection.disabled = false;
      } else if (isContractor) {
        contractorSection.disabled = false;
      }
    }

    // Style the tab button based on its status.
    if (tabButton) {
      tabButton.style.borderBottom = "4px solid transparent";
      tabButton.style.fontWeight = "normal";
      const statusForColor =
        stageSpecificConsultantStatus ||
        stageSpecificContractorStatus ||
        "Pending";
      const color = STATUS_COLORS[statusForColor];
      if (
        color &&
        statusForColor !== "Pending" &&
        statusForColor !== "Awaiting Re-inspection"
      ) {
        tabButton.style.borderBottomColor = color;
        tabButton.style.fontWeight = "bold";
      }
    }

    // Visual cue for a locked contractor section.
    if (rejectionCount >= 3) {
      const legend = contractorSection.querySelector("legend");
      if (legend && !legend.textContent.includes("(قفل شده)")) {
        legend.textContent += " (قفل شده)";
      }
    }
  });

  // Finally, decide if the main save button should be visible.
  if (saveButton) {
    saveButton.style.display = isAnySectionEditable ? "inline-block" : "none";
  }
}

//<editor-fold desc="SVG Initialization and Interaction">
/**
 * Makes an SVG element interactive by adding the correct click handler.
 * This version uses the dedicated 'planroles' constant for permission checks.
 * @param {SVGElement} element The SVG element to make interactive.
 * @param {string} groupId The ID of the parent group (e.g., 'Atieh', 'GFRC').
 * @param {string} elementId The specific ID of the element.
 * @param {boolean} isPlan A flag indicating if the current view is the main plan.
 */
function makeElementInteractive(element, groupId, elementId, isPlan) {
  element.classList.add("interactive-element");

  const clickHandler = (event) => {
    event.stopPropagation();

    if (isPlan) {
      // --- LOGIC FOR MAIN PLAN NAVIGATION ---
      console.log(
        `Main plan click detected on region group: ${element.dataset.regionKey}`
      );

      // Safety Check: Ensure the planroles configuration exists
      if (typeof planroles === "undefined") {
        console.error(
          "CRITICAL ERROR: 'planroles' configuration object is missing."
        );
        return;
      }

      const userRole = document.body.dataset.userRole;
      const isAdmin = userRole === "admin" || userRole === "superuser";
      const regionKey = element.dataset.regionKey;

      if (!regionKey) {
        console.warn(
          "Warning: Clicked element on the main plan is missing a 'data-region-key'. Click ignored.",
          element
        );
        return;
      }

      const regionRoleConfig = planroles[regionKey];

      if (!regionRoleConfig) {
        console.warn(
          `Region "${regionKey}" not found in 'planroles' config. Click ignored.`
        );
        return;
      }

      const hasPermission =
        isAdmin ||
        (regionRoleConfig.contractor_id &&
          userRole &&
          regionRoleConfig.contractor_id.trim() === userRole.trim());
      console.log(
        `Permission check for region '${regionKey}': ${
          hasPermission ? "GRANTED" : "DENIED"
        }`
      );

      if (hasPermission) {
        showZoneSelectionMenu(regionKey, event);
      } else {
        console.log(
          `Access Denied: User role '[${userRole}]' cannot access region '[${regionKey}]'.`
        );
      }
    } else {
      // --- LOGIC FOR ZONE PLAN INSPECTIONS (SINGLE & BATCH) ---
      console.log(`Zone plan click detected on element: ${elementId}`);

      if (event.ctrlKey || event.metaKey) {
        // MULTI-SELECT MODE (Ctrl+Click or Cmd+Click)
        console.log(
          `Batch selection action (Ctrl+Click) on element: ${elementId}`
        );
        toggleSelection(element);
      } else {
        // SINGLE-SELECT MODE (Normal Click)
        console.log(`Single inspection action for element: ${elementId}`);
        clearAllSelections();
        closeAllForms();

        currentlyActiveSvgElement = element;
        element.classList.add("svg-element-active");

        const dynamicContext = {
          elementType: element.dataset.elementType,
          planFile: currentPlanFileName,
          block: element.dataset.block,
          zoneName: element.dataset.zoneName,
          floorLevel: element.dataset.floorLevel,
          axisSpan: element.dataset.axisSpan,
          widthCm: element.dataset.widthCm,
          heightCm: element.dataset.heightCm,
          areaSqm: element.dataset.areaSqm,
          geometry_json: element.dataset.geometry_json,
        };

        if (element.dataset.elementType === "GFRC") {
          console.log(`Element is GFRC, showing part selection menu.`);
          showGfrcSubPanelMenu(element, dynamicContext);
        } else {
          console.log(
            `Element is ${element.dataset.elementType}, opening form directly.`
          );
          openChecklistForm(
            elementId,
            element.dataset.elementType,
            dynamicContext
          );
        }
      }
    }
  };

  element.addEventListener("click", clickHandler);
}

function initializeElementsByType(groupElement, elementType, groupId) {
  const isPlan = currentPlanFileName.toLowerCase() === "plan.svg";
  const elements = groupElement.querySelectorAll("path, rect, circle, polygon");

  elements.forEach((el, index) => {
    if (isPlan) {
      el.dataset.regionKey = groupId;
      makeElementInteractive(el, groupId, el.id || `${groupId}_${index}`, true);
    } else {
      if (!el.id) return;
      const dbData = currentPlanDbData[el.id];
      if (dbData) {
        // Step 1: ALWAYS attach all data from the database to the element's dataset.
        // This makes data available for tooltips or other features, even if not clickable.
        el.dataset.uniquePanelId = el.id;
        el.dataset.elementType = dbData.type;
        el.dataset.axisSpan = dbData.axis;
        el.dataset.floorLevel = dbData.floor;
        el.dataset.widthCm = dbData.width;
        el.dataset.heightCm = dbData.height;
        el.dataset.areaSqm = dbData.area;
        el.dataset.status = dbData.status;
        el.dataset.block = dbData.block;
        el.dataset.contractor = dbData.contractor;
        el.dataset.zoneName = dbData.zoneName;
        el.dataset.geometry_json = dbData.geometry;

        // Add orientation data for GFRC panels
        if (elementType === "GFRC" && dbData.width && dbData.height) {
          el.dataset.panelOrientation =
            parseFloat(dbData.width) > parseFloat(dbData.height) * 1.5
              ? "افقی"
              : "عمودی";
        }
        if (dbData.is_interactive) {
          // If the API says this element should be interactive for the current user,
          // then call makeElementInteractive to add the click listener.
          makeElementInteractive(el, groupId, el.id, false);
          el.style.opacity = "";
          el.style.cursor = "pointer";
          // Optional: Add a subtle visual effect for interactive elements
          el.style.transition = "opacity 0.3s ease";
        } else {
          // If the element is NOT interactive for the current user:
          // - Do NOT add a click listener.
          // - Apply visual styles to indicate it's disabled.
          el.style.opacity = "0.4"; // Make it look faded
          el.style.cursor = "not-allowed"; // Change the mouse cursor
        }
      }
    }
  });
}

function applyGroupStylesAndControls(svgElement) {
  const isPlan = currentPlanFileName.toLowerCase() === "plan.svg";
  const layerControlsContainer = document.getElementById(
    "layerControlsContainer"
  );
  layerControlsContainer.innerHTML = "";

  for (const groupId in svgGroupConfig) {
    const config = svgGroupConfig[groupId];
    const groupElement = svgElement.getElementById(groupId);
    if (groupElement) {
      groupElement.style.display = config.defaultVisible ? "" : "none";

      if (isPlan && config.color) {
        groupElement.querySelectorAll("path, rect, polygon").forEach((el) => {
          el.style.fill = config.color;
          el.style.fillOpacity = "0.7";
        });
      }

      if (config.interactive && config.elementType) {
        initializeElementsByType(groupElement, config.elementType, groupId);
      }

      // Your original layer toggle button logic is restored
      const button = document.createElement("button");
      button.textContent = config.label;
      button.className = config.defaultVisible ? "active" : "";
      button.addEventListener("click", () => {
        const isVisible = groupElement.style.display !== "none";
        groupElement.style.display = isVisible ? "none" : "";
        button.classList.toggle("active", !isVisible);
      });
      layerControlsContainer.appendChild(button);
    }
  }

  if (!isPlan) {
    // --- NEW: Add Crack Layer Toggle Button ---
    const crackLayerBtn = document.createElement("button");
    crackLayerBtn.textContent = "نمایش ترک‌ها";
    crackLayerBtn.className = "active"; // On by default
    crackLayerBtn.addEventListener("click", () => {
      const crackLayer = svgElement.getElementById("crack-layer");
      if (crackLayer) {
        const isVisible = crackLayer.style.display !== "none";
        crackLayer.style.display = isVisible ? "none" : "";
        crackLayerBtn.classList.toggle("active", !isVisible);
      }
    });
    layerControlsContainer.appendChild(crackLayerBtn);
  }
  applyElementVisibilityAndColor(svgElement, currentPlanDbData);
}

//</editor-fold>

//<editor-fold desc="SVG Loading and Navigation">
function getRegionAndZoneInfoForFile(svgFullFilename) {
  for (const regionKey in regionToZoneMap) {
    const zonesInRegion = regionToZoneMap[regionKey];
    const foundZone = zonesInRegion.find(
      (zone) => zone.svgFile.toLowerCase() === svgFullFilename.toLowerCase()
    );
    if (foundZone) {
      const regionConfig = svgGroupConfig[regionKey];
      return {
        regionKey: regionKey,
        zoneLabel: foundZone.label,
        contractor: regionConfig?.contractor,
        block: regionConfig?.block,
      };
    }
  }
  return null;
}

function initializeStatusLegend() {
  const legendItems = document.querySelectorAll(".legend-item");
  legendItems.forEach((item) => {
    item.addEventListener("click", () => {
      const status = item.dataset.status;
      // Toggle the status in our global tracker
      visibleStatuses[status] = !visibleStatuses[status];
      // Toggle the active class for visual feedback
      item.classList.toggle("active", visibleStatuses[status]);
      saveStateToSession("statusVisibility", visibleStatuses);
      // Re-apply styles to the current SVG
      if (currentSvgElement) {
        applyElementVisibilityAndColor(currentSvgElement, currentPlanDbData);
      }
    });
  });
}
// In ghom_app.js, REPLACE this function

function setupRegionZoneNavigationIfNeeded() {
  const regionSelect = document.getElementById("regionSelect");
  const zoneButtonsContainer = document.getElementById("zoneButtonsContainer");
  if (!regionSelect || !zoneButtonsContainer) return;

  if (regionSelect.dataset.initialized) return;

  regionSelect.innerHTML =
    '<option value="">-- ابتدا یک محدوده انتخاب کنید --</option>';

  // Get user role from the body tag
  const userRole = document.body.dataset.userRole;
  const isAdmin = userRole === "admin" || userRole === "superuser";

  // Use the svgGroupConfig and regionToZoneMap objects that are already in your file.
  for (const regionKey in regionToZoneMap) {
    const regionConfig = svgGroupConfig[regionKey];
    if (!regionConfig) continue; // Skip if no config exists for this region

    // --- THIS IS THE ONLY CHANGE IN LOGIC ---
    // It checks the new contractor_id property you just added.
    if (isAdmin || regionConfig.contractor_id === userRole) {
      const option = document.createElement("option");
      option.value = regionKey;
      option.textContent = regionConfig.label || regionKey;
      regionSelect.appendChild(option);
    }
  }

  // The rest of your original function logic stays the same
  regionSelect.addEventListener("change", function () {
    zoneButtonsContainer.innerHTML = "";
    const selectedRegionKey = this.value;
    if (!selectedRegionKey) return;

    const zones = regionToZoneMap[selectedRegionKey] || [];

    zones.forEach((zone) => {
      const button = document.createElement("button");
      button.textContent = zone.label;
      button.addEventListener("click", () => loadAndDisplaySVG(zone.svgFile));
      zoneButtonsContainer.appendChild(button);
    });
  });

  regionSelect.dataset.initialized = "true";
}

//<editor-fold desc="DOM Ready and Event Listeners">
document.addEventListener("DOMContentLoaded", () => {
  // --- 1. SETUP EVENT LISTENERS FIRST ---

  // Correctly set up the "Back to Plan" button listener.
  // It now performs both actions on a single click.
  document.getElementById("backToPlanBtn").addEventListener("click", () => {
    clearSessionState();
    loadAndDisplaySVG(SVG_BASE_PATH + "Plan.svg");
  });

  // Initialize the legend so it's ready for clicks.
  initializeStatusLegend();

  // Setup form handlers (your existing code for this is fine).
  const gfrcForm = document.getElementById("gfrc-form-element");
  if (gfrcForm) {
    gfrcForm.addEventListener("submit", function (event) {
      event.preventDefault();
      const form = event.target;
      const formData = new FormData(form);
      const saveBtn = form.querySelector(".btn.save");
      const itemsPayload = [];
      form.querySelectorAll(".checklist-input").forEach((input) => {
        const itemId = input.dataset.itemId;
        const statusRadio = form.querySelector(
          `input[name="status_${itemId}"]:checked`
        );
        itemsPayload.push({
          itemId: itemId,
          status: statusRadio ? statusRadio.value : "N/A",
          value: input.value,
        });
      });
      formData.append("items", JSON.stringify(itemsPayload));
      saveBtn.textContent = "در حال ذخیره...";
      saveBtn.disabled = true;
      fetch(`${SVG_BASE_PATH}api/save_inspection.php`, {
        method: "POST",
        body: formData,
      })
        .then((res) => res.json())
        .then((data) => {
          if (data.status === "success") {
            alert(data.message);
            closeForm("gfrcChecklistForm");
          } else {
            alert("خطا در ذخیره‌سازی: " + data.message);
          }
        })
        .catch((err) => alert("خطای ارتباطی: " + err))
        .finally(() => {
          saveBtn.textContent = "ذخیره";
          saveBtn.disabled = false;
        });
    });
  }

  const universalFormPopup = document.getElementById("universalChecklistForm");
  if (universalFormPopup) {
    universalFormPopup.addEventListener("submit", function (event) {
      if (event.target.id !== "checklist-form") return;
      event.preventDefault();

      const form = event.target;
      const saveBtn = universalFormPopup.querySelector(".btn.save");

      // ===================================================================
      // START: CRITICAL FIX FOR FILE UPLOADS
      // Initialize FormData FROM the form element itself. This is the only
      // way to make the browser package the file data correctly.
      // ===================================================================
      const formData = new FormData(form);
      // ===================================================================
      // END: CRITICAL FIX
      // ===================================================================

      const stagesData = {};

      // Loop over each stage's content area
      form.querySelectorAll(".stage-tab-content").forEach((stageEl) => {
        const stageId = stageEl.id.replace("stage-content-", "");
        const stagePayload = {};

        // 1. Gather checklist item data (your logic is correct)
        const stageItems = [];
        stageEl.querySelectorAll(".item-row").forEach((itemEl) => {
          const radio = itemEl.querySelector('input[type="radio"]:checked');
          const status = radio ? radio.value : "Pending";

          const textInput = itemEl.querySelector(".checklist-input");
          const drawingInput = itemEl.querySelector(".drawing-data-input");

          if (drawingInput) {
            // This is a drawing item
            stageItems.push({
              item_id: drawingInput.dataset.itemId, // CORRECT WAY TO GET ID
              status: status,
              value: drawingInput.value,
            });
          } else if (textInput) {
            // This is a standard text item
            stageItems.push({
              item_id: textInput.dataset.itemId, // CORRECT WAY TO GET ID
              status: status,
              value: textInput.value,
            });
          }
        });
        stagePayload.items = stageItems;

        // 2. Conditionally gather other fields based on role (your logic is correct)
        const userRole = USER_ROLE;
        if (userRole === "admin" || userRole === "superuser") {
          const consultantSection = stageEl.querySelector(
            ".consultant-section"
          );
          if (consultantSection.querySelector('[name="overall_status"]').value)
            stagePayload.overall_status = consultantSection.querySelector(
              '[name="overall_status"]'
            ).value;
          if (consultantSection.querySelector('[name="inspection_date"]').value)
            stagePayload.inspection_date = consultantSection.querySelector(
              '[name="inspection_date"]'
            ).value;
          if (consultantSection.querySelector('[name="notes"]').value)
            stagePayload.notes =
              consultantSection.querySelector('[name="notes"]').value;
        }
        if (
          in_array(userRole, ["cat", "crs", "coa", "crs"]) ||
          userRole === "superuser"
        ) {
          const contractorSection = stageEl.querySelector(
            ".contractor-section"
          );
          if (
            contractorSection.querySelector('[name="contractor_status"]').value
          )
            stagePayload.contractor_status = contractorSection.querySelector(
              '[name="contractor_status"]'
            ).value;
          if (contractorSection.querySelector('[name="contractor_date"]').value)
            stagePayload.contractor_date = contractorSection.querySelector(
              '[name="contractor_date"]'
            ).value;
          if (
            contractorSection.querySelector('[name="contractor_notes"]').value
          )
            stagePayload.contractor_notes = contractorSection.querySelector(
              '[name="contractor_notes"]'
            ).value;
        }

        stagesData[stageId] = stagePayload;
      });

      if (Object.keys(stagesData).length === 0) {
        alert("هیچ تغییری برای ذخیره کردن وجود ندارد.");
        return;
      }

      formData.append("stages", JSON.stringify(stagesData));

      saveBtn.textContent = "در حال ذخیره...";
      saveBtn.disabled = true;

      fetch(`${SVG_BASE_PATH}api/save_inspection.php`, {
        method: "POST",
        body: formData,
      })
        .then((res) => {
          if (!res.ok) {
            return res.text().then((text) => {
              throw new Error(text);
            });
          }
          return res.json();
        })
        .then((data) => {
          if (data.status === "success") {
            alert(data.message);
            closeForm("universalChecklistForm");
            loadAndDisplaySVG(SVG_BASE_PATH + currentPlanFileName);
          } else {
            throw new Error(
              data.message || "An unknown server error occurred."
            );
          }
        })
        .catch((err) => {
          console.error("Save Error:", err);
          // Try to parse error if it's a JSON string from PHP
          try {
            const errorObj = JSON.parse(err.message);
            alert(
              `خطا در ذخیره‌سازی: ${errorObj.message}\nجزئیات: ${errorObj.details}`
            );
          } catch (e) {
            alert("خطای ارتباطی یا ناشناخته: " + err.message);
          }
        })
        .finally(() => {
          saveBtn.textContent = "ذخیره تغییرات";
          saveBtn.disabled = false;
        });
    });
  }

  // Helper function 'in_array' needs to be available
  function in_array(needle, haystack) {
    for (let i = 0; i < haystack.length; i++) {
      if (haystack[i] == needle) return true;
    }
    return false;
  }

  // --- 2. DEFINE THE INITIAL VIEW LOADER ---
  function loadInitialView() {
    // Load Status Filters from session
    const savedStatuses = loadStateFromSession("statusVisibility");
    if (savedStatuses) {
      visibleStatuses = savedStatuses;
      // Update the legend UI to match the loaded state
      document.querySelectorAll(".legend-item").forEach((item) => {
        const status = item.dataset.status;
        const isActive = visibleStatuses[status] ?? false;
        item.classList.toggle("active", isActive);
      });
    }

    // Load the Last Viewed Plan from session
    const lastPlan = loadStateFromSession("lastViewedPlan");
    if (lastPlan && lastPlan !== "null") {
      // Safety check for the string "null"
      loadAndDisplaySVG(SVG_BASE_PATH + lastPlan);
    } else {
      // Default to the main plan if nothing is saved
      loadAndDisplaySVG(SVG_BASE_PATH + "Plan.svg");
    }
  }
  document
    .getElementById("batch-inspect-btn")
    ?.addEventListener("click", openBatchForm);
  document
    .getElementById("clear-selection-btn")
    ?.addEventListener("click", clearAllSelections);
  document
    .getElementById("submit-batch-btn")
    ?.addEventListener("click", submitBatchInspection);
  handleDeepLink();
  // --- 3. EXECUTE THE INITIAL LOAD ---
  // This is now the ONLY place where the initial view is triggered.

  const formPopupElement = document.getElementById("universalChecklistForm");
  if (formPopupElement && typeof interact !== "undefined") {
    // Only enable dragging/resizing on desktop
    if (window.innerWidth >= 992) {
      interact(formPopupElement)
        .resizable({
          edges: { left: true, right: true, bottom: true, top: true },
          listeners: {
            move(event) {
              let target = event.target;
              let x = parseFloat(target.getAttribute("data-x")) || 0;
              let y = parseFloat(target.getAttribute("data-y")) || 0;

              // Update size
              target.style.width = event.rect.width + "px";
              target.style.height = event.rect.height + "px";

              // Adjust position if resizing from top or left
              x += event.deltaRect.left;
              y += event.deltaRect.top;

              target.style.transform = "translate(" + x + "px," + y + "px)";

              target.setAttribute("data-x", x);
              target.setAttribute("data-y", y);
            },
          },
          modifiers: [
            interact.modifiers.restrictEdges({
              outer: "parent",
            }),
            interact.modifiers.restrictSize({
              min: { width: 450, height: 500 },
              max: {
                width: window.innerWidth * 0.95,
                height: window.innerHeight * 0.9,
              },
            }),
          ],
          inertia: true,
        })
        .draggable({
          allowFrom: ".form-header-new", // Only allow dragging from header
          listeners: {
            move(event) {
              let target = event.target;
              let x =
                (parseFloat(target.getAttribute("data-x")) || 0) + event.dx;
              let y =
                (parseFloat(target.getAttribute("data-y")) || 0) + event.dy;

              target.style.transform = "translate(" + x + "px, " + y + "px)";

              target.setAttribute("data-x", x);
              target.setAttribute("data-y", y);
            },
          },
          inertia: true,
          modifiers: [
            interact.modifiers.restrictRect({
              restriction: "parent",
              endOnly: true,
            }),
          ],
        });
    }
  }
});

async function loadAndRenderCrackLayer(planFile, svgElement) {
  try {
    console.log(`Loading crack layer for plan: ${planFile}`);

    const response = await fetch(
      `${SVG_BASE_PATH}api/get_cracks_for_plan.php?plan=${planFile}`
    );
    if (!response.ok) throw new Error(`API request failed: ${response.status}`);

    const cracksData = await response.json();
    console.log(
      `Loaded crack data for ${cracksData.length} elements:`,
      cracksData
    );

    if (cracksData.length === 0) {
      console.log("No crack data found for this plan");
      return;
    }

    // Remove existing crack layer if it exists
    let crackLayer = svgElement.getElementById("crack-layer");
    if (crackLayer) crackLayer.remove();

    crackLayer = document.createElementNS("http://www.w3.org/2000/svg", "g");
    crackLayer.id = "crack-layer";
    crackLayer.style.pointerEvents = "none";

    // Define width factors for different crack severities
    const WIDTH_FACTORS = {
      "#FFEB3B": 1, // Fine crack (Yellow)
      "#FF9800": 5, // Medium crack (Orange)
      "#F44336": 15, // Deep crack (Red)
      "#FF0000": 15, // Red
      "#0000FF": 8, // Blue (rectangles)
      "#00FF00": 6, // Green (circles)
      "#FF00FF": 3, // Magenta (free drawings)
    };

    // Create gradient definitions for spectrum colors
    const defs =
      svgElement.querySelector("defs") ||
      document.createElementNS("http://www.w3.org/2000/svg", "defs");
    if (!svgElement.querySelector("defs")) {
      svgElement.appendChild(defs);
    }

    // Enhanced color spectrum function
    function getSpectrumColor(severityScore, maxSeverity) {
      const normalizedSeverity = Math.min(severityScore / maxSeverity, 1);

      if (normalizedSeverity === 0) {
        return { fill: "transparent", opacity: 0 };
      } else if (normalizedSeverity <= 0.25) {
        const ratio = normalizedSeverity / 0.25;
        const r = Math.round(139 + (205 - 139) * ratio);
        const g = Math.round(195 + (220 - 195) * ratio);
        const b = Math.round(74 + (50 - 74) * ratio);
        return { fill: `rgb(${r}, ${g}, ${b})`, opacity: 0.3 + ratio * 0.2 };
      } else if (normalizedSeverity <= 0.5) {
        const ratio = (normalizedSeverity - 0.25) / 0.25;
        const r = Math.round(205 + (255 - 205) * ratio);
        const g = Math.round(220 + (235 - 220) * ratio);
        const b = Math.round(50 + (59 - 50) * ratio);
        return { fill: `rgb(${r}, ${g}, ${b})`, opacity: 0.4 + ratio * 0.15 };
      } else if (normalizedSeverity <= 0.75) {
        const ratio = (normalizedSeverity - 0.5) / 0.25;
        const r = Math.round(255);
        const g = Math.round(235 - 83 * ratio);
        const b = Math.round(59 - 59 * ratio);
        return { fill: `rgb(${r}, ${g}, ${b})`, opacity: 0.5 + ratio * 0.15 };
      } else {
        const ratio = (normalizedSeverity - 0.75) / 0.25;
        const r = Math.round(255 - 11 * ratio);
        const g = Math.round(152 - 85 * ratio);
        const b = Math.round(0 + 54 * ratio);
        return { fill: `rgb(${r}, ${g}, ${b})`, opacity: 0.6 + ratio * 0.2 };
      }
    }

    function createRadialGradient(id, centerColor, edgeColor, opacity) {
      const gradient = document.createElementNS(
        "http://www.w3.org/2000/svg",
        "radialGradient"
      );
      gradient.id = id;
      gradient.setAttribute("cx", "50%");
      gradient.setAttribute("cy", "50%");
      gradient.setAttribute("r", "70%");

      const stop1 = document.createElementNS(
        "http://www.w3.org/2000/svg",
        "stop"
      );
      stop1.setAttribute("offset", "0%");
      stop1.setAttribute("stop-color", centerColor);
      stop1.setAttribute("stop-opacity", opacity);

      const stop2 = document.createElementNS(
        "http://www.w3.org/2000/svg",
        "stop"
      );
      stop2.setAttribute("offset", "100%");
      stop2.setAttribute("stop-color", edgeColor);
      stop2.setAttribute("stop-opacity", opacity * 0.7);

      gradient.appendChild(stop1);
      gradient.appendChild(stop2);
      defs.appendChild(gradient);

      return `url(#${id})`;
    }

    function getSeverityText(severityScore) {
      if (severityScore > 1000) return "خطر بالا";
      else if (severityScore > 400) return "خطر متوسط";
      else if (severityScore > 50) return "خطر کم";
      else if (severityScore > 0) return "بسیار کم";
      else return "بدون ترک";
    }

    // Tooltip element
    const tooltip = document.createElement("div");
    tooltip.className = "crack-tooltip";
    tooltip.style.display = "none";
    tooltip.style.cssText = `
      position: absolute;
      background: rgba(0, 0, 0, 0.9);
      color: white;
      padding: 12px 16px;
      border-radius: 8px;
      font-size: 13px;
      line-height: 1.5;
      pointer-events: none;
      z-index: 1000;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
      max-width: 250px;
    `;
    document.body.appendChild(tooltip);

    // Calculate maximum severity for normalization
    let maxSeverity = 0;
    cracksData.forEach((data) => {
      if (!data.drawing_json) return;
      try {
        const drawing = JSON.parse(data.drawing_json);
        let severityScore = 0;

        // Calculate severity for lines
        if (drawing.lines) {
          drawing.lines.forEach((line) => {
            const widthFactor =
              WIDTH_FACTORS[(line.color || "#F44336").toUpperCase()] || 1;
            const coords = line.coords;
            const dx = coords[2] - coords[0];
            const dy = coords[3] - coords[1];
            const length = Math.sqrt(dx * dx + dy * dy);
            severityScore += length * widthFactor;
          });
        }

        // Calculate severity for rectangles
        if (drawing.rectangles) {
          drawing.rectangles.forEach((rect) => {
            const widthFactor =
              WIDTH_FACTORS[(rect.color || "#0000FF").toUpperCase()] || 5;
            const coords = rect.coords;
            const width = Math.abs(coords[2] - coords[0]);
            const height = Math.abs(coords[3] - coords[1]);
            const area = width * height;
            severityScore += Math.sqrt(area) * widthFactor;
          });
        }

        // Calculate severity for circles
        if (drawing.circles) {
          drawing.circles.forEach((circle) => {
            const widthFactor =
              WIDTH_FACTORS[(circle.color || "#00FF00").toUpperCase()] || 4;
            const coords = circle.coords;
            const width = Math.abs(coords[2] - coords[0]);
            const height = Math.abs(coords[3] - coords[1]);
            const radius = Math.sqrt(width * width + height * height) / 2;
            const circumference = 2 * Math.PI * radius;
            severityScore += circumference * widthFactor;
          });
        }

        // Calculate severity for free drawings
        if (drawing.freeDrawings) {
          drawing.freeDrawings.forEach((freeDraw) => {
            const widthFactor =
              WIDTH_FACTORS[(freeDraw.color || "#FF00FF").toUpperCase()] || 2;
            let totalLength = 0;

            // ENHANCED: Handle both array and object point formats
            for (let i = 1; i < freeDraw.points.length; i++) {
              const prevPoint = freeDraw.points[i - 1];
              const currPoint = freeDraw.points[i];

              // Handle both array [x,y] and object {x,y} formats
              const prevX = Array.isArray(prevPoint)
                ? prevPoint[0]
                : prevPoint.x;
              const prevY = Array.isArray(prevPoint)
                ? prevPoint[1]
                : prevPoint.y;
              const currX = Array.isArray(currPoint)
                ? currPoint[0]
                : currPoint.x;
              const currY = Array.isArray(currPoint)
                ? currPoint[1]
                : currPoint.y;

              if (
                prevX !== undefined &&
                prevY !== undefined &&
                currX !== undefined &&
                currY !== undefined
              ) {
                const dx = currX - prevX;
                const dy = currY - prevY;
                totalLength += Math.sqrt(dx * dx + dy * dy);
              }
            }
            severityScore += totalLength * widthFactor;
          });
        }

        maxSeverity = Math.max(maxSeverity, severityScore);
      } catch (e) {
        console.warn(
          "Could not parse drawing data for max severity calculation:",
          data.element_id,
          e
        );
      }
    });

    console.log(`Maximum severity calculated: ${maxSeverity}`);

    cracksData.forEach((data, index) => {
      try {
        if (!data.drawing_json) return;

        console.log(
          `Processing element ${data.element_id} (${index + 1}/${
            cracksData.length
          })`
        );
        const drawing = JSON.parse(data.drawing_json);
        console.log(`Drawing data for ${data.element_id}:`, drawing);

        const panelElement = svgElement.getElementById(data.element_id);
        if (!panelElement) {
          console.warn(`Panel element not found: ${data.element_id}`);
          return;
        }

        let severityScore = 0;
        let shapeCount = 0;
        let totalLength = 0;

        // Process and render lines
        if (drawing.lines && drawing.lines.length > 0) {
          console.log(
            `Rendering ${drawing.lines.length} lines for ${data.element_id}`
          );
          drawing.lines.forEach((lineData) => {
            const coords = lineData.coords;
            if (!coords || coords.length !== 4) return;

            const widthFactor =
              WIDTH_FACTORS[(lineData.color || "#F44336").toUpperCase()] || 1;
            const dx = coords[2] - coords[0];
            const dy = coords[3] - coords[1];
            const length = Math.sqrt(dx * dx + dy * dy);
            severityScore += length * widthFactor;
            totalLength += length;
            shapeCount++;

            const lineEl = document.createElementNS(
              "http://www.w3.org/2000/svg",
              "line"
            );
            lineEl.setAttribute("x1", coords[0]);
            lineEl.setAttribute("y1", coords[1]);
            lineEl.setAttribute("x2", coords[2]);
            lineEl.setAttribute("y2", coords[3]);
            lineEl.setAttribute("stroke", lineData.color || "#F44336");
            lineEl.setAttribute("stroke-width", "2");
            lineEl.setAttribute("stroke-linecap", "round");
            lineEl.setAttribute("opacity", "0.8");
            lineEl.style.filter = "drop-shadow(0 0 2px rgba(0,0,0,0.3))";

            crackLayer.appendChild(lineEl);
          });
        }

        // Process and render rectangles
        if (drawing.rectangles && drawing.rectangles.length > 0) {
          console.log(
            `Rendering ${drawing.rectangles.length} rectangles for ${data.element_id}`
          );
          drawing.rectangles.forEach((rectData) => {
            const coords = rectData.coords;
            if (!coords || coords.length !== 4) return;

            const widthFactor =
              WIDTH_FACTORS[(rectData.color || "#0000FF").toUpperCase()] || 5;
            const width = Math.abs(coords[2] - coords[0]);
            const height = Math.abs(coords[3] - coords[1]);
            const area = width * height;
            severityScore += Math.sqrt(area) * widthFactor;
            totalLength += (width + height) * 2;
            shapeCount++;

            const rectEl = document.createElementNS(
              "http://www.w3.org/2000/svg",
              "rect"
            );
            rectEl.setAttribute("x", Math.min(coords[0], coords[2]));
            rectEl.setAttribute("y", Math.min(coords[1], coords[3]));
            rectEl.setAttribute("width", width);
            rectEl.setAttribute("height", height);
            rectEl.setAttribute("fill", "transparent");
            rectEl.setAttribute("stroke", rectData.color || "#0000FF");
            rectEl.setAttribute("stroke-width", "2");
            rectEl.setAttribute("opacity", "0.8");
            rectEl.style.filter = "drop-shadow(0 0 2px rgba(0,0,0,0.3))";

            crackLayer.appendChild(rectEl);
          });
        }

        // Process and render circles
        if (drawing.circles && drawing.circles.length > 0) {
          console.log(
            `Rendering ${drawing.circles.length} circles for ${data.element_id}`
          );
          drawing.circles.forEach((circleData) => {
            const coords = circleData.coords;
            if (!coords || coords.length !== 4) return;

            const widthFactor =
              WIDTH_FACTORS[(circleData.color || "#00FF00").toUpperCase()] || 4;
            const centerX = (coords[0] + coords[2]) / 2;
            const centerY = (coords[1] + coords[3]) / 2;
            const width = Math.abs(coords[2] - coords[0]);
            const height = Math.abs(coords[3] - coords[1]);
            const radius = Math.sqrt(width * width + height * height) / 2;
            const circumference = 2 * Math.PI * radius;
            severityScore += circumference * widthFactor;
            totalLength += circumference;
            shapeCount++;

            const circleEl = document.createElementNS(
              "http://www.w3.org/2000/svg",
              "circle"
            );
            circleEl.setAttribute("cx", centerX);
            circleEl.setAttribute("cy", centerY);
            circleEl.setAttribute("r", radius);
            circleEl.setAttribute("fill", "transparent");
            circleEl.setAttribute("stroke", circleData.color || "#00FF00");
            circleEl.setAttribute("stroke-width", "2");
            circleEl.setAttribute("opacity", "0.8");
            circleEl.style.filter = "drop-shadow(0 0 2px rgba(0,0,0,0.3))";

            crackLayer.appendChild(circleEl);
          });
        }

        // ENHANCED: Process and render free drawings with better point handling
        if (drawing.freeDrawings && drawing.freeDrawings.length > 0) {
          console.log(
            `Rendering ${drawing.freeDrawings.length} free drawings for ${data.element_id}`
          );

          drawing.freeDrawings.forEach((freeDrawData, freeDrawIndex) => {
            console.log(
              `Processing free drawing ${freeDrawIndex + 1}:`,
              freeDrawData
            );

            if (!freeDrawData.points || freeDrawData.points.length < 2) {
              console.warn(
                `Free drawing ${freeDrawIndex + 1} has insufficient points:`,
                freeDrawData.points
              );
              return;
            }

            const widthFactor =
              WIDTH_FACTORS[(freeDrawData.color || "#FF00FF").toUpperCase()] ||
              2;

            let pathLength = 0;
            const validPoints = [];

            // Process and validate points
            freeDrawData.points.forEach((point, pointIndex) => {
              let x, y;

              // Handle both array [x,y] and object {x,y} formats
              if (Array.isArray(point)) {
                x = point[0];
                y = point[1];
              } else if (typeof point === "object" && point !== null) {
                x = point.x;
                y = point.y;
              } else {
                console.warn(
                  `Invalid point format at index ${pointIndex}:`,
                  point
                );
                return;
              }

              // Validate coordinates
              if (
                typeof x === "number" &&
                typeof y === "number" &&
                !isNaN(x) &&
                !isNaN(y)
              ) {
                validPoints.push([x, y]);

                // Calculate path length
                if (validPoints.length > 1) {
                  const prevPoint = validPoints[validPoints.length - 2];
                  const dx = x - prevPoint[0];
                  const dy = y - prevPoint[1];
                  pathLength += Math.sqrt(dx * dx + dy * dy);
                }
              } else {
                console.warn(`Invalid coordinates at point ${pointIndex}:`, {
                  x,
                  y,
                });
              }
            });

            if (validPoints.length < 2) {
              console.warn(
                `Free drawing ${
                  freeDrawIndex + 1
                } has insufficient valid points:`,
                validPoints.length
              );
              return;
            }

            console.log(
              `Free drawing ${freeDrawIndex + 1}: ${
                validPoints.length
              } valid points, length: ${pathLength.toFixed(2)}`
            );

            severityScore += pathLength * widthFactor;
            totalLength += pathLength;
            shapeCount++;

            // Create SVG path
            const pathString =
              "M " + validPoints.map((p) => p.join(",")).join(" L ");

            const pathEl = document.createElementNS(
              "http://www.w3.org/2000/svg",
              "path"
            );
            pathEl.setAttribute("d", pathString);
            pathEl.setAttribute("fill", "transparent");
            pathEl.setAttribute("stroke", freeDrawData.color || "#FF00FF");
            pathEl.setAttribute("stroke-width", "2");
            pathEl.setAttribute("stroke-linecap", "round");
            pathEl.setAttribute("stroke-linejoin", "round");
            pathEl.setAttribute("opacity", "0.8");
            pathEl.style.filter = "drop-shadow(0 0 2px rgba(0,0,0,0.3))";

            crackLayer.appendChild(pathEl);
            console.log(
              `Successfully rendered free drawing ${freeDrawIndex + 1} with ${
                validPoints.length
              } points`
            );
          });
        }

        console.log(
          `Element ${
            data.element_id
          } summary: ${shapeCount} shapes, severity: ${severityScore.toFixed(
            2
          )}`
        );

        // Apply color coding to panel if there are any shapes
        if (shapeCount > 0) {
          const colorData = getSpectrumColor(severityScore, maxSeverity);
          const severityText = getSeverityText(severityScore);

          if (severityScore > 0) {
            const gradientId = `crack-gradient-${index}`;
            const edgeColor = colorData.fill
              .replace("rgb", "rgba")
              .replace(")", `, ${colorData.opacity * 0.5})`);
            const centerColor = colorData.fill
              .replace("rgb", "rgba")
              .replace(")", `, ${colorData.opacity})`);
            const gradientFill = createRadialGradient(
              gradientId,
              centerColor,
              edgeColor,
              1
            );
            panelElement.style.fill = gradientFill;
          } else {
            panelElement.style.fill = colorData.fill;
            panelElement.style.fillOpacity = colorData.opacity;
          }

          panelElement.style.transition = "all 0.3s ease";

          // Enhanced tooltip with shape information
          panelElement.addEventListener("mouseover", (e) => {
            tooltip.style.display = "block";

            let shapeInfo = "";
            if (drawing.lines && drawing.lines.length > 0) {
              shapeInfo += `<br>خطوط: ${drawing.lines.length}`;
            }
            if (drawing.rectangles && drawing.rectangles.length > 0) {
              shapeInfo += `<br>مستطیل‌ها: ${drawing.rectangles.length}`;
            }
            if (drawing.circles && drawing.circles.length > 0) {
              shapeInfo += `<br>دایره‌ها: ${drawing.circles.length}`;
            }
            if (drawing.freeDrawings && drawing.freeDrawings.length > 0) {
              shapeInfo += `<br>ترسیم‌های آزاد: ${drawing.freeDrawings.length}`;
            }

            tooltip.innerHTML = `
              <strong>وضعیت ترک‌ها:</strong>
              ${shapeInfo}
              <br><br>
              کل اشکال: ${shapeCount}<br>
              طول کل: ${totalLength.toFixed(1)} mm<br>
              امتیاز شدت: ${severityScore.toFixed(1)}<br>
              سطح خطر: <strong>${severityText}</strong>
            `;

            tooltip.style.left = `${e.clientX + 15}px`;
            tooltip.style.top = `${e.clientY}px`;

            panelElement.style.filter = "brightness(1.3) saturate(1.2)";
            panelElement.style.transform = "scale(1.02)";
          });

          panelElement.addEventListener("mousemove", (e) => {
            tooltip.style.left = `${e.clientX + 15}px`;
            tooltip.style.top = `${e.clientY}px`;
          });

          panelElement.addEventListener("mouseout", () => {
            tooltip.style.display = "none";
            panelElement.style.filter = "none";
            panelElement.style.transform = "scale(1)";
          });
        }
      } catch (e) {
        console.warn(
          "Could not parse drawing data for element:",
          data.element_id,
          e
        );
      }
    });

    svgElement.appendChild(crackLayer);
    console.log("Crack layer rendering completed successfully");
  } catch (error) {
    console.error("Failed to load or render enhanced crack layer:", error);
  }
}
function loadAndDisplaySVG(svgFullFilename) {
  const svgContainer = document.getElementById("svgContainer");
  if (!svgContainer) return;

  closeAllForms();
  svgContainer.innerHTML = "";
  svgContainer.classList.add("loading");

  const baseFilename = svgFullFilename.substring(
    svgFullFilename.lastIndexOf("/") + 1
  );
  currentPlanFileName = baseFilename;
  saveStateToSession("lastViewedPlan", baseFilename);
  const isPlan = baseFilename.toLowerCase() === "plan.svg";

  document.getElementById("regionZoneNavContainer").style.display = isPlan
    ? "flex"
    : "none";
  document.getElementById("backToPlanBtn").style.display = isPlan
    ? "none"
    : "block";

  if (isPlan) {
    setupRegionZoneNavigationIfNeeded();
    updateCurrentZoneInfo(null);
  } else {
    const info = getRegionAndZoneInfoForFile(svgFullFilename);
    if (info) {
      updateCurrentZoneInfo(info.zoneLabel, info.contractor, info.block);
    }
  }

  return Promise.all([
    fetch(SVG_BASE_PATH + baseFilename).then((res) => {
      if (res.status === 404) {
        throw new Error("نقشه این زون هنوز بارگذاری نشده است.");
      }
      if (!res.ok) {
        throw new Error(`خطای سرور: ${res.statusText}`);
      }
      return res.text();
    }),
    isPlan
      ? Promise.resolve({})
      : fetch(`/ghom/api/get_plan_elements.php?plan=${baseFilename}`).then(
          (res) => {
            if (!res.ok) return {};
            return res.json();
          }
        ),
  ])
    .then(([svgData, rawDbData]) => {
      svgContainer.classList.remove("loading");

      // Process data for compatibility
      currentPlanDbData = processApiDataForCompatibility(rawDbData);

      console.log("Loaded and processed DB Data:", currentPlanDbData);

      svgContainer.innerHTML = svgData;
      const zoomControlsHtml = `<div class="zoom-controls"><button id="zoomInBtn">+</button><button id="zoomOutBtn">-</button><button id="zoomResetBtn">⌂</button></div>`;
      svgContainer.insertAdjacentHTML("afterbegin", zoomControlsHtml);

      currentSvgElement = svgContainer.querySelector("svg");
      if (!currentSvgElement) throw new Error("SVG element not found in data.");

      applyGroupStylesAndControls(currentSvgElement);
      setupZoomControls();

      if (!isPlan) {
        console.log("Applying enhanced element visibility and colors...");
        applyElementVisibilityAndColor(currentSvgElement, currentPlanDbData);
        loadAndRenderCrackLayer(baseFilename, currentSvgElement);
      }
    })
    .catch((error) => {
      svgContainer.classList.remove("loading");
      console.error("Error during plan loading:", error);
      svgContainer.innerHTML = `<p style="color:red; font-weight:bold;">خطا در بارگذاری نقشه: ${error.message}</p>`;
    });
}
function loadInitialView() {
  // Load Status Filters from session
  const savedStatuses = loadStateFromSession("statusVisibility");
  if (savedStatuses) {
    visibleStatuses = savedStatuses;
    // Update the legend UI to match the loaded state
    document.querySelectorAll(".legend-item").forEach((item) => {
      const status = item.dataset.status;
      const isActive = visibleStatuses[status] ?? false;
      item.classList.toggle("active", isActive);
    });
  }

  // Load the Last Viewed Plan from session
  const lastPlan = loadStateFromSession("lastViewedPlan");
  if (lastPlan && lastPlan !== "null") {
    // Safety check for the string "null"
    loadAndDisplaySVG(SVG_BASE_PATH + lastPlan);
  } else {
    // Default to the main plan if nothing is saved
    loadAndDisplaySVG(SVG_BASE_PATH + "Plan.svg");
  }
}
async function handleDeepLink() {
  console.log("DEBUG: handleDeepLink function started.");
  const urlParams = new URLSearchParams(window.location.search);
  const planFile = urlParams.get("plan");
  const rawElementIdFromUrl = urlParams.get("element_id");

  if (planFile && rawElementIdFromUrl) {
    // --- START OF FIX: Clean the input string ---
    const elementIdFromUrl = rawElementIdFromUrl.replace(/\s/g, ""); // Remove ALL whitespace
    // --- END OF FIX ---

    console.log(
      `DEBUG: Deep Link Detected. Plan: '${planFile}', Cleaned Element ID: '${elementIdFromUrl}'.`
    );

    await loadAndDisplaySVG(SVG_BASE_PATH + planFile);
    console.log("DEBUG: SVG loading complete.");

    setTimeout(() => {
      console.log("DEBUG: Starting element search after delay...");
      let baseElementId = elementIdFromUrl;
      let partName = null;
      const parts = elementIdFromUrl.split("-");
      const lastPart = parts[parts.length - 1];

      if (
        ["face", "up", "down", "left", "right", "default"].includes(lastPart)
      ) {
        partName = lastPart;
        baseElementId = parts.slice(0, -1).join("-");
      }
      console.log(
        `DEBUG: Parsed IDs -> baseElementId: '${baseElementId}', partName: '${
          partName || "null"
        }'`
      );

      const elementInSvg = document.getElementById(baseElementId);

      if (elementInSvg) {
        console.log(
          `DEBUG: SUCCESS! Found element in SVG with ID: '${baseElementId}'.`
        );
        elementInSvg.classList.add("deep-link-highlight");
        elementInSvg.scrollIntoView({
          behavior: "smooth",
          block: "center",
          inline: "center",
        });
        const dynamicContext = {
          elementType: elementInSvg.dataset.elementType,
          planFile: planFile,
          block: elementInSvg.dataset.block,
          zoneName: elementInSvg.dataset.zoneName,
          floorLevel: elementInSvg.dataset.floorLevel,
          axisSpan: elementInSvg.dataset.axisSpan,
          widthCm: elementInSvg.dataset.widthCm,
          heightCm: elementInSvg.dataset.heightCm,
          areaSqm: elementInSvg.dataset.areaSqm,
          geometry_json: elementInSvg.dataset.geometry_json,
          panelOrientation: elementInSvg.dataset.panelOrientation,
        };

        if (elementInSvg.dataset.elementType === "GFRC" && partName) {
          console.log(
            "DEBUG: Element is GFRC. Calling showGfrcSubPanelMenu to open form directly."
          );
          showGfrcSubPanelMenu(elementInSvg, dynamicContext, partName);
        } else {
          console.log(
            "DEBUG: Element is NOT GFRC or has no part name. Calling openChecklistForm directly."
          );
          openChecklistForm(
            baseElementId,
            elementInSvg.dataset.elementType,
            dynamicContext
          );
        }
      } else {
        console.error(
          `DEBUG: FAILED to find element in SVG with ID: '${baseElementId}'. The alert will now show.`
        );
        alert(
          `خطا: المان با شناسه پایه '${baseElementId}' در نقشه '${planFile}' یافت نشد.`
        );
      }
    }, 500);
  } else {
    console.log(
      "DEBUG: No deep link parameters found. Calling loadInitialView."
    );
    loadInitialView();
  }
}

//</editor-fold>
function applyDataAndStyles(svgElement, dbData) {
  // Loop through every known element from the database
  for (const elementId in dbData) {
    const data = dbData[elementId];
    const el = svgElement.getElementById(elementId);

    if (el) {
      // Apply the status color
      el.style.fill = STATUS_COLORS[data.status] || STATUS_COLORS["Pending"];

      // Attach all database info to the element's dataset
      el.dataset.elementType = data.type;
      el.dataset.floorLevel = data.floor;
      el.dataset.axisSpan = data.axis;
      el.dataset.widthCm = data.width;
      el.dataset.heightCm = data.height;
      el.dataset.areaSqm = data.area;
      el.dataset.status = data.status;
      // Mark as interactive so the click listener can find it
      el.classList.add("interactive-element");
    }
  }
}
/**
 * ENHANCED VERSION: Apply multi-stage spectrum coloring based on completion percentage
 * This replaces the existing applyElementVisibilityAndColor function
 */
function applyElementVisibilityAndColor(svgElement, dbData) {
  console.log(
    "Applying enhanced multi-stage element visibility and colors...",
    dbData
  );

  for (const elementId in dbData) {
    const el = svgElement.getElementById(elementId);
    if (!el) continue;

    const data = dbData[elementId];
    const status = data.status;
    const elementType = data.type;

    // 1. SET VISIBILITY based on the legend filter
    if (visibleStatuses[status]) {
      el.style.display = ""; // Show element
    } else {
      el.style.display = "none"; // Hide element
      continue; // No need to color a hidden element
    }

    // 2. SET COLOR - Enhanced for multi-stage visualization
    let finalColor;
    let strokeColor = "none";
    let strokeWidth = "0";
    let strokeDasharray = "none";

    // Check if element has multi-stage data
    if (
      data.stages_data &&
      Array.isArray(data.stages_data) &&
      data.stages_data.length > 0
    ) {
      // Multi-stage element - use spectrum color based on completion
      const completionPercentage = data.completion_percentage || 0;
      const totalStages = data.total_stages || 1;
      const completedStages = data.completed_stages || 0;

      console.log(
        `Element ${elementId}: ${completedStages}/${totalStages} stages completed (${completionPercentage}%)`
      );

      // Generate spectrum color
      finalColor = getMultiStageSpectrumColor(completionPercentage, status);

      // Add progress indicators
      if (completionPercentage > 0 && completionPercentage < 100) {
        strokeColor = getProgressBorderColor(completionPercentage);
        strokeWidth = "3";
        strokeDasharray = totalStages > 1 ? "5,3" : "none";
      } else if (completionPercentage === 100) {
        strokeColor = "#28a745"; // Green for complete
        strokeWidth = "2";
        strokeDasharray = "none";
      }

      // Add visual emphasis for critical statuses
      if (status === "Reject") {
        strokeColor = "#dc3545";
        strokeWidth = "4";
        strokeDasharray = "none";
      } else if (status === "Awaiting Re-inspection") {
        strokeColor = "#0dcaf0";
        strokeWidth = "3";
        strokeDasharray = "8,4";
      }
    } else {
      // Single stage element - use original logic
      if (status === "Pending") {
        if (elementType === "GFRC") {
          const orientation = el.dataset.panelOrientation;
          const gfrcConfig = svgGroupConfig["GFRC"];
          if (gfrcConfig && gfrcConfig.colors) {
            if (orientation === "افقی" && gfrcConfig.colors.h) {
              finalColor = gfrcConfig.colors.h;
            } else if (orientation === "عمودی" && gfrcConfig.colors.v) {
              finalColor = gfrcConfig.colors.v;
            } else {
              finalColor = STATUS_COLORS["Pending"];
            }
          } else {
            finalColor = STATUS_COLORS["Pending"];
          }
        } else {
          const group = el.closest("g");
          if (
            group &&
            group.id &&
            svgGroupConfig[group.id] &&
            svgGroupConfig[group.id].color
          ) {
            finalColor = svgGroupConfig[group.id].color;
          } else {
            finalColor = STATUS_COLORS["Pending"];
          }
        }
      } else {
        finalColor = STATUS_COLORS[status] || STATUS_COLORS["Pending"];
      }
    }

    // Apply styles
    el.style.fill = finalColor;
    el.style.stroke = strokeColor;
    el.style.strokeWidth = strokeWidth;
    el.style.strokeDasharray = strokeDasharray;
    el.style.strokeOpacity = strokeColor !== "none" ? "0.8" : "0";

    // 3. Update element dataset
    el.dataset.status = status;
    if (data.completion_percentage !== undefined) {
      el.dataset.completionPercentage = data.completion_percentage;
      el.dataset.totalStages = data.total_stages || 1;
      el.dataset.completedStages = data.completed_stages || 0;
    }

    // 4. Add enhanced tooltip
    addEnhancedMultiStageTooltip(el, data);
  }

  console.log("Enhanced multi-stage element coloring completed");
}

/**
 * Enhanced spectrum color function for multi-stage elements
 */
function getMultiStageSpectrumColor(completionPercentage, status) {
  // Critical statuses override the spectrum color
  if (status === "Reject") return STATUS_COLORS["Reject"];
  if (status === "Awaiting Re-inspection")
    return STATUS_COLORS["Awaiting Re-inspection"];
  if (status === "Repair") return STATUS_COLORS["Repair"];
  // START OF FIX
  if (status === "Pre-Inspection Complete")
    return STATUS_COLORS["Pre-Inspection Complete"];
  // END OF FIX
  if (status === "OK") return STATUS_COLORS["OK"];

  const p = Math.max(0, Math.min(100, completionPercentage));
  let r, g, b;

  if (p === 0) return "rgba(108, 117, 125, 0.6)"; // Grey for 0%
  if (p <= 50) {
    // Red to Yellow
    const ratio = p / 50;
    r = 255;
    g = Math.round(59 + 196 * ratio); // from 59 (red) to 255 (yellow)
    b = 48;
  } else {
    // Yellow to Green
    const ratio = (p - 50) / 50;
    r = Math.round(255 - 215 * ratio); // from 255 (yellow) to 40 (green)
    g = 255 - Math.round(88 * ratio); // from 255 to 167
    b = 48 + Math.round(21 * ratio); // from 48 to 69
  }
  const alpha = 0.5 + (p / 100) * 0.3; // Opacity from 0.5 to 0.8
  return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

/**
 * Enhanced tooltip for multi-stage elements
 */
function addEnhancedMultiStageTooltip(element, data) {
  // Remove existing tooltip listeners
  if (element._tooltipHandler) {
    element.removeEventListener("mouseover", element._tooltipHandler);
    element.removeEventListener("mouseout", element._tooltipRemoveHandler);
  }

  const tooltipHandler = (e) => {
    // Remove any existing tooltip
    const existingTooltip = document.querySelector(
      ".enhanced-multi-stage-tooltip"
    );
    if (existingTooltip) {
      existingTooltip.remove();
    }

    const tooltip = document.createElement("div");
    tooltip.className = "enhanced-multi-stage-tooltip";
    tooltip.style.cssText = `
      position: absolute;
      background: linear-gradient(135deg, rgba(0, 0, 0, 0.95), rgba(40, 40, 40, 0.95));
      color: white;
      padding: 14px 18px;
      border-radius: 10px;
      font-size: 13px;
      line-height: 1.5;
      pointer-events: none;
      z-index: 1000;
      font-family: 'Vazir', Tahoma, sans-serif;
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
      border: 2px solid rgba(255, 255, 255, 0.3);
      max-width: 320px;
      white-space: normal;
      direction: rtl;
      text-align: right;
      backdrop-filter: blur(5px);
    `;

    const status = data.status || "Pending";
    const completionPercentage = data.completion_percentage || 0;
    const totalStages = data.total_stages || 1;
    const completedStages = data.completed_stages || 0;

    // Status translation
    const statusTranslations = {
      OK: "تایید شده",
      Reject: "رد شده",
      Repair: "نیاز به تعمیر",
      "Awaiting Re-inspection": "در انتظار بازرسی مجدد",
      "Pre-Inspection Complete": "آماده بازرسی",
      Pending: "در حال اجرا",
      "In Progress": "در حال انجام",
    };

    let stagesInfo = "";
    let progressBar = "";

    if (totalStages > 1) {
      // Create visual progress bar
      progressBar = `
        <div style="margin-top: 8px;">
          <div style="display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 11px; font-weight: bold;">پیشرفت:</span>
            <div style="flex: 1; background: rgba(255,255,255,0.2); height: 8px; border-radius: 4px; overflow: hidden;">
              <div style="background: ${getProgressBorderColor(
                completionPercentage
              )}; height: 100%; width: ${completionPercentage}%; transition: width 0.3s ease; border-radius: 4px;"></div>
            </div>
            <span style="font-size: 11px; font-weight: bold;">${completionPercentage.toFixed(
              1
            )}%</span>
          </div>
        </div>
      `;

      stagesInfo = `
        <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid rgba(255,255,255,0.3);">
          <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
            <span><strong>مراحل کامل:</strong> ${completedStages}</span>
            <span><strong>کل مراحل:</strong> ${totalStages}</span>
          </div>
          ${progressBar}
          <div style="margin-top: 8px; font-size: 11px; color: #e9ecef;">
            ${getStageStatusSummary(data.stages_data || [])}
          </div>
        </div>
      `;
    }

    tooltip.innerHTML = `
      <div style="font-weight: bold; font-size: 14px; margin-bottom: 8px; border-bottom: 1px solid rgba(255,255,255,0.3); padding-bottom: 6px;">
        ${element.id}
      </div>
      <div style="margin-bottom: 4px;"><strong>وضعیت:</strong> ${
        statusTranslations[status] || status
      }</div>
      <div style="margin-bottom: 4px;"><strong>نوع:</strong> ${
        data.type || "نامشخص"
      }</div>
      ${
        element.dataset.axisSpan
          ? `<div style="margin-bottom: 4px;"><strong>محور:</strong> ${element.dataset.axisSpan}</div>`
          : ""
      }
      ${
        element.dataset.floorLevel
          ? `<div style="margin-bottom: 4px;"><strong>طبقه:</strong> ${element.dataset.floorLevel}</div>`
          : ""
      }
      ${
        data.contractor
          ? `<div style="margin-bottom: 4px;"><strong>پیمانکار:</strong> ${data.contractor}</div>`
          : ""
      }
      ${stagesInfo}
    `;

    document.body.appendChild(tooltip);

    // Position tooltip
    const rect = tooltip.getBoundingClientRect();
    let left = e.clientX + 15;
    let top = e.clientY - 10;

    // Keep tooltip on screen
    if (left + rect.width > window.innerWidth) {
      left = e.clientX - rect.width - 15;
    }
    if (top + rect.height > window.innerHeight) {
      top = e.clientY - rect.height + 10;
    }
    if (top < 0) {
      top = 10;
    }

    tooltip.style.left = `${left}px`;
    tooltip.style.top = `${top}px`;

    element._currentTooltip = tooltip;

    // Auto-remove after delay
    element._tooltipTimeout = setTimeout(() => {
      if (tooltip && tooltip.parentNode) {
        tooltip.remove();
      }
      element._currentTooltip = null;
    }, 6000);
  };

  const removeHandler = () => {
    if (element._currentTooltip) {
      element._currentTooltip.remove();
      element._currentTooltip = null;
    }
    if (element._tooltipTimeout) {
      clearTimeout(element._tooltipTimeout);
      element._tooltipTimeout = null;
    }
  };

  element._tooltipHandler = tooltipHandler;
  element._tooltipRemoveHandler = removeHandler;

  element.addEventListener("mouseover", tooltipHandler);
  element.addEventListener("mouseout", removeHandler);
}

/**
 * Generate a summary of stage statuses
 */
function getStageStatusSummary(stagesData) {
  if (!Array.isArray(stagesData) || stagesData.length === 0) {
    return "هیچ مرحله‌ای ثبت نشده";
  }

  const counts = {
    OK: 0,
    Reject: 0,
    Repair: 0,
    "Awaiting Re-inspection": 0,
    "Pre-Inspection Complete": 0,
    Pending: 0,
  };

  stagesData.forEach((stage) => {
    const status = stage.status || "Pending";
    if (counts.hasOwnProperty(status)) {
      counts[status]++;
    } else {
      counts.Pending++;
    }
  });

  const summary = [];
  if (counts.OK > 0) summary.push(`✓ ${counts.OK} تایید`);
  if (counts.Reject > 0) summary.push(`✗ ${counts.Reject} رد`);
  if (counts.Repair > 0) summary.push(`⚠ ${counts.Repair} تعمیر`);
  if (counts["Awaiting Re-inspection"] > 0)
    summary.push(`⏳ ${counts["Awaiting Re-inspection"]} انتظار`);
  if (counts["Pre-Inspection Complete"] > 0)
    summary.push(`📋 ${counts["Pre-Inspection Complete"]} آماده`);
  if (counts.Pending > 0) summary.push(`⏸ ${counts.Pending} معلق`);

  return summary.join(" | ") || "بدون وضعیت";
}
function addEnhancedTooltip(element, data) {
  // Remove existing tooltip listeners
  if (element._tooltipHandler) {
    element.removeEventListener("mouseover", element._tooltipHandler);
    element.removeEventListener("mouseout", element._tooltipRemoveHandler);
  }

  const tooltipHandler = (e) => {
    // Remove any existing tooltip
    const existingTooltip = document.querySelector(".enhanced-tooltip");
    if (existingTooltip) {
      existingTooltip.remove();
    }

    const tooltip = document.createElement("div");
    tooltip.className = "enhanced-tooltip";
    tooltip.style.cssText = `
            position: absolute;
            background: linear-gradient(135deg, rgba(0, 0, 0, 0.95), rgba(40, 40, 40, 0.95));
            color: white;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 13px;
            line-height: 1.4;
            pointer-events: none;
            z-index: 1000;
            white-space: nowrap;
            font-family: 'Vazir', Tahoma, sans-serif;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.2);
            max-width: 300px;
            white-space: normal;
            direction: rtl;
            text-align: right;
        `;

    const status = data.status || "Pending";
    const completionPercentage = data.completion_percentage || 0;
    const totalStages = data.total_stages || 1;
    const completedStages = data.completed_stages || 0;

    // Status translation
    const statusTranslations = {
      OK: "تایید شده",
      Reject: "رد شده",
      Repair: "نیاز به تعمیر",
      "Awaiting Re-inspection": "در انتظار بازرسی مجدد",
      "Pre-Inspection Complete": "آماده بازرسی",
      Pending: "در حال اجرا",
      "In Progress": "در حال انجام",
    };

    let stagesInfo = "";
    if (totalStages > 1) {
      stagesInfo = `
                <div style="margin-top: 8px; padding-top: 8px; border-top: 1px solid rgba(255,255,255,0.2);">
                    <div><strong>مراحل:</strong> ${completedStages} از ${totalStages} تکمیل شده</div>
                    <div><strong>درصد پیشرفت:</strong> ${completionPercentage.toFixed(
                      1
                    )}%</div>
                    <div style="margin-top: 4px;">
                        <div style="background: rgba(255,255,255,0.2); height: 6px; border-radius: 3px; overflow: hidden;">
                            <div style="background: ${getProgressBorderColor(
                              completionPercentage
                            )}; height: 100%; width: ${completionPercentage}%; transition: width 0.3s ease;"></div>
                        </div>
                    </div>
                </div>
            `;
    }

    tooltip.innerHTML = `
            <div><strong>${element.id}</strong></div>
            <div><strong>وضعیت:</strong> ${
              statusTranslations[status] || status
            }</div>
            <div><strong>نوع:</strong> ${data.type || "نامشخص"}</div>
            ${
              element.dataset.axisSpan
                ? `<div><strong>محور:</strong> ${element.dataset.axisSpan}</div>`
                : ""
            }
            ${
              element.dataset.floorLevel
                ? `<div><strong>طبقه:</strong> ${element.dataset.floorLevel}</div>`
                : ""
            }
            ${
              data.contractor
                ? `<div><strong>پیمانکار:</strong> ${data.contractor}</div>`
                : ""
            }
            ${stagesInfo}
        `;

    document.body.appendChild(tooltip);

    // Position tooltip
    const rect = tooltip.getBoundingClientRect();
    let left = e.clientX + 15;
    let top = e.clientY - 10;

    // Keep tooltip on screen
    if (left + rect.width > window.innerWidth) {
      left = e.clientX - rect.width - 15;
    }
    if (top + rect.height > window.innerHeight) {
      top = e.clientY - rect.height + 10;
    }

    tooltip.style.left = `${left}px`;
    tooltip.style.top = `${top}px`;

    element._currentTooltip = tooltip;

    // Auto-remove after delay
    element._tooltipTimeout = setTimeout(() => {
      if (tooltip && tooltip.parentNode) {
        tooltip.remove();
      }
      element._currentTooltip = null;
    }, 5000);
  };

  const removeHandler = () => {
    if (element._currentTooltip) {
      element._currentTooltip.remove();
      element._currentTooltip = null;
    }
    if (element._tooltipTimeout) {
      clearTimeout(element._tooltipTimeout);
      element._tooltipTimeout = null;
    }
  };

  element._tooltipHandler = tooltipHandler;
  element._tooltipRemoveHandler = removeHandler;

  element.addEventListener("mouseover", tooltipHandler);
  element.addEventListener("mouseout", removeHandler);
}
function processApiDataForCompatibility(apiResponse) {
  // Your existing API already returns the correct format
  // Just ensure we handle any multi-stage data if it exists
  if (apiResponse && typeof apiResponse === "object") {
    for (const elementId in apiResponse) {
      const elementData = apiResponse[elementId];

      // Ensure backward compatibility
      if (!elementData.status) {
        elementData.status = "Pending";
      }

      // If we have stages data but no completion percentage, calculate it
      if (elementData.stages_data && !elementData.completion_percentage) {
        const stages = elementData.stages_data;
        const total = stages.length;
        const completed = stages.filter((s) => s.status === "OK").length;
        elementData.completion_percentage =
          total > 0 ? (completed / total) * 100 : 0;
        elementData.total_stages = total;
        elementData.completed_stages = completed;
      }
    }
  }

  return apiResponse;
}
function getMultiStageSpectrumColor(completionPercentage, status) {
  // Handle critical statuses first
  if (status === "Reject") {
    return STATUS_COLORS["Reject"];
  }

  if (status === "OK") return STATUS_COLORS["OK"];

  if (status === "Awaiting Re-inspection") {
    return STATUS_COLORS["Awaiting Re-inspection"];
  }

  // Create spectrum color based on completion percentage
  const percentage = Math.max(0, Math.min(100, completionPercentage || 0));

  if (percentage === 0) {
    return "rgba(108, 117, 125, 0.6)"; // Gray for 0%
  }

  let r, g, b, alpha;

  if (percentage <= 25) {
    // Red to orange spectrum (0-25%)
    const ratio = percentage / 25;
    r = 220;
    g = Math.round(53 + 100 * ratio);
    b = 69;
    alpha = 0.5 + ratio * 0.2;
  } else if (percentage <= 50) {
    // Orange to yellow spectrum (25-50%)
    const ratio = (percentage - 25) / 25;
    r = Math.round(220 + 35 * ratio);
    g = Math.round(153 + 102 * ratio);
    b = Math.round(69 - 69 * ratio);
    alpha = 0.6 + ratio * 0.1;
  } else if (percentage <= 75) {
    // Yellow to light green spectrum (50-75%)
    const ratio = (percentage - 50) / 25;
    r = Math.round(255 - 127 * ratio);
    g = 255;
    b = 0;
    alpha = 0.7 + ratio * 0.1;
  } else {
    // Light green to dark green spectrum (75-100%)
    const ratio = (percentage - 75) / 25;
    r = Math.round(128 - 88 * ratio);
    g = Math.round(255 - 88 * ratio);
    b = Math.round(0 + 69 * ratio);
    alpha = 0.8 + ratio * 0.1;
  }

  return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

/**
 * FIXED: Progress border color function
 */
function getProgressBorderColor(percentage) {
  if (percentage <= 25) return "#dc3545"; // Red
  if (percentage <= 50) return "#fd7e14"; // Orange
  if (percentage <= 75) return "#ffc107"; // Yellow
  return "#28a745"; // Green
}

function getProgressBorderColor(percentage) {
  if (percentage <= 25) return "#dc3545"; // Red
  if (percentage <= 50) return "#fd7e14"; // Orange
  if (percentage <= 75) return "#ffc107"; // Yellow
  return "#28a745"; // Green
}

/**
 * Adds enhanced tooltip for multi-stage elements
 */
function addMultiStageTooltip(element, data) {
  // Remove existing tooltip listeners
  if (element._tooltipHandler) {
    element.removeEventListener("mouseover", element._tooltipHandler);
    element.removeEventListener("mouseout", element._tooltipRemoveHandler);
  }

  const tooltipHandler = (e) => {
    // Remove any existing tooltip
    const existingTooltip = document.querySelector(".multi-stage-tooltip");
    if (existingTooltip) {
      existingTooltip.remove();
    }

    const tooltip = document.createElement("div");
    tooltip.className = "multi-stage-tooltip";
    tooltip.style.cssText = `
            position: absolute;
            background: linear-gradient(135deg, rgba(0, 0, 0, 0.95), rgba(40, 40, 40, 0.95));
            color: white;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 13px;
            line-height: 1.4;
            pointer-events: none;
            z-index: 1000;
            white-space: nowrap;
            font-family: 'Vazir', Tahoma, sans-serif;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.2);
            max-width: 300px;
            white-space: normal;
        `;

    const completionPercentage = data.completion_percentage || 0;
    const totalStages = data.total_stages || 0;
    const completedStages = data.completed_stages || 0;
    const status = data.status || "Pending";

    let stagesInfo = "";
    if (totalStages > 1) {
      stagesInfo = `
                <div style="margin-top: 8px; padding-top: 8px; border-top: 1px solid rgba(255,255,255,0.2);">
                    <div><strong>مراحل:</strong> ${completedStages} از ${totalStages} تکمیل شده</div>
                    <div><strong>درصد پیشرفت:</strong> ${completionPercentage.toFixed(
                      1
                    )}%</div>
                    <div style="margin-top: 4px;">
                        <div style="background: rgba(255,255,255,0.2); height: 6px; border-radius: 3px; overflow: hidden;">
                            <div style="background: ${getProgressBorderColor(
                              completionPercentage
                            )}; height: 100%; width: ${completionPercentage}%; transition: width 0.3s ease;"></div>
                        </div>
                    </div>
                </div>
            `;
    }

    // Status translation
    const statusTranslations = {
      OK: "تایید شده",
      Reject: "رد شده",
      Repair: "نیاز به تعمیر",
      "Awaiting Re-inspection": "در انتظار بازرسی مجدد",
      "Pre-Inspection Complete": "آماده بازرسی",
      Pending: "در حال اجرا",
      "In Progress": "در حال انجام",
    };

    tooltip.innerHTML = `
            <div><strong>${element.id}</strong></div>
            <div><strong>وضعیت:</strong> ${
              statusTranslations[status] || status
            }</div>
            <div><strong>نوع:</strong> ${data.type || "نامشخص"}</div>
            ${
              element.dataset.axisSpan
                ? `<div><strong>محور:</strong> ${element.dataset.axisSpan}</div>`
                : ""
            }
            ${
              element.dataset.floorLevel
                ? `<div><strong>طبقه:</strong> ${element.dataset.floorLevel}</div>`
                : ""
            }
            ${stagesInfo}
        `;

    document.body.appendChild(tooltip);

    // Position tooltip
    const rect = tooltip.getBoundingClientRect();
    let left = e.clientX + 15;
    let top = e.clientY - 10;

    // Keep tooltip on screen
    if (left + rect.width > window.innerWidth) {
      left = e.clientX - rect.width - 15;
    }
    if (top + rect.height > window.innerHeight) {
      top = e.clientY - rect.height + 10;
    }

    tooltip.style.left = `${left}px`;
    tooltip.style.top = `${top}px`;

    element._currentTooltip = tooltip;

    // Auto-remove after delay
    element._tooltipTimeout = setTimeout(() => {
      if (tooltip && tooltip.parentNode) {
        tooltip.remove();
      }
      element._currentTooltip = null;
    }, 5000);
  };

  const removeHandler = () => {
    if (element._currentTooltip) {
      element._currentTooltip.remove();
      element._currentTooltip = null;
    }
    if (element._tooltipTimeout) {
      clearTimeout(element._tooltipTimeout);
      element._tooltipTimeout = null;
    }
  };

  element._tooltipHandler = tooltipHandler;
  element._tooltipRemoveHandler = removeHandler;

  element.addEventListener("mouseover", tooltipHandler);
  element.addEventListener("mouseout", removeHandler);
}
/**
 * Updated get_plan_elements API call handler to process multi-stage data
 */
function processMultiStageApiData(apiResponse) {
  const processedData = {};

  // If API returns grouped stage data, process it
  if (apiResponse && typeof apiResponse === "object") {
    for (const elementId in apiResponse) {
      const elementData = apiResponse[elementId];

      // Handle both single-stage and multi-stage data formats
      if (Array.isArray(elementData.stages)) {
        // Multi-stage element
        elementData.stages.forEach((stageData, index) => {
          const stageElementId = `${elementId}_stage_${
            stageData.stage_id || index
          }`;
          processedData[stageElementId] = {
            ...stageData,
            baseElementId: elementId,
            type: elementData.type || elementData.elementType,
            is_interactive: elementData.is_interactive,
          };
        });

        // Also keep the base element for coloring
        processedData[elementId] = {
          ...elementData,
          status: "Multi-stage", // Special status for multi-stage elements
          stages: elementData.stages,
        };
      } else {
        // Single-stage element (backward compatibility)
        processedData[elementId] = elementData;
      }
    }
  }

  return processedData;
}
/**
 * Update the legend to show multi-stage status options
 */
function updateLegendForMultiStage() {
  const legendContainer =
    document.querySelector(".legend-container") ||
    document.querySelector(".status-legend")?.parentElement;

  if (!legendContainer) return;

  // Remove existing completion legend
  const existingCompletionLegend =
    legendContainer.querySelector(".completion-legend");
  if (existingCompletionLegend) {
    existingCompletionLegend.remove();
  }

  // Create new completion legend
  const completionLegend = document.createElement("div");
  completionLegend.className = "completion-legend";
  completionLegend.innerHTML = `
        <h4>پیشرفت تکمیل مراحل:</h4>
        <div class="completion-scale">
            <div class="completion-item" title="بدون پیشرفت">
                <span class="completion-color" style="background: rgba(108, 117, 125, 0.6);"></span>
                <span>0%</span>
            </div>
            <div class="completion-item" title="پیشرفت کم">
                <span class="completion-color" style="background: rgba(220, 53, 69, 0.7);"></span>
                <span>25%</span>
            </div>
            <div class="completion-item" title="پیشرفت متوسط">
                <span class="completion-color" style="background: rgba(255, 193, 7, 0.7);"></span>
                <span>50%</span>
            </div>
            <div class="completion-item" title="پیشرفت خوب">
                <span class="completion-color" style="background: rgba(128, 255, 0, 0.7);"></span>
                <span>75%</span>
            </div>
            <div class="completion-item" title="تکمیل شده">
                <span class="completion-color" style="background: rgba(40, 167, 69, 0.8);"></span>
                <span>100%</span>
            </div>
        </div>
    `;

  // Add CSS for completion legend if not exists
  if (!document.getElementById("completion-legend-styles")) {
    const style = document.createElement("style");
    style.id = "completion-legend-styles";
    style.textContent = `
            .completion-legend {
                margin-top: 15px;
                padding: 12px;
                border: 2px solid #dee2e6;
                border-radius: 8px;
                background: linear-gradient(135deg, #f8f9fa, #e9ecef);
                font-family: 'Vazir', Tahoma, sans-serif;
                direction: rtl;
            }
            .completion-legend h4 {
                margin: 0 0 12px 0;
                font-size: 14px;
                color: #495057;
                font-weight: bold;
                text-align: center;
            }
            .completion-scale {
                display: flex;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 8px;
            }
            .completion-item {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 4px;
                font-size: 11px;
                font-weight: bold;
                color: #495057;
                transition: transform 0.2s ease;
                cursor: help;
            }
            .completion-item:hover {
                transform: scale(1.1);
            }
            .completion-color {
                width: 20px;
                height: 20px;
                border-radius: 50%;
                border: 2px solid #333;
                box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            }
            
            /* Enhanced tooltip styles */
            .multi-stage-tooltip {
                font-family: 'Vazir', Tahoma, sans-serif !important;
                direction: rtl !important;
                text-align: right !important;
            }
        `;
    document.head.appendChild(style);
  }

  // Insert after existing legend
  const existingLegend = legendContainer.querySelector(
    ".legend, .status-legend"
  );
  if (existingLegend) {
    existingLegend.parentNode.insertBefore(
      completionLegend,
      existingLegend.nextSibling
    );
  } else {
    legendContainer.appendChild(completionLegend);
  }

  console.log("Multi-stage completion legend added");
}

//<editor-fold desc="Utility and Math Functions">
function getRectangleDimensions(dAttribute) {
  if (!dAttribute) return null;
  const commands = dAttribute
    .trim()
    .toUpperCase()
    .split(/(?=[LMCZHV])/);
  let points = [],
    currentX = 0,
    currentY = 0;
  commands.forEach((commandStr) => {
    const type = commandStr.charAt(0);
    const args =
      commandStr
        .substring(1)
        .trim()
        .split(/[\s,]+/)
        .map(Number) || [];
    let i = 0;
    switch (type) {
      case "M":
      case "L":
        while (i < args.length) {
          currentX = args[i++];
          currentY = args[i++];
          points.push({
            x: currentX,
            y: currentY,
          });
        }
        break;
      case "H":
        while (i < args.length) {
          currentX = args[i++];
          points.push({
            x: currentX,
            y: currentY,
          });
        }
        break;
      case "V":
        while (i < args.length) {
          currentY = args[i++];
          points.push({
            x: currentX,
            y: currentY,
          });
        }
        break;
    }
  });
  if (points.length < 3) return null;
  const xValues = points.map((p) => p.x),
    yValues = points.map((p) => p.y);
  const minX = Math.min(...xValues),
    maxX = Math.max(...xValues);
  const minY = Math.min(...yValues),
    maxY = Math.max(...yValues);
  return {
    x: minX,
    y: minY,
    width: maxX - minX,
    height: maxY - minY,
  };
}
function initializeMultiStageLegend() {
  // Wait a bit for the DOM to be ready
  setTimeout(() => {
    updateLegendForMultiStage();
  }, 500);
}
function extractAllAxisMarkers(svgElement) {
  currentSvgAxisMarkersX = [];
  currentSvgAxisMarkersY = [];
  const allTexts = Array.from(svgElement.querySelectorAll("text"));
  const viewBoxHeight = svgElement.viewBox.baseVal.height;
  const viewBoxWidth = svgElement.viewBox.baseVal.width;
  // More generous thresholds
  const Y_EDGE_THRESHOLD = viewBoxHeight * 0.2;
  const X_EDGE_THRESHOLD = viewBoxWidth * 0.2;

  allTexts.forEach((textEl) => {
    try {
      const content = textEl.textContent.trim();
      if (!content) return;
      const bbox = textEl.getBBox();
      if (bbox.width === 0 || bbox.height === 0) return; // Skip invisible elements

      // Y-Axis (Floors): Must be text like "+12.25", "1st FLOOR", etc., AND be near the left/right edges.
      if (
        content.toLowerCase().includes("floor") ||
        content.match(/^\s*\+\d+\.\d+\s*$/)
      ) {
        if (
          bbox.x < X_EDGE_THRESHOLD ||
          bbox.x + bbox.width > viewBoxWidth - X_EDGE_THRESHOLD
        ) {
          currentSvgAxisMarkersY.push({
            text: content,
            x: bbox.x,
            y: bbox.y + bbox.height / 2,
          });
        }
      }
      // X-Axis (Grid Letters/Numbers): Must be simple letters or numbers, AND be near the top/bottom edges.
      else if (content.match(/^[A-Z0-9]{1,3}$/)) {
        if (
          bbox.y < Y_EDGE_THRESHOLD ||
          bbox.y + bbox.height > viewBoxHeight - Y_EDGE_THRESHOLD
        ) {
          currentSvgAxisMarkersX.push({
            text: content,
            x: bbox.x + bbox.width / 2,
            y: bbox.y,
          });
        }
      }
    } catch (e) {
      /* Ignore errors */
    }
  });

  currentSvgAxisMarkersX.sort((a, b) => a.x - b.x);
  currentSvgAxisMarkersY.sort((a, b) => a.y - b.y);

  // --- DEBUGGING: Uncomment to see what markers are being found ---
  console.log(
    "Found X-Axis Markers:",
    currentSvgAxisMarkersX.map((m) => m.text)
  );
  console.log(
    "Found Y-Axis (Floor) Markers:",
    currentSvgAxisMarkersY.map((m) => m.text)
  );
}

function getElementSpatialContext(element) {
  let axisSpan = "N/A",
    floorLevel = "N/A";
  try {
    const elBBox = element.getBBox();
    const elCenterX = elBBox.x + elBBox.width / 2;
    const elCenterY = elBBox.y + elBBox.height / 2;

    // Find nearest X-axis marker to the left and right
    let leftMarker = null,
      rightMarker = null;
    currentSvgAxisMarkersX.forEach((marker) => {
      if (marker.x <= elCenterX) {
        leftMarker = marker;
      }
      if (marker.x >= elCenterX && !rightMarker) {
        rightMarker = marker;
      }
    });
    if (leftMarker && rightMarker) {
      axisSpan =
        leftMarker.text === rightMarker.text
          ? leftMarker.text
          : `${leftMarker.text}-${rightMarker.text}`;
    } else if (leftMarker) {
      axisSpan = `> ${leftMarker.text}`;
    } else if (rightMarker) {
      axisSpan = `< ${rightMarker.text}`;
    }

    // Find nearest Y-axis (floor) marker that is BELOW the element's center
    let belowMarker = null;
    currentSvgAxisMarkersY.forEach((marker) => {
      if (marker.y >= elCenterY && (!belowMarker || marker.y < belowMarker.y)) {
        belowMarker = marker;
      }
    });
    if (belowMarker) {
      floorLevel = belowMarker.text;
    }
  } catch (e) {
    /* ignore */
  }
  return {
    axisSpan,
    floorLevel,
    derivedId: null,
  };
}
//</editor-fold>

//<editor-fold desc="Zoom and Pan Functions">
function setupZoomControls() {
  const zoomInBtn = document.getElementById("zoomInBtn");
  const zoomOutBtn = document.getElementById("zoomOutBtn");
  const zoomResetBtn = document.getElementById("zoomResetBtn");
  const svgContainer = document.getElementById("svgContainer");
  zoomInBtn.addEventListener("click", () => zoomSvg(currentZoom + zoomStep));
  zoomOutBtn.addEventListener("click", () => zoomSvg(currentZoom - zoomStep));
  zoomResetBtn.addEventListener("click", resetZoomAndPan);
  svgContainer.addEventListener("wheel", handleWheelZoom, {
    passive: false,
  });
  svgContainer.addEventListener("mousedown", handleMouseDown);
  document.addEventListener("mousemove", handleMouseMove);
  document.addEventListener("mouseup", handleMouseUp);
  svgContainer.addEventListener("mouseleave", handleMouseUp);
  svgContainer.addEventListener("touchstart", handleTouchStart, {
    passive: false,
  });
  svgContainer.addEventListener("touchmove", handleTouchMove, {
    passive: false,
  });
  document.addEventListener("touchend", handleTouchEnd, {
    passive: false,
  });
}

function handleWheelZoom(e) {
  e.preventDefault();
  const svgContainerRect = e.currentTarget.getBoundingClientRect();
  const svgX = (e.clientX - svgContainerRect.left - panX) / currentZoom;
  const svgY = (e.clientY - svgContainerRect.top - panY) / currentZoom;
  const delta = e.deltaY < 0 ? zoomStep : -zoomStep;
  zoomSvg(currentZoom * (1 + delta), svgX, svgY);
}

function handleMouseDown(e) {
  if (e.target.closest(".zoom-controls, .interactive-element")) return;
  isPanning = true;
  panStartX = e.clientX - panX;
  panStartY = e.clientY - panY;
  e.currentTarget.classList.add("dragging");
}

function handleMouseMove(e) {
  if (!isPanning) return;
  e.preventDefault();
  panX = e.clientX - panStartX;
  panY = e.clientY - panStartY;
  updateTransform();
}

function handleMouseUp(e) {
  if (isPanning) {
    isPanning = false;
    document.getElementById("svgContainer").classList.remove("dragging");
  }
}

function handleTouchStart(e) {
  if (e.target.closest(".zoom-controls")) return;
  if (e.touches.length === 1) {
    isPanning = true;
    const touch = e.touches[0];
    panStartX = touch.clientX - panX;
    panStartY = touch.clientY - panY;
  } else if (e.touches.length === 2) {
    isPanning = false;
    const t1 = e.touches[0],
      t2 = e.touches[1];
    lastTouchDistance = Math.hypot(
      t2.clientX - t1.clientX,
      t2.clientY - t1.clientY
    );
  }
}

function handleTouchMove(e) {
  e.preventDefault();
  if (e.touches.length === 1 && isPanning) {
    const touch = e.touches[0];
    panX = touch.clientX - panStartX;
    panY = touch.clientY - panStartY;
    updateTransform();
  } else if (e.touches.length === 2) {
    const t1 = e.touches[0],
      t2 = e.touches[1];
    const dist = Math.hypot(t2.clientX - t1.clientX, t2.clientY - t1.clientY);
    if (lastTouchDistance > 0) {
      const scale = dist / lastTouchDistance;
      const midX = (t1.clientX + t2.clientX) / 2;
      const midY = (t1.clientY + t2.clientY) / 2;
      const rect = e.currentTarget.getBoundingClientRect();
      zoomSvg(
        currentZoom * scale,
        (midX - rect.left - panX) / currentZoom,
        (midY - rect.top - panY) / currentZoom
      );
    }
    lastTouchDistance = dist;
  }
}

function handleTouchEnd(e) {
  isPanning = false;
  if (e.touches.length < 2) lastTouchDistance = 0;
}

function addTouchClickSupport(element, clickHandler) {
  let touchStartTime, touchMoved;
  element.addEventListener(
    "touchstart",
    (e) => {
      e.stopPropagation();
      touchMoved = false;
      touchStartTime = Date.now();
    },
    {
      passive: true,
    }
  );
  element.addEventListener(
    "touchmove",
    () => {
      touchMoved = true;
    },
    {
      passive: true,
    }
  );
  element.addEventListener(
    "touchend",
    (e) => {
      if (!touchMoved && Date.now() - touchStartTime < 300) {
        e.preventDefault();
        clickHandler(e);
      }
    },
    {
      passive: false,
    }
  );
}

function zoomSvg(newZoomFactor, pivotX, pivotY) {
  if (!currentSvgElement) return;
  const svgContainerRect = document
    .getElementById("svgContainer")
    .getBoundingClientRect();
  pivotX = pivotX ?? svgContainerRect.width / 2;
  pivotY = pivotY ?? svgContainerRect.height / 2;
  const newZoom = Math.max(minZoom, Math.min(maxZoom, newZoomFactor));
  panX -= pivotX * (newZoom - currentZoom);
  panY -= pivotY * (newZoom - currentZoom);
  currentZoom = newZoom;
  updateTransform();
  updateZoomButtonStates();
}

function resetZoomAndPan() {
  currentZoom = 1;
  panX = 0;
  panY = 0;
  updateTransform();
  updateZoomButtonStates();
}

function updateTransform() {
  if (currentSvgElement) {
    currentSvgElement.style.transform = `translate(${panX}px, ${panY}px) scale(${currentZoom})`;
    currentSvgElement.style.transformOrigin = `0 0`;
  }
}

function updateZoomButtonStates() {
  const zoomInBtn = document.getElementById("zoomInBtn");
  const zoomOutBtn = document.getElementById("zoomOutBtn");
  if (zoomInBtn && zoomOutBtn) {
    zoomInBtn.disabled = currentZoom >= maxZoom;
    zoomOutBtn.disabled = currentZoom <= minZoom;
  }
}
//</editor-fold>
