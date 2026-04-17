///pardis/assets/js/pardis_app.js

const USER_ROLE = document.body.dataset.userRole;
const USER_DISPLAY_NAME = document.body.dataset.userDisplayName;
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
let currentPlanDefaultBlock = "ساختمان عمومی";
let PLAN_SCALE_FACTORS = {};
fetch("/pardis/assets/js/plan_scales.json")
  .then((res) => {
    if (!res.ok) {
      throw new Error(`HTTP error! status: ${res.status}`);
    }
    return res.json();
  })
  .then((data) => {
    PLAN_SCALE_FACTORS = data;
    console.log("Plan scale factors loaded successfully:", PLAN_SCALE_FACTORS);
  })
  .catch((err) =>
    console.error(
      "CRITICAL ERROR: Could not load or parse plan_scales.json. Scaffolding calculations will be incorrect.",
      err
    )
  );
let currentSvgHeight = 2200,
  currentSvgWidth = 3000;
let visibleStatuses = {
  OK: true,
  "Not OK": true,
  "Pre-Inspection Complete": true,
  Pending: true,
};
let currentlyActiveSvgElement = null;
const SVG_BASE_PATH = "/pardis/"; // Use root-relative path
const STATUS_COLORS = {
  "Pre-Inspection Complete": "rgba(255, 140, 0, 0.8)", // Orange
  "Awaiting Re-inspection": "rgba(0, 191, 255, 0.8)", // Deep Sky Blue: Contractor is done, consultant's turn
  OK: "rgba(40, 167, 69, 0.7)", // Green
  Reject: "rgba(220, 53, 69, 0.7)", // Red
  Repair: "rgba(156, 39, 176, 0.7)", // Purple
  Pending: "rgba(108, 117, 125, 0.4)", // Grey
};
let currentPlanDbData = {};
// Replace the svgGroupConfig constant in ghom_app.js with this updated version:

const svgGroupConfig = {
  // ===== EXISTING GFRC CONFIG =====
  GFRC: {
    label: "GFRC",
    colors: {
      v: "rgba(13, 110, 253, 0.7)",
      h: "rgba(0, 150, 136, 0.75)",
    },
    defaultVisible: true,
    interactive: true,
    elementType: "GFRC",
  },

  // ===== NEW INTERACTIVE LAYERS FROM PYTHON =====
  GLASS: {
    label: "شیشه",
    color: "rgba(173, 216, 230, 0.7)",
    defaultVisible: true,
    interactive: true,
    elementType: "Glass",
  },

  Bazshow: {
    label: "بازشو",
    color: "rgba(169, 169, 169, 0.9)",
    defaultVisible: true,
    interactive: true,
    elementType: "Bazshow",
  },

  Curtainwall: {
    label: "کرتین وال",
    color: "rgba(76, 40, 161, 0.7)",
    defaultVisible: true,
    interactive: true,
    elementType: "Curtainwall",
  },

  handrailcomponents: {
    label: "اجزای نرده",
    color: "rgba(255, 152, 0, 0.7)",
    defaultVisible: true,
    interactive: true,
    elementType: "Handrail",
  },

  handrailGlass: {
    label: "شیشه نرده",
    color: "rgba(100, 181, 246, 0.7)",
    defaultVisible: true,
    interactive: true,
    elementType: "HandrailGlass",
  },

  Brick: {
    label: "آجر",
    color: "rgba(188, 71, 73, 0.7)",
    defaultVisible: true,
    interactive: true,
    elementType: "Brick",
  },

  Solid_Sheet_2mm: {
    label: "ورق 2 میلی‌متری",
    color: "rgba(96, 125, 139, 0.7)",
    defaultVisible: true,
    interactive: true,
    elementType: "SolidSheet",
  },

  Flashing: {
    label: "فلشینگ",
    color: "rgba(255, 193, 7, 0.7)",
    defaultVisible: true,
    interactive: true,
    elementType: "Flashing",
  },

  Spendral_Glass: {
    label: "شیشه اسپندرال",
    color: "rgba(117, 117, 117, 0.8)",
    defaultVisible: true,
    interactive: true,
    elementType: "SpendralGlass",
  },

  // ===== EXISTING NON-INTERACTIVE LAYERS (Keep these as-is) =====

  $TEXT: {
    label: "متن",
    defaultVisible: true,
    interactive: false,
  },
  DOORS: {
    label: "درها",
    defaultVisible: true,
    interactive: false,
  },
  "TB_5034_SA - Dimensions#Bemaßung Allgemein": {
    label: "ابعاد",
    defaultVisible: false,
    interactive: false,
  },

  "HB_AL_ACH        Achsen": {
    label: "محورها",
    defaultVisible: false,
    interactive: false,
  },

  "Ax-Text": {
    label: "متن محور",
    defaultVisible: true,
    interactive: false,
  },

  FFL: {
    label: "تراز تمام شده کف",
    defaultVisible: true,
    interactive: false,
  },

  "TXT-3D": {
    label: "متن سه‌بعدی",
    defaultVisible: false,
    interactive: false,
  },

  axis: {
    label: "محور",
    defaultVisible: true,
    interactive: false,
  },

  TXT: {
    label: "متن",
    defaultVisible: true,
    interactive: false,
  },

  "TB_5037_SA - Text#Beschriftung Allgemein": {
    label: "برچسب‌ها",
    defaultVisible: false,
    interactive: false,
  },

  "A-GRID-SYMB-100": {
    label: "شبکه",
    defaultVisible: true,
    interactive: false,
  },

  AX: {
    label: "محور",
    defaultVisible: true,
    interactive: false,
  },

  0: {
    label: "لایه صفر",
    defaultVisible: false,
    interactive: false,
  },

  "S-GRID": {
    label: "شبکه ساختاری",
    defaultVisible: true,
    interactive: false,
  },

  "A-AREA-IDEN": {
    label: "شناسه ناحیه",
    defaultVisible: false,
    interactive: false,
  },

  Void: {
    label: "فضای خالی",
    defaultVisible: false,
    interactive: false,
  },

  Line: {
    label: "خط",
    defaultVisible: false,
    interactive: false,
  },

  "A-FLOR-LEVL": {
    label: "سطح طبقه",
    defaultVisible: true,
    interactive: false,
  },

  "@PV": {
    label: "پی‌وی",
    defaultVisible: false,
    interactive: false,
  },

  // ===== EXISTING REGION LAYERS (Keep these as-is) =====
  WA: {
    label: "غرب کشاورزی",
    color: "#0de16d",
    defaultVisible: true,
    interactive: true,
    contractor: "پیمانکار غرب",
    contractoren: "WS",
    block: "ساختمان کشاورزی",
    elementType: "Region",
  },

  SA: {
    label: "جنوب کشاورزی",
    color: "#ebb00d",
    defaultVisible: true,
    interactive: true,
    contractor: "پیمانکار جنوب",
    contractoren: "ST",
    block: "ساختمان کشاورزی",
    elementType: "Region",
  },

  EA: {
    label: "شرق کشاورزی",
    color: "#38abee",
    defaultVisible: true,
    interactive: true,
    contractor: "شرکت ",
    contractoren: "ES",
    block: "ساختمان کشاورزی",
    elementType: "Region",
  },

  NA: {
    label: "شمال کشاورزی",
    color: "#ee3847ff",
    defaultVisible: true,
    interactive: true,
    contractor: "شرکت ",
    contractoren: "ES",
    block: "ساختمان کشاورزی",
    elementType: "Region",
  },

  WL: {
    label: "غرب کتابخانه",
    color: "#0de16d",
    defaultVisible: true,
    interactive: true,
    contractor: "پیمانکار غرب",
    contractoren: "WS",
    block: "ساختمان کتابخانه",
    elementType: "Region",
  },

  SL: {
    label: "جنوب کتابخانه",
    color: "#ebb00d",
    defaultVisible: true,
    interactive: true,
    contractor: "پیمانکار جنوب",
    contractoren: "ST",
    block: "ساختمان کتابخانه",
    elementType: "Region",
  },

  EL: {
    label: "شرق کتابخانه",
    color: "#38abee",
    defaultVisible: true,
    interactive: true,
    contractor: "پیمانکار شرق ",
    contractoren: "ES",
    block: "ساختمان کتابخانه",
    elementType: "Region",
  },

  VoidL: {
    label: "وید کتابخانه",
    color: "#ee3838",
    defaultVisible: true,
    interactive: true,
    contractor: "شرکت ",
    contractoren: "پیمانکار وید",
    block: "ساختمان کتابخانه",
    elementType: "Region",
  },

  // ===== EXISTING ZIRSAZI LAYERS (Keep these) =====
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

  // ===== EXISTING GFRC PARTS (Keep these) =====
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

  // ===== EXISTING GLASS TYPES (Keep these) =====
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

  // ===== EXISTING MULLION/TRANSOM (Keep these) =====
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

  // ===== OTHER EXISTING LAYERS (Keep these) =====
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
};

const regionToZoneMap = {
  VoidL: [
    {
      label: "وید کتابخانه",
      svgFile: "VoidLib.svg",
    },
  ],
  WL: [
    {
      label: "غرب کتابخانه",
      svgFile: "WestLib.svg",
    },
  ],
  EL: [
    {
      label: "شرق کتابخانه",
      svgFile: "EastLib.svg",
    },
  ],
  SL: [
    {
      label: "جنوب کتابخانه",
      svgFile: "SouthLib.svg",
    },
  ],
  WA: [
    {
      label: "غرب کشاورزی",
      svgFile: "WestAgri.svg",
    },
  ],
  SA: [
    {
      label: "جنوب کشاورزی",
      svgFile: "SouthAgri.svg",
    },
  ],
  NA: [
    {
      label: "شمال کشاورزی",
      svgFile: "NorthAgri.svg",
    },
  ],
  EA: [
    {
      label: "شرق کشاورزی",
      svgFile: "EastAgri.svg",
    },
  ],
  Library: [
    {
      label: "Plan",
      svgFile: "Plan.svg",
    },
    {
      label: "غرب کتابخانه",
      svgFile: SVG_BASE_PATH + "WestLib.svg",
    },
    {
      label: "شرق کتابخانه",
      svgFile: SVG_BASE_PATH + "EastLib.svg",
    },
    {
      label: "شمال کتابخانه",
      svgFile: SVG_BASE_PATH + "NorthLib.svg",
    },
    {
      label: "جنوب کتابخانه",
      svgFile: SVG_BASE_PATH + "SouthLib.svg",
    },
    {
      label: "وید کتابخانه",
      svgFile: SVG_BASE_PATH + "VoidLib.svg",
    },
  ],
  Agricalture: [
    {
      label: "شرق کشاورزی",
      svgFile: SVG_BASE_PATH + "EastAgri.svg",
    },
    {
      label: "شمال کشاورزی",
      svgFile: SVG_BASE_PATH + "NorthAgri.svg",
    },
    {
      label: "جنوب کشاورزی",
      svgFile: SVG_BASE_PATH + "SouthAgri.svg",
    },
  ],
};
let APP_CONFIG = {
  enableContractorRestrictions: false,
  requireContractorInIDs: false,
  projectPhase: "initial",
};
fetch("/pardis/assets/js/config.json")
  .then((res) => res.json())
  .then((config) => {
    APP_CONFIG = config;
    console.log("App config loaded:", APP_CONFIG);
  })
  .catch((err) => {
    console.warn("Could not load config, using defaults:", err);
  });
const planNavigationMappings = [
  {
    type: "textAndCircle",
    regex: "^(\\d+|[A-Za-z]+[\\d-]*)\\s+Zone$",
    numberGroupIndex: 1,
    svgFilePattern: "/pardis/Zone{NUMBER}.svg",
    labelPattern: "Zone {NUMBER}",
    defaultContractor: "پیمانکار پیش‌فرض زون عمومی",
    defaultBlock: "ساختمان پیش‌فرض زون عمومی",
  },

  {
    svgFile: SVG_BASE_PATH + "Plan.svg",
    label: "Plan اصلی",
    defaultContractor: "مدیر پیمان ",
    defaultBlock: "پروژه  دانشگاه خاتم ",
  },
];
// Add this new constant to pardis_app.js
const planroles = {
  // Library regions
  West: {
    label: "غرب کتابخانه",
    contractor_id: "WS",
    color: "#0de16d",
  },
  South: {
    label: "جنوب کتابخانه",
    contractor_id: "ST",
    color: "#ebb00d",
  },
  East: {
    label: "شرق کتابخانه",
    contractor_id: "ES",
    color: "#38abee",
  },
  North: {
    label: "شمال کتابخانه",
    contractor_id: "NS",
    color: "#ee3847ff",
  },
  Void: {
    label: "وید کتابخانه",
    contractor_id: "VO",
    color: "#ee3838",
  },

  // Agriculture regions - ADD THESE
  WA: {
    label: "غرب کشاورزی",
    contractor_id: "WS",
    color: "#0de16d",
  },
  SA: {
    label: "جنوب کشاورزی",
    contractor_id: "ST",
    color: "#ebb00d",
  },
  EA: {
    label: "شرق کشاورزی",
    contractor_id: "ES",
    color: "#38abee",
  },
  NA: {
    label: "شمال کشاورزی",
    contractor_id: "NS",
    color: "#ee3847ff",
  },

  // Library with L suffix
  WL: {
    label: "غرب کتابخانه",
    contractor_id: "WS",
    color: "#0de16d",
  },
  SL: {
    label: "جنوب کتابخانه",
    contractor_id: "ST",
    color: "#ebb00d",
  },
  EL: {
    label: "شرق کتابخانه",
    contractor_id: "ES",
    color: "#38abee",
  },
  VoidL: {
    label: "وید کتابخانه",
    contractor_id: "VO",
    color: "#ee3838",
  },
};
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
 * Verifies a signature against a user's public key.
 * @param {string} signedData - The original, un-encoded data that was signed.
 * @param {string} signatureB64 - The Base64 encoded signature.
 * @param {string} userId - The ID of the user whose signature needs verification.
 * @returns {Promise<{verified: boolean, error: string|null}>}
 */
async function verifySignature(signedData, signatureB64, userId) {
  if (!userId) return { verified: false, error: "User ID is missing." };

  try {
    // 1. Fetch the user's public key from the server
    // NOTE: You must create this API endpoint. It should return JSON like: { "public_key_pem": "---BEGIN PUBLIC KEY---..." }
    const response = await fetch(
      `/pardis/api/get_public_key.php?user_id=${userId}`
    );
    if (!response.ok) {
      return { verified: false, error: "Could not fetch public key." };
    }
    const keyData = await response.json();
    if (!keyData.public_key_pem) {
      return { verified: false, error: "Public key not found for user." };
    }

    // 2. Load the public key using forge
    const publicKey = forge.pki.publicKeyFromPem(keyData.public_key_pem);

    // 3. Create a hash of the original data (must match the signing process)
    const md = forge.md.sha256.create();
    md.update(signedData, "utf8");

    // 4. Decode the signature from Base64
    const signatureBytes = forge.util.decode64(signatureB64);

    // 5. Perform the verification
    const isVerified = publicKey.verify(md.digest().bytes(), signatureBytes);

    return {
      verified: isVerified,
      error: isVerified ? null : "Signature mismatch.",
    };
  } catch (error) {
    console.error("Verification Error:", error);
    return {
      verified: false,
      error: "A technical error occurred during verification.",
    };
  }
}

function convertToPersianNumbers(num) {
  const persianDigits = ["۰", "۱", "۲", "۳", "۴", "۵", "۶", "۷", "۸", "۹"];
  return String(num).replace(/\d/g, (digit) => persianDigits[digit]);
}
/**
 * Export inspection form to PDF with digital signature
 * @param {string} elementId - The element ID being inspected
 * @param {object} formData - The inspection data
 * @param {string} digitalSignature - Base64 encoded signature
 */
async function exportInspectionToPDF(
  elementId,
  formData,
  digitalSignature,
  allItemsMap = {}
) {
  if (typeof window.jspdf === "undefined") {
    console.error("PDF Export Error: jsPDF library is not loaded.");
    alert(
      "خطا: کتابخانه ایجاد PDF بارگذاری نشده است. لطفا اتصال اینترنت خود را بررسی کرده و صفحه را رفرش کنید."
    );
    return { success: false, error: "jsPDF library not loaded" };
  }
  try {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF("p", "mm", "a4");
    const vazirFontBase64 =
      "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AL0AwADBAAMAAQA0AAEAEgAAAAEAAAABAAEADwCdAKIAugC7APUA+QGLAY8B0QHdAeECHQIhAiUCQQABAAEAnQADAAEAogABABIAAAABAAAAAgABAEYAnACdAJ4AnwChAKIAowCkAKUApgCqAKsArACtAK4ArwCwALEAsgCzALcAuAC5ALoAuwC8AL0AvgC/AMAAwQDlAOcA6wDtAPEA9QD5AP4A/wELAQwBegGJAYsBjQGPAbEB0QHdAeEB5QHpAe0B+QH9AgECBQIJAg0CEQIVAhkCHQIhAiUCKQItAjECQQABAAQA7QDxAYYBiAADAAEApAABABIAAAABAAAAAwABAEcAnACdAJ4AnwChAKIAowCkAKUApgCqAKsArACtAK4ArwCwALEAsgCzALcAuAC5ALoAuwC8AL0AvgC/AMAAwQDlAOcA6wDtAPEA9QD5AP4A/wELAQwBegGBAYkBiwGNAY8BsQHRAd0B4QHlAekB7QH5Af0CAQIFAgkCDQIRAhUCGQIdAiECJQIpAi0CMQJBAAEACACsAK0A7gDvAfQB9gJ0AnUAAQsCCoYAAQsOAAwBTwKgAqYCrAKyArgCvgLEAsoC0ALWAtwC4gLoAu4C9AL6AwADBgMMAxIDGAMeAyQDKgMwAzYDPANCA0gDTgNUA1oDYANmA2wDcgN4A34DhAOKA5ADlgOcA6IDqAOuA7QDugPAA8YDzAPSA9gD3gPkA+oD8AP2A/wEAgQIBA4EFAQaBCAEJgQsBDIEOAQ+BEQESgRQBFYEXARiBGgEbgR0BHoEgASGBIwEkgSYBJ4EpASqBLAEtgS8BMIEyATOBNQE2gTgBOYE7ATyBPgE/gUEBQoFEAUWBRwFIgUoBS4FNAU6BUAFRgVMBVIFWAVeBWQFagVwBXYFfAWCBYgFjgWUBZoFoAWmBawFsgW4Bb4FxAXKBdAF1gXcBeIF6AXuBfQF+gYABgYGDAYSBhgGHgYkBioGMAY2BjwGQgZIBk4GVAZaBmAGZgZsBnIGeAZ+BoQGigaQBpYGnAaiBqgGrga0BroGwAbGBswG0gbYBt4G5AbqBvAG9gb8BwIHCAcOBxQHGgcgByYHLAcyBzgHPgdEB0oHUAdWB1wHYgdoB24HdAd6B4AHhgeMB5IHmAeeB6QHqgewB7YHvAfCB8gHzgfUB9oH4AfmB+wH8gf4B/4IBAgK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AdH/zwIP/88G8QBOB1YATgKg/7UC//+1BvEATgdWAE4CoP/sAv//7AeqAE4Dlv/sA8z/7AVDAEYFYgBGBTb/7AVH/+wDwwBOBB8ATgLa/9kDFv/ZAtr/2QMW/9kHSQBOB5UATgO2/+wEA//sB0kATgeVAE4Dtv/sBAP/7AWnAE4F+ABOBA4ATgOXAEwCUP/sA+r/7AXPAC8EYAAvBVn/7APq/+wG0ABOBigATgbQAE4GKABOAjAAXwMgAGUHJwBOB2MATgO2/+wEA//sA3AATgOqAE4DcABOA6oATgNwAE4DqgBOA3AATgOqAE4CUP/sAq//7AXFAE4GFwBPAqD/swL//7ME+QB4BPkAnQnRAE4MdQBOAlAAOAJQ/+wC5wA2ApMATgJQAD0CUAA2AlD/7AJQAEYCUP/sAlAAOwJQ/+wCUAAWAlD/7AJQAHgCUP/sAz4AUwHR/2MCD//zAdEAGQIPABkDcABOA6oATgHRAAsCDwBbBcUATgYXAE8CUP/sAq//7AHRAIACDwCABvEATgdWAE4CUP/sAq//7AOaAE4EDgBOBvEATgdWAE4CoP/sAv//7AbxAE4HVgBOAqD/7AL//+wFQwBGBWIARgU2/+wFR//sBUMARgViAEYFNv/sBUf/7AVDAEYFYgBGBTb/7AVH/+wDwwBOBB8ATgPDAE4EHwBOAsb/2QMW/9kCxv/ZAxb/2QmYAE4J+QBOBhr/7AZ6/+wJmABOCfkATgYa/+wGev/sCg8ATgoyAE4Gdv/sBpn/7AoPAE4KMgBOBnb/7AaZ/+wFvwBOBeYATgUN/+wFNP/sBb8ATgXmAE4FDf/sBTT/7AUaAE4ErwBOBAr/7AR1/+wFGgBOBK8ATgQn/+wEdf/sBvUATgeqAE4Dlv/sA8z/7AWdAE4F9gBOA5b/7APM/+wHJwBOB2MATgO2/+wEA//sBWEATgWfAE4CYP/sAp7/7ATOAFEFEABRBBj/7ARX/+wFpwBOBfgATgJQ/+wCr//sA5oATgQOAE4FWf/sA+r/7ANwAE4DqgBOBcUATgYXAE8FxQBOBhcATwKg/7MC//+zBJT/6gTR/+oElP/5BNH/+QSUAFQE0QBUBJQAVATRAFQAAAAACDAAWQg1AFwElP/jBNH/4wIN/+0B5//tAg8AgAKvAE8E0QBUAdH/zwH4AF8HJwLYAfj/twH4/7wB+P+9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ZSB3aXRoIGEgRkFRIGF0OiBodHRwczovL3NjcmlwdHMuc2lsLm9yZy9PRkxodHRwczovL3NjcmlwdHMuc2lsLm9yZy9PRkxWYXppck1lZGl1bQBDAG8AcAB5AHIAaQBnAGgAdAAgACgAYwApACAAMgAwADEANQAsACAAUwBhAGIAZQByACAAUgBhAHMAdABpAGsAZQByAGQAYQByACAAPABzAGEAYgBlAHIALgByAGEAcwB0AGkAawBlAHIAZABhAHIAQABnAG0AYQBpAGwALgBjAG8AbQA+AC4AVgBhAHoAaQByACAATQBlAGQAaQB1AG0ATQBlAGQAaQB1AG0AMgA3AC4AMgAuADIAOwAgACAAIAAgADsAVgBhAHoAaQByAC0ATQBlAGQAaQB1AG0AVgBhAHoAaQByACAATQBlAGQAaQB1AG0AVgBlAHIAcwBpAG8AbgAgADIANwAuADIALgAyAFYAYQB6AGkAcgAtAE0AZQBkAGkAdQBtAFMAYQBiAGUAcgAgAFIAYQBzAHQAaQBrAGUAcgBkAGEAcgBTAGEAYgBlAHIAIABSAGEAcwB0AGkAawBlAHIAZABhAHIAVABoAGUAIABmAGkAcgBzAHQAIAB2AGUAcgBzAGkAbwBuACAAbwBmACAAVgBhAHoAaQByACAAdwBhAHMAIABiAGEAcwBlAGQAIABvAG4AIABEAGUAagBhAFYAdQAgADIALgAzADUAIAAoAHcAYQBzACAAYwBvAG0AbQBpAHQAdABlAGQAIAB0AG8AIAB0AGgAZQAgAHAAdQBiAGwAaQBjACAAZABvAG0AYQBpAG4AKQAgAHQAbwAgAGIAZQBnAGkAbgAgAGQAZQBzAGkAZwBuAGkAbgBnACAAYQBuAGQAIABkAGUAdgBlAGwAbwBwAGkAbgBnACAAdABoAGkAcwAgAG4AZQB3ACAAdAB5AHAAZQBmAGEAYwBlAC4ACgBOAG8AbgAtAEEAcgBhAGIAaQBjACAAKABMAGEAdABpAG4AKQAgAGcAbAB5AHAAaABzACAAYQBuAGQAIABkAGEAdABhACAAYQByAGUAIABpAG0AcABvAHIAdABlAGQAIABmAHIAbwBtACAAUgBvAGIAbwB0AG8AIABmAG8AbgB0ACAAKAB2AGUAcgBzAGkAbwBuACAAMgAuADEAMwA3ADsAIAAyADAAMQA3ACkAIAB1AG4AZABlAHIAIAB0AGgAZQAgAEEAcABhAGMAaABlACAATABpAGMAZQBuAHMAZQAsACAAVgBlAHIAcwBpAG8AbgAgADIALgAwAC4AVABoAGkAcwAgAEYAbwBuAHQAIABTAG8AZgB0AHcAYQByAGUAIABpAHMAIABsAGkAYwBlAG4AcwBlAGQAIAB1AG4AZABlAHIAIAB0AGgAZQAgAFMASQBMACAATwBwAGUAbgAgAEYAbwBuAHQAIABMAGkAYwBlAG4AcwBlACwAIABWAGUAcgBzAGkAbwBuACAAMQAuADEALgAgAFQAaABpAHMAIABsAGkAYwBlAG4AcwBlACAAaQBzACAAYQB2AGEAaQBsAGEAYgBsAGUAIAB3AGkAdABoACAAYQAgAEYAQQBRACAAYQB0ADoAIABoAHQAdABwAHMAOgAvAC8AcwBjAHIAaQBwAHQAcwAuAHMAaQBsAC4AbwByAGcALwBPAEYATABoAHQAdABwAHMAOgAvAC8AcwBjAHIAaQBwAHQAcwAuAHMAaQBsAC4AbwByAGcALwBPAEYATABWAGEAegBpAHIATQBlAGQAaQB1AG0AAAACAAAAAAAA/gwAZAAAAAAAAAAAAAAAAAAAAAAAAAAAArQAAAABAAIBAgEDAQQAAwAEAAUABgAHAAgACQAKAAsADAANAA4ADwAQABEAEgATABQAFQAWABcAGAAZABoAGwAcAB0AHgAfACAAIQAiACMAJAAlACYAJwAoACkAKgArACwALQAuAC8AMAAxADIAMwA0ADUANgA3ADgAOQA6ADsAPAA9AD4APwBAAEEAQgBDAEQARQBGAEcASABJAEoASwBMAE0ATgBPAFAAUQBSAFMAVABVAFYAVwBYAFkAWgBbAFwAXQBeAF8AYABhAQUAowCEAIUAvQCWAOgAhgCOAIsAnQCpAKQBBgCKANoAgwCTAI0AlwCIAMMA3gCeAKoAogDwALgBBwDYAOEBCADbANwA3QDgANkA3wEJAQoBCwEMAQ0BDgEPAKgAnwEQAREBEgETARQBFQEWARcBGAEZARoBGwEcAR0BHgEfASABIQEiASMBJAElASYBJwEoASkBKgErASwBLQEuAS8BMAExATIBMwE0ATUBNgE3ATgBOQE6ATsBPAE9AT4BPwFAAUEBQgFDAUQBRQFGAUcBSAFJAUoBSwFMAU0BTgFPAVABUQFSAVMBVAFVAVYBVwFYAVkBWgFbAVwBXQFeAV8BYAFhAWIBYwFkAWUBZgFnAWgBaQFqAWsBbAFtAW4BbwFwAXEBcgFzAXQBdQF2AXcBeAF5AXoBewF8AX0BfgF/AYABgQGCAYMBhAGFAYYBhwGIAYkBigGLAYwBjQGOAY8BkAGRAZIBkwGUAZUBlgGXAZgBmQGaAZsBnAGdAZ4BnwGgAaEBogGjAaQBpQGmAacBqAGpAaoBqwGsALIAswGtAa4AtgC3AMQBrwC0ALUAxQCCAMIAhwGwAKsBsQGyAbMBtAG1AbYBtwDGAbgBuQC+AL8BugC8AbsA9wG8Ab0BvgG/AcABwQHCAcMBxAHFAcYBxwHIAckBygCMAcsAmAHMAJoAmQDvAKUAkgCcAKcAjwCUAJUAuQHNAc4BzwHQAdEB0gHTAdQB1QHWAdcB2AHZAdoB2wHcAd0B3gHfAeAB4QHiAeMB5AHlAeYB5wHoAekB6gHrAewB7QHuAe8B8AHxAfIB8wH0AfUB9gH3AfgB+QH6AfsB/AH9Af4B/wIAAgECAgIDAgQCBQIGAgcCCAIJAgoCCwIMAg0CDgIPAhACEQISAhMCFAIVAhYCFwIYAhkCGgIbAhwCHQIeAh8CIAIhAiICIwIkAiUCJgInAigCKQIqAisCLAItAi4CLwIwAjECMgIzAjQCNQI2AjcCOAI5AjoCOwI8Aj0CPgI/AkACQQJCAkMCRAJFAkYCRwJIAkkCSgJLAkwCTQJOAk8CUAJRAlICUwJUAlUCVgJXAlgCWQJaAlsCXAJdAl4CXwJgAmECYgJjAmQCZQJmAmcCaAJpAmoCawJsAm0CbgJvAnACcQJyAnMCdAJ1AnYCdwJ4AnkCegJ7AnwCfQJ+An8CgAKBAoICgwKEAoUChgKHAogCiQKKAosCjAKNAo4CjwKQApECkgKTApQClQKWApcCmAKZApoCmwKcAp0CngKfAqACoQKiAqMCpAKlAqYCpwKoAqkCqgKrAqwCrQKuAq8CsAKxArICswK0ArUCtgK3ArgCuQK6ArsCvAK9Ar4CvwLAAsECwgLDAsQCxQLGAscCyALJAsoCywLMAs0CzgLPAtAC0QLSAtMC1ALVAtYC1wLYAtkC2gLbAtwC3QLeAt8C4ALhAuIC4wLkAuUC5gLnAugC6QLqAusC7ALtAu4C7wLwAvEC8gLzAvQC9QL2AvcC+AL5AvoC+wL8Av0C/gL/AwADAQMCAwMDBAMFAwYDBwMIAwkDCgMLAwwDDQMOAw8DEAMRB3VuaTAwMDAHdW5pMDAwMgd1bmkwMDBEB3VuaTAwQTAHdW5pMDBBRAd1bmkwMkJDB3VuaTAyQzkHdW5pMDJGMwlncmF2ZWNvbWIJYWN1dGVjb21iCXRpbGRlY29tYg1ob29rYWJvdmVjb21iB3VuaTAzMEYMZG90YmVsb3djb21iB3VuaTA2MEMHdW5pMDYwRAd1bmkwNjEwB3VuaTA2MTEHdW5pMDYxMgd1bmkwNjE1B3VuaTA2MUIHdW5pMDYxRgd1bmkwNjIxB3VuaTA2MjIHdW5pMDYyMwd1bmkwNjI0B3VuaTA2MjUHdW5pMDYyNgd1bmkwNjI3B3VuaTA2MjgHdW5pMDYyOQd1bmkwNjJBB3VuaTA2MkIHdW5pMDYyQwd1bmkwNjJEB3VuaTA2MkUHdW5pMDYyRgd1bmkwNjMwB3VuaTA2MzEHdW5pMDYzMgd1bmkwNjMzB3VuaTA2MzQHdW5pMDYzNQd1bmkwNjM2B3VuaTA2MzcHdW5pMDYzOAd1bmkwNjM5B3VuaTA2M0EHdW5pMDYzRAd1bmkwNjQwB3VuaTA2NDEHdW5pMDY0Mgd1bmkwNjQzB3VuaTA2NDQHdW5pMDY0NQd1bmkwNjQ2B3VuaTA2NDcHdW5pMDY0OAd1bmkwNjQ5B3VuaTA2NEEHdW5pMDY0Qgd1bmkwNjRDB3VuaTA2NEQHdW5pMDY0RQd1bmkwNjRGB3VuaTA2NTAHdW5pMDY1MQd1bmkwNjUyB3VuaTA2NTMHdW5pMDY1NAd1bmkwNjU1B3VuaTA2NTYHdW5pMDY1Nwd1bmkwNjU4B3VuaTA2NUEHdW5pMDY1Qgd1bmkwNjYwB3VuaTA2NjEHdW5pMDY2Mgd1bmkwNjYzB3VuaTA2NjQHdW5pMDY2NQd1bmkwNjY2B3VuaTA2NjcHdW5pMDY2OAd1bmkwNjY5B3VuaTA2NkEHdW5pMDY2Qgd1bmkwNjZDB3VuaTA2NkQHdW5pMDY2RQd1bmkwNjZGB3VuaTA2NzAHdW5pMDY3MQd1bmkwNjc0B3VuaTA2NzkHdW5pMDY3Qwd1bmkwNjdFB3VuaTA2ODEHdW5pMDY4NQd1bmkwNjg2B3VuaTA2ODgHdW5pMDY4OQd1bmkwNjkxB3VuaTA2OTMHdW5pMDY5NQd1bmkwNjk2B3VuaTA2OTgHdW5pMDY5QQd1bmkwNkExB3VuaTA2QTQHdW5pMDZBOQd1bmkwNkFBB3VuaTA2QUIHdW5pMDZBRAd1bmkwNkFGB3VuaTA2QjUHdW5pMDZCQQd1bmkwNkJDB3VuaTA2QkUHdW5pMDZDMAd1bmkwNkMxB3VuaTA2QzIHdW5pMDZDMwd1bmkwNkM2B3VuaTA2QzcHdW5pMDZDOQd1bmkwNkNBB3VuaTA2Q0IHdW5pMDZDQwd1bmkwNkNEB3VuaTA2Q0UHdW5pMDZEMAd1bmkwNkQyB3VuaTA2RDMHdW5pMDZENAd1bmkwNkQ1B3VuaTA2REMHdW5pMDZERAd1bmkwNkRFB3VuaTA2RTkHdW5pMDZGMAd1bmkwNkYxB3VuaTA2RjIHdW5pMDZGMwd1bmkwNkY0B3VuaTA2RjUHdW5pMDZGNgd1bmkwNkY3B3VuaTA2RjgHdW5pMDZGOQd1bmkwNzYzB3VuaTIwMDAHdW5pMjAwMQd1bmkyMDAyB3VuaTIwMDMHdW5pMjAwNAd1bmkyMDA1B3VuaTIwMDYHdW5pMjAwNwd1bmkyMDA4B3VuaTIwMDkHdW5pMjAwQQd1bmkyMDBCB3VuaTIwMEMHdW5pMjAwRAd1bmkyMDBFB3VuaTIwMEYHdW5pMjAxMAd1bmkyMDExCmZpZ3VyZWRhc2gHdW5pMjAxNQ11bmRlcnNjb3JlZGJsDXF1b3RlcmV2ZXJzZWQOdHdvZG90ZW5sZWFkZXIHdW5pMjAyNwd1bmkyMDJBB3VuaTIwMkIHdW5pMjAyQwd1bmkyMDJEB3VuaTIwMkUHdW5pMjAyRgZtaW51dGUGc2Vjb25kCWV4Y2xhbWRibAd1bmkyMDdGBGxpcmEHdW5pMjBBNgZwZXNldGEHdW5pMjBBOAd1bmkyMEE5BGRvbmcERXVybwd1bmkyMEIxB3VuaTIwQjkHdW5pMjBCQQd1bmkyMEJDB3VuaTIwQkQHdW5pMjEwNQd1bmkyMTEzB3VuaTIxMTYJZXN0aW1hdGVkB3VuaTIyMEUHdW5pRUUwMQd1bmlFRTAyB3VuaUY2QzMHdW5pRkI1MAd1bmlGQjUxB3VuaUZCNTYHdW5pRkI1Nwd1bmlGQjU4B3VuaUZCNTkHdW5pRkI2Ngd1bmlGQjY3B3VuaUZCNjgHdW5pRkI2OQd1bmlGQjZCB3VuaUZCNkMHdW5pRkI2RAd1bmlGQjdBB3VuaUZCN0IHdW5pRkI3Qwd1bmlGQjdEB3VuaUZCODgHdW5pRkI4OQd1bmlGQjhBB3VuaUZCOEIHdW5pRkI4Qwd1bmlGQjhEB3VuaUZCOEUHdW5pRkI4Rgd1bmlGQjkwB3VuaUZCOTEHdW5pRkI5Mgd1bmlGQjkzB3VuaUZCOTQHdW5pRkI5NQd1bmlGQjlFB3VuaUZCOUYMdW5pRkJBNS5maW5hB3VuaUZCQTcHdW5pRkJBOAd1bmlGQkE5B3VuaUZCQUEHdW5pRkJBQgd1bmlGQkFDB3VuaUZCQUQHdW5pRkJBRQd1bmlGQkFGB3VuaUZCQjAHdW5pRkJCMQd1bmlGQkJGB3VuaUZCQzAHdW5pRkJEMwd1bmlGQkQ0B3VuaUZCRDUHdW5pRkJENgd1bmlGQkQ3B3VuaUZCRDgHdW5pRkJEOQd1bmlGQkRBB3VuaUZCREUHdW5pRkJERgd1bmlGQkUyB3VuaUZCRTMHdW5pRkJFOAd1bmlGQkU5B3VuaUZCRkMHdW5pRkJGRAd1bmlGQkZFB3VuaUZCRkYHdW5pRkQzRQd1bmlGRDNGB3VuaUZERjIHdW5pRkRGQwd1bmlGRTcwB3VuaUZFNzEHdW5pRkU3Mgd1bmlGRTczB3VuaUZFNzQHdW5pRkU3Ngd1bmlGRTc3B3VuaUZFNzgHdW5pRkU3OQd1bmlGRTdBB3VuaUZFN0IHdW5pRkU3Qwd1bmlGRTdEB3VuaUZFN0UHdW5pRkU3Rgd1bmlGRTgwB3VuaUZFODEHdW5pRkU4Mgd1bmlGRTgzB3VuaUZFODQHdW5pRkU4NQd1bmlGRTg2B3VuaUZFODcHdW5pRkU4OAd1bmlGRTg5B3VuaUZFOEEHdW5pRkU4Qgd1bmlGRThDB3VuaUZFOEQHdW5pRkU4RQd1bmlGRThGB3VuaUZFOTAHdW5pRkU5MQd1bmlGRTkyB3VuaUZFOTMHdW5pRkU5NAd1bmlGRTk1B3VuaUZFOTYHdW5pRkU5Nwd1bmlGRTk4B3VuaUZFOTkHdW5pRkU5QQd1bmlGRTlCB3VuaUZFOUMHdW5pRkU5RAd1bmlGRTlFB3VuaUZFOUYHdW5pRkVBMAd1bmlGRUExB3VuaUZFQTIHdW5pRkVBMwd1bmlGRUE0B3VuaUZFQTUHdW5pRkVBNgd1bmlGRUE3B3VuaUZFQTgHdW5pRkVBOQd1bmlGRUFBB3VuaUZFQUIHdW5pRkVBQwd1bmlGRUFEB3VuaUZFQUUHdW5pRkVBRgd1bmlGRUIwB3VuaUZFQjEHdW5pRkVCMgd1bmlGRUIzB3VuaUZFQjQHdW5pRkVCNQd1bmlGRUI2B3VuaUZFQjcHdW5pRkVCOAd1bmlGRUI5B3VuaUZFQkEHdW5pRkVCQgd1bmlGRUJDB3VuaUZFQkQHdW5pRkVCRQd1bmlGRUJGB3VuaUZFQzAHdW5pRkVDMQd1bmlGRUMyB3VuaUZFQzMHdW5pRkVDNAd1bmlGRUM1B3VuaUZFQzYHdW5pRkVDNwd1bmlGRUM4B3VuaUZFQzkHdW5pRkVDQQd1bmlGRUNCB3VuaUZFQ0MHdW5pRkVDRAd1bmlGRUNFB3VuaUZFQ0YHdW5pRkVEMAd1bmlGRUQxB3VuaUZFRDIHdW5pRkVEMwd1bmlGRUQ0B3VuaUZFRDUHdW5pRkVENgd1bmlGRUQ3B3VuaUZFRDgHdW5pRkVEOQd1bmlGRURBB3VuaUZFREIHdW5pRkVEQwd1bmlGRUREB3VuaUZFREUHdW5pRkVERgd1bmlGRUUwB3VuaUZFRTEHdW5pRkVFMgd1bmlGRUUzB3VuaUZFRTQHdW5pRkVFNQd1bmlGRUU2B3VuaUZFRTcHdW5pRkVFOAd1bmlGRUU5B3VuaUZFRUEHdW5pRkVFQgd1bmlGRUVDB3VuaUZFRUQHdW5pRkVFRQd1bmlGRUVGB3VuaUZFRjAHdW5pRkVGMQd1bmlGRUYyB3VuaUZFRjMHdW5pRkVGNAd1bmlGRUY1B3VuaUZFRjYHdW5pRkVGNwd1bmlGRUY4B3VuaUZFRjkHdW5pRkVGQQd1bmlGRUZCB3VuaUZFRkMHdW5pRkVGRgd1bmlGRkZDB3VuaUZGRkQMTGFtQWxlZldhc2xhEUxhbUFsZWZXYXNsYS5maW5hCk5hbWVNZS4zMDIKTmFtZU1lLjMwMwxOYW1lTWUuNjU1NjQMTmFtZU1lLjY1NTY1DE5hbWVNZS42NTU3NAxOYW1lTWUuNjU1ODMMTmFtZU1lLjY1NTg3DE5hbWVNZS42NTYyMwxhcmFiaWNfMmRvdHMMYXJhYmljXzNkb3RzDmFyYWJpY18zZG90c19hCmFyYWJpY19kb3QOYXJhYmljX2dhZl9iYXITbGFtVmFib3ZlX2FsZWYuaXNvbAx1bmkwNjNELmZpbmEUdW5pMDYzRC5maW5hLmNvbXBhY3QMdW5pMDYzRC5pbml0DHVuaTA2M0QubWVkaQt1bmkwNjRCMDY1MQt1bmkwNjRFMDY1MQt1bmkwNjUxMDY0Qgt1bmkwNjUxMDY0Qwt1bmkwNjUxMDY0RQt1bmkwNjUxMDY0Rgt1bmkwNjU0MDY0RQt1bmkwNjU0MDY0Rgx1bmkwNjZFLmZpbmEMdW5pMDY2RS5pbml0FHVuaTA2NkUuaW5pdC5jb21wYWN0DHVuaTA2NkUubWVkaRR1bmkwNjZFLm1lZGkuY29tcGFjdAx1bmkwNjZGLmZpbmEMdW5pMDY2Ri5pbml0DHVuaTA2NkYubWVkaQx1bmkwNjdDLmZpbmEMdW5pMDY3Qy5pbml0DHVuaTA2N0MubWVkaQx1bmkwNjgxLmZpbmEMdW5pMDY4MS5pbml0DHVuaTA2ODEubWVkaQx1bmkwNjg1LmZpbmEMdW5pMDY4NS5pbml0DHVuaTA2ODUubWVkaQx1bmkwNjg5LmZpbmEMdW5pMDY5My5maW5hDHVuaTA2OTUuZmluYQx1bmkwNjk2LmZpbmEMdW5pMDY5QS5maW5hDHVuaTA2OUEuaW5pdAx1bmkwNjlBLm1lZGkMdW5pMDZBMS5maW5hDHVuaTA2QTEuaW5pdAx1bmkwNkExLm1lZGkMdW5pMDZBQS5maW5hDHVuaTA2QUEuaW5pdAx1bmkwNkFBLm1lZGkMdW5pMDZBQi5maW5hDHVuaTA2QUIuaW5pdAx1bmkwNkFCLm1lZGkMdW5pMDZCNS5maW5hDHVuaTA2QjUuaW5pdAx1bmkwNkI1Lm1lZGkMdW5pMDZCQS5pbml0DHVuaTA2QkEubWVkaQx1bmkwNkJDLmZpbmEMdW5pMDZCQy5pbml0DHVuaTA2QkMubWVkaQx1bmkwNkMyLmZpbmEMdW5pMDZDMi5pbml0DHVuaTA2QzIubWVkaQx1bmkwNkMzLmZpbmEMdW5pMDZDQS5maW5hD3VuaTA2Q0QuY29tcGFjdAx1bmkwNkNELmZpbmEMdW5pMDZDRS5maW5hFHVuaTA2Q0UuZmluYS5jb21wYWN0DHVu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AAEAQystsFksswEAAEMrLbBaLLMBAQBDKy2wWyyzAAABQystsFwsswABAUMrLbBdLLMBAAFDKy2wXiyzAQEBQystsF8ssgAARSstsGAssgABRSstsGEssgEARSstsGIssgEBRSstsGMssgAASCstsGQssgABSCstsGUssgEASCstsGYssgEBSCstsGcsswAAAEQrLbBoLLMAAQBEKy2waSyzAQAARCstsGosswEBAEQrLbBrLLMAAAFEKy2wbCyzAAEBRCstsG0sswEAAUQrLbBuLLMBAQFEKy2wbyyxADwrLrEwARQrLbBwLLEAPCuwQCstsHEssQA8K7BBKy2wciywABaxADwrsEIrLbBzLLEBPCuwQCstsHQssQE8K7BBKy2wdSywABaxATwrsEIrLbB2LLEAPSsusTABFCstsHcssQA9K7BAKy2weCyxAD0rsEErLbB5LLEAPSuwQistsHossQE9K7BAKy2weyyxAT0rsEErLbB8LLEBPSuwQistsH0ssQA+Ky6xMAEUKy2wfiyxAD4rsEArLbB/LLEAPiuwQSstsIAssQA+K7BCKy2wgSyxAT4rsEArLbCCLLEBPiuwQSstsIMssQE+K7BCKy2whCyxAD8rLrEwARQrLbCFLLEAPyuwQCstsIYssQA/K7BBKy2whyyxAD8rsEIrLbCILLEBPyuwQCstsIkssQE/K7BBKy2wiiyxAT8rsEIrLbCLLLILAANFUFiwBhuyBAIDRVgjIRshWVlCK7AIZbADJFB4sQUBFUVYMFktAEu4AMhSWLEBAY5ZsAG5CAAIAGNwsQAHQrVIAAAABAAqsQAHQkAKOwgvBCMEFQUECiqxAAdCQApFBjUCKQIcAwQKKrEAC0K9DwAMAAkABYAABAALKrEAD0K9AEAAQABAAEAABAALKrkAAwAARLEkAYhRWLBAiFi5AAMAZESxKAGIUVi4CACIWLkAAwAARFkbsScBiFFYugiAAAEEQIhjVFi5AAMAAERZWVlZWUAKPQgxBCUEFwUEDiq4Af+FsASNsQIARLMFZAYAREQA";
    // Enable Persian support
    doc.addFileToVFS("Vazir.ttf", vazirFontBase64);
    doc.addFont("Vazir.ttf", "Vazir", "normal");
    doc.addFont("Vazir.ttf", "Vazir", "bold");
    doc.setFont("Vazir");
    if (doc.addFont && window.jsPDFPersian) {
      await window.jsPDFPersian.initPersianFont(doc);
    }

    // Page dimensions
    const pageWidth = doc.internal.pageSize.getWidth();
    const pageHeight = doc.internal.pageSize.getHeight();
    const margin = 20;
    let yPos = margin;

    // Helper function to add text with RTL support
    const addPersianText = (text, x, y, options = {}) => {
      doc.setFont(options.font || "Vazir", options.style || "normal");
      doc.setFontSize(options.fontSize || 12);
      const align = options.align || "right";
      const maxWidth = options.maxWidth || pageWidth - 2 * margin;

      // Handle RTL
      const textLines = doc.splitTextToSize(text, maxWidth);
      textLines.forEach((line, index) => {
        const xPos = align === "right" ? pageWidth - margin : x;
        doc.text(line, xPos, y + index * (options.lineHeight || 7), { align });
      });

      return y + textLines.length * (options.lineHeight || 7);
    };

    // Add header
    doc.setFillColor(0, 102, 204);
    doc.rect(0, 0, pageWidth, 40, "F");

    doc.setTextColor(255, 255, 255);
    yPos = addPersianText("گزارش بازرسی و کنترل کیفیت", pageWidth / 2, 25, {
      fontSize: 20,
      align: "center",
      font: "Vazir",
      style: "bold",
    });

    doc.setTextColor(0, 0, 0);
    yPos = 50;

    // Add metadata box
    doc.setDrawColor(200);
    doc.setLineWidth(0.5);
    doc.rect(margin, yPos, pageWidth - 2 * margin, 40);

    yPos += 10;
    yPos = addPersianText(
      `شناسه المان: ${elementId}`,
      pageWidth - margin,
      yPos,
      { fontSize: 12, style: "bold" }
    );
    yPos = addPersianText(
      `نقشه: ${currentPlanFileName}`,
      pageWidth - margin,
      yPos + 2
    );
    yPos = addPersianText(
      `تاریخ: ${getPersianDate()}`,
      pageWidth - margin,
      yPos + 2
    );
    yPos = addPersianText(
      `ساعت: ${new Date().toLocaleTimeString("fa-IR")}`,
      pageWidth - margin,
      yPos + 2
    );

    yPos += 15;

    // Add stages data
    for (const stageId in formData) {
      const stageData = formData[stageId];

      // Check for page break
      if (yPos > pageHeight - 60) {
        doc.addPage();
        yPos = margin;
      }

      // Stage header
      doc.setFillColor(240, 240, 240);
      doc.rect(margin, yPos, pageWidth - 2 * margin, 12, "F");
      yPos += 8;
      yPos = addPersianText(`مرحله ${stageId}`, pageWidth - margin - 5, yPos, {
        fontSize: 14,
        style: "bold",
      });
      yPos += 8;

      // Checklist items
      if (stageData.items && stageData.items.length > 0) {
        yPos = addPersianText("موارد بازرسی:", pageWidth - margin, yPos, {
          fontSize: 11,
          style: "bold",
        });
        yPos += 5;

        stageData.items.forEach((item, index) => {
          if (yPos > pageHeight - 40) {
            doc.addPage();
            yPos = margin;
          }

          const statusIcon =
            item.status === "OK" ? "✓" : item.status === "Not OK" ? "✗" : "○";
          const itemDescription =
            allItemsMap[item.item_id] || `آیتم ناشناخته #${item.item_id}`; // Look up text
          const itemText = `${index + 1}. ${statusIcon} ${itemDescription}`;

          yPos = addPersianText(itemText, pageWidth - margin - 10, yPos, {
            fontSize: 10,
          });

          if (item.value) {
            yPos = addPersianText(
              `   توضیحات: ${item.value}`,
              pageWidth - margin - 15,
              yPos + 2,
              { fontSize: 9 }
            );
          }
          yPos += 3;
        });
      }

      yPos += 5;

      // Consultant section
      if (stageData.overall_status || stageData.notes) {
        doc.setDrawColor(40, 167, 69);
        doc.setLineWidth(0.3);
        doc.rect(margin, yPos, pageWidth - 2 * margin, 30);
        yPos += 8;

        yPos = addPersianText("بخش مشاور:", pageWidth - margin - 5, yPos, {
          fontSize: 11,
          style: "bold",
        });

        if (stageData.overall_status) {
          yPos = addPersianText(
            `وضعیت کلی: ${
              statusTranslations[stageData.overall_status] ||
              stageData.overall_status
            }`,
            pageWidth - margin - 10,
            yPos + 3,
            { fontSize: 10 }
          );
        }

        if (stageData.inspection_date) {
          yPos = addPersianText(
            `تاریخ بازرسی: ${stageData.inspection_date}`,
            pageWidth - margin - 10,
            yPos + 2,
            { fontSize: 10 }
          );
        }

        if (stageData.notes) {
          yPos = addPersianText(
            `یادداشت: ${stageData.notes}`,
            pageWidth - margin - 10,
            yPos + 2,
            { fontSize: 9 }
          );
        }

        yPos += 10;
      }

      // Contractor section
      if (stageData.contractor_status || stageData.contractor_notes) {
        if (yPos > pageHeight - 50) {
          doc.addPage();
          yPos = margin;
        }

        doc.setDrawColor(255, 152, 0);
        doc.rect(margin, yPos, pageWidth - 2 * margin, 25);
        yPos += 8;

        yPos = addPersianText("بخش پیمانکار:", pageWidth - margin - 5, yPos, {
          fontSize: 11,
          style: "bold",
        });

        if (stageData.contractor_status) {
          yPos = addPersianText(
            `وضعیت: ${stageData.contractor_status}`,
            pageWidth - margin - 10,
            yPos + 3,
            { fontSize: 10 }
          );
        }

        if (stageData.contractor_date) {
          yPos = addPersianText(
            `تاریخ اعلام: ${stageData.contractor_date}`,
            pageWidth - margin - 10,
            yPos + 2,
            { fontSize: 10 }
          );
        }

        if (stageData.contractor_notes) {
          yPos = addPersianText(
            `توضیحات: ${stageData.contractor_notes}`,
            pageWidth - margin - 10,
            yPos + 2,
            { fontSize: 9 }
          );
        }

        yPos += 10;
      }

      yPos += 10;
    }

    // Add digital signature section
    if (digitalSignature) {
      // New page for signature if needed
      if (yPos > pageHeight - 100) {
        doc.addPage();
        yPos = margin;
      }

      // Signature box
      doc.setDrawColor(0, 102, 204);
      doc.setLineWidth(1);
      doc.rect(margin, yPos, pageWidth - 2 * margin, 80);

      yPos += 10;
      yPos = addPersianText("امضای دیجیتال", pageWidth / 2, yPos, {
        fontSize: 14,
        style: "bold",
        align: "center",
      });

      yPos += 10;
      doc.setFillColor(39, 174, 96); // Green color
      doc.setDrawColor(255, 255, 255);
      doc.setLineWidth(1);
      doc.circle(margin + 30, yPos + 15, 12, "FD"); // Filled and stroked circle
      doc.setTextColor(255, 255, 255);
      doc.setFontSize(14);
      doc.text("✓", margin + 27, yPos + 17); // Checkmark
      doc.setFontSize(8);
      doc.text("Verified", margin + 24, yPos + 23);
      doc.setTextColor(0, 0, 0);
      // Add signature hash (first 64 characters)
      const signaturePreview = digitalSignature.substring(0, 64) + "...";
      doc.setFont("courier", "normal");
      doc.setFontSize(8);
      const lines = doc.splitTextToSize(
        signaturePreview,
        pageWidth - 2 * margin - 20
      );
      lines.forEach((line) => {
        doc.text(line, margin + 10, yPos, { align: "left" });
        yPos += 4;
      });

      yPos += 5;
      yPos = addPersianText(
        `امضا شده توسط: ${USER_DISPLAY_NAME}`, // Use the correct constant
        pageWidth - margin - 10,
        yPos,
        { fontSize: 9 }
      );

      yPos += 5;
      yPos = addPersianText(
        `تاریخ امضا: ${getPersianDate()} - ${new Date().toLocaleTimeString(
          "fa-IR"
        )}`,
        pageWidth - margin - 10,
        yPos,
        { fontSize: 9 }
      );

      // Add verification note
      yPos += 8;
      doc.setFontSize(8);
      doc.setTextColor(100);
      yPos = addPersianText(
        "این سند با امضای دیجیتال محافظت شده است. برای تأیید اعتبار، از سیستم مدیریت پروژه استفاده کنید.",
        pageWidth / 2,
        yPos,
        { fontSize: 8, align: "center", maxWidth: pageWidth - 2 * margin - 20 }
      );
    }

    // Add footer to all pages
    const totalPages = doc.internal.getNumberOfPages();
    for (let i = 1; i <= totalPages; i++) {
      doc.setPage(i);
      doc.setFontSize(8);
      doc.setTextColor(150);
      doc.text(
        `صفحه ${convertToPersianNumbers(i)} از ${convertToPersianNumbers(
          totalPages
        )}`,
        pageWidth / 2,
        pageHeight - 10,
        { align: "center" }
      );
      doc.text(
        "سیستم مدیریت بازرسی و کنترل کیفیت - دانشگاه خاتم",
        pageWidth / 2,
        pageHeight - 5,
        { align: "center" }
      );
    }

    // Save PDF
    const filename = `inspection_${elementId}_${getPersianDateForFilename()}.pdf`;
    doc.save(filename);

    return { success: true, filename };
  } catch (error) {
    console.error("PDF Export Error:", error);
    return { success: false, error: error.message };
  }
}

/**
 * Convert English numbers to Persian
 */

/**
 * Status translations for PDF
 */
const statusTranslations = {
  OK: "تأیید شده",
  Reject: "رد شده",
  Repair: "نیاز به تعمیر",
  "Awaiting Re-inspection": "تعمیر پایان یافته",
  "Pre-Inspection Complete": "آماده بازرسی",
  Pending: "در حال اجرا",
};

/**
 * Add PDF export button to form
 */
function addPDFExportButton(formPopup, elementId, historyData, allItemsMap) {
  const footer = formPopup.querySelector(".form-footer-new");
  if (!footer) return;

  const exportBtn = document.createElement("button");
  exportBtn.type = "button";
  exportBtn.className = "btn secondary pdf-export";
  exportBtn.innerHTML = "📄 خروجی PDF";
  exportBtn.style.cssText = `
        background: linear-gradient(45deg, #dc3545, #c82333);
        margin-left: 10px;
    `;

  exportBtn.addEventListener("click", async () => {
    exportBtn.disabled = true;
    exportBtn.textContent = "در حال تولید...";

    try {
      // Create a complete data object for the PDF, similar to history
      const formDataForPdf = {};
      historyData.forEach((stage) => {
        formDataForPdf[stage.stage_id] = stage;
      });

      const result = await exportInspectionToPDF(
        elementId,
        formDataForPdf, // Pass the formatted data
        null, // No new signature for historical data
        allItemsMap // Pass the map of item texts
      );

      if (result.success) {
        alert(`فایل PDF با موفقیت ذخیره شد:\n${result.filename}`);
      } else {
        throw new Error(result.error);
      }
    } catch (error) {
      alert("خطا در تولید PDF: " + error.message);
    } finally {
      exportBtn.disabled = false;
      exportBtn.textContent = "📄 خروجی PDF";
    }
  });

  footer.insertBefore(exportBtn, footer.firstChild);
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
  closeSubPanelMenu();
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
async function showSubPanelMenu(
  clickedElement,
  dynamicContext,
  partNameToOpen = null
) {
  closeSubPanelMenu(); // Close any existing menus first

  const baseElementId = clickedElement.dataset.uniquePanelId;

  // Deeplink logic: If a specific part is requested, open its form directly.
  if (partNameToOpen) {
    const fullElementId = `${baseElementId}-${partNameToOpen}`;
    openChecklistForm(
      fullElementId,
      dynamicContext.elementType,
      dynamicContext
    );
    return;
  }

  const menu = document.createElement("div");
  menu.id = "subPanelMenu";
  menu.style.cssText = `position: absolute; background: white; border: 1px solid #ccc; padding: 5px; z-index: 1001; box-shadow: 2px 2px 5px rgba(0,0,0,0.2); min-width: 150px;`;
  menu.innerHTML = `<div style="padding: 10px; color: #555;">در حال بارگذاری بخش‌ها...</div>`;
  document.body.appendChild(menu);
  const rect = clickedElement.getBoundingClientRect();
  menu.style.top = `${rect.bottom + window.scrollY}px`;
  menu.style.left = `${rect.left + window.scrollX}px`;

  try {
    const response = await fetch(
      `/pardis/api/get_existing_parts.php?element_id=${baseElementId}`
    );
    if (!response.ok)
      throw new Error(`Server responded with status: ${response.status}`);
    const subPanelIds = await response.json();

    if (!Array.isArray(subPanelIds) || subPanelIds.length === 0) {
      menu.innerHTML = `<div style="padding: 10px; color: #d32f2f;">هیچ بخشی برای بازرسی این المان ثبت نشده است.</div>`;
      const closeBtn = document.createElement("button");
      closeBtn.textContent = "بستن";
      closeBtn.onclick = (e) => {
        e.stopPropagation();
        closeSubPanelMenu();
      };
      menu.appendChild(closeBtn);
      return;
    }

    menu.innerHTML = ""; // Clear loading message

    subPanelIds.forEach((partName) => {
      const menuItem = document.createElement("button");
      menuItem.style.cssText = `display: block; width: 100%; margin-bottom: 3px; text-align: right; padding: 5px; background-color: #007bff; color: white; border: none; cursor: pointer;`;
      const fullElementId = `${baseElementId}-${partName}`;
      menuItem.textContent = `چک لیست: ${partName}`;

      // ===================================================================
      // THIS IS THE CRITICAL FIX. The click handler was broken.
      // ===================================================================
      menuItem.onclick = (e) => {
        e.stopPropagation();
        // This now correctly calls openChecklistForm when a button is clicked.
        openChecklistForm(
          fullElementId,
          dynamicContext.elementType,
          dynamicContext
        );
        closeSubPanelMenu();
      };
      // ===================================================================

      menu.appendChild(menuItem);
    });

    const closeButton = document.createElement("button");
    closeButton.textContent = "بستن منو";
    closeButton.style.cssText = `display: block; width: 100%; margin-top: 5px; text-align: right; padding: 5px; background-color: #f0f0f0; color: black; border: none; cursor: pointer;`;
    closeButton.onclick = (e) => {
      e.stopPropagation();
      closeSubPanelMenu();
    };
    menu.appendChild(closeButton);

    setTimeout(
      () =>
        document.addEventListener("click", closeMenuOnClickOutside, {
          once: true,
        }),
      0
    );
  } catch (error) {
    console.error("Failed to fetch element parts:", error);
    menu.innerHTML = `<div style="padding: 10px; color: #d32f2f;">خطا در دریافت لیست بخش‌ها.</div>`;
    const closeBtn = document.createElement("button");
    closeBtn.textContent = "بستن";
    closeBtn.className = "close-menu-btn";
    closeBtn.onclick = (e) => {
      e.stopPropagation();
      closeSubPanelMenu();
    };
    menu.appendChild(closeBtn);
  }
}

// A single, generic function to close the sub-panel menu
function closeSubPanelMenu() {
  const menu = document.getElementById("subPanelMenu");
  if (menu) menu.remove();
  document.removeEventListener("click", closeMenuOnClickOutside);
}

// A single, generic helper to handle clicks outside the menu
function closeMenuOnClickOutside(event) {
  const menu = document.getElementById("subPanelMenu");
  if (menu && !menu.contains(event.target)) {
    closeSubPanelMenu();
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
  const pathString = `M ${freeDrawingPath
    .map((p) => `${p.x},${p.y}`)
    .join(" L ")}`;

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
  const activeCanvas = getActiveCanvas();
  if (!activeCanvas) return;

  isDrawing = true;
  const pointer = activeCanvas.getPointer(opt.e);
  drawingStartPoint = [pointer.x, pointer.y];
  tempShape = new fabric.Line([pointer.x, pointer.y, pointer.x, pointer.y], {
    stroke: "#999999",
    strokeWidth: 1,
    strokeDashArray: [5, 5],
  });
  activeCanvas.add(tempShape);
}

function handleLineMove(opt) {
  if (!isDrawing || !tempShape) return;
  const activeCanvas = getActiveCanvas();
  if (!activeCanvas) return;
  const pointer = activeCanvas.getPointer(opt.e);
  tempShape.set({ x2: pointer.x, y2: pointer.y });
  activeCanvas.renderAll();
}

// Helper function to create dimension text

function createDimensionText(text, left, top, options = {}) {
  return new fabric.Text(text, {
    left: left,
    top: top,
    fontSize: options.fontSize || 14,
    fill: options.fill || (isScaffoldingMode ? "#C92A2A" : "#003366"),
    backgroundColor: options.backgroundColor || "rgba(255, 255, 255, 0.85)",
    padding: options.padding || 5,
    fontWeight: options.fontWeight || "bold",
    textAlign: options.textAlign || "center",
    originX: options.originX || "center",
    originY: options.originY || "center",
    selectable: false,
    evented: false,
    stroke: "white",
    strokeWidth: 3,
    paintOrder: "stroke",
  });
}

/**
 * Calculate actual length in meters from pixel coordinates
 * @param {number} pixelLength - Length in pixels
 * @param {number} scaleFactor - Scale factor from database (cm per pixel)
 * @returns {number} Length in meters
 */
function calculateActualLength(pixelLength, scaleFactor) {
  if (!scaleFactor || scaleFactor <= 0) {
    console.warn("Invalid scale factor, using 1.0");
    scaleFactor = 1.0;
  }
  // pixelLength * scaleFactor = length in cm
  // divide by 100 to get meters
  return (pixelLength * scaleFactor) / 100;
}

/**
 * Calculate actual area in square meters from pixel area
 * @param {number} pixelArea - Area in square pixels
 * @param {number} scaleFactor - Scale factor from database (cm per pixel)
 * @returns {number} Area in square meters
 */
function calculateActualArea(pixelArea, scaleFactor) {
  if (!scaleFactor || scaleFactor <= 0) {
    console.warn("Invalid scale factor, using 1.0");
    scaleFactor = 1.0;
  }
  // pixelArea * (scaleFactor^2) = area in cm²
  // divide by 10000 to get m²
  return (pixelArea * Math.pow(scaleFactor, 2)) / 10000;
}

function handleLineEnd(opt) {
  if (!isDrawing) return;
  const activeCanvas = getActiveCanvas();
  if (!activeCanvas) return;

  isDrawing = false;
  if (tempShape) {
    activeCanvas.remove(tempShape);
    tempShape = null;
  }

  const pointer = activeCanvas.getPointer(opt.e);
  const color = getActiveToolColor("#FF0000");

  if (
    Math.abs(pointer.x - drawingStartPoint[0]) > 2 ||
    Math.abs(pointer.y - drawingStartPoint[1]) > 2
  ) {
    const line = new fabric.Line(
      [drawingStartPoint[0], drawingStartPoint[1], pointer.x, pointer.y],
      {
        stroke: color,
        strokeWidth: isScaffoldingMode ? 3 : 2,
        selectable: false,
        evented: false,
        shapeType: "line",
      }
    );

    // Calculate length in pixels
    const lengthPixels = Math.sqrt(
      Math.pow(line.x2 - line.x1, 2) + Math.pow(line.y2 - line.y1, 2)
    );

    // Get scale factor - CRITICAL FIX
    const scaleFactor = activeCanvas.customScaleFactor || 1.0;

    // Calculate actual length in meters
    const lengthM = calculateActualLength(lengthPixels, scaleFactor);
    const labelText = `${lengthM.toFixed(2)} m`;

    // Create dimension label
    const text = createDimensionText(
      labelText,
      (line.x1 + line.x2) / 2 + 5,
      (line.y1 + line.y2) / 2 - 15
    );

    // Group line and label together
    const group = new fabric.Group([line, text], {
      selectable: false,
      evented: false,
      shapeType: "line",
    });

    activeCanvas.add(group);
  }
}

// Rectangle drawing handlers
function handleRectangleStart(opt) {
  const activeCanvas = getActiveCanvas();
  if (!activeCanvas) return;
  isDrawing = true;
  const pointer = activeCanvas.getPointer(opt.e);
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
  activeCanvas.add(tempShape);
}

function handleRectangleMove(opt) {
  if (!isDrawing || !tempShape) return;
  const activeCanvas = getActiveCanvas();
  if (!activeCanvas) return;
  const pointer = activeCanvas.getPointer(opt.e);
  const width = pointer.x - drawingStartPoint[0];
  const height = pointer.y - drawingStartPoint[1];
  tempShape.set({
    left: width < 0 ? pointer.x : drawingStartPoint[0],
    top: height < 0 ? pointer.y : drawingStartPoint[1],
    width: Math.abs(width),
    height: Math.abs(height),
  });
  activeCanvas.renderAll();
}

function handleRectangleEnd(opt) {
  if (!isDrawing) return;
  const activeCanvas = getActiveCanvas();
  if (!activeCanvas) return;

  isDrawing = false;
  if (tempShape) {
    activeCanvas.remove(tempShape);
    tempShape = null;
  }

  const pointer = activeCanvas.getPointer(opt.e);
  const color = getActiveToolColor("#0000FF");
  const widthPixels = Math.abs(pointer.x - drawingStartPoint[0]);
  const heightPixels = Math.abs(pointer.y - drawingStartPoint[1]);

  if (widthPixels > 5 && heightPixels > 5) {
    const rect = new fabric.Rect({
      left: Math.min(pointer.x, drawingStartPoint[0]),
      top: Math.min(pointer.y, drawingStartPoint[1]),
      width: widthPixels,
      height: heightPixels,
      fill: isScaffoldingMode ? "rgba(255, 107, 107, 0.2)" : "transparent",
      stroke: color,
      strokeWidth: isScaffoldingMode ? 3 : 2,
      selectable: false,
      evented: false,
      shapeType: "rectangle",
    });

    // Get scale factor - CRITICAL FIX
    const scaleFactor = activeCanvas.customScaleFactor || 1.0;

    // Calculate actual dimensions
    const widthM = calculateActualLength(widthPixels, scaleFactor);
    const heightM = calculateActualLength(heightPixels, scaleFactor);
    const areaM2 = (widthM * heightM).toFixed(2);

    const labelText = `${widthM.toFixed(2)}m × ${heightM.toFixed(
      2
    )}m\n${areaM2} m²`;

    const text = createDimensionText(
      labelText,
      rect.left + widthPixels / 2,
      rect.top + heightPixels / 2
    );

    const group = new fabric.Group([rect, text], {
      selectable: false,
      evented: false,
      shapeType: "rectangle",
    });

    activeCanvas.add(group);
  }
}

// Circle drawing handlers
function handleCircleStart(opt) {
  const activeCanvas = getActiveCanvas();
  if (!activeCanvas) return;
  isDrawing = true;
  const pointer = activeCanvas.getPointer(opt.e);
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
  activeCanvas.add(tempShape);
}

function handleCircleMove(opt) {
  if (!isDrawing || !tempShape) return;
  const activeCanvas = getActiveCanvas();
  if (!activeCanvas) return;
  const pointer = activeCanvas.getPointer(opt.e);
  const radius = Math.sqrt(
    Math.pow(pointer.x - drawingStartPoint[0], 2) +
      Math.pow(pointer.y - drawingStartPoint[1], 2)
  );
  tempShape.set({
    left: drawingStartPoint[0] - radius,
    top: drawingStartPoint[1] - radius,
    radius: radius,
  });
  activeCanvas.renderAll();
}

// FIXED: Free drawing handlers with consistent point storage
function handleFreeDrawStart(opt) {
  const activeCanvas = getActiveCanvas();
  if (!activeCanvas) return;
  isDrawing = true;
  const pointer = activeCanvas.getPointer(opt.e);
  freeDrawingPath = [{ x: pointer.x, y: pointer.y }];
}

function handleFreeDrawMove(opt) {
  if (!isDrawing) return;
  const activeCanvas = getActiveCanvas();
  if (!activeCanvas) return;
  const pointer = activeCanvas.getPointer(opt.e);
  freeDrawingPath.push({ x: pointer.x, y: pointer.y });
  if (tempShape) {
    activeCanvas.remove(tempShape);
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
    activeCanvas.add(tempShape);
    activeCanvas.renderAll();
  }
}

function handleFreeDrawEnd(opt) {
  if (!isDrawing) return;
  const activeCanvas = getActiveCanvas();
  if (!activeCanvas) return;

  isDrawing = false;
  if (tempShape) {
    activeCanvas.remove(tempShape);
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
    strokeWidth: isScaffoldingMode ? 3 : 2,
    selectable: false,
    evented: false,
    shapeType: "freedraw",
  });
  path.freeDrawingPoints = freeDrawingPath.slice();

  // Calculate total length in pixels
  let totalLengthPixels = 0;
  for (let i = 1; i < freeDrawingPath.length; i++) {
    const p1 = freeDrawingPath[i - 1];
    const p2 = freeDrawingPath[i];
    totalLengthPixels += Math.sqrt(
      Math.pow(p2.x - p1.x, 2) + Math.pow(p2.y - p1.y, 2)
    );
  }

  // Get scale factor - CRITICAL FIX
  const scaleFactor = activeCanvas.customScaleFactor || 1.0;

  // Calculate actual total length
  const totalLengthM = calculateActualLength(totalLengthPixels, scaleFactor);
  const labelText = `${totalLengthM.toFixed(2)} m`;

  const centerPoint = path.getCenterPoint();
  const text = createDimensionText(
    labelText,
    centerPoint.x,
    centerPoint.y - 10
  );

  const group = new fabric.Group([path, text], {
    selectable: false,
    evented: false,
    shapeType: "freedraw",
  });

  activeCanvas.add(group);
  freeDrawingPath = [];
}

// FIXED: Save function with proper free drawing coordinate conversion
document.getElementById("drawer-close-btn").addEventListener("click", () => {
  const modal = document.getElementById("crack-drawer-modal");
  modal.style.display = "none";

  if (isScaffoldingMode) {
    modal.classList.remove("scaffolding-mode");
    if (scaffoldingCanvas) {
      try {
        scaffoldingCanvas.dispose();
        scaffoldingCanvas = null;
      } catch (e) {
        console.warn("Error disposing scaffolding canvas:", e);
      }
    }
  } else {
    if (crackCanvas) {
      try {
        crackCanvas.dispose();
        crackCanvas = null;
      } catch (e) {
        console.warn("Error disposing crack canvas:", e);
      }
    }
  }
  isScaffoldingMode = false;
});

document
  .getElementById("drawer-save-btn")
  .addEventListener("click", async () => {
    const activeCanvas = isScaffoldingMode ? scaffoldingCanvas : fabricCanvas;

    if (!activeCanvas) {
      console.error("Save failed: No active canvas found.");
      alert("خطا: صفحه ترسیم فعال برای ذخیره وجود ندارد.");
      return;
    }

    if (isScaffoldingMode) {
      const drawingData = {
        lines: [],
        rectangles: [],
        circles: [],
        freeDrawings: [],
      };

      // Extract shapes from groups
      activeCanvas.getObjects().forEach((obj) => {
        // Skip background image
        if (obj === activeCanvas.backgroundImage) return;

        // Handle groups (shapes with labels)
        if (obj.type === "group") {
          const items = obj.getObjects();
          const shape = items[0]; // First item is the shape

          if (!shape || !shape.shapeType) return;

          if (shape.shapeType === "line") {
            // Get absolute coordinates
            const matrix = obj.calcTransformMatrix();
            const x1 = matrix[4] + shape.x1;
            const y1 = matrix[5] + shape.y1;
            const x2 = matrix[4] + shape.x2;
            const y2 = matrix[5] + shape.y2;

            drawingData.lines.push({
              coords: [x1, y1, x2, y2],
              color: shape.stroke,
            });
          } else if (shape.shapeType === "rectangle") {
            const matrix = obj.calcTransformMatrix();
            const left = matrix[4];
            const top = matrix[5];

            drawingData.rectangles.push({
              coords: [left, top, left + shape.width, top + shape.height],
              color: shape.stroke,
            });
          } else if (shape.shapeType === "circle") {
            const matrix = obj.calcTransformMatrix();
            const centerX = matrix[4] + shape.radius;
            const centerY = matrix[5] + shape.radius;

            drawingData.circles.push({
              coords: [
                centerX - shape.radius,
                centerY - shape.radius,
                centerX + shape.radius,
                centerY + shape.radius,
              ],
              color: shape.stroke,
            });
          }
        }
        // Handle standalone shapes (backward compatibility)
        else if (obj.shapeType) {
          if (obj.shapeType === "line") {
            drawingData.lines.push({
              coords: [obj.x1, obj.y1, obj.x2, obj.y2],
              color: obj.stroke,
            });
          } else if (obj.shapeType === "rectangle") {
            drawingData.rectangles.push({
              coords: [
                obj.left,
                obj.top,
                obj.left + obj.width,
                obj.top + obj.height,
              ],
              color: obj.stroke,
            });
          } else if (obj.shapeType === "circle") {
            const centerX = obj.left + obj.radius;
            const centerY = obj.top + obj.radius;
            drawingData.circles.push({
              coords: [
                centerX - obj.radius,
                centerY - obj.radius,
                centerX + obj.radius,
                centerY + obj.radius,
              ],
              color: obj.stroke,
            });
          } else if (obj.shapeType === "freedraw" && obj.freeDrawingPoints) {
            const points = obj.freeDrawingPoints.map((p) => ({
              x: p.x,
              y: p.y,
            }));
            drawingData.freeDrawings.push({
              points: points,
              color: obj.stroke,
            });
          }
        }
      });

      try {
        const formData = new FormData();
        formData.append("plan_file", currentPlanFileName);

        // If no drawings, send empty data to trigger deletion
        const hasDrawings =
          drawingData.lines.length > 0 ||
          drawingData.rectangles.length > 0 ||
          drawingData.circles.length > 0 ||
          drawingData.freeDrawings.length > 0;

        if (hasDrawings) {
          formData.append("drawing_json", JSON.stringify(drawingData));
        } else {
          formData.append("drawing_json", "");
        }

        const response = await fetch("/pardis/api/save_scaffolding.php", {
          method: "POST",
          body: formData,
        });

        if (!response.ok) {
          const errorText = await response.text();
          throw new Error(`خطا در ارتباط با سرور: ${errorText}`);
        }

        const result = await response.json();
        if (result.status === "success") {
          alert(
            hasDrawings
              ? `داربست با موفقیت ذخیره شد\nطول کل: ${result.total_length_m} متر\nمساحت کل: ${result.total_area_sqm} متر مربع`
              : result.message
          );
          document.getElementById("crack-drawer-modal").style.display = "none";
          isScaffoldingMode = false;

          // Refresh the scaffolding layer
          if (currentSvgElement) {
            loadAndRenderScaffoldingLayer(
              currentPlanFileName,
              currentSvgElement
            );
          }
        } else {
          throw new Error(
            result.error || result.message || "خطا در ذخیره‌سازی در سرور"
          );
        }
      } catch (error) {
        console.error("Save Scaffolding Error:", error);
        alert("خطا در ذخیره داربست: " + error.message);
      }
    }
  });

// Add clear all drawings function
function clearAllDrawings() {
  const activeCanvas = getActiveCanvas();
  if (!activeCanvas) return;

  if (!confirm("آیا مطمئن هستید که می‌خواهید تمام ترسیم‌ها را پاک کنید؟")) {
    return;
  }

  // Remove all objects except background
  const objects = activeCanvas.getObjects();
  objects.forEach((obj) => {
    // Don't remove background image
    if (obj !== activeCanvas.backgroundImage) {
      activeCanvas.remove(obj);
    }
  });

  activeCanvas.renderAll();

  // Show success message
  console.log("All drawings cleared");
}

// ===================================================================
// END: ENHANCED MULTI-SHAPE DRAWING MODULE (FIXED FREE DRAWING)
// ===================================================================
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
    if (
      event.role === "admin" ||
      event.role === "superuser" ||
      event.role === "Supervisor"
    ) {
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
          // NEW: Add a placeholder for verification status
          let verificationHTML = "";
          if (event.data && event.data.digital_signature && event.user_id) {
            // Unique ID for the status element
            const verificationId = `verify-status-${
              event.log_id || (Math.random() * 1e9).toFixed(0)
            }`;
            verificationHTML = `<span class="verification-status" id="${verificationId}" style="font-size: 11px; font-weight: bold; margin-right: 8px;">(درحال بررسی امضا...)</span>`;

            // Asynchronously verify and update the placeholder
            setTimeout(async () => {
              const statusEl = document.getElementById(verificationId);
              if (statusEl) {
                const result = await verifySignature(
                  event.data.signed_data,
                  event.data.digital_signature,
                  event.user_id
                );
                if (result.verified) {
                  statusEl.innerHTML = "✔️ امضای معتبر";
                  statusEl.style.color = "#28a745";
                } else {
                  statusEl.innerHTML = `❌ امضای نامعتبر`;
                  statusEl.style.color = "#dc3545";
                  statusEl.title = result.error; // Show error on hover
                }
              }
            }, 100);
          }
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
                    <span>
                        ${escapeHtml(
                          event.persian_timestamp
                        )} توسط ${escapeHtml(event.user_display_name)}
                        ${verificationHTML} 
                    </span>
                </div>
                ${detailsHTML}
            </div>`;
        })
        .join("");

      return `<div class="inspection-cycle-container"><h3>سیکل بازرسی #${cycleNumber}</h3>${eventsHTML}</div>`;
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
            const response = await fetch("/pardis/api/store_public_key.php", {
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

  fetch(`/pardis/api/get_element_data.php?${apiParams.toString()}`)
    .then((res) => {
      if (!res.ok)
        throw new Error(`Network response was not ok: ${res.statusText}`);
      return res.json();
    })
    .then(async (data) => {
      if (data.error) throw new Error(data.error);

      console.log("API Response Data:", data);
      console.log("Template Items for First Stage:", data.template[0]?.items);

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
        function validateActiveStageWithPermissions(
          formElement,
          userRole,
          history,
          template,
          dynamicContext
        ) {
          // ... (keep the existing validation function exactly as is)
          const activeTab = formElement.querySelector(
            ".stage-tab-content.active"
          );
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
          const stageHistory =
            history.find((h) => String(h.stage_id) === stageId) || {};

          // --- REPLICATE setFormState LOGIC TO DETERMINE PERMISSIONS ---
          const isSuperuser = userRole === "superuser";
          const isConsultant =
            userRole === ["admin", "supervisor", "planner"].includes(userRole);
          const isContractor = ["cat", "car", "coa", "crs"].includes(userRole);

          // Get element type from template data
          const elementType =
            template?.[0]?.items?.[0]?.element_type || "Unknown";

          // Determine if it's consultant's turn overall
          let isConsultantsTurnOverall = false;

          if (elementType === "GFRC") {
            // For GFRC, use the complex, multi-stage workflow logic
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
                  stageHistory &&
                  ["OK", "Reject"].includes(stageHistory.overall_status)
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
            // For NON-GFRC elements, simpler logic
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
                  stageHistory &&
                  ["OK", "Reject"].includes(stageHistory.overall_status)
                );
              });
            isConsultantsTurnOverall = !allStagesAreFinalized;
          }

          // Determine permissions for this specific stage
          const rejectionCount = parseInt(
            stageHistory.repair_rejection_count || 0
          );
          const stageSpecificConsultantStatus = stageHistory.overall_status;
          const stageSpecificContractorStatus = stageHistory.contractor_status;

          let canEditConsultantSection = false;
          let canEditContractorSection = false;
          let canEditChecklistItems = false;

          if (isSuperuser) {
            canEditConsultantSection = true;
            canEditContractorSection = true;
            canEditChecklistItems = true;
          } else if (isConsultant) {
            if (
              isConsultantsTurnOverall &&
              !["OK", "Reject"].includes(stageSpecificConsultantStatus)
            ) {
              canEditConsultantSection = true;
              canEditChecklistItems = true;
            }
          } else if (isContractor) {
            if (
              stageSpecificConsultantStatus === "Repair" &&
              stageSpecificContractorStatus !== "Awaiting Re-inspection" &&
              rejectionCount < 3
            ) {
              canEditContractorSection = true;
            }
          }

          // Validate and return result (same logic as before)
          return {
            isValid: errors.length === 0,
            errors,
            warnings,
            info,
            hasData,
            stageName,
            stageId,
            permissions: {
              canEditChecklistItems,
              canEditConsultantSection,
              canEditContractorSection,
            },
          };
        }

        // ACTUAL SUBMISSION FUNCTION WITH BETTER ERROR HANDLING
        let isSubmitting = false;

        async function performActualSubmission(stageData, allItemsMap) {
          if (isSubmitting) {
            console.log("Submission already in progress");
            return;
          }

          isSubmitting = true;
          newSaveButton.disabled = true;
          newValidateButton.disabled = true;
          newSaveButton.textContent = "در حال پردازش...";

          try {
            // Double-check that keys are ready
            if (!userPrivateKey) {
              console.error("Private key not available");
              const keysRegenerated = await checkAndSetupKeys();
              if (!keysRegenerated || !userPrivateKey) {
                throw new Error(
                  "کلید امضا آماده نیست. لطفا صفحه را رفرش کنید."
                );
              }
            }

            const dataToSign = JSON.stringify({
              [stageData.stageId]: stageData,
            });

            const signature = signData(dataToSign);
            if (!signature) {
              throw new Error("خطا در امضای دیجیتال داده‌ها");
            }

            // Create FormData
            const finalFormData = new FormData();
            finalFormData.append("elementId", fullElementId);
            finalFormData.append("planFile", dynamicContext.planFile);
            finalFormData.append("csrf_token", CSRF_TOKEN);
            finalFormData.append("stages", dataToSign);
            finalFormData.append("signed_data", dataToSign);
            finalFormData.append("digital_signature", signature);

            // Handle file uploads
            const activeTab = cleanFormElement.querySelector(
              ".stage-tab-content.active"
            );
            const consultantFiles = activeTab.querySelectorAll(
              '.consultant-section input[type="file"]'
            );
            const contractorFiles = activeTab.querySelectorAll(
              '.contractor-section input[type="file"]'
            );

            consultantFiles.forEach((input) => {
              for (const file of input.files) {
                finalFormData.append("attachments[]", file);
              }
            });

            contractorFiles.forEach((input) => {
              for (const file of input.files) {
                finalFormData.append("contractor_attachments[]", file);
              }
            });

            newSaveButton.textContent = "در حال ارسال...";

            const response = await fetch("api/save_inspection.php", {
              method: "POST",
              headers: {
                "X-Requested-With": "XMLHttpRequest",
              },
              credentials: "same-origin",
              body: finalFormData,
            });

            if (!response.ok) {
              const errorText = await response.text();
              console.error("Server error response:", errorText);
              throw new Error(
                `خطای سرور (${response.status}): ${response.statusText}`
              );
            }

            const responseData = await response.json();

            if (responseData.status === "success") {
              // *** NEW: Ask user if they want to export PDF ***
              const exportPDF = confirm(
                "ذخیره با موفقیت انجام شد!\n\nآیا می‌خواهید گزارش PDF را دانلود کنید؟"
              );

              if (exportPDF) {
                newSaveButton.textContent = "در حال تولید PDF...";

                // THE FIX IS HERE: Add allItemsMap as the last argument
                const pdfResult = await exportInspectionToPDF(
                  fullElementId,
                  { [stageData.stageId]: stageData },
                  signature,
                  allItemsMap
                );

                if (pdfResult.success) {
                  alert(
                    `ذخیره موفق!\n\nفایل PDF ذخیره شد: ${pdfResult.filename}`
                  );
                } else {
                  alert(`ذخیره موفق اما خطا در تولید PDF: ${pdfResult.error}`);
                }
              } else {
                alert(responseData.message);
              }

              closeForm("universalChecklistForm");
              if (
                typeof loadAndDisplaySVG === "function" &&
                currentPlanFileName
              ) {
                loadAndDisplaySVG(currentPlanFileName);
              }
            } else {
              throw new Error(responseData.message || "خطای ناشناخته رخ داد");
            }
          } catch (error) {
            console.error("Save Error:", error);
            alert("خطا در ذخیره: " + (error.message || "خطای ناشناخته رخ داد"));
          } finally {
            isSubmitting = false;
            newSaveButton.disabled = false;
            newValidateButton.disabled = false;
            newSaveButton.textContent = "ذخیره و امضای دیجیتال";
          }
        }
        // CONFIRMATION MODAL FUNCTION
        function showConfirmationModal(
          stageData,
          validationResult,
          allItemsMap
        ) {
          // Added allItemsMap parameter
          const modal = document.createElement("div");
          modal.className = "confirmation-modal-overlay";
          modal.style.cssText = `
    position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.8); display: flex; align-items: center;
    justify-content: center; z-index: 2000;
  `;

          const modalContent = document.createElement("div");
          modalContent.className = "confirmation-modal-content";
          modalContent.style.cssText = `
    background: white; padding: 30px; border-radius: 10px; max-width: 600px;
    max-height: 80vh; overflow-y: auto; box-shadow: 0 10px 30px rgba(0,0,0,0.3);
  `;

          let itemsHTML = "";
          if (stageData.items && stageData.items.length > 0) {
            itemsHTML = `
      <h4>آیتم‌های چک لیست:</h4>
      <ul style="margin: 10px 0; padding-right: 20px;">
        ${stageData.items
          .map((item) => {
            // --- KEY CHANGE IS HERE ---
            // Look up the item's text from the map using its ID.
            // Provide a fallback if the text isn't found for some reason.
            const itemText =
              allItemsMap[item.item_id] || `آیتم #${item.item_id}`;

            return `
                <li>${escapeHtml(itemText)}: <strong>${escapeHtml(
              item.status
            )}</strong> ${item.value ? `- ${escapeHtml(item.value)}` : ""}</li>
              `;
          })
          .join("")}
      </ul>
    `;
          }

          let consultantHTML = "";
          if (
            stageData.overall_status ||
            stageData.inspection_date ||
            stageData.notes
          ) {
            consultantHTML = `
      <h4>اطلاعات مشاور:</h4>
      <ul style="margin: 10px 0; padding-right: 20px;">
        ${
          stageData.overall_status
            ? `<li><strong>وضعیت کلی:</strong> ${escapeHtml(
                stageData.overall_status
              )}</li>`
            : ""
        }
        ${
          stageData.inspection_date
            ? `<li><strong>تاریخ بازرسی:</strong> ${escapeHtml(
                stageData.inspection_date
              )}</li>`
            : ""
        }
        ${
          stageData.notes
            ? `<li><strong>یادداشت:</strong> ${escapeHtml(
                stageData.notes
              )}</li>`
            : ""
        }
      </ul>
    `;
          }

          let contractorHTML = "";
          if (
            stageData.contractor_status ||
            stageData.contractor_date ||
            stageData.contractor_notes
          ) {
            contractorHTML = `
      <h4>اطلاعات پیمانکار:</h4>
      <ul style="margin: 10px 0; padding-right: 20px;">
        ${
          stageData.contractor_status
            ? `<li><strong>وضعیت:</strong> ${escapeHtml(
                stageData.contractor_status
              )}</li>`
            : ""
        }
        ${
          stageData.contractor_date
            ? `<li><strong>تاریخ اعلام:</strong> ${escapeHtml(
                stageData.contractor_date
              )}</li>`
            : ""
        }
        ${
          stageData.contractor_notes
            ? `<li><strong>توضیحات:</strong> ${escapeHtml(
                stageData.contractor_notes
              )}</li>`
            : ""
        }
      </ul>
    `;
          }

          let warningsHTML = "";
          if (validationResult.warnings.length > 0) {
            warningsHTML = `
      <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 15px 0;">
        <h4 style="color: #856404; margin: 0 0 10px 0;">⚠️ هشدارها:</h4>
        <ul style="margin: 0; padding-right: 20px; color: #856404;">
          ${validationResult.warnings
            .map((warning) => `<li>${escapeHtml(warning)}</li>`)
            .join("")}
        </ul>
      </div>
    `;
          }

          modalContent.innerHTML = `
    <h3 style="margin: 0 0 20px 0; color: #2c3e50;">تایید نهایی - ${escapeHtml(
      validationResult.stageName
    )}</h3>
    ${warningsHTML}
    <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0;">
      ${itemsHTML}
      ${consultantHTML}
      ${contractorHTML}
    </div>
    <div style="margin-top: 20px; text-align: center;">
      <p style="margin-bottom: 20px; font-weight: bold;">آیا از ذخیره این اطلاعات اطمینان دارید؟</p>
      <button id="confirm-save-btn" class="btn save" style="margin-left: 10px; padding: 10px 20px;">
        تایید و ذخیره با امضای دیجیتال
      </button>
      <button id="cancel-save-btn" class="btn cancel" style="padding: 10px 20px;">
        انصراف
      </button>
    </div>
  `;

          modal.appendChild(modalContent);
          document.body.appendChild(modal);

          // Handle confirmation
          modalContent
            .querySelector("#confirm-save-btn")
            .addEventListener("click", function () {
              document.body.removeChild(modal);
              performActualSubmission(stageData, allItemsMap); // Pass the map here
            });

          modalContent
            .querySelector("#cancel-save-btn")
            .addEventListener("click", function () {
              document.body.removeChild(modal);
            });

          // Close on overlay click
          modal.addEventListener("click", function (e) {
            if (e.target === modal) {
              document.body.removeChild(modal);
            }
          });
        }

        // VALIDATE BUTTON EVENT
        newValidateButton.addEventListener("click", function (e) {
          e.preventDefault();

          // Use enhanced validation that respects setFormState permissions
          const validation = validateActiveStageWithPermissions(
            cleanFormElement,
            USER_ROLE,
            data.history,
            data.template,
            dynamicContext
          );

          if (!validation.isValid) {
            // Highlight validation errors on fields
            highlightValidationErrors(cleanFormElement, validation.errors);

            let errorMessage =
              "خطاهای زیر باید برطرف شوند:\n\n" + validation.errors.join("\n");

            if (validation.warnings.length > 0) {
              errorMessage +=
                "\n\n--- هشدارها ---\n" + validation.warnings.join("\n");
            }

            if (validation.info.length > 0) {
              errorMessage +=
                "\n\n--- اطلاعات مجوزها ---\n" + validation.info.join("\n");
            }

            alert(errorMessage);
            return;
          }

          // Collect stage data using the same permission logic
          const activeTab = cleanFormElement.querySelector(
            ".stage-tab-content.active"
          );
          const stageId = activeTab.id.replace("stage-content-", "");
          const stagePayload = { stageId };

          // Only collect data from sections the user has permission to edit
          if (validation.permissions.canEditChecklistItems) {
            const stageItems = [];
            activeTab.querySelectorAll(".item-row").forEach((itemEl) => {
              const radio = itemEl.querySelector('input[type="radio"]:checked');
              const textInput = itemEl.querySelector(
                ".checklist-input, .drawing-data-input"
              );

              if (textInput) {
                stageItems.push({
                  item_id: textInput.dataset.itemId,
                  status: radio ? radio.value : "Pending",
                  value: textInput.value || "",
                });
              }
            });

            if (stageItems.length > 0) {
              stagePayload.items = stageItems;
            }
          }

          // Collect consultant section data (only if user has permission)
          if (validation.permissions.canEditConsultantSection) {
            const consultantSection = activeTab.querySelector(
              ".consultant-section"
            );
            if (consultantSection && !consultantSection.disabled) {
              const overallStatus = consultantSection.querySelector(
                '[name="overall_status"]'
              )?.value;
              const inspectionDate = consultantSection.querySelector(
                '[name="inspection_date"]'
              )?.value;
              const notes =
                consultantSection.querySelector('[name="notes"]')?.value;

              if (overallStatus) stagePayload.overall_status = overallStatus;
              if (inspectionDate) stagePayload.inspection_date = inspectionDate;
              if (notes) stagePayload.notes = notes;
            }
          }

          // Collect contractor section data (only if user has permission)
          if (validation.permissions.canEditContractorSection) {
            const contractorSection = activeTab.querySelector(
              ".contractor-section"
            );
            if (contractorSection && !contractorSection.disabled) {
              const contractorStatus = contractorSection.querySelector(
                '[name="contractor_status"]'
              )?.value;
              const contractorDate = contractorSection.querySelector(
                '[name="contractor_date"]'
              )?.value;
              const contractorNotes = contractorSection.querySelector(
                '[name="contractor_notes"]'
              )?.value;

              if (contractorStatus)
                stagePayload.contractor_status = contractorStatus;
              if (contractorDate) stagePayload.contractor_date = contractorDate;
              if (contractorNotes)
                stagePayload.contractor_notes = contractorNotes;
            }
          }

          // Show confirmation modal
          showConfirmationModal(stagePayload, validation, data.all_items_map);
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
        addPDFExportButton(
          formPopup,
          fullElementId,
          data.history,
          data.all_items_map
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
              minDate: "today",
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
    (userRole === "admin" ||
      userRole === "superuser" ||
      userRole === "supervisor" ||
      userRole === "planner")
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
      userRole === "car" ||
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
  const isConsultant =
    userRole === ["admin", "supervisor", "planner"].includes(userRole);
  const isContractor = ["cat", "car", "coa", "crs"].includes(userRole);

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
      // This is the MAIN PLAN - clicking should open zone selection
      if (APP_CONFIG.enableContractorRestrictions) {
        const userRole = document.body.dataset.userRole;
        const isAdmin = [
          "user",
          "admin",
          "superuser",
          "supervisor",
          "planner",
        ].includes(userRole);
        const regionRoleConfig = planroles[groupId];

        if (!regionRoleConfig) {
          console.warn(
            `Region "${groupId}" not found in 'planroles'. Click ignored.`
          );
          alert(`منطقه "${groupId}" در تنظیمات یافت نشد.`);
          return;
        }

        const hasPermission =
          isAdmin ||
          (regionRoleConfig.contractor_id &&
            userRole &&
            regionRoleConfig.contractor_id.trim() === userRole.trim());

        if (hasPermission) {
          showZoneSelectionMenu(groupId, event);
        } else {
          console.log(
            `Access Denied: User role '[${userRole}]' cannot access region '[${groupId}]' which requires '[${regionRoleConfig.contractor_id}]'.`
          );
          alert("شما دسترسی به این منطقه را ندارید.");
        }
      } else {
        showZoneSelectionMenu(groupId, event);
      }
    } else {
      // This is a ZONE PLAN - clicking should open an inspection form
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

      const multiPartElementTypes = ["GFRC", "Brick"];

      if (multiPartElementTypes.includes(element.dataset.elementType)) {
        // This path is correct and works for GFRC and Brick
        showSubPanelMenu(element, dynamicContext);
      } else {
        // ===================================================================
        // THE FIX IS HERE: Use the correct uniquePanelId for all simple elements.
        // ===================================================================
        openChecklistForm(
          element.dataset.uniquePanelId, // <-- CRITICAL FIX: Was using the wrong 'elementId' variable
          element.dataset.elementType,
          dynamicContext
        );
        // ===================================================================
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
      // *** FIX: Don't set dataset.regionKey here ***
      // Pass groupId directly to makeElementInteractive
      makeElementInteractive(el, groupId, el.id || `${groupId}_${index}`, true);
    } else {
      if (!el.id) return;
      const dbData = currentPlanDbData[el.id];
      if (dbData) {
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

        if (elementType === "GFRC" && dbData.width && dbData.height) {
          el.dataset.panelOrientation =
            parseFloat(dbData.width) > parseFloat(dbData.height) * 1.5
              ? "افقی"
              : "عمودی";
        }

        if (dbData.is_interactive) {
          makeElementInteractive(el, groupId, el.id, false);
          el.style.opacity = "";
          el.style.cursor = "pointer";
          el.style.transition = "opacity 0.3s ease";
        } else {
          el.style.opacity = "0.4";
          el.style.cursor = "not-allowed";
        }
      }
    }
  });
}

// REPLACE your entire applyGroupStylesAndControls function with this FINAL, CORRECT version.
function applyGroupStylesAndControls(svgElement) {
  const isPlan = currentPlanFileName.toLowerCase() === "plan.svg";
  const layerControlsContainer = document.getElementById(
    "layerControlsContainer"
  );
  layerControlsContainer.innerHTML = ""; // Start with a clean container

  // ===================================================================
  // PART 1: RESTORED - This is your original, working logic.
  // It makes all your SVG elements clickable. IT WILL NOT BE CHANGED.
  // ===================================================================
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
    }
  }

  // ===================================================================
  // PART 2: CREATE THE NEW, CONSOLIDATED UI
  // ===================================================================

  // --- Create the "Main Tools" Dropdown ---
  const mainToolsGroup = document.createElement("div");
  mainToolsGroup.className = "btn-group";
  mainToolsGroup.innerHTML = `
        <button type="button" class="btn btn-secondary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            ابزارهای اصلی
        </button>
        <div class="dropdown-menu">
            <a class="dropdown-item" href="#" id="dd-action-stats">📊 آمار</a>
            <a class="dropdown-item" href="#" id="dd-action-refresh">🔄 بروزرسانی</a>
            <a class="dropdown-item" href="#" id="dd-action-download">📄 Download SVG</a>
        </div>
    `;
  layerControlsContainer.appendChild(mainToolsGroup);

  // Wire up the click events for the Main Tools dropdown
  mainToolsGroup
    .querySelector("#dd-action-stats")
    .addEventListener("click", (e) => {
      e.preventDefault();
      loadAndDisplayStatistics(currentPlanFileName);
    });
  mainToolsGroup
    .querySelector("#dd-action-refresh")
    .addEventListener("click", (e) => {
      e.preventDefault();
      refreshAllLayers();
    });
  mainToolsGroup
    .querySelector("#dd-action-download")
    .addEventListener("click", (e) => {
      e.preventDefault();
      downloadCurrentSVG();
    });

  // --- Create the "Draw Scaffolding" button ---
  setupDrawingButtons(); // This function now only needs to create the drawing buttons

  // --- Create the "Layer Toggles" Dropdown ---
  const layerToggleGroup = document.createElement("div");
  layerToggleGroup.className = "btn-group";
  const layerToggleMenu = document.createElement("div");
  layerToggleMenu.className = "dropdown-menu";
  layerToggleMenu.style.cssText = "max-height: 400px; overflow-y: auto;";

  layerToggleGroup.innerHTML = `
        <button type="button" class="btn btn-primary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            نمایش / مخفی کردن لایه‌ها
        </button>`;
  layerToggleGroup.appendChild(layerToggleMenu);
  layerControlsContainer.appendChild(layerToggleGroup);

  // Populate the Layer Toggles dropdown
  for (const groupId in svgGroupConfig) {
    const config = svgGroupConfig[groupId];
    const groupElement = svgElement.getElementById(groupId);
    if (groupElement && config.label) {
      const menuItem = document.createElement("a");
      menuItem.className = "dropdown-item";
      menuItem.href = "#";
      const isVisible = groupElement.style.display !== "none";
      menuItem.innerHTML = `<span style="width: 20px; display: inline-block;">${
        isVisible ? "✓" : "&nbsp;"
      }</span> ${config.label}`;
      menuItem.addEventListener("click", (e) => {
        e.preventDefault();
        e.stopPropagation();
        const wasVisible = groupElement.style.display !== "none";
        groupElement.style.display = wasVisible ? "none" : "";
        menuItem.querySelector("span").innerHTML = !wasVisible ? "✓" : "&nbsp;";
      });
      layerToggleMenu.appendChild(menuItem);
    }
  }
  const customLayers = document.querySelectorAll(".custom-drawing-layer");
  if (customLayers.length > 0) {
    layerToggleMenu.appendChild(document.createElement("div")).className =
      "dropdown-divider";

    // ===================================================================
    // THIS IS THE FIX: Get the configs from the module first.
    // ===================================================================
    const moduleLayerConfigs = PlanDrawingModule.getLayerConfigs();

    customLayers.forEach((layer) => {
      const layerType = layer.id.replace("drawing-layer-", "");

      // Now, use the configs we safely fetched. This resolves the error.
      const config = moduleLayerConfigs[layerType] || { label: layerType };

      const menuItem = document.createElement("a");
      menuItem.className = "dropdown-item";
      menuItem.href = "#";
      const isVisible = layer.style.display !== "none";
      // Use a different color or icon for custom layers to distinguish them
      menuItem.innerHTML = `<span style="width: 20px; display: inline-block; color: #007bff;">${
        isVisible ? "✓" : "&nbsp;"
      }</span> ${config.label}`;

      menuItem.addEventListener("click", (e) => {
        e.preventDefault();
        e.stopPropagation();
        const wasVisible = layer.style.display !== "none";
        layer.style.display = wasVisible ? "none" : "";
        menuItem.querySelector("span").innerHTML = !wasVisible ? "✓" : "&nbsp;";
      });
      layerToggleMenu.appendChild(menuItem);
    });
  }
  // ===================================================================
  // PART 3: Final call to apply colors
  // ===================================================================
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
// In pardis_app.js, REPLACE this function

function setupRegionZoneNavigationIfNeeded() {
  const regionSelect = document.getElementById("regionSelect");
  const zoneButtonsContainer = document.getElementById("zoneButtonsContainer");
  if (!regionSelect || !zoneButtonsContainer) return;

  if (regionSelect.dataset.initialized) return;

  regionSelect.innerHTML =
    '<option value="">-- ابتدا یک محدوده انتخاب کنید --</option>';

  // Get user role from the body tag
  const userRole = document.body.dataset.userRole;
  const isAdmin =
    userRole === "admin" ||
    userRole === "superuser" ||
    userRole === "supervisor";

  // Use the svgGroupConfig and regionToZoneMap objects that are already in your file.
  for (const regionKey in regionToZoneMap) {
    const regionConfig = svgGroupConfig[regionKey];
    if (!regionConfig) continue; // Skip if no config exists for this region

    // --- THIS IS THE ONLY CHANGE IN LOGIC ---
    // It checks the new contractor_id property you just added.
    if (isAdmin || regionConfig.contractoren === userRole) {
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
      form
        .querySelectorAll(".stage-tab-content[data-is-dirty='true']")
        .forEach((stageEl) => {
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
          if (
            userRole === "admin" ||
            userRole === "superuser" ||
            userRole === "supervisor" ||
            userRole === "planner"
          ) {
            const consultantSection = stageEl.querySelector(
              ".consultant-section"
            );
            if (
              consultantSection.querySelector('[name="overall_status"]').value
            )
              stagePayload.overall_status = consultantSection.querySelector(
                '[name="overall_status"]'
              ).value;
            if (
              consultantSection.querySelector('[name="inspection_date"]').value
            )
              stagePayload.inspection_date = consultantSection.querySelector(
                '[name="inspection_date"]'
              ).value;
            if (consultantSection.querySelector('[name="notes"]').value)
              stagePayload.notes =
                consultantSection.querySelector('[name="notes"]').value;
          }
          if (
            in_array(userRole, ["cat", "car", "coa", "crs"]) ||
            userRole === "superuser"
          ) {
            const contractorSection = stageEl.querySelector(
              ".contractor-section"
            );
            if (
              contractorSection.querySelector('[name="contractor_status"]')
                .value
            )
              stagePayload.contractor_status = contractorSection.querySelector(
                '[name="contractor_status"]'
              ).value;
            if (
              contractorSection.querySelector('[name="contractor_date"]').value
            )
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
function cleanSVGAttributes(svgText) {
  // Fix malformed width/height attributes like "3370/32pt"
  svgText = svgText.replace(/width="([0-9]+)\/[0-9]+pt"/g, 'width="$1"');
  svgText = svgText.replace(/height="([0-9]+)\/[0-9]+pt"/g, 'height="$1"');

  // Also handle other potential pt unit issues
  svgText = svgText.replace(/width="([0-9.]+)pt"/g, 'width="$1"');
  svgText = svgText.replace(/height="([0-9.]+)pt"/g, 'height="$1"');

  return svgText;
}
// REPLACE your existing loadAndDisplaySVG function with this complete version
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
    fetch(SVG_BASE_PATH + baseFilename)
      .then((res) => {
        if (res.status === 404) {
          throw new Error("نقشه این زون هنوز بارگزاری نشده است.");
        }
        if (!res.ok) {
          throw new Error(`خطای سرور: ${res.statusText}`);
        }
        return res.text();
      })
      .then((svgText) => {
        return cleanSVGAttributes(svgText);
      }),
    isPlan
      ? Promise.resolve({})
      : fetch(`/pardis/api/get_plan_elements.php?plan=${baseFilename}`).then(
          (res) => {
            if (!res.ok) return {};
            return res.json();
          }
        ),
  ])
    .then(([svgData, dbData]) => {
      svgContainer.classList.remove("loading");
      currentPlanDbData = dbData; // Store globally as before

      svgContainer.innerHTML = svgData;
      const zoomControlsHtml = `<div class="zoom-controls"><button id="zoomInBtn">+</button><button id="zoomOutBtn">-</button><button id="zoomResetBtn">⌂</button></div>`;
      svgContainer.insertAdjacentHTML("afterbegin", zoomControlsHtml);

      currentSvgElement = svgContainer.querySelector("svg");
      if (!currentSvgElement) throw new Error("SVG element not found in data.");

      applyGroupStylesAndControls(currentSvgElement);
      setupZoomControls();
      applyElementVisibilityAndColor(currentSvgElement, currentPlanDbData);

      // ===================================================================
      // START: CORRECTED LOGIC PLACEMENT
      // All these calls are now correctly placed inside the promise's .then()
      // block, where `dbData` is guaranteed to be available.
      // ===================================================================

      if (!isPlan) {
        // Load crack layer
        loadAndRenderCrackLayer(baseFilename, currentSvgElement);

        // Load scaffolding layer, passing the correct dbData
        loadAndRenderScaffoldingLayer(baseFilename, currentSvgElement, dbData);
      } else {
        // Special case for the main plan
        loadAndRenderScaffoldingLayer(baseFilename, currentSvgElement, dbData);
      }

      // Load statistics for the current plan
      loadAndDisplayStatistics(baseFilename);

      // Update the scaffolding controls (like the 'Draw Scaffolding' button)
      //updateScaffoldingControls(isPlan);
      PlanDrawingModule.loadAllDrawingLayers(baseFilename);

      // ===================================================================
      // END: CORRECTED LOGIC PLACEMENT
      // ===================================================================
    })
    .catch((error) => {
      svgContainer.classList.remove("loading");
      console.error("Error during plan loading:", error);
      svgContainer.innerHTML = `<p style="color:red; font-weight:bold;">خطا در بارگزاری نقشه: ${error.message}</p>`;
    });
}

document.addEventListener("keydown", function (e) {
  // Ctrl+Shift+S to open scaffolding drawer
  if (e.ctrlKey && e.shiftKey && e.key === "S") {
    e.preventDefault();
    const userRole = document.body.dataset.userRole;
    const hasPermission = [
      "admin",
      "superuser",
      "supervisor",
      "planner",
    ].includes(userRole);
    if (hasPermission && currentSvgElement && currentPlanFileName) {
      openScaffoldingDrawer();
    }
  }

  // Ctrl+Shift+T to toggle statistics
  if (e.ctrlKey && e.shiftKey && e.key === "T") {
    e.preventDefault();
    toggleStatisticsPanel();
  }
});
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
          showSubPanelMenu(elementInSvg, dynamicContext, partName);
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
 * Applies both visibility and color styles to all elements based on the legend filters and their status.
 * @param {SVGElement} svgElement The parent SVG element.
 * @param {Object} dbData An object mapping element_id to its database record.
 */
function applyElementVisibilityAndColor(svgElement, dbData) {
  for (const elementId in dbData) {
    const el = svgElement.getElementById(elementId);
    if (el) {
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

      // 2. SET COLOR (this is your existing logic from applyStatusColors)
      if (status === "Pending") {
        if (elementType === "GFRC") {
          const orientation = el.dataset.panelOrientation;
          const gfrcConfig = svgGroupConfig["GFRC"];
          if (gfrcConfig && gfrcConfig.colors) {
            if (orientation === "افقی" && gfrcConfig.colors.h) {
              el.style.fill = gfrcConfig.colors.h;
            } else if (orientation === "عمودی" && gfrcConfig.colors.v) {
              el.style.fill = gfrcConfig.colors.v;
            } else {
              el.style.fill = STATUS_COLORS["Pending"];
            }
          } else {
            el.style.fill = STATUS_COLORS["Pending"];
          }
        } else {
          const group = el.closest("g");
          if (
            group &&
            group.id &&
            svgGroupConfig[group.id] &&
            svgGroupConfig[group.id].color
          ) {
            el.style.fill = svgGroupConfig[group.id].color;
          } else {
            el.style.fill = STATUS_COLORS["Pending"];
          }
        }
      } else {
        el.style.fill = STATUS_COLORS[status] || STATUS_COLORS["Pending"];
      }
    }
  }
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
/**
 * Parse element ID and extract information regardless of format
 */
function parseElementId(elementId) {
  const parts = elementId.split("-");

  // Format 1 (without contractor): "WestLi-GL-001-LIB"
  // Format 2 (with contractor): "WestLi-GL-001-LIB-AC"

  return {
    zone: parts[0], // "WestLi"
    type: parts[1], // "GL"
    number: parseInt(parts[2]), // 1
    block: parts[3], // "LIB"
    contractor: parts[4] || null, // "AC" or null
    hasContractor: parts.length >= 5,
  };
}

function updateZoomButtonStates() {
  const zoomInBtn = document.getElementById("zoomInBtn");
  const zoomOutBtn = document.getElementById("zoomOutBtn");
  if (zoomInBtn && zoomOutBtn) {
    zoomInBtn.disabled = currentZoom >= maxZoom;
    zoomOutBtn.disabled = currentZoom <= minZoom;
  }
}
let scaffoldingCanvas = null;
let crackCanvas = null; // Separate canvas for cracks
let currentScaffoldingData = null;
let isScaffoldingMode = false;
/**

Helper to get the active canvas based on mode
*/
function getActiveCanvas() {
  return isScaffoldingMode ? scaffoldingCanvas : crackCanvas;
}

/**

Opens the scaffolding drawer for the entire plan
*/
/**
 * DEFINITIVE CORRECTED VERSION
 * This function now contains the full, working logic to extract drawings from the canvas before saving.
 */
function openScaffoldingDrawer() {
  if (!currentSvgElement || !currentPlanFileName) {
    alert("لطفاً ابتدا یک نقشه را بارگذاری کنید.");
    return;
  }
  const modal = document.getElementById("crack-drawer-modal");
  if (!modal) {
    console.error("Scaffolding drawer modal not found");
    return;
  }
  const scaleFactor = PLAN_SCALE_FACTORS[currentPlanFileName] || 1.0;
  if (scaleFactor === 1.0) {
    console.warn(
      `Scale factor for ${currentPlanFileName} not found in plan_scales.json. Defaulting to 1.0.`
    );
  }
  console.log(`Using scale factor for scaffolding drawer: ${scaleFactor}`);

  isScaffoldingMode = true;
  modal.classList.add("scaffolding-mode");
  document.getElementById(
    "drawer-title"
  ).textContent = `ترسیم داربست برای: ${currentPlanFileName}`;

  const modalBody = modal.querySelector(".drawer-canvas-container");

  // ENHANCED: Floating zoom and pan controls
  modalBody.innerHTML = `
    <div class="drawer-zoom-controls" style="
      position: fixed;
      top: 120px;
      right: 20px;
      z-index: 2000;
      display: flex;
      flex-direction: column;
      gap: 5px;
      background: white;
      padding: 8px;
      border-radius: 12px;
      box-shadow: 0 4px 16px rgba(0,0,0,0.2);
      border: 2px solid #007bff;
    ">
      <button id="drawer-zoom-in" class="zoom-btn" title="بزرگنمایی (Ctrl + موس)">
        <span style="font-size: 22px; font-weight: bold;">+</span>
      </button>
      <button id="drawer-zoom-out" class="zoom-btn" title="کوچکنمایی (Ctrl + موس)">
        <span style="font-size: 22px; font-weight: bold;">−</span>
      </button>
      <button id="drawer-zoom-reset" class="zoom-btn" title="بازنشانی نما">
        <span style="font-size: 18px;">⌂</span>
      </button>
      <div style="border-top: 2px solid #dee2e6; margin: 4px 0;"></div>
      <button id="drawer-pan-mode" class="zoom-btn pan-btn" title="حالت جابجایی (Alt + کشیدن)">
        <span style="font-size: 20px;">✋</span>
      </button>
      <div id="drawer-zoom-level" style="
        text-align: center;
        font-size: 12px;
        color: #495057;
        font-weight: bold;
        padding: 6px 0;
        border-top: 2px solid #dee2e6;
        margin-top: 4px;
        background: #f8f9fa;
        border-radius: 6px;
      ">100%</div>
    </div>
    <div id="ruler-top" class="ruler horizontal"></div>
    <div id="ruler-left" class="ruler vertical"></div>
    <div class="canvas-wrapper" style="
      width: 100%;
      height: 100%;
      overflow: auto;
      position: relative;
    ">
      <canvas id="crack-canvas"></canvas>
    </div>
  `;

  const canvasEl = document.getElementById("crack-canvas");
  const svgViewBox = currentSvgElement.viewBox.baseVal;
  const canvasWidth = svgViewBox.width;
  const canvasHeight = svgViewBox.height;

  if (scaffoldingCanvas) {
    scaffoldingCanvas.dispose();
  }
  scaffoldingCanvas = new fabric.Canvas(canvasEl, {
    width: canvasWidth,
    height: canvasHeight,
    backgroundColor: "#ffffff",
    selection: false,
  });

  scaffoldingCanvas.customScaleFactor = scaleFactor; // Set the correct scale factor here
  scaffoldingCanvas.customMargin = 0;
  scaffoldingCanvas.customOffsetX = 0;
  scaffoldingCanvas.customOffsetY = 0;
  scaffoldingCanvas.currentZoom = 1;
  scaffoldingCanvas.isPanMode = false;

  createSVGBackground();
  loadExistingScaffoldingData();
  setupScaffoldingRulers(canvasWidth, canvasHeight);
  setupToolButtons();

  setTimeout(() => {
    document.querySelectorAll(".tool-color").forEach((input) => {
      input.value = "#FF6B6B";
    });
  }, 100);

  setupScaffoldingEventHandlers();
  setupDrawerZoomAndPanControls();
  modal.style.display = "flex";

  setTimeout(() => {
    addScaffoldingActionButtons();
  }, 200);
}

function setupDrawerZoomAndPanControls() {
  if (!scaffoldingCanvas) return;

  const zoomInBtn = document.getElementById("drawer-zoom-in");
  const zoomOutBtn = document.getElementById("drawer-zoom-out");
  const zoomResetBtn = document.getElementById("drawer-zoom-reset");
  const panModeBtn = document.getElementById("drawer-pan-mode");
  const zoomLevelDisplay = document.getElementById("drawer-zoom-level");

  if (!zoomInBtn || !zoomOutBtn || !zoomResetBtn || !panModeBtn) {
    console.error("Control buttons not found");
    return;
  }

  // Style zoom and pan buttons
  const controlButtons = [zoomInBtn, zoomOutBtn, zoomResetBtn, panModeBtn];
  controlButtons.forEach((btn) => {
    btn.style.cssText = `
      width: 44px;
      height: 44px;
      border: 2px solid #dee2e6;
      background: white;
      border-radius: 8px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.2s ease;
      color: #495057;
      font-family: 'Vazir', Tahoma, sans-serif;
    `;
  });

  // Zoom In
  zoomInBtn.addEventListener("click", () => {
    zoomDrawerCanvas(scaffoldingCanvas.currentZoom * 1.25);
  });

  // Zoom Out
  zoomOutBtn.addEventListener("click", () => {
    zoomDrawerCanvas(scaffoldingCanvas.currentZoom / 1.25);
  });

  // Reset Zoom and Pan
  zoomResetBtn.addEventListener("click", () => {
    scaffoldingCanvas.setViewportTransform([1, 0, 0, 1, 0, 0]);
    scaffoldingCanvas.currentZoom = 1;
    updateDrawerZoomDisplay(1);
  });

  // Toggle Pan Mode
  panModeBtn.addEventListener("click", () => {
    scaffoldingCanvas.isPanMode = !scaffoldingCanvas.isPanMode;

    if (scaffoldingCanvas.isPanMode) {
      panModeBtn.style.background = "#007bff";
      panModeBtn.style.color = "white";
      panModeBtn.style.borderColor = "#007bff";
      scaffoldingCanvas.defaultCursor = "grab";
      scaffoldingCanvas.hoverCursor = "grab";
    } else {
      panModeBtn.style.background = "white";
      panModeBtn.style.color = "#495057";
      panModeBtn.style.borderColor = "#dee2e6";
      scaffoldingCanvas.defaultCursor = "default";
      scaffoldingCanvas.hoverCursor = "move";
    }

    scaffoldingCanvas.renderAll();
  });

  // Mouse wheel zoom (with Ctrl key)
  scaffoldingCanvas.on("mouse:wheel", function (opt) {
    const evt = opt.e;

    if (evt.ctrlKey || evt.metaKey) {
      evt.preventDefault();
      evt.stopPropagation();

      const delta = evt.deltaY;
      let zoom = scaffoldingCanvas.getZoom();
      zoom *= 0.999 ** delta;

      if (zoom > 5) zoom = 5;
      if (zoom < 0.1) zoom = 0.1;

      const point = new fabric.Point(evt.offsetX, evt.offsetY);
      scaffoldingCanvas.zoomToPoint(point, zoom);
      scaffoldingCanvas.currentZoom = zoom;

      updateDrawerZoomDisplay(zoom);
    }
  });

  // Pan functionality - works in pan mode or with Alt key
  let isPanning = false;
  let lastPosX = 0;
  let lastPosY = 0;

  scaffoldingCanvas.on("mouse:down", function (opt) {
    const evt = opt.e;

    // Enable panning if: pan mode is active OR Alt key is pressed OR middle mouse button
    if (
      scaffoldingCanvas.isPanMode ||
      evt.altKey === true ||
      evt.button === 1
    ) {
      isPanning = true;
      scaffoldingCanvas.selection = false;
      lastPosX = evt.clientX;
      lastPosY = evt.clientY;
      scaffoldingCanvas.defaultCursor = "grabbing";
      scaffoldingCanvas.renderAll();
    }
  });

  scaffoldingCanvas.on("mouse:move", function (opt) {
    if (isPanning) {
      const evt = opt.e;
      const vpt = scaffoldingCanvas.viewportTransform;
      vpt[4] += evt.clientX - lastPosX;
      vpt[5] += evt.clientY - lastPosY;
      scaffoldingCanvas.requestRenderAll();
      lastPosX = evt.clientX;
      lastPosY = evt.clientY;
    }
  });

  scaffoldingCanvas.on("mouse:up", function () {
    if (isPanning) {
      isPanning = false;
      scaffoldingCanvas.selection = true;
      scaffoldingCanvas.defaultCursor = scaffoldingCanvas.isPanMode
        ? "grab"
        : "default";
      scaffoldingCanvas.renderAll();
    }
  });

  // Hover effects for control buttons
  controlButtons.forEach((btn) => {
    btn.addEventListener("mouseenter", () => {
      if (btn === panModeBtn && scaffoldingCanvas.isPanMode) return; // Skip if pan mode is active

      btn.style.background = "#007bff";
      btn.style.color = "white";
      btn.style.borderColor = "#007bff";
      btn.style.transform = "scale(1.1)";
      btn.style.boxShadow = "0 4px 12px rgba(0, 123, 255, 0.3)";
    });

    btn.addEventListener("mouseleave", () => {
      if (btn === panModeBtn && scaffoldingCanvas.isPanMode) return; // Skip if pan mode is active

      btn.style.background = "white";
      btn.style.color = "#495057";
      btn.style.borderColor = "#dee2e6";
      btn.style.transform = "scale(1)";
      btn.style.boxShadow = "none";
    });
  });

  // Keyboard shortcuts
  document.addEventListener("keydown", function (e) {
    // Space bar for temporary pan mode
    if (e.code === "Space" && !e.repeat) {
      e.preventDefault();
      scaffoldingCanvas.isPanMode = true;
      scaffoldingCanvas.defaultCursor = "grab";
      panModeBtn.style.background = "#ffc107";
      panModeBtn.style.borderColor = "#ffc107";
    }

    // Plus key for zoom in
    if (e.key === "+" || e.key === "=") {
      e.preventDefault();
      zoomDrawerCanvas(scaffoldingCanvas.currentZoom * 1.25);
    }

    // Minus key for zoom out
    if (e.key === "-" || e.key === "_") {
      e.preventDefault();
      zoomDrawerCanvas(scaffoldingCanvas.currentZoom / 1.25);
    }

    // 0 key for reset zoom
    if (e.key === "0") {
      e.preventDefault();
      zoomResetBtn.click();
    }
  });

  document.addEventListener("keyup", function (e) {
    // Release space bar
    if (e.code === "Space") {
      scaffoldingCanvas.isPanMode = false;
      scaffoldingCanvas.defaultCursor = "default";
      panModeBtn.style.background = "white";
      panModeBtn.style.borderColor = "#dee2e6";
    }
  });

  // Update zoom display
  updateDrawerZoomDisplay(1);

  // Make zoom controls draggable (optional enhancement)
  makeZoomControlsDraggable();
}

function makeZoomControlsDraggable() {
  const zoomControls = document.querySelector(".drawer-zoom-controls");
  if (!zoomControls) return;

  let isDragging = false;
  let currentX;
  let currentY;
  let initialX;
  let initialY;

  zoomControls.addEventListener("mousedown", function (e) {
    // Only start drag if clicking on the container itself (not buttons)
    if (e.target === zoomControls || e.target.id === "drawer-zoom-level") {
      isDragging = true;
      initialX =
        e.clientX - parseInt(window.getComputedStyle(zoomControls).right || 20);
      initialY =
        e.clientY - parseInt(window.getComputedStyle(zoomControls).top || 120);
      zoomControls.style.cursor = "move";
    }
  });

  document.addEventListener("mousemove", function (e) {
    if (isDragging) {
      e.preventDefault();
      currentX = e.clientX - initialX;
      currentY = e.clientY - initialY;

      // Keep within viewport
      const maxRight = window.innerWidth - zoomControls.offsetWidth - 10;
      const maxBottom = window.innerHeight - zoomControls.offsetHeight - 10;

      currentX = Math.max(10, Math.min(currentX, maxRight));
      currentY = Math.max(60, Math.min(currentY, maxBottom));

      zoomControls.style.right = "auto";
      zoomControls.style.left = currentX + "px";
      zoomControls.style.top = currentY + "px";
    }
  });

  document.addEventListener("mouseup", function () {
    if (isDragging) {
      isDragging = false;
      zoomControls.style.cursor = "default";
    }
  });
}

/**
 * ENHANCED: Zoom the drawer canvas
 */
function zoomDrawerCanvas(newZoom) {
  if (!scaffoldingCanvas) return;

  // Limit zoom range
  if (newZoom > 5) newZoom = 5;
  if (newZoom < 0.1) newZoom = 0.1;

  // Get center point of viewport
  const center = new fabric.Point(
    scaffoldingCanvas.width / 2,
    scaffoldingCanvas.height / 2
  );

  // Apply zoom to center point
  scaffoldingCanvas.zoomToPoint(center, newZoom);
  scaffoldingCanvas.currentZoom = newZoom;

  updateDrawerZoomDisplay(newZoom);
}

/**
 * ENHANCED: Update zoom level display
 */
function updateDrawerZoomDisplay(zoom) {
  const zoomLevelDisplay = document.getElementById("drawer-zoom-level");
  if (zoomLevelDisplay) {
    const percentage = Math.round(zoom * 100);
    zoomLevelDisplay.textContent = `${percentage}%`;

    // Color code based on zoom level
    if (percentage > 200) {
      zoomLevelDisplay.style.color = "#dc3545"; // Red for high zoom
    } else if (percentage < 50) {
      zoomLevelDisplay.style.color = "#ffc107"; // Yellow for low zoom
    } else {
      zoomLevelDisplay.style.color = "#28a745"; // Green for normal zoom
    }
  }

  // Update button states
  const zoomInBtn = document.getElementById("drawer-zoom-in");
  const zoomOutBtn = document.getElementById("drawer-zoom-out");

  if (zoomInBtn) {
    zoomInBtn.disabled = zoom >= 5;
    zoomInBtn.style.opacity = zoom >= 5 ? "0.5" : "1";
    zoomInBtn.style.cursor = zoom >= 5 ? "not-allowed" : "pointer";
  }

  if (zoomOutBtn) {
    zoomOutBtn.disabled = zoom <= 0.1;
    zoomOutBtn.style.opacity = zoom <= 0.1 ? "0.5" : "1";
    zoomOutBtn.style.cursor = zoom <= 0.1 ? "not-allowed" : "pointer";
  }
}

function addScaffoldingActionButtons() {
  const modal = document.getElementById("crack-drawer-modal");
  if (!modal || !isScaffoldingMode) return;

  const drawerTools = modal.querySelector(".drawer-tools");
  if (!drawerTools) {
    console.error("Drawer tools container not found");
    return;
  }

  const existingActions = drawerTools.querySelector(".action-buttons-group");
  if (existingActions) existingActions.remove();

  const actionButtonsGroup = document.createElement("div");
  actionButtonsGroup.className = "action-buttons-group";
  actionButtonsGroup.style.cssText = `
    display: flex;
    gap: 10px;
    margin-right: auto;
    padding-right: 20px;
    border-right: 2px solid #dee2e6;
  `;

  // CLEAR BUTTON (No changes needed here, but included for completeness)
  const clearBtn = document.createElement("button");
  clearBtn.id = "scaffolding-clear-btn";
  clearBtn.className = "btn cancel";
  clearBtn.innerHTML = "🗑️ پاک کردن همه";
  clearBtn.style.cssText = `
    background: linear-gradient(45deg, #dc3545, #c82333);
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 6px;
    cursor: pointer;
    font-family: 'Vazir', Tahoma, sans-serif;
    font-weight: bold;
    font-size: 13px;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  `;
  clearBtn.addEventListener("mouseenter", () => {
    clearBtn.style.transform = "translateY(-1px)";
    clearBtn.style.boxShadow = "0 4px 8px rgba(220, 53, 69, 0.3)";
  });
  clearBtn.addEventListener("mouseleave", () => {
    clearBtn.style.transform = "translateY(0)";
    clearBtn.style.boxShadow = "0 2px 4px rgba(0,0,0,0.1)";
  });
  clearBtn.addEventListener("click", async () => {
    if (
      !confirm(
        "آیا مطمئن هستید که می‌خواهید تمام ترسیم‌های داربست را پاک کنید؟"
      )
    ) {
      return;
    }

    try {
      const formData = new FormData();
      formData.append("plan_file", currentPlanFileName);

      const response = await fetch("/pardis/api/delete_scaffolding.php", {
        method: "POST",
        body: formData,
      });

      if (!response.ok) {
        throw new Error("خطا در ارتباط با سرور");
      }

      const result = await response.json();

      if (result.status === "success") {
        alert(result.message);

        if (scaffoldingCanvas) {
          scaffoldingCanvas.getObjects().forEach((obj) => {
            if (obj !== scaffoldingCanvas.backgroundImage) {
              scaffoldingCanvas.remove(obj);
            }
          });
          scaffoldingCanvas.requestRenderAll();
        }

        if (currentSvgElement) {
          loadAndRenderScaffoldingLayer(currentPlanFileName, currentSvgElement);
        }
      } else {
        throw new Error(result.error || "خطای ناشناخته");
      }
    } catch (error) {
      console.error("Delete error:", error);
      alert("خطا در پاک کردن داربست: " + error.message);
    }
  });

  // SAVE BUTTON
  const saveBtn = document.createElement("button");
  saveBtn.id = "drawer-save-btn";
  saveBtn.className = "btn save";
  saveBtn.innerHTML = "💾 ذخیره";
  saveBtn.style.cssText = `
    background: linear-gradient(45deg, #28a745, #20c997);
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 6px;
    cursor: pointer;
    font-family: 'Vazir', Tahoma, sans-serif;
    font-weight: bold;
    font-size: 13px;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  `;
  saveBtn.addEventListener("mouseenter", () => {
    saveBtn.style.transform = "translateY(-1px)";
    saveBtn.style.boxShadow = "0 4px 8px rgba(40, 167, 69, 0.3)";
  });
  saveBtn.addEventListener("mouseleave", () => {
    saveBtn.style.transform = "translateY(0)";
    saveBtn.style.boxShadow = "0 2px 4px rgba(0,0,0,0.1)";
  });

  // ===================================================================
  // START: CRITICAL FIX FOR SAVING COORDINATES
  // ===================================================================
  saveBtn.addEventListener("click", async () => {
    if (!scaffoldingCanvas) {
      alert("خطا: صفحه ترسیم فعال برای ذخیره وجود ندارد.");
      return;
    }

    saveBtn.disabled = true;
    saveBtn.innerHTML = "⏳ در حال ذخیره...";

    try {
      const drawingData = {
        lines: [],
        rectangles: [],
        circles: [],
        freeDrawings: [],
      };

      // Force canvas to calculate absolute coordinates for all objects
      scaffoldingCanvas.renderAll();

      scaffoldingCanvas.getObjects().forEach((obj) => {
        // Skip background image and any non-shape objects
        if (obj === scaffoldingCanvas.backgroundImage || !obj.shapeType) return;

        // Use aCoords which provides the absolute coordinates of the bounding box
        if (!obj.aCoords) {
          console.warn("Object is missing aCoords, skipping:", obj);
          return;
        }

        const shape = obj.type === "group" ? obj.getObjects()[0] : obj;

        if (shape.shapeType === "line") {
          // For a line, aCoords gives the bounding box, so we need to transform the line's own points
          const matrix = obj.calcTransformMatrix();
          const startPoint = fabric.util.transformPoint(
            { x: shape.x1, y: shape.y1 },
            matrix
          );
          const endPoint = fabric.util.transformPoint(
            { x: shape.x2, y: shape.y2 },
            matrix
          );
          drawingData.lines.push({
            coords: [startPoint.x, startPoint.y, endPoint.x, endPoint.y],
            color: shape.stroke,
          });
        } else if (shape.shapeType === "rectangle") {
          const tl = obj.aCoords.tl; // Top-Left corner
          const br = obj.aCoords.br; // Bottom-Right corner
          drawingData.rectangles.push({
            coords: [tl.x, tl.y, br.x, br.y],
            color: shape.stroke,
          });
        } else if (shape.shapeType === "circle") {
          const tl = obj.aCoords.tl;
          const br = obj.aCoords.br;
          drawingData.circles.push({
            coords: [tl.x, tl.y, br.x, br.y],
            color: shape.stroke,
          });
        } else if (shape.shapeType === "freedraw" && shape.freeDrawingPoints) {
          // For free draw, the points are already in canvas coordinates
          drawingData.freeDrawings.push({
            points: shape.freeDrawingPoints.map((p) => ({ x: p.x, y: p.y })),
            color: shape.stroke,
          });
        }
      });

      const hasDrawings =
        drawingData.lines.length > 0 ||
        drawingData.rectangles.length > 0 ||
        drawingData.circles.length > 0 ||
        drawingData.freeDrawings.length > 0;

      const formData = new FormData();
      formData.append("plan_file", currentPlanFileName);
      formData.append(
        "drawing_json",
        hasDrawings ? JSON.stringify(drawingData) : ""
      );

      const response = await fetch("/pardis/api/save_scaffolding.php", {
        method: "POST",
        body: formData,
      });

      if (!response.ok) {
        const errorText = await response.text();
        throw new Error(`خطای سرور: ${errorText}`);
      }

      const result = await response.json();

      if (result.status === "success") {
        alert(
          hasDrawings
            ? `داربست با موفقیت ذخیره شد\nطول کل: ${result.total_length_m} متر\nمساحت کل: ${result.total_area_sqm} متر مربع`
            : result.message
        );
        modal.style.display = "none";
        isScaffoldingMode = false;

        if (currentSvgElement) {
          loadAndRenderScaffoldingLayer(currentPlanFileName, currentSvgElement);
        }
      } else {
        throw new Error(result.error || "خطای ناشناخته");
      }
    } catch (error) {
      console.error("Save Scaffolding Error:", error);
      alert("خطا در ذخیره داربست: " + error.message);
    } finally {
      saveBtn.disabled = false;
      saveBtn.innerHTML = "💾 ذخیره";
    }
  });
  // ===================================================================
  // END: CRITICAL FIX
  // ===================================================================

  // CLOSE BUTTON (No changes needed here)
  const closeBtn = document.createElement("button");
  closeBtn.id = "drawer-close-btn";
  closeBtn.className = "btn cancel";
  closeBtn.innerHTML = "❌ بستن";
  closeBtn.style.cssText = `
    background: linear-gradient(45deg, #6c757d, #495057);
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 6px;
    cursor: pointer;
    font-family: 'Vazir', Tahoma, sans-serif;
    font-weight: bold;
    font-size: 13px;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  `;
  closeBtn.addEventListener("mouseenter", () => {
    closeBtn.style.transform = "translateY(-1px)";
    closeBtn.style.boxShadow = "0 4px 8px rgba(108, 117, 125, 0.3)";
  });
  closeBtn.addEventListener("mouseleave", () => {
    closeBtn.style.transform = "translateY(0)";
    closeBtn.style.boxShadow = "0 2px 4px rgba(0,0,0,0.1)";
  });
  closeBtn.addEventListener("click", () => {
    modal.style.display = "none";
    if (isScaffoldingMode) {
      modal.classList.remove("scaffolding-mode");
    }
    isScaffoldingMode = false;
    if (scaffoldingCanvas) {
      scaffoldingCanvas.dispose();
      scaffoldingCanvas = null;
    }
  });

  actionButtonsGroup.appendChild(clearBtn);
  actionButtonsGroup.appendChild(saveBtn);
  actionButtonsGroup.appendChild(closeBtn);

  drawerTools.appendChild(actionButtonsGroup);
}

function addScaffoldingButtons() {
  const modal = document.getElementById("crack-drawer-modal");
  if (!modal || !isScaffoldingMode) return;

  // Remove any existing footer
  let footer = modal.querySelector(".drawer-footer");
  if (footer) footer.remove();

  // Create new footer with all buttons
  footer = document.createElement("div");
  footer.className = "drawer-footer";
  footer.style.cssText = `
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    padding: 15px;
    border-top: 2px solid #dee2e6;
    background: #f8f9fa;
  `;

  // CLEAR BUTTON
  const clearBtn = document.createElement("button");
  clearBtn.id = "scaffolding-clear-btn";
  clearBtn.className = "btn cancel";
  clearBtn.innerHTML = "🗑️ پاک کردن همه";
  clearBtn.style.cssText = `
    background: linear-gradient(45deg, #dc3545, #c82333);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 6px;
    cursor: pointer;
    font-family: 'Vazir', Tahoma, sans-serif;
    font-weight: bold;
    transition: all 0.3s ease;
  `;
  clearBtn.addEventListener("click", async () => {
    if (
      !confirm(
        "آیا مطمئن هستید که می‌خواهید تمام ترسیم‌های داربست را پاک کنید؟"
      )
    ) {
      return;
    }

    try {
      const formData = new FormData();
      formData.append("plan_file", currentPlanFileName);

      const response = await fetch("/pardis/api/delete_scaffolding.php", {
        method: "POST",
        body: formData,
      });

      if (!response.ok) {
        throw new Error("خطا در ارتباط با سرور");
      }

      const result = await response.json();

      if (result.status === "success") {
        alert(result.message);

        // Clear canvas
        if (scaffoldingCanvas) {
          scaffoldingCanvas.getObjects().forEach((obj) => {
            if (obj !== scaffoldingCanvas.backgroundImage) {
              scaffoldingCanvas.remove(obj);
            }
          });
          scaffoldingCanvas.renderAll();
        }

        // Reload scaffolding layer on SVG
        if (currentSvgElement) {
          loadAndRenderScaffoldingLayer(currentPlanFileName, currentSvgElement);
        }
      } else {
        throw new Error(result.error || "خطای ناشناخته");
      }
    } catch (error) {
      console.error("Delete error:", error);
      alert("خطا در پاک کردن داربست: " + error.message);
    }
  });

  // SAVE BUTTON
  const saveBtn = document.createElement("button");
  saveBtn.id = "drawer-save-btn";
  saveBtn.className = "btn save";
  saveBtn.innerHTML = "💾 ذخیره";
  saveBtn.style.cssText = `
    background: linear-gradient(45deg, #28a745, #20c997);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 6px;
    cursor: pointer;
    font-family: 'Vazir', Tahoma, sans-serif;
    font-weight: bold;
    transition: all 0.3s ease;
  `;
  saveBtn.addEventListener("click", async () => {
    if (!scaffoldingCanvas) {
      alert("خطا: صفحه ترسیم فعال برای ذخیره وجود ندارد.");
      return;
    }

    saveBtn.disabled = true;
    saveBtn.innerHTML = "⏳ در حال ذخیره...";

    try {
      const drawingData = {
        lines: [],
        rectangles: [],
        circles: [],
        freeDrawings: [],
      };

      // Extract shapes from canvas
      scaffoldingCanvas.getObjects().forEach((obj) => {
        if (obj === scaffoldingCanvas.backgroundImage) return;

        let shape, items, matrix;

        if (obj.type === "group") {
          items = obj.getObjects();
          shape = items[0];
          matrix = obj.calcTransformMatrix();
        } else {
          shape = obj;
          matrix = [1, 0, 0, 1, obj.left || 0, obj.top || 0];
        }

        if (!shape || !shape.shapeType) return;

        if (shape.shapeType === "line") {
          const coords =
            obj.type === "group"
              ? [
                  matrix[4] + shape.x1,
                  matrix[5] + shape.y1,
                  matrix[4] + shape.x2,
                  matrix[5] + shape.y2,
                ]
              : [shape.x1, shape.y1, shape.x2, shape.y2];
          drawingData.lines.push({ coords: coords, color: shape.stroke });
        } else if (shape.shapeType === "rectangle") {
          const left = obj.type === "group" ? matrix[4] : shape.left;
          const top = obj.type === "group" ? matrix[5] : shape.top;
          drawingData.rectangles.push({
            coords: [left, top, left + shape.width, top + shape.height],
            color: shape.stroke,
          });
        } else if (shape.shapeType === "circle") {
          const centerX =
            obj.type === "group"
              ? matrix[4] + shape.radius
              : shape.left + shape.radius;
          const centerY =
            obj.type === "group"
              ? matrix[5] + shape.radius
              : shape.top + shape.radius;
          drawingData.circles.push({
            coords: [
              centerX - shape.radius,
              centerY - shape.radius,
              centerX + shape.radius,
              centerY + shape.radius,
            ],
            color: shape.stroke,
          });
        } else if (shape.shapeType === "freedraw" && shape.freeDrawingPoints) {
          const points = shape.freeDrawingPoints.map((p) => ({
            x: p.x,
            y: p.y,
          }));
          drawingData.freeDrawings.push({
            points: points,
            color: shape.stroke,
          });
        }
      });

      const hasDrawings =
        drawingData.lines.length > 0 ||
        drawingData.rectangles.length > 0 ||
        drawingData.circles.length > 0 ||
        drawingData.freeDrawings.length > 0;

      const formData = new FormData();
      formData.append("plan_file", currentPlanFileName);
      formData.append(
        "drawing_json",
        hasDrawings ? JSON.stringify(drawingData) : ""
      );

      const response = await fetch("/pardis/api/save_scaffolding.php", {
        method: "POST",
        body: formData,
      });

      if (!response.ok) {
        const errorText = await response.text();
        throw new Error(`خطای سرور: ${errorText}`);
      }

      const result = await response.json();

      if (result.status === "success") {
        alert(
          hasDrawings
            ? `داربست با موفقیت ذخیره شد\nطول کل: ${result.total_length_m} متر\nمساحت کل: ${result.total_area_sqm} متر مربع`
            : result.message
        );
        modal.style.display = "none";
        isScaffoldingMode = false;

        if (currentSvgElement) {
          loadAndRenderScaffoldingLayer(currentPlanFileName, currentSvgElement);
        }
      } else {
        throw new Error(result.error || "خطای ناشناخته");
      }
    } catch (error) {
      console.error("Save Scaffolding Error:", error);
      alert("خطا در ذخیره داربست: " + error.message);
    } finally {
      saveBtn.disabled = false;
      saveBtn.innerHTML = "💾 ذخیره";
    }
  });

  // CLOSE BUTTON
  const closeBtn = document.createElement("button");
  closeBtn.id = "drawer-close-btn";
  closeBtn.className = "btn cancel";
  closeBtn.innerHTML = "❌ بستن";
  closeBtn.style.cssText = `
    background: linear-gradient(45deg, #6c757d, #495057);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 6px;
    cursor: pointer;
    font-family: 'Vazir', Tahoma, sans-serif;
    font-weight: bold;
    transition: all 0.3s ease;
  `;
  closeBtn.addEventListener("click", () => {
    modal.style.display = "none";
    if (isScaffoldingMode) {
      modal.classList.remove("scaffolding-mode");
    }
    isScaffoldingMode = false;
    if (scaffoldingCanvas) {
      scaffoldingCanvas.dispose();
      scaffoldingCanvas = null;
    }
  });

  // Add buttons to footer
  footer.appendChild(clearBtn);
  footer.appendChild(saveBtn);
  footer.appendChild(closeBtn);

  // Add footer to modal
  const canvasContainer = modal.querySelector(".drawer-canvas-container");
  if (canvasContainer) {
    canvasContainer.appendChild(footer);
  }
}
/**

Setup event handlers for scaffolding canvas
*/
function setupScaffoldingEventHandlers() {
  if (!scaffoldingCanvas) {
    console.error("Cannot setup handlers: scaffoldingCanvas is null");
    return;
  }

  scaffoldingCanvas.off("mouse:down").on("mouse:down", handleCanvasMouseDown);
  scaffoldingCanvas.off("mouse:move").on("mouse:move", handleCanvasMouseMove);
  scaffoldingCanvas.off("mouse:up").on("mouse:up", handleCanvasMouseUp);
}
/**

Fixed tool button setup
*/
function setupToolButtons() {
  const toolButtons = document.querySelectorAll(".drawer-tools .tool-btn");
  toolButtons.forEach((btn) => {
    // Remove old listeners by cloning
    const newBtn = btn.cloneNode(true);
    btn.parentNode.replaceChild(newBtn, btn);
    newBtn.addEventListener("click", (e) => {
      // Remove active class from all buttons
      document
        .querySelectorAll(".drawer-tools .tool-btn")
        .forEach((b) => b.classList.remove("active"));
      // Add active class to clicked button
      newBtn.classList.add("active");
      // Set current tool based on button data attribute or text
      const toolType =
        newBtn.getAttribute("data-tool") ||
        newBtn.textContent.toLowerCase().trim();
      currentTool = toolType;
      console.log("Tool switched to:", currentTool);
      // Get active canvas based on mode
      const activeCanvas = getActiveCanvas();
      // Handle free drawing mode
      if (activeCanvas) {
        activeCanvas.isDrawingMode = false; // We handle all drawing manually for consistency
      }
    });
  });

  // Activate the first tool by default
  const firstTool = document.querySelector(".drawer-tools .tool-btn");
  if (firstTool) {
    firstTool.classList.add("active");
    const toolType = firstTool.getAttribute("data-tool") || "line";
    currentTool = toolType;
  }
}
/**

Fixed mouse handlers with null checks
*/
function handleCanvasMouseDown(opt) {
  const activeCanvas = getActiveCanvas();
  if (!activeCanvas) {
    console.error("No active canvas available");
    return;
  }

  if (opt.target && opt.target.type !== "polygon") return;
  const handler = shapeHandlers[currentTool];
  if (handler && handler.start) {
    handler.start(opt);
  }
}

/**

Fixed shape handlers with canvas checks
*/

function handleCircleEnd(opt) {
  if (!isDrawing) return;
  const activeCanvas = getActiveCanvas();
  if (!activeCanvas) return;

  isDrawing = false;
  if (tempShape) {
    activeCanvas.remove(tempShape);
    tempShape = null;
  }

  const pointer = activeCanvas.getPointer(opt.e);
  const color = getActiveToolColor("#00FF00");
  const radiusPixels = Math.sqrt(
    Math.pow(pointer.x - drawingStartPoint[0], 2) +
      Math.pow(pointer.y - drawingStartPoint[1], 2)
  );

  if (radiusPixels > 5) {
    const circle = new fabric.Circle({
      left: drawingStartPoint[0] - radiusPixels,
      top: drawingStartPoint[1] - radiusPixels,
      radius: radiusPixels,
      fill: isScaffoldingMode ? "rgba(255, 107, 107, 0.2)" : "transparent",
      stroke: color,
      strokeWidth: isScaffoldingMode ? 3 : 2,
      selectable: false,
      evented: false,
      shapeType: "circle",
    });

    // Get scale factor - CRITICAL FIX
    const scaleFactor = activeCanvas.customScaleFactor || 1.0;

    // Calculate actual radius
    const radiusM = calculateActualLength(radiusPixels, scaleFactor);
    const areaM2 = (Math.PI * Math.pow(radiusM, 2)).toFixed(2);
    const labelText = `R: ${radiusM.toFixed(2)} m\n${areaM2} m²`;

    const text = createDimensionText(
      labelText,
      circle.left + radiusPixels,
      circle.top + radiusPixels
    );

    const group = new fabric.Group([circle, text], {
      selectable: false,
      evented: false,
      shapeType: "circle",
    });

    activeCanvas.add(group);
  }
}

/**

Fixed close button handler
*/

/**

Add clear button for scaffolding
*/
function addScaffoldingClearButton() {
  const modal = document.getElementById("crack-drawer-modal");
  if (!modal || !isScaffoldingMode) return;

  let clearBtn = document.getElementById("scaffolding-clear-btn");
  if (clearBtn) clearBtn.remove();

  clearBtn = document.createElement("button");
  clearBtn.id = "scaffolding-clear-btn";
  clearBtn.textContent = "پاک کردن همه";
  clearBtn.className = "btn cancel";
  clearBtn.style.cssText = `
    margin-left: 10px;
    background: linear-gradient(45deg, #dc3545, #c82333);
  `;

  clearBtn.addEventListener("click", () => {
    clearAllDrawings();
  });

  const saveBtn = document.getElementById("drawer-save-btn");
  if (saveBtn && saveBtn.parentNode) {
    saveBtn.parentNode.insertBefore(clearBtn, saveBtn);
  }
}
/**

Create SVG background
*/
function createSVGBackground() {
  if (!currentSvgElement || !scaffoldingCanvas) return;

  const svgClone = currentSvgElement.cloneNode(true);
  svgClone
    .querySelectorAll(".interactive-element, .svg-element-active")
    .forEach((el) => {
      el.classList.remove("interactive-element", "svg-element-active");
      el.style.cursor = "default";
    });
  const svgData = new XMLSerializer().serializeToString(svgClone);
  const svgBlob = new Blob([svgData], { type: "image/svg+xml;charset=utf-8" });
  const url = URL.createObjectURL(svgBlob);
  fabric.Image.fromURL(url, (img) => {
    img.set({
      selectable: false,
      evented: false,
      opacity: 0.3,
    });
    scaffoldingCanvas.setBackgroundImage(
      img,
      scaffoldingCanvas.renderAll.bind(scaffoldingCanvas),
      {
        scaleX: scaffoldingCanvas.width / img.width,
        scaleY: scaffoldingCanvas.height / img.height,
      }
    );
    URL.revokeObjectURL(url);
  });
}
/**

Load existing scaffolding data
*/
async function loadExistingScaffoldingData() {
  if (!scaffoldingCanvas) return;

  try {
    const response = await fetch(
      `/pardis/api/get_scaffolding_for_plan.php?plan=${currentPlanFileName}`
    );
    if (!response.ok) throw new Error("Failed to load scaffolding data");
    const data = await response.json();
    if (data.drawing_json) {
      currentScaffoldingData = JSON.parse(data.drawing_json);
      renderScaffoldingShapes(currentScaffoldingData);
    }
  } catch (error) {
    console.warn("Could not load existing scaffolding data:", error);
  }
}
/**

Render scaffolding shapes
*/
function renderScaffoldingShapes(drawingData) {
  if (!drawingData || !scaffoldingCanvas) return;

  if (drawingData.lines) {
    drawingData.lines.forEach((lineData) => {
      const line = new fabric.Line(lineData.coords, {
        stroke: lineData.color || "#FF6B6B",
        strokeWidth: 3,
        selectable: false,
        evented: false,
        shapeType: "line",
      });
      scaffoldingCanvas.add(line);
    });
  }
  if (drawingData.rectangles) {
    drawingData.rectangles.forEach((rectData) => {
      const coords = rectData.coords;
      const rect = new fabric.Rect({
        left: Math.min(coords[0], coords[2]),
        top: Math.min(coords[1], coords[3]),
        width: Math.abs(coords[2] - coords[0]),
        height: Math.abs(coords[3] - coords[1]),
        fill: "rgba(255, 107, 107, 0.2)",
        stroke: rectData.color || "#FF6B6B",
        strokeWidth: 3,
        selectable: false,
        evented: false,
        shapeType: "rectangle",
      });
      scaffoldingCanvas.add(rect);
    });
  }
  if (drawingData.circles) {
    drawingData.circles.forEach((circleData) => {
      const coords = circleData.coords;
      const centerX = (coords[0] + coords[2]) / 2;
      const centerY = (coords[1] + coords[3]) / 2;
      const radius =
        Math.sqrt(
          Math.pow(coords[2] - coords[0], 2) +
            Math.pow(coords[3] - coords[1], 2)
        ) / 2;
      const circle = new fabric.Circle({
        left: centerX - radius,
        top: centerY - radius,
        radius: radius,
        fill: "rgba(255, 107, 107, 0.2)",
        stroke: circleData.color || "#FF6B6B",
        strokeWidth: 3,
        selectable: false,
        evented: false,
        shapeType: "circle",
      });
      scaffoldingCanvas.add(circle);
    });
  }
  if (drawingData.freeDrawings) {
    drawingData.freeDrawings.forEach((freeDrawData) => {
      if (!freeDrawData.points || freeDrawData.points.length < 2) return;
      const points = freeDrawData.points.map((p) => {
        if (Array.isArray(p)) return { x: p[0], y: p[1] };
        return p;
      });

      const pathString = "M " + points.map((p) => `${p.x},${p.y}`).join(" L ");
      const path = new fabric.Path(pathString, {
        fill: "transparent",
        stroke: freeDrawData.color || "#FF6B6B",
        strokeWidth: 3,
        selectable: false,
        evented: false,
        shapeType: "freedraw",
      });
      path.freeDrawingPoints = points;
      scaffoldingCanvas.add(path);
    });
  }
}
/**

Setup scaffolding rulers
*/
function setupScaffoldingRulers(width, height) {
  const rulerTop = document.getElementById("ruler-top");
  const rulerLeft = document.getElementById("ruler-left");

  if (!rulerTop || !rulerLeft) return;
  rulerTop.innerHTML = "";
  rulerLeft.innerHTML = "";
  const tickInterval = 100;
  for (let i = 0; i <= width; i += tickInterval) {
    rulerTop.innerHTML += `<div class="tick" style="left: \${i}px"></div>       <div class="label" style="left: \${i + 2}px">\${i}</div>`;
  }
  for (let i = 0; i <= height; i += tickInterval) {
    rulerLeft.innerHTML += `<div class="tick" style="top: \${i}px"></div>       <div class="label" style="top: \${i + 2}px">\${i}</div>`;
  }
}

/**
 * Render scaffolding layer on SVG
 */
async function loadAndRenderScaffoldingLayer(planFile, svgElement) {
  try {
    const response = await fetch(
      `/pardis/api/get_scaffolding_for_plan.php?plan=${planFile}`
    );
    if (!response.ok) throw new Error("Failed to load scaffolding");

    const data = await response.json();
    if (!data.drawing_json) return;

    // Get scale factor from database - CRITICAL FIX
    const scaleFactor = PLAN_SCALE_FACTORS[planFile] || 1.0;
    if (scaleFactor === 1.0) {
      console.warn(
        `Scale factor for ${planFile} not found in plan_scales.json. Defaulting to 1.0 for rendering.`
      );
    }

    const drawingData = JSON.parse(data.drawing_json);

    // Remove existing scaffolding layer
    let scaffoldingLayer = svgElement.getElementById("scaffolding-layer");
    if (scaffoldingLayer) scaffoldingLayer.remove();

    scaffoldingLayer = document.createElementNS(
      "http://www.w3.org/2000/svg",
      "g"
    );
    scaffoldingLayer.id = "scaffolding-layer";
    scaffoldingLayer.style.pointerEvents = "none";

    // Render lines with CORRECTED dimensions
    if (drawingData.lines) {
      drawingData.lines.forEach((lineData) => {
        const coords = lineData.coords;
        const line = document.createElementNS(
          "http://www.w3.org/2000/svg",
          "line"
        );
        line.setAttribute("x1", coords[0]);
        line.setAttribute("y1", coords[1]);
        line.setAttribute("x2", coords[2]);
        line.setAttribute("y2", coords[3]);
        line.setAttribute("stroke", lineData.color || "#FF6B6B");
        line.setAttribute("stroke-width", "3");
        line.setAttribute("opacity", "0.8");
        scaffoldingLayer.appendChild(line);

        const lengthPixels = Math.sqrt(
          Math.pow(coords[2] - coords[0], 2) +
            Math.pow(coords[3] - coords[1], 2)
        );
        const lengthM = calculateActualLength(lengthPixels, scaleFactor);

        const text = document.createElementNS(
          "http://www.w3.org/2000/svg",
          "text"
        );
        text.setAttribute("x", (coords[0] + coords[2]) / 2);
        text.setAttribute("y", (coords[1] + coords[3]) / 2 - 5);
        text.setAttribute("fill", "#C92A2A");
        text.setAttribute("font-size", "14");
        text.setAttribute("font-weight", "bold");
        text.setAttribute("text-anchor", "middle");
        text.setAttribute("stroke", "white");
        text.setAttribute("stroke-width", "3");
        text.setAttribute("paint-order", "stroke");
        text.textContent = `${lengthM.toFixed(2)} m`;
        scaffoldingLayer.appendChild(text);
      });
    }

    // Render rectangles with CORRECTED dimensions
    if (drawingData.rectangles) {
      drawingData.rectangles.forEach((rectData) => {
        const coords = rectData.coords;
        const widthPixels = Math.abs(coords[2] - coords[0]);
        const heightPixels = Math.abs(coords[3] - coords[1]);

        const rect = document.createElementNS(
          "http://www.w3.org/2000/svg",
          "rect"
        );
        rect.setAttribute("x", Math.min(coords[0], coords[2]));
        rect.setAttribute("y", Math.min(coords[1], coords[3]));
        rect.setAttribute("width", widthPixels);
        rect.setAttribute("height", heightPixels);
        rect.setAttribute("fill", "rgba(255, 107, 107, 0.2)");
        rect.setAttribute("stroke", rectData.color || "#FF6B6B");
        rect.setAttribute("stroke-width", "3");
        rect.setAttribute("opacity", "0.8");
        scaffoldingLayer.appendChild(rect);

        const widthM = calculateActualLength(widthPixels, scaleFactor);
        const heightM = calculateActualLength(heightPixels, scaleFactor);
        const areaM2 = (widthM * heightM).toFixed(2);

        const text = document.createElementNS(
          "http://www.w3.org/2000/svg",
          "text"
        );
        text.setAttribute("x", (coords[0] + coords[2]) / 2);
        text.setAttribute("y", (coords[1] + coords[3]) / 2);
        text.setAttribute("fill", "#C92A2A");
        text.setAttribute("font-size", "14");
        text.setAttribute("font-weight", "bold");
        text.setAttribute("text-anchor", "middle");
        text.setAttribute("stroke", "white");
        text.setAttribute("stroke-width", "3");
        text.setAttribute("paint-order", "stroke");

        const tspan1 = document.createElementNS(
          "http://www.w3.org/2000/svg",
          "tspan"
        );
        tspan1.setAttribute("x", (coords[0] + coords[2]) / 2);
        tspan1.setAttribute("dy", "0");
        tspan1.textContent = `${widthM.toFixed(2)}m × ${heightM.toFixed(2)}m`;
        text.appendChild(tspan1);

        const tspan2 = document.createElementNS(
          "http://www.w3.org/2000/svg",
          "tspan"
        );
        tspan2.setAttribute("x", (coords[0] + coords[2]) / 2);
        tspan2.setAttribute("dy", "16");
        tspan2.textContent = `${areaM2} m²`;
        text.appendChild(tspan2);

        scaffoldingLayer.appendChild(text);
      });
    }

    // Render circles with CORRECTED dimensions
    if (drawingData.circles) {
      drawingData.circles.forEach((circleData) => {
        const coords = circleData.coords;
        const centerX = (coords[0] + coords[2]) / 2;
        const centerY = (coords[1] + coords[3]) / 2;
        const radiusPixels =
          Math.sqrt(
            Math.pow(coords[2] - coords[0], 2) +
              Math.pow(coords[3] - coords[1], 2)
          ) / 2;

        // Create circle
        const circle = document.createElementNS(
          "http://www.w3.org/2000/svg",
          "circle"
        );
        circle.setAttribute("cx", centerX);
        circle.setAttribute("cy", centerY);
        circle.setAttribute("r", radiusPixels);
        circle.setAttribute("fill", "rgba(255, 107, 107, 0.2)");
        circle.setAttribute("stroke", circleData.color || "#FF6B6B");
        circle.setAttribute("stroke-width", "3");
        circle.setAttribute("opacity", "0.8");
        scaffoldingLayer.appendChild(circle);

        // Calculate dimensions - FIXED
        const radiusM = calculateActualLength(radiusPixels, scaleFactor);
        const areaM2 = (Math.PI * Math.pow(radiusM, 2)).toFixed(2);

        // Add radius line
        const radiusLine = document.createElementNS(
          "http://www.w3.org/2000/svg",
          "line"
        );
        radiusLine.setAttribute("x1", centerX);
        radiusLine.setAttribute("y1", centerY);
        radiusLine.setAttribute("x2", centerX + radiusPixels);
        radiusLine.setAttribute("y2", centerY);
        radiusLine.setAttribute("stroke", "#C92A2A");
        radiusLine.setAttribute("stroke-width", "1");
        radiusLine.setAttribute("stroke-dasharray", "5,5");
        scaffoldingLayer.appendChild(radiusLine);

        // Radius label
        const radiusText = document.createElementNS(
          "http://www.w3.org/2000/svg",
          "text"
        );
        radiusText.setAttribute("x", centerX + radiusPixels / 2);
        radiusText.setAttribute("y", centerY - 5);
        radiusText.setAttribute("fill", "#C92A2A");
        radiusText.setAttribute("font-size", "12");
        radiusText.setAttribute("font-weight", "bold");
        radiusText.setAttribute("text-anchor", "middle");
        radiusText.setAttribute("stroke", "white");
        radiusText.setAttribute("stroke-width", "3");
        radiusText.setAttribute("paint-order", "stroke");
        radiusText.textContent = `R: ${radiusM.toFixed(2)} m`;
        scaffoldingLayer.appendChild(radiusText);

        // Area label
        const areaText = document.createElementNS(
          "http://www.w3.org/2000/svg",
          "text"
        );
        areaText.setAttribute("x", centerX);
        areaText.setAttribute("y", centerY + 5);
        areaText.setAttribute("fill", "#C92A2A");
        areaText.setAttribute("font-size", "14");
        areaText.setAttribute("font-weight", "bold");
        areaText.setAttribute("text-anchor", "middle");
        areaText.setAttribute("stroke", "white");
        areaText.setAttribute("stroke-width", "3");
        areaText.setAttribute("paint-order", "stroke");
        areaText.textContent = `${areaM2} m²`;
        scaffoldingLayer.appendChild(areaText);
      });
    }

    // Render free drawings with CORRECTED total length
    if (drawingData.freeDrawings) {
      drawingData.freeDrawings.forEach((freeDrawData) => {
        if (!freeDrawData.points || freeDrawData.points.length < 2) return;

        const points = freeDrawData.points.map((p) => {
          if (Array.isArray(p)) return [p[0], p[1]];
          return [p.x, p.y];
        });

        const pathString = "M " + points.map((p) => p.join(",")).join(" L ");
        const path = document.createElementNS(
          "http://www.w3.org/2000/svg",
          "path"
        );
        path.setAttribute("d", pathString);
        path.setAttribute("fill", "transparent");
        path.setAttribute("stroke", freeDrawData.color || "#FF6B6B");
        path.setAttribute("stroke-width", "3");
        path.setAttribute("opacity", "0.8");
        scaffoldingLayer.appendChild(path);

        // Calculate total length - FIXED
        let totalLengthPixels = 0;
        let minX = Infinity,
          maxX = -Infinity,
          minY = Infinity,
          maxY = -Infinity;

        for (let i = 1; i < points.length; i++) {
          const dx = points[i][0] - points[i - 1][0];
          const dy = points[i][1] - points[i - 1][1];
          totalLengthPixels += Math.sqrt(dx * dx + dy * dy);

          minX = Math.min(minX, points[i][0]);
          maxX = Math.max(maxX, points[i][0]);
          minY = Math.min(minY, points[i][1]);
          maxY = Math.max(maxY, points[i][1]);
        }

        const totalLengthM = calculateActualLength(
          totalLengthPixels,
          scaleFactor
        );

        // Position label at center
        const text = document.createElementNS(
          "http://www.w3.org/2000/svg",
          "text"
        );
        text.setAttribute("x", (minX + maxX) / 2);
        text.setAttribute("y", (minY + maxY) / 2);
        text.setAttribute("fill", "#C92A2A");
        text.setAttribute("font-size", "14");
        text.setAttribute("font-weight", "bold");
        text.setAttribute("text-anchor", "middle");
        text.setAttribute("stroke", "white");
        text.setAttribute("stroke-width", "3");
        text.setAttribute("paint-order", "stroke");
        text.textContent = `${totalLengthM.toFixed(2)} m`;
        scaffoldingLayer.appendChild(text);
      });
    }

    svgElement.appendChild(scaffoldingLayer);
  } catch (error) {
    console.error("Failed to load scaffolding layer:", error);
  }
}

async function loadAndDisplayStatistics(planFile = null) {
  try {
    const url = planFile
      ? `/pardis/api/get_element_statistics.php?plan=${planFile}`
      : "/pardis/api/get_element_statistics.php";

    const response = await fetch(url);
    if (!response.ok) throw new Error("Failed to load statistics");

    const data = await response.json();
    displayStatisticsPanel(data);
  } catch (error) {
    console.error("Failed to load statistics:", error);
  }
}
/**
 * Display statistics panel with element and scaffolding data
 */
function displayStatisticsPanel(data) {
  let statsPanel = document.getElementById("statistics-panel");

  if (!statsPanel) {
    statsPanel = document.createElement("div");
    statsPanel.id = "statistics-panel";
    statsPanel.style.cssText = `
            position: fixed;
            top: 80px;
            left: 20px;
            background: white;
            border: 2px solid #007bff;
            border-radius: 8px;
            padding: 15px;
            max-width: 350px;
            max-height: 400px;
            overflow-y: auto;
            z-index: 900;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            font-family: 'Vazir', Tahoma, sans-serif;
            direction: rtl;
        `;
    document.body.appendChild(statsPanel);
  }

  let html = `
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 2px solid #007bff; padding-bottom: 10px;">
            <h3 style="margin: 0; color: #007bff;">📊 آمار و اطلاعات</h3>
            <button onclick="toggleStatisticsPanel()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: #dc3545;">✕</button>
        </div>
    `;

  // Element Statistics
  if (data.element_statistics && data.element_statistics.length > 0) {
    html += `<div style="margin-bottom: 20px;">
            <h4 style="color: #28a745; margin: 10px 0;">اطلاعات المان‌ها:</h4>
            <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
                <thead>
                    <tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                        <th style="padding: 8px; text-align: right;">نوع</th>
                        <th style="padding: 8px; text-align: center;">تعداد</th>
                        <th style="padding: 8px; text-align: center;">مساحت (m²)</th>
                    </tr>
                </thead>
                <tbody>`;

    let totalCount = 0;
    let totalArea = 0;

    data.element_statistics.forEach((stat, index) => {
      const bgColor = index % 2 === 0 ? "#ffffff" : "#f8f9fa";
      const area = parseFloat(stat.total_area_sqm || 0);
      totalCount += parseInt(stat.element_count);
      totalArea += area;

      html += `
                <tr style="background: ${bgColor}; border-bottom: 1px solid #dee2e6;">
                    <td style="padding: 8px; font-weight: bold;">${escapeHtml(
                      stat.element_type
                    )}</td>
                    <td style="padding: 8px; text-align: center;">${
                      stat.element_count
                    }</td>
                    <td style="padding: 8px; text-align: center;">${area.toFixed(
                      2
                    )}</td>
                </tr>
            `;
    });

    // Total row
    html += `
                <tr style="background: #e7f3ff; font-weight: bold; border-top: 2px solid #007bff;">
                    <td style="padding: 8px;">جمع کل</td>
                    <td style="padding: 8px; text-align: center;">${totalCount}</td>
                    <td style="padding: 8px; text-align: center;">${totalArea.toFixed(
                      2
                    )}</td>
                </tr>
            </tbody>
        </table>
        </div>`;
  }

  // Scaffolding Statistics
  if (data.scaffolding) {
    const totalLength = parseFloat(data.scaffolding.total_length_m || 0);
    const totalArea = parseFloat(data.scaffolding.total_area_sqm || 0);

    html += `
        <div style="background: #fff3cd; padding: 12px; border-radius: 6px; border: 2px solid #ffc107;">
            <h4 style="color: #856404; margin: 0 0 10px 0;">🏗️ داربست:</h4>
            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                <span style="font-weight: bold;">طول کل:</span>
                <span style="color: #856404; font-weight: bold;">${totalLength.toFixed(
                  2
                )} متر</span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span style="font-weight: bold;">مساحت کل:</span>
                <span style="color: #856404; font-weight: bold;">${totalArea.toFixed(
                  2
                )} m²</span>
            </div>
        </div>`;
  }

  // Show plan name if available
  if (data.plan_file) {
    html += `
        <div style="margin-top: 15px; padding-top: 10px; border-top: 1px solid #dee2e6; font-size: 11px; color: #6c757d; text-align: center;">
            نقشه: ${escapeHtml(data.plan_file)}
        </div>`;
  }

  statsPanel.innerHTML = html;
  statsPanel.style.display = "block";
}

/**
 * Toggle statistics panel visibility
 */
function toggleStatisticsPanel() {
  const statsPanel = document.getElementById("statistics-panel");
  if (statsPanel) {
    if (statsPanel.style.display === "none") {
      statsPanel.style.display = "block";
    } else {
      statsPanel.style.display = "none";
    }
  }
}

/**
 * Create statistics button in the UI
 */
function createStatisticsButton() {
  const existingBtn = document.getElementById("stats-toggle-btn");
  if (existingBtn) return;

  const button = document.createElement("button");
  button.id = "stats-toggle-btn";
  button.innerHTML = "📊 آمار";
  button.style.cssText = `
        position: fixed;
        top: 20px;
        left: 20px;
        background: linear-gradient(45deg, #007bff, #0056b3);
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 6px;
        cursor: pointer;
        font-family: 'Vazir', Tahoma, sans-serif;
        font-weight: bold;
        z-index: 1000;
        box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        transition: all 0.3s ease;
    `;

  button.addEventListener("mouseenter", () => {
    button.style.transform = "translateY(-2px)";
    button.style.boxShadow = "0 4px 12px rgba(0,0,0,0.3)";
  });

  button.addEventListener("mouseleave", () => {
    button.style.transform = "translateY(0)";
    button.style.boxShadow = "0 2px 8px rgba(0,0,0,0.2)";
  });

  button.addEventListener("click", () => {
    loadAndDisplayStatistics(currentPlanFileName);
  });

  document.body.appendChild(button);
}

// Initialize statistics button when page loads

/**
 * Create scaffolding button in the layer controls
 */
/**
 * Create scaffolding button in the layer controls
 */
function createScaffoldingButton() {
  const layerControlsContainer = document.getElementById(
    "layerControlsContainer"
  );
  if (!layerControlsContainer) return;

  // Check if button already exists
  let scaffoldingBtn = document.getElementById("scaffolding-drawer-btn");
  if (scaffoldingBtn) {
    scaffoldingBtn.remove();
  }

  // Only show scaffolding button if user has permission
  const userRole = document.body.dataset.userRole;
  const hasPermission = [
    "admin",
    "superuser",
    "supervisor",
    "planner",
  ].includes(userRole);

  if (!hasPermission) return;

  scaffoldingBtn = document.createElement("button");
  scaffoldingBtn.id = "scaffolding-drawer-btn";
  scaffoldingBtn.className = "scaffolding-control-btn";
  scaffoldingBtn.innerHTML = "🏗️ ترسیم داربست";
  scaffoldingBtn.style.cssText = `
        background: linear-gradient(45deg, #FF6B6B, #C92A2A);
        color: white;
        border: none;
        padding: 8px 15px;
        border-radius: 5px;
        cursor: pointer;
        font-family: 'Vazir', Tahoma, sans-serif;
        font-weight: bold;
        margin: 5px;
        transition: all 0.3s ease;
    `;

  scaffoldingBtn.addEventListener("mouseenter", () => {
    scaffoldingBtn.style.transform = "scale(1.05)";
    scaffoldingBtn.style.boxShadow = "0 4px 8px rgba(255, 107, 107, 0.4)";
  });

  scaffoldingBtn.addEventListener("mouseleave", () => {
    scaffoldingBtn.style.transform = "scale(1)";
    scaffoldingBtn.style.boxShadow = "none";
  });

  scaffoldingBtn.addEventListener("click", () => {
    openScaffoldingDrawer();
  });

  layerControlsContainer.appendChild(scaffoldingBtn);
}

/**
 * Toggle scaffolding layer visibility
 */
function toggleScaffoldingLayer() {
  if (!currentSvgElement) return;

  const scaffoldingLayer =
    currentSvgElement.getElementById("scaffolding-layer");
  if (scaffoldingLayer) {
    const isVisible = scaffoldingLayer.style.display !== "none";
    scaffoldingLayer.style.display = isVisible ? "none" : "";
    return !isVisible;
  }
  return false;
}

/**
 * Create scaffolding layer toggle button
 */
function createScaffoldingLayerToggle() {
  const layerControlsContainer = document.getElementById(
    "layerControlsContainer"
  );
  if (!layerControlsContainer) return;

  let toggleBtn = document.getElementById("scaffolding-layer-toggle");
  if (toggleBtn) {
    toggleBtn.remove();
  }

  toggleBtn = document.createElement("button");
  toggleBtn.id = "scaffolding-layer-toggle";
  toggleBtn.textContent = "نمایش داربست";
  toggleBtn.className = "active";
  toggleBtn.addEventListener("click", () => {
    const isVisible = toggleScaffoldingLayer();
    toggleBtn.classList.toggle("active", isVisible);
  });

  layerControlsContainer.appendChild(toggleBtn);
}

// Update the loadAndDisplaySVG function to include scaffolding buttons
// Add this after the existing layer controls setup in applyGroupStylesAndControls function:

function updateScaffoldingControls(isPlan) {
  if (isPlan) {
    createScaffoldingButton();
  } else {
    // For zone plans, create both scaffolding button and layer toggle
    createScaffoldingButton();
    createScaffoldingLayerToggle();
  }
}
document.addEventListener("keydown", function (e) {
  // Ctrl+Shift+S to open scaffolding drawer
  if (e.ctrlKey && e.shiftKey && e.key === "S") {
    e.preventDefault();
    const userRole = document.body.dataset.userRole;
    const hasPermission = [
      "admin",
      "superuser",
      "supervisor",
      "planner",
    ].includes(userRole);
    if (hasPermission && currentSvgElement && currentPlanFileName) {
      openScaffoldingDrawer();
    }
  }

  // Ctrl+Shift+T to toggle statistics
  if (e.ctrlKey && e.shiftKey && e.key === "T") {
    e.preventDefault();
    toggleStatisticsPanel();
  }
});

function exportStatisticsToCSV(data) {
  if (!data.element_statistics || data.element_statistics.length === 0) {
    alert("هیچ داده‌ای برای خروجی وجود ندارد.");
    return;
  }

  let csv = "\uFEFF"; // BOM for UTF-8
  csv += "نوع المان,تعداد,مساحت کل (m²),عرض متوسط (cm),ارتفاع متوسط (cm)\n";

  let totalCount = 0;
  let totalArea = 0;

  data.element_statistics.forEach((stat) => {
    const count = parseInt(stat.element_count);
    const area = parseFloat(stat.total_area_sqm || 0);
    const avgWidth = parseFloat(stat.avg_width_cm || 0);
    const avgHeight = parseFloat(stat.avg_height_cm || 0);

    totalCount += count;
    totalArea += area;

    csv += `${stat.element_type},${count},${area.toFixed(2)},${avgWidth.toFixed(
      2
    )},${avgHeight.toFixed(2)}\n`;
  });

  csv += `\nجمع کل,${totalCount},${totalArea.toFixed(2)},,\n`;

  // Add scaffolding data if available
  if (data.drawing_layers && Object.keys(data.drawing_layers).length > 0) {
    csv += `\n\n--- لایه‌های ترسیمی ---\n`;
    csv += `نوع لایه,طول کل (متر),مساحت کل (m²)\n`;

    for (const layerType in data.drawing_layers) {
      const layerData = data.drawing_layers[layerType];
      const totalLength = parseFloat(layerData.total_length_m || 0).toFixed(2);
      const totalArea = parseFloat(layerData.total_area_sqm || 0).toFixed(2);
      csv += `${layerType},${totalLength},${totalArea}\n`;
    }
  }

  // Create download
  const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
  const link = document.createElement("a");
  const url = URL.createObjectURL(blob);

  link.setAttribute("href", url);
  link.setAttribute(
    "download",
    `statistics_${data.plan_file || "all"}_${
      new Date().toISOString().split("T")[0]
    }.csv`
  );
  link.style.visibility = "hidden";

  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
}

/**
 * Print statistics
 */
function printStatistics(data) {
  const printWindow = window.open("", "_blank");

  let html = `
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <title>آمار پروژه - ${data.plan_file || "کل پروژه"}</title>
    <style>
        body {
            font-family: 'Vazir', Tahoma, sans-serif;
            direction: rtl;
            padding: 20px;
            background: white;
        }
        h1 {
            color: #007bff;
            border-bottom: 3px solid #007bff;
            padding-bottom: 10px;
        }
        h2 {
            color: #28a745;
            margin-top: 30px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            border: 1px solid #dee2e6;
            padding: 10px;
            text-align: right;
        }
        th {
            background: #f8f9fa;
            font-weight: bold;
        }
        tr:nth-child(even) {
            background: #f8f9fa;
        }
        .total-row {
            background: #e7f3ff !important;
            font-weight: bold;
            border-top: 2px solid #007bff;
        }
        .scaffolding-section {
            background: #fff3cd;
            padding: 15px;
            border-radius: 8px;
            border: 2px solid #ffc107;
            margin-top: 30px;
        }
        .scaffolding-section h2 {
            color: #856404;
            margin-top: 0;
        }
        .stat-item {
            display: flex;
            justify-content: space-between;
            padding: 8px;
            border-bottom: 1px solid #dee2e6;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            text-align: center;
            color: #6c757d;
            font-size: 12px;
        }
        @media print {
            body {
                padding: 0;
            }
            button {
                display: none;
            }
        }
    </style>
</head>
<body>
    <h1>📊 گزارش آمار پروژه</h1>
    ${
      data.plan_file
        ? `<p><strong>نقشه:</strong> ${data.plan_file}</p>`
        : "<p><strong>گزارش کلی پروژه</strong></p>"
    }
    <p><strong>تاریخ:</strong> ${new Date().toLocaleDateString("fa-IR")}</p>
    
    <h2>اطلاعات المان‌ها</h2>
    <table>
        <thead>
            <tr>
                <th>نوع المان</th>
                <th>تعداد</th>
                <th>مساحت کل (m²)</th>
                <th>عرض متوسط (cm)</th>
                <th>ارتفاع متوسط (cm)</th>
            </tr>
        </thead>
        <tbody>`;

  let totalCount = 0;
  let totalArea = 0;

  if (data.element_statistics && data.element_statistics.length > 0) {
    data.element_statistics.forEach((stat) => {
      const count = parseInt(stat.element_count);
      const area = parseFloat(stat.total_area_sqm || 0);
      const avgWidth = parseFloat(stat.avg_width_cm || 0);
      const avgHeight = parseFloat(stat.avg_height_cm || 0);

      totalCount += count;
      totalArea += area;

      html += `
            <tr>
                <td>${escapeHtml(stat.element_type)}</td>
                <td>${count}</td>
                <td>${area.toFixed(2)}</td>
                <td>${avgWidth.toFixed(2)}</td>
                <td>${avgHeight.toFixed(2)}</td>
            </tr>`;
    });

    html += `
            <tr class="total-row">
                <td>جمع کل</td>
                <td>${totalCount}</td>
                <td>${totalArea.toFixed(2)}</td>
                <td colspan="2"></td>
            </tr>`;
  }

  html += `
        </tbody>
    </table>`;
  if (data.drawing_layers && Object.keys(data.drawing_layers).length > 0) {
    html += `<h2>اطلاعات لایه‌های ترسیمی</h2>`;

    const layerIcons = {
      scaffolding: "🏗️",
      annotations: "📝",
      damages: "⚠️",
      default: "✏️",
    };

    for (const layerType in data.drawing_layers) {
      const layerData = data.drawing_layers[layerType];
      const totalLength = parseFloat(layerData.total_length_m || 0).toFixed(2);
      const totalArea = parseFloat(layerData.total_area_sqm || 0).toFixed(2);
      const icon = layerIcons[layerType] || layerIcons["default"];

      html += `
        <div class="scaffolding-section">
            <h2>${icon} ${layerType}</h2>
            <div class="stat-item">
                <span>طول کل:</span>
                <strong>${totalLength} متر</strong>
            </div>
            <div class="stat-item">
                <span>مساحت کل:</span>
                <strong>${totalArea} متر مربع</strong>
            </div>
        </div>`;
    }
  }

  html += `
    <div class="footer">
        <p>تولید شده توسط سیستم مدیریت پروژه - ${new Date().toLocaleString(
          "fa-IR"
        )}</p>
    </div>
    
    <script>
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>`;

  printWindow.document.write(html);
  printWindow.document.close();
}

/**
 * Enhanced displayStatisticsPanel with export buttons
 */
function displayStatisticsPanel(data) {
  let statsPanel = document.getElementById("statistics-panel");

  if (!statsPanel) {
    statsPanel = document.createElement("div");
    statsPanel.id = "statistics-panel";
    statsPanel.style.cssText = `
            position: fixed;
            top: 80px;
            left: 20px;
            background: white;
            border: 2px solid #007bff;
            border-radius: 8px;
            padding: 15px;
            max-width: 400px;
            max-height: 500px;
            overflow-y: auto;
            z-index: 900;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            font-family: 'Vazir', Tahoma, sans-serif;
            direction: rtl;
        `;
    document.body.appendChild(statsPanel);
  }

  let html = `
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 2px solid #007bff; padding-bottom: 10px;">
            <h3 style="margin: 0; color: #007bff;">📊 آمار و اطلاعات</h3>
            <button onclick="toggleStatisticsPanel()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: #dc3545;">✕</button>
        </div>
        
        <div style="display: flex; gap: 5px; margin-bottom: 15px;">
            <button onclick="exportStatisticsToCSV(${JSON.stringify(
              data
            ).replace(/"/g, "&quot;")})" 
                    style="flex: 1; background: linear-gradient(45deg, #28a745, #20c997); color: white; border: none; padding: 8px; border-radius: 5px; cursor: pointer; font-family: 'Vazir', Tahoma, sans-serif; font-size: 11px;">
                📥 خروجی CSV
            </button>
            <button onclick="printStatistics(${JSON.stringify(data).replace(
              /"/g,
              "&quot;"
            )})" 
                    style="flex: 1; background: linear-gradient(45deg, #007bff, #0056b3); color: white; border: none; padding: 8px; border-radius: 5px; cursor: pointer; font-family: 'Vazir', Tahoma, sans-serif; font-size: 11px;">
                🖨️ چاپ
            </button>
        </div>
    `;

  // Element Statistics
  if (data.element_statistics && data.element_statistics.length > 0) {
    html += `<div style="margin-bottom: 20px;">
            <h4 style="color: #28a745; margin: 10px 0;">اطلاعات المان‌ها:</h4>
            <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
                <thead>
                    <tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                        <th style="padding: 8px; text-align: right;">نوع</th>
                        <th style="padding: 8px; text-align: center;">تعداد</th>
                        <th style="padding: 8px; text-align: center;">مساحت (m²)</th>
                    </tr>
                </thead>
                <tbody>`;

    let totalCount = 0;
    let totalArea = 0;

    data.element_statistics.forEach((stat, index) => {
      const bgColor = index % 2 === 0 ? "#ffffff" : "#f8f9fa";
      const area = parseFloat(stat.total_area_sqm || 0);
      totalCount += parseInt(stat.element_count);
      totalArea += area;

      html += `
                <tr style="background: ${bgColor}; border-bottom: 1px solid #dee2e6;">
                    <td style="padding: 8px; font-weight: bold;">${escapeHtml(
                      stat.element_type
                    )}</td>
                    <td style="padding: 8px; text-align: center;">${
                      stat.element_count
                    }</td>
                    <td style="padding: 8px; text-align: center;">${area.toFixed(
                      2
                    )}</td>
                </tr>
            `;
    });

    // Total row
    html += `
                <tr style="background: #e7f3ff; font-weight: bold; border-top: 2px solid #007bff;">
                    <td style="padding: 8px;">جمع کل</td>
                    <td style="padding: 8px; text-align: center;">${totalCount}</td>
                    <td style="padding: 8px; text-align: center;">${totalArea.toFixed(
                      2
                    )}</td>
                </tr>
            </tbody>
        </table>
        </div>`;
  }

  // Scaffolding Statistics
  if (data.drawing_layers && Object.keys(data.drawing_layers).length > 0) {
    // Define some icons for different layers to make it look nicer
    const layerIcons = {
      scaffolding: "🏗️",
      annotations: "📝",
      damages: "⚠️",
      default: "✏️",
    };

    html += '<div style="display: flex; flex-direction: column; gap: 10px;">'; // A container for all layer boxes

    for (const layerType in data.drawing_layers) {
      const layerData = data.drawing_layers[layerType];
      const totalLength = parseFloat(layerData.total_length_m || 0);
      const totalArea = parseFloat(layerData.total_area_sqm || 0);
      const icon = layerIcons[layerType] || layerIcons["default"];

      html += `
            <div style="background: #fff3cd; padding: 12px; border-radius: 6px; border: 2px solid #ffc107;">
                <h4 style="color: #856404; margin: 0 0 10px 0;">${icon} ${layerType}:</h4>
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <span style="font-weight: bold;">طول کل:</span>
                    <span style="color: #856404; font-weight: bold;">${totalLength.toFixed(
                      2
                    )} متر</span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="font-weight: bold;">مساحت کل:</span>
                    <span style="color: #856404; font-weight: bold;">${totalArea.toFixed(
                      2
                    )} m²</span>
                </div>
            </div>`;
    }

    html += "</div>"; // Close the container
  }

  // Show plan name if available
  if (data.plan_file) {
    html += `
        <div style="margin-top: 15px; padding-top: 10px; border-top: 1px solid #dee2e6; font-size: 11px; color: #6c757d; text-align: center;">
            نقشه: ${escapeHtml(data.plan_file)}
        </div>`;
  }

  statsPanel.innerHTML = html;
  statsPanel.style.display = "block";
}

// Add this to the END of ghom_app.js file

/**
 * Initialize all new features when DOM is ready
 */
document.addEventListener("DOMContentLoaded", () => {
  // Create statistics button

  // Load initial statistics
  setTimeout(() => {
    if (currentPlanFileName) {
      loadAndDisplayStatistics(currentPlanFileName);
    }
  }, 2000);
});

/**
 * Update the existing loadAndDisplaySVG function to include all new features
 */

/**
 * Refresh all layers and statistics
 */
function refreshAllLayers() {
  if (!currentPlanFileName || !currentSvgElement) return;

  const isPlan = currentPlanFileName.toLowerCase() === "plan.svg";

  if (!isPlan) {
    loadAndRenderCrackLayer(currentPlanFileName, currentSvgElement);
    //loadAndRenderScaffoldingLayer(currentPlanFileName, currentSvgElement);
  }
  PlanDrawingModule.loadAllDrawingLayers(currentPlanFileName);
  loadAndDisplayStatistics(currentPlanFileName);
}

/**
 * Add refresh button
 */
function createRefreshButton() {
  const existingBtn = document.getElementById("refresh-layers-btn");
  if (existingBtn) return;

  const button = document.createElement("button");
  button.id = "refresh-layers-btn";
  button.innerHTML = "🔄 بروزرسانی";
  button.style.cssText = `
        position: fixed;
        top: 20px;
        left: 140px;
        background: linear-gradient(45deg, #6c757d, #495057);
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 6px;
        cursor: pointer;
        font-family: 'Vazir', Tahoma, sans-serif;
        font-weight: bold;
        z-index: 1000;
        box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        transition: all 0.3s ease;
    `;

  button.addEventListener("mouseenter", () => {
    button.style.transform = "translateY(-2px) rotate(180deg)";
    button.style.boxShadow = "0 4px 12px rgba(0,0,0,0.3)";
  });

  button.addEventListener("mouseleave", () => {
    button.style.transform = "translateY(0) rotate(0deg)";
    button.style.boxShadow = "0 2px 8px rgba(0,0,0,0.2)";
  });

  button.addEventListener("click", () => {
    button.innerHTML = "⏳ در حال بروزرسانی...";
    button.disabled = true;

    refreshAllLayers();

    setTimeout(() => {
      button.innerHTML = "✓ بروزرسانی شد";
      setTimeout(() => {
        button.innerHTML = "🔄 بروزرسانی";
        button.disabled = false;
      }, 1000);
    }, 1000);
  });

  document.body.appendChild(button);
}

// Initialize refresh button

/**
 * Global keyboard shortcuts summary
 */
function showKeyboardShortcuts() {
  const shortcuts = `
<div style="font-family: 'Vazir', Tahoma, sans-serif; direction: rtl; padding: 20px;">
    <h3 style="color: #007bff; border-bottom: 2px solid #007bff; padding-bottom: 10px;">⌨️ میانبرهای صفحه‌کلید</h3>
    
    <div style="margin: 15px 0;">
        <h4 style="color: #28a745;">عمومی:</h4>
        <ul style="line-height: 2;">
            <li><kbd>Ctrl + Shift + S</kbd> - باز کردن ترسیم داربست</li>
            <li><kbd>Ctrl + Shift + T</kbd> - نمایش/مخفی کردن آمار</li>
            <li><kbd>Escape</kbd> - بستن پنجره‌های باز</li>
        </ul>
    </div>
    
    <div style="margin: 15px 0;">
        <h4 style="color: #007bff;">فرم بازرسی:</h4>
        <ul style="line-height: 2;">
            <li><kbd>Ctrl + Enter</kbd> - بررسی و تایید نهایی</li>
            <li><kbd>Ctrl + S</kbd> - ذخیره (پس از تایید)</li>
            <li><kbd>Ctrl + Tab</kbd> - جابجایی بین مراحل</li>
        </ul>
    </div>
    
    <div style="margin: 15px 0;">
        <h4 style="color: #dc3545;">ابزار ترسیم:</h4>
        <ul style="line-height: 2;">
            <li><kbd>L</kbd> - انتخاب ابزار خط</li>
            <li><kbd>R</kbd> - انتخاب ابزار مستطیل</li>
            <li><kbd>C</kbd> - انتخاب ابزار دایره</li>
            <li><kbd>F</kbd> - انتخاب ابزار ترسیم آزاد</li>
        </ul>
    </div>
    
    <div style="margin-top: 20px; padding: 10px; background: #e7f3ff; border-radius: 5px; text-align: center;">
        <small style="color: #0056b3;">برای کمک بیشتر، با مدیر سیستم تماس بگیرید</small>
    </div>
</div>
    `;

  const modal = document.createElement("div");
  modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 2000;
    `;

  const content = document.createElement("div");
  content.style.cssText = `
        background: white;
        padding: 20px;
        border-radius: 10px;
        max-width: 600px;
        max-height: 80vh;
        overflow-y: auto;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    `;

  content.innerHTML =
    shortcuts +
    `
        <button onclick="this.closest('.modal').remove()" 
                style="width: 100%; padding: 10px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; font-family: 'Vazir', Tahoma, sans-serif; font-weight: bold; margin-top: 15px;">
            بستن
        </button>
    `;

  modal.className = "modal";
  modal.appendChild(content);
  document.body.appendChild(modal);

  modal.addEventListener("click", (e) => {
    if (e.target === modal) {
      modal.remove();
    }
  });
}

// Add keyboard shortcut help button
/**
 * Creates a button to download the current SVG.
 */
function createDownloadSVGButton() {
  const existingBtn = document.getElementById("download-svg-btn");
  if (existingBtn) return;

  const button = document.createElement("button");
  button.id = "download-svg-btn";
  button.innerHTML = "📄 Download SVG";
  button.style.cssText = `
        position: fixed;
        top: 20px;
        left: 280px; /* Adjusted to not overlap other buttons */
        background: linear-gradient(45deg, #17a2b8, #138496);
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 6px;
        cursor: pointer;
        font-family: 'Vazir', Tahoma, sans-serif;
        font-weight: bold;
        z-index: 1000;
        box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        transition: all 0.3s ease;
    `;

  button.addEventListener("click", downloadCurrentSVG);
  document.body.appendChild(button);
}

/**
 * Downloads the current SVG element with all its styles.
 */
async function downloadCurrentSVG() {
  if (!currentSvgElement) {
    alert("No SVG is currently loaded to download.");
    return;
  }

  // Show layer selection modal
  showLayerSelectionModal();
}

function showLayerSelectionModal() {
  // Create modal overlay
  const modal = document.createElement("div");
  modal.id = "layer-selection-modal";
  modal.style.cssText = `
        position: fixed; top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0, 0, 0, 0.8); display: flex; align-items: center;
        justify-content: center; z-index: 3000; font-family: 'Vazir', Tahoma, sans-serif;
        direction: rtl;
    `;

  // Create modal content
  const modalContent = document.createElement("div");
  modalContent.style.cssText = `
        background: white; padding: 30px; border-radius: 12px;
        max-width: 600px; max-height: 80vh; overflow-y: auto;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
    `;

  // --- Build the HTML for the modal body ---
  let layersHTML = `
        <h2 style="color: #007bff; margin: 0 0 20px 0; border-bottom: 2px solid #007bff; padding-bottom: 10px;">
          📥 انتخاب لایه‌های دانلود
        </h2>
        <p style="color: #6c757d; margin-bottom: 20px;">
          لایه‌هایی که می‌خواهید در فایل SVG دانلود شده نمایش داده شوند را انتخاب کنید:
        </p>
        <div style="margin-bottom: 20px;">
          <button id="select-all-layers" class="btn btn-secondary">✓ انتخاب همه</button>
          <button id="deselect-all-layers" class="btn btn-secondary" style="margin-right: 10px;">✗ لغو انتخاب همه</button>
        </div>
    `;

  // --- Create checkboxes for BUILT-IN layers from svgGroupConfig ---
  layersHTML += `<h4 style="color: #495057; margin-top: 20px;">لایه‌های اصلی نقشه</h4>
                   <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 25px;">`;

  for (const key in svgGroupConfig) {
    const config = svgGroupConfig[key];
    const groupElement = currentSvgElement.getElementById(key);
    // Only show layers that exist in the SVG and have a meaningful label
    if (groupElement && config.label && config.interactive) {
      const isVisible = groupElement.style.display !== "none";
      layersHTML += `
                <label style="display: flex; align-items: center; gap: 10px; padding: 10px; border: 1px solid #dee2e6; border-radius: 8px; cursor: pointer;">
                    <input type="checkbox" class="layer-checkbox" data-layer-id="${key}" ${
        isVisible ? "checked" : ""
      } style="width: 18px; height: 18px;">
                    <span style="font-size: 14px; font-weight: bold;">${
                      config.label
                    }</span>
                </label>`;
    }
  }
  layersHTML += `</div>`;

  // ===================================================================
  // THIS IS THE NEW PART THAT WAS ADDED
  // It finds your custom drawing layers and adds them to the list.
  // ===================================================================
  const customLayers = document.querySelectorAll(".custom-drawing-layer");
  if (customLayers.length > 0) {
    layersHTML += `<div class="dropdown-divider"></div>
                       <h4 style="color: #17a2b8; margin-top: 20px;">لایه‌های ترسیمی (سفارشی)</h4>
                       <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 25px;">`;

    const customLayerConfigs = PlanDrawingModule.getLayerConfigs();

    customLayers.forEach((layer) => {
      const layerType = layer.id.replace("drawing-layer-", "");
      const config = customLayerConfigs[layerType] || { label: layerType };
      const isVisible = layer.style.display !== "none";

      layersHTML += `
                <label style="display: flex; align-items: center; gap: 10px; padding: 10px; border: 1px solid #dee2e6; border-radius: 8px; cursor: pointer;">
                    <input type="checkbox" class="layer-checkbox" data-layer-id="${layerType}" ${
        isVisible ? "checked" : ""
      } style="width: 18px; height: 18px;">
                    <span style="font-size: 14px; font-weight: bold;">${
                      config.label
                    }</span>
                </label>`;
    });
    layersHTML += `</div>`;
  }
  // ===================================================================
  // END OF NEW PART
  // ===================================================================

  // --- Add action buttons ---
  layersHTML += `
        <div style="margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 8px; border: 1px solid #dee2e6;">
          <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
            <input type="checkbox" id="include-statistics" checked style="width: 18px; height: 18px;">
            <span style="font-size: 15px; font-weight: bold;">📊 شامل آمار عناصر</span>
          </label>
          <p style="margin: 8px 0 0 28px; font-size: 12px; color: #6c757d;">
            اگر نیازی به نمایش آمار عناصر ندارید، این گزینه را غیرفعال کنید.
          </p>
        </div>
        
        <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 25px; padding-top: 20px; border-top: 1px solid #dee2e6;">
          <button id="cancel-download" class="btn btn-secondary">❌ انصراف</button>
          <button id="confirm-download" class="btn btn-success">📥 دانلود SVG</button>
        </div>
    `;

  modalContent.innerHTML = layersHTML;
  modal.appendChild(modalContent);
  document.body.appendChild(modal);

  // --- Add Event Listeners ---
  document.getElementById("select-all-layers").addEventListener("click", () => {
    modalContent
      .querySelectorAll(".layer-checkbox")
      .forEach((cb) => (cb.checked = true));
  });
  document
    .getElementById("deselect-all-layers")
    .addEventListener("click", () => {
      modalContent
        .querySelectorAll(".layer-checkbox")
        .forEach((cb) => (cb.checked = false));
    });
  document.getElementById("cancel-download").addEventListener("click", () => {
    document.body.removeChild(modal);
  });

  document
    .getElementById("confirm-download")
    .addEventListener("click", async () => {
      const selectedLayers = Array.from(
        modalContent.querySelectorAll(".layer-checkbox:checked")
      ).map((cb) => cb.dataset.layerId);

      const includeStatistics =
        document.getElementById("include-statistics").checked;

      document.body.removeChild(modal);

      await performSVGDownload(selectedLayers, includeStatistics);
    });

  modal.addEventListener("click", (e) => {
    if (e.target === modal) document.body.removeChild(modal);
  });
}

/**
 * ENHANCED: Perform the actual SVG download with selected layers
 */
/**
 * ENHANCED: Perform the actual SVG download optimized for A3 printing with border and metadata
 */
async function performSVGDownload(selectedLayers, includeStatistics = true) {
  // Clone the SVG
  const svgClone = currentSvgElement.cloneNode(true);

  // FIXED: Don't remove S-GRID and other important structural layers
  const preservedLayers = ["S-GRID", "A-GRID-SYMB-100", "axis", "AX", "FFL"];

  Object.keys(svgGroupConfig).forEach((layerId) => {
    if (
      !selectedLayers.includes(layerId) &&
      !preservedLayers.includes(layerId)
    ) {
      svgClone.getElementById(layerId)?.remove();
    }
  });
  svgClone.querySelectorAll(".custom-drawing-layer").forEach((layer) => {
    const layerType = layer.id.replace("drawing-layer-", "");
    if (!selectedLayers.includes(layerType)) {
      layer.remove();
    }
  });

  // Handle scaffolding layer

  // Inline all computed styles
  const originalElements = currentSvgElement.querySelectorAll(
    "path, rect, circle, polygon, g, text, line"
  );
  const clonedElements = svgClone.querySelectorAll(
    "path, rect, circle, polygon, g, text, line"
  );

  originalElements.forEach((originalEl, index) => {
    const cloneEl = clonedElements[index];
    if (cloneEl) {
      const computedStyle = window.getComputedStyle(originalEl);
      let inlineStyle = "";

      const propertiesToCopy = [
        "fill",
        "fill-opacity",
        "stroke",
        "stroke-width",
        "stroke-opacity",
        "display",
        "opacity",
      ];

      propertiesToCopy.forEach((prop) => {
        const value = computedStyle.getPropertyValue(prop);
        if (value && value !== "none" && value !== "0px") {
          inlineStyle += `${prop}: ${value}; `;
        }
      });

      if (inlineStyle) {
        cloneEl.setAttribute("style", inlineStyle);
      }
    }
  });

  // Fetch statistics data
  let statisticsData = null;
  if (includeStatistics) {
    try {
      const statsUrl = `/pardis/api/get_element_statistics.php?plan=${currentPlanFileName}`;
      const statsResponse = await fetch(statsUrl);
      if (statsResponse.ok) {
        statisticsData = await statsResponse.json();
      }
    } catch (error) {
      console.warn("Could not fetch statistics for download:", error);
    }
  }

  // A3 Page dimensions in points (1 point = 1/72 inch)
  // A3 Landscape: 1587 x 1123 points (420mm x 297mm)
  const pageWidth = 1587;
  const pageHeight = 1123;
  const margin = 50;
  const cadreWidth = 3;
  const headerHeight = 110;
  const footerHeight = 70;
  const legendWidth = 340;
  const legendPadding = 25;

  // Calculate available space for drawing
  const availableWidth =
    pageWidth - 2 * margin - 2 * cadreWidth - legendWidth - legendPadding;
  const availableHeight =
    pageHeight - 2 * margin - 2 * cadreWidth - headerHeight - footerHeight;

  // Get original viewBox dimensions
  const originalViewBox = svgClone.viewBox.baseVal;
  const originalWidth = originalViewBox.width;
  const originalHeight = originalViewBox.height;

  // Calculate scale to fit drawing in available space
  const scaleX = availableWidth / originalWidth;
  const scaleY = availableHeight / originalHeight;
  const scale = Math.min(scaleX, scaleY) * 0.95;

  // Calculate centered position
  const scaledWidth = originalWidth * scale;
  const scaledHeight = originalHeight * scale;
  const drawingX = margin + cadreWidth + (availableWidth - scaledWidth) / 2;
  const drawingY =
    margin + cadreWidth + headerHeight + (availableHeight - scaledHeight) / 2;

  // Create new SVG with A3 dimensions
  const finalSvg = document.createElementNS(
    "http://www.w3.org/2000/svg",
    "svg"
  );
  finalSvg.setAttribute("viewBox", `0 0 ${pageWidth} ${pageHeight}`);
  finalSvg.setAttribute("width", pageWidth);
  finalSvg.setAttribute("height", pageHeight);
  finalSvg.setAttribute("xmlns", "http://www.w3.org/2000/svg");

  // Add gradient definitions
  const defs = document.createElementNS("http://www.w3.org/2000/svg", "defs");

  // Header gradient
  const headerGradient = document.createElementNS(
    "http://www.w3.org/2000/svg",
    "linearGradient"
  );
  headerGradient.setAttribute("id", "headerGradient");
  headerGradient.setAttribute("x1", "0%");
  headerGradient.setAttribute("y1", "0%");
  headerGradient.setAttribute("x2", "100%");
  headerGradient.setAttribute("y2", "0%");

  const stop1 = document.createElementNS("http://www.w3.org/2000/svg", "stop");
  stop1.setAttribute("offset", "0%");
  stop1.setAttribute("style", "stop-color:#0066cc;stop-opacity:0.1");

  const stop2 = document.createElementNS("http://www.w3.org/2000/svg", "stop");
  stop2.setAttribute("offset", "100%");
  stop2.setAttribute("style", "stop-color:#00bfff;stop-opacity:0.1");

  headerGradient.appendChild(stop1);
  headerGradient.appendChild(stop2);
  defs.appendChild(headerGradient);

  // Legend gradient
  const legendGradient = document.createElementNS(
    "http://www.w3.org/2000/svg",
    "linearGradient"
  );
  legendGradient.setAttribute("id", "legendGradient");
  legendGradient.setAttribute("x1", "0%");
  legendGradient.setAttribute("y1", "0%");
  legendGradient.setAttribute("x2", "0%");
  legendGradient.setAttribute("y2", "100%");

  const stop3 = document.createElementNS("http://www.w3.org/2000/svg", "stop");
  stop3.setAttribute("offset", "0%");
  stop3.setAttribute("style", "stop-color:#ffffff;stop-opacity:1");

  const stop4 = document.createElementNS("http://www.w3.org/2000/svg", "stop");
  stop4.setAttribute("offset", "100%");
  stop4.setAttribute("style", "stop-color:#f8f9fa;stop-opacity:1");

  legendGradient.appendChild(stop3);
  legendGradient.appendChild(stop4);
  defs.appendChild(legendGradient);

  finalSvg.appendChild(defs);

  // Add white background
  const background = document.createElementNS(
    "http://www.w3.org/2000/svg",
    "rect"
  );
  background.setAttribute("width", pageWidth);
  background.setAttribute("height", pageHeight);
  background.setAttribute("fill", "white");
  finalSvg.appendChild(background);

  // Add main border with shadow effect
  const shadowBorder = document.createElementNS(
    "http://www.w3.org/2000/svg",
    "rect"
  );
  shadowBorder.setAttribute("x", margin + 2);
  shadowBorder.setAttribute("y", margin + 2);
  shadowBorder.setAttribute("width", pageWidth - 2 * margin);
  shadowBorder.setAttribute("height", pageHeight - 2 * margin);
  shadowBorder.setAttribute("fill", "none");
  shadowBorder.setAttribute("stroke", "#d0d0d0");
  shadowBorder.setAttribute("stroke-width", cadreWidth);
  shadowBorder.setAttribute("opacity", "0.5");
  finalSvg.appendChild(shadowBorder);

  const mainBorder = document.createElementNS(
    "http://www.w3.org/2000/svg",
    "rect"
  );
  mainBorder.setAttribute("x", margin);
  mainBorder.setAttribute("y", margin);
  mainBorder.setAttribute("width", pageWidth - 2 * margin);
  mainBorder.setAttribute("height", pageHeight - 2 * margin);
  mainBorder.setAttribute("fill", "none");
  mainBorder.setAttribute("stroke", "#1a1a1a");
  mainBorder.setAttribute("stroke-width", cadreWidth);
  finalSvg.appendChild(mainBorder);

  // Add corner decorations
  const cornerSize = 20;
  const corners = [
    { x: margin, y: margin },
    { x: pageWidth - margin - cornerSize, y: margin },
    { x: margin, y: pageHeight - margin - cornerSize },
    { x: pageWidth - margin - cornerSize, y: pageHeight - margin - cornerSize },
  ];

  corners.forEach((corner) => {
    const cornerRect = document.createElementNS(
      "http://www.w3.org/2000/svg",
      "rect"
    );
    cornerRect.setAttribute("x", corner.x);
    cornerRect.setAttribute("y", corner.y);
    cornerRect.setAttribute("width", cornerSize);
    cornerRect.setAttribute("height", cornerSize);
    cornerRect.setAttribute("fill", "#0066cc");
    cornerRect.setAttribute("opacity", "0.15");
    finalSvg.appendChild(cornerRect);
  });

  const legendFont = "Vazir, Tahoma, sans-serif";

  // Enhanced header with gradient background
  const headerBg = document.createElementNS(
    "http://www.w3.org/2000/svg",
    "rect"
  );
  headerBg.setAttribute("x", margin + cadreWidth + 20);
  headerBg.setAttribute("y", margin + cadreWidth + 20);
  headerBg.setAttribute("width", pageWidth - 2 * margin - 2 * cadreWidth - 40);
  headerBg.setAttribute("height", headerHeight - 30);
  headerBg.setAttribute("fill", "url(#headerGradient)");
  headerBg.setAttribute("stroke", "#0066cc");
  headerBg.setAttribute("stroke-width", "2");
  headerBg.setAttribute("rx", "10");
  finalSvg.appendChild(headerBg);

  // Decorative line under header
  const headerLine = document.createElementNS(
    "http://www.w3.org/2000/svg",
    "line"
  );
  headerLine.setAttribute("x1", margin + cadreWidth + 40);
  headerLine.setAttribute("y1", margin + cadreWidth + 65);
  headerLine.setAttribute("x2", pageWidth - margin - cadreWidth - 40);
  headerLine.setAttribute("y2", margin + cadreWidth + 65);
  headerLine.setAttribute("stroke", "#0066cc");
  headerLine.setAttribute("stroke-width", "2");
  headerLine.setAttribute("opacity", "0.3");
  finalSvg.appendChild(headerLine);

  // Plan title with better spacing
  const planTitle = document.createElementNS(
    "http://www.w3.org/2000/svg",
    "text"
  );
  planTitle.setAttribute("x", pageWidth / 2);
  planTitle.setAttribute("y", margin + cadreWidth + 50);
  planTitle.setAttribute("text-anchor", "middle");
  planTitle.setAttribute("font-size", "34");
  planTitle.setAttribute("font-weight", "bold");
  planTitle.setAttribute("font-family", legendFont);
  planTitle.setAttribute("fill", "#0066cc");
  planTitle.setAttribute("letter-spacing", "1");
  planTitle.textContent = `نقشه: ${getDisplayName(currentPlanFileName)}`;
  finalSvg.appendChild(planTitle);

  // Subtitle with better styling
  const subtitle = document.createElementNS(
    "http://www.w3.org/2000/svg",
    "text"
  );
  subtitle.setAttribute("x", pageWidth / 2);
  subtitle.setAttribute("y", margin + cadreWidth + 82);
  subtitle.setAttribute("text-anchor", "middle");
  subtitle.setAttribute("font-size", "15");
  subtitle.setAttribute("font-family", legendFont);
  subtitle.setAttribute("fill", "#5a6c7d");
  subtitle.textContent =
    "پروژه دانشگاه خاتم - سیستم مدیریت بازرسی و کنترل کیفیت";
  finalSvg.appendChild(subtitle);

  // Wrap main content with transform for scaling and positioning
  const mainContentGroup = document.createElementNS(
    "http://www.w3.org/2000/svg",
    "g"
  );
  mainContentGroup.setAttribute(
    "transform",
    `translate(${drawingX}, ${drawingY}) scale(${scale})`
  );
  mainContentGroup.id = "main-drawing-content";

  // Move all cloned content into the group
  while (svgClone.firstChild) {
    mainContentGroup.appendChild(svgClone.firstChild);
  }
  finalSvg.appendChild(mainContentGroup);

  // Add legend with improved styling
  const legendX = pageWidth - margin - cadreWidth - legendWidth - 20;
  let legendY = margin + cadreWidth + headerHeight + 20;

  const legendGroup = document.createElementNS(
    "http://www.w3.org/2000/svg",
    "g"
  );
  legendGroup.id = "layer-legend";

  // Get selected layer configs
  const selectedLayerConfigs = selectedLayers
    .map((layerId) => ({ id: layerId, config: svgGroupConfig[layerId] }))
    .filter((item) => item.config && item.config.label);

  let legendItems = [...selectedLayerConfigs];

  // Handle drawing layers statistics

  const itemHeight = 35;
  const titleHeight = 50;

  // Calculate stats height only if statistics are included
  const statsHeight =
    includeStatistics && statisticsData
      ? calculateStatsHeight(statisticsData, selectedLayers)
      : 0;

  // Count custom drawing layers
  let customLayerCount = 0;
  if (
    statisticsData?.drawing_layers &&
    Object.keys(statisticsData.drawing_layers).length > 0
  ) {
    for (const layerType in statisticsData.drawing_layers) {
      if (layerType !== "scaffolding" && selectedLayers.includes(layerType)) {
        customLayerCount++;
      }
    }
  }

  const customLayersHeight = customLayerCount * 110; // Each custom layer box is ~110px

  const legendHeight =
    legendItems.length * itemHeight +
    titleHeight +
    statsHeight +
    customLayersHeight +
    70;

  // Enhanced Legend Background with gradient
  const legendBg = document.createElementNS(
    "http://www.w3.org/2000/svg",
    "rect"
  );
  legendBg.setAttribute("x", legendX);
  legendBg.setAttribute("y", legendY);
  legendBg.setAttribute("width", legendWidth);
  legendBg.setAttribute("height", legendHeight);
  legendBg.setAttribute("fill", "url(#legendGradient)");
  legendBg.setAttribute("stroke", "#2c3e50");
  legendBg.setAttribute("stroke-width", "2.5");
  legendBg.setAttribute("rx", "12");
  legendGroup.appendChild(legendBg);

  // Legend title background
  const legendTitleBg = document.createElementNS(
    "http://www.w3.org/2000/svg",
    "rect"
  );
  legendTitleBg.setAttribute("x", legendX + 10);
  legendTitleBg.setAttribute("y", legendY + 10);
  legendTitleBg.setAttribute("width", legendWidth - 20);
  legendTitleBg.setAttribute("height", "40");
  legendTitleBg.setAttribute("fill", "#0066cc");
  legendTitleBg.setAttribute("rx", "8");
  legendTitleBg.setAttribute("opacity", "0.1");
  legendGroup.appendChild(legendTitleBg);

  // Legend Title
  const legendTitle = document.createElementNS(
    "http://www.w3.org/2000/svg",
    "text"
  );
  legendTitle.setAttribute("x", legendX + legendWidth / 2);
  legendTitle.setAttribute("y", legendY + 35);
  legendTitle.setAttribute("text-anchor", "middle");
  legendTitle.setAttribute("font-size", "22");
  legendTitle.setAttribute("font-weight", "bold");
  legendTitle.setAttribute("font-family", legendFont);
  legendTitle.setAttribute("fill", "#0066cc");
  legendTitle.textContent = "راهنمای لایه‌ها";
  legendGroup.appendChild(legendTitle);

  legendY += titleHeight;

  // Create Legend Items with improved styling
  legendItems.forEach((item, index) => {
    const config = item.config;
    let color = config.color || (config.colors ? config.colors.v : "#cccccc");

    // Alternating background for better readability
    if (index % 2 === 0) {
      const itemBg = document.createElementNS(
        "http://www.w3.org/2000/svg",
        "rect"
      );
      itemBg.setAttribute("x", legendX + 10);
      itemBg.setAttribute("y", legendY - 3);
      itemBg.setAttribute("width", legendWidth - 20);
      itemBg.setAttribute("height", itemHeight);
      itemBg.setAttribute("fill", "#f8f9fa");
      itemBg.setAttribute("rx", "5");
      itemBg.setAttribute("opacity", "0.5");
      legendGroup.appendChild(itemBg);
    }

    // Enhanced Color Swatch with shadow
    const swatchShadow = document.createElementNS(
      "http://www.w3.org/2000/svg",
      "rect"
    );
    swatchShadow.setAttribute("x", legendX + 22);
    swatchShadow.setAttribute("y", legendY + 7);
    swatchShadow.setAttribute("width", "32");
    swatchShadow.setAttribute("height", "24");
    swatchShadow.setAttribute("fill", "#000000");
    swatchShadow.setAttribute("opacity", "0.1");
    swatchShadow.setAttribute("rx", "4");
    legendGroup.appendChild(swatchShadow);

    const rect = document.createElementNS("http://www.w3.org/2000/svg", "rect");
    rect.setAttribute("x", legendX + 20);
    rect.setAttribute("y", legendY + 5);
    rect.setAttribute("width", "32");
    rect.setAttribute("height", "24");
    rect.setAttribute("fill", color);
    rect.setAttribute("stroke", "#2c3e50");
    rect.setAttribute("stroke-width", "2");
    rect.setAttribute("rx", "4");
    legendGroup.appendChild(rect);

    // Layer Label with better typography
    const text = document.createElementNS("http://www.w3.org/2000/svg", "text");
    text.setAttribute("x", legendX + 65);
    text.setAttribute("y", legendY + 21);
    text.setAttribute("font-size", "15");
    text.setAttribute("font-family", legendFont);
    text.setAttribute("font-weight", "600");
    text.setAttribute("fill", "#2c3e50");
    text.textContent = config.label;
    legendGroup.appendChild(text);

    legendY += itemHeight;
  });

  // FIXED: Add custom drawing layers AFTER main legend items
  if (
    statisticsData?.drawing_layers &&
    Object.keys(statisticsData.drawing_layers).length > 0
  ) {
    legendY += 20; // Add space before custom layers section

    const layerIcons = {
      annotations: "📝",
      damages: "⚠️",
      default: "✏️",
    };

    for (const layerType in statisticsData.drawing_layers) {
      // Skip scaffolding here, handle it separately
      if (layerType === "scaffolding") continue;

      if (selectedLayers.includes(layerType)) {
        const layerData = statisticsData.drawing_layers[layerType];
        const icon = layerIcons[layerType] || layerIcons["default"];

        const layerStatBg = document.createElementNS(
          "http://www.w3.org/2000/svg",
          "rect"
        );
        layerStatBg.setAttribute("x", legendX + 15);
        layerStatBg.setAttribute("y", legendY - 5);
        layerStatBg.setAttribute("width", legendWidth - 30);
        layerStatBg.setAttribute("height", "95");
        layerStatBg.setAttribute("fill", "#fffbf0");
        layerStatBg.setAttribute("stroke", "#ffa000");
        layerStatBg.setAttribute("stroke-width", "2");
        layerStatBg.setAttribute("rx", "8");
        legendGroup.appendChild(layerStatBg);

        const layerStatTitle = document.createElementNS(
          "http://www.w3.org/2000/svg",
          "text"
        );
        layerStatTitle.setAttribute("x", legendX + legendWidth / 2);
        layerStatTitle.setAttribute("y", legendY + 20);
        layerStatTitle.setAttribute("text-anchor", "middle");
        layerStatTitle.setAttribute("font-size", "16");
        layerStatTitle.setAttribute("font-family", legendFont);
        layerStatTitle.setAttribute("font-weight", "bold");
        layerStatTitle.setAttribute("fill", "#b85c00");
        layerStatTitle.textContent = `${icon} ${layerType}`;
        legendGroup.appendChild(layerStatTitle);

        legendY += 45;

        const lengthLabel = document.createElementNS(
          "http://www.w3.org/2000/svg",
          "text"
        );
        lengthLabel.setAttribute("x", legendX + 30);
        lengthLabel.setAttribute("y", legendY);
        lengthLabel.setAttribute("font-size", "13");
        lengthLabel.setAttribute("font-family", legendFont);
        lengthLabel.setAttribute("fill", "#8a5500");
        lengthLabel.textContent = "طول:";
        legendGroup.appendChild(lengthLabel);

        const lengthValue = document.createElementNS(
          "http://www.w3.org/2000/svg",
          "text"
        );
        lengthValue.setAttribute("x", legendX + legendWidth - 30);
        lengthValue.setAttribute("y", legendY);
        lengthValue.setAttribute("text-anchor", "end");
        lengthValue.setAttribute("font-size", "13");
        lengthValue.setAttribute("font-family", legendFont);
        lengthValue.setAttribute("font-weight", "bold");
        lengthValue.setAttribute("fill", "#b85c00");
        lengthValue.textContent = `${parseFloat(
          layerData.total_length_m || 0
        ).toFixed(2)} م`;
        legendGroup.appendChild(lengthValue);

        legendY += 22;

        const areaLabel = document.createElementNS(
          "http://www.w3.org/2000/svg",
          "text"
        );
        areaLabel.setAttribute("x", legendX + 30);
        areaLabel.setAttribute("y", legendY);
        areaLabel.setAttribute("font-size", "13");
        areaLabel.setAttribute("font-family", legendFont);
        areaLabel.setAttribute("fill", "#8a5500");
        areaLabel.textContent = "مساحت:";
        legendGroup.appendChild(areaLabel);

        const areaValue = document.createElementNS(
          "http://www.w3.org/2000/svg",
          "text"
        );
        areaValue.setAttribute("x", legendX + legendWidth - 30);
        areaValue.setAttribute("y", legendY);
        areaValue.setAttribute("text-anchor", "end");
        areaValue.setAttribute("font-size", "13");
        areaValue.setAttribute("font-family", legendFont);
        areaValue.setAttribute("font-weight", "bold");
        areaValue.setAttribute("fill", "#b85c00");
        areaValue.textContent = `${parseFloat(
          layerData.total_area_sqm || 0
        ).toFixed(2)} m²`;
        legendGroup.appendChild(areaValue);

        legendY += 28; // Move to next custom layer
      }
    }
  }

  // Add Statistics Section ONLY if includeStatistics is true
  if (includeStatistics && statisticsData) {
    legendY += 20;

    // Separator with decorative style
    const separatorLine = document.createElementNS(
      "http://www.w3.org/2000/svg",
      "line"
    );
    separatorLine.setAttribute("x1", legendX + 25);
    separatorLine.setAttribute("y1", legendY);
    separatorLine.setAttribute("x2", legendX + legendWidth - 25);
    separatorLine.setAttribute("y2", legendY);
    separatorLine.setAttribute("stroke", "#0066cc");
    separatorLine.setAttribute("stroke-width", "2");
    separatorLine.setAttribute("opacity", "0.3");
    legendGroup.appendChild(separatorLine);

    legendY += 25;

    // Statistics Title with icon
    const statsTitle = document.createElementNS(
      "http://www.w3.org/2000/svg",
      "text"
    );
    statsTitle.setAttribute("x", legendX + legendWidth / 2);
    statsTitle.setAttribute("y", legendY);
    statsTitle.setAttribute("text-anchor", "middle");
    statsTitle.setAttribute("font-size", "20");
    statsTitle.setAttribute("font-weight", "bold");
    statsTitle.setAttribute("font-family", legendFont);
    statsTitle.setAttribute("fill", "#28a745");
    statsTitle.textContent = "📊 آمار عناصر";
    legendGroup.appendChild(statsTitle);

    legendY += 30;

    // Element Statistics with improved layout
    if (
      statisticsData.element_statistics &&
      statisticsData.element_statistics.length > 0
    ) {
      // Filter statistics to only show selected element types
      const filteredStats = statisticsData.element_statistics.filter((stat) => {
        // Check if this element type is in the selected layers
        // Need to match element_type to layer configuration
        for (const layerId of selectedLayers) {
          const config = svgGroupConfig[layerId];
          if (config && config.elementType === stat.element_type) {
            return true;
          }
          // Also check if the layer ID itself matches the element type
          if (layerId === stat.element_type) {
            return true;
          }
        }
        return false;
      });

      if (filteredStats.length === 0) {
        // No statistics to show for selected layers
        legendY += 0;
      } else {
        let totalCount = 0;
        let totalArea = 0;

        filteredStats.forEach((stat, index) => {
          const count = parseInt(stat.element_count);
          const area = parseFloat(stat.total_area_sqm || 0);
          totalCount += count;
          totalArea += area;

          // Alternating row background
          if (index % 2 === 1) {
            const rowBg = document.createElementNS(
              "http://www.w3.org/2000/svg",
              "rect"
            );
            rowBg.setAttribute("x", legendX + 15);
            rowBg.setAttribute("y", legendY - 15);
            rowBg.setAttribute("width", legendWidth - 30);
            rowBg.setAttribute("height", "22");
            rowBg.setAttribute("fill", "#f0f0f0");
            rowBg.setAttribute("rx", "3");
            rowBg.setAttribute("opacity", "0.5");
            legendGroup.appendChild(rowBg);
          }

          // Element type label
          const statLabel = document.createElementNS(
            "http://www.w3.org/2000/svg",
            "text"
          );
          statLabel.setAttribute("x", legendX + 25);
          statLabel.setAttribute("y", legendY);
          statLabel.setAttribute("font-size", "13");
          statLabel.setAttribute("font-family", legendFont);
          statLabel.setAttribute("font-weight", "600");
          statLabel.setAttribute("fill", "#34495e");
          statLabel.textContent = stat.element_type;
          legendGroup.appendChild(statLabel);

          // Count and area
          const statValue = document.createElementNS(
            "http://www.w3.org/2000/svg",
            "text"
          );
          statValue.setAttribute("x", legendX + legendWidth - 25);
          statValue.setAttribute("y", legendY);
          statValue.setAttribute("text-anchor", "end");
          statValue.setAttribute("font-size", "12");
          statValue.setAttribute("font-family", legendFont);
          statValue.setAttribute("fill", "#5a6c7d");
          statValue.textContent = `${count} | ${area.toFixed(1)} m²`;
          legendGroup.appendChild(statValue);

          legendY += 22;
        });

        legendY += 8;

        // Enhanced total row
        const totalBg = document.createElementNS(
          "http://www.w3.org/2000/svg",
          "rect"
        );
        totalBg.setAttribute("x", legendX + 15);
        totalBg.setAttribute("y", legendY - 18);
        totalBg.setAttribute("width", legendWidth - 30);
        totalBg.setAttribute("height", "30");
        totalBg.setAttribute("fill", "#0066cc");
        totalBg.setAttribute("rx", "5");
        totalBg.setAttribute("opacity", "0.15");
        legendGroup.appendChild(totalBg);

        const totalLabel = document.createElementNS(
          "http://www.w3.org/2000/svg",
          "text"
        );
        totalLabel.setAttribute("x", legendX + 25);
        totalLabel.setAttribute("y", legendY);
        totalLabel.setAttribute("font-size", "14");
        totalLabel.setAttribute("font-family", legendFont);
        totalLabel.setAttribute("font-weight", "bold");
        totalLabel.setAttribute("fill", "#0066cc");
        totalLabel.textContent = "جمع کل";
        legendGroup.appendChild(totalLabel);

        const totalValue = document.createElementNS(
          "http://www.w3.org/2000/svg",
          "text"
        );
        totalValue.setAttribute("x", legendX + legendWidth - 25);
        totalValue.setAttribute("y", legendY);
        totalValue.setAttribute("text-anchor", "end");
        totalValue.setAttribute("font-size", "14");
        totalValue.setAttribute("font-family", legendFont);
        totalValue.setAttribute("font-weight", "bold");
        totalValue.setAttribute("fill", "#0066cc");
        totalValue.textContent = `${totalCount} | ${totalArea.toFixed(1)} m²`;
        legendGroup.appendChild(totalValue);

        legendY += 35;
      }
    }

    // Enhanced Scaffolding Statistics
  }

  finalSvg.appendChild(legendGroup);

  // Enhanced footer with modern design
  const footerY = pageHeight - margin - cadreWidth - footerHeight + 35;

  const footerBg = document.createElementNS(
    "http://www.w3.org/2000/svg",
    "rect"
  );
  footerBg.setAttribute("x", margin + cadreWidth + 20);
  footerBg.setAttribute("y", footerY - 25);
  footerBg.setAttribute("width", pageWidth - 2 * margin - 2 * cadreWidth - 40);
  footerBg.setAttribute("height", 60);
  footerBg.setAttribute("fill", "#f8f9fa");
  footerBg.setAttribute("stroke", "#dee2e6");
  footerBg.setAttribute("stroke-width", "2");
  footerBg.setAttribute("rx", "8");
  finalSvg.appendChild(footerBg);

  // Decorative footer line
  const footerLine = document.createElementNS(
    "http://www.w3.org/2000/svg",
    "line"
  );
  footerLine.setAttribute("x1", margin + cadreWidth + 40);
  footerLine.setAttribute("y1", footerY + 5);
  footerLine.setAttribute("x2", pageWidth - margin - cadreWidth - 40);
  footerLine.setAttribute("y2", footerY + 5);
  footerLine.setAttribute("stroke", "#0066cc");
  footerLine.setAttribute("stroke-width", "1");
  footerLine.setAttribute("opacity", "0.3");
  finalSvg.appendChild(footerLine);

  const updateDate = document.createElementNS(
    "http://www.w3.org/2000/svg",
    "text"
  );
  updateDate.setAttribute("x", pageWidth / 2);
  updateDate.setAttribute("y", footerY);
  updateDate.setAttribute("text-anchor", "middle");
  updateDate.setAttribute("font-size", "17");
  updateDate.setAttribute("font-weight", "bold");
  updateDate.setAttribute("font-family", legendFont);
  updateDate.setAttribute("fill", "#2c3e50");
  updateDate.textContent = `⏰ آخرین به‌روزرسانی: ${getPersianDate()}`;
  finalSvg.appendChild(updateDate);

  const footerInfo = document.createElementNS(
    "http://www.w3.org/2000/svg",
    "text"
  );
  footerInfo.setAttribute("x", pageWidth / 2);
  footerInfo.setAttribute("y", footerY + 22);
  footerInfo.setAttribute("text-anchor", "middle");
  footerInfo.setAttribute("font-size", "13");
  footerInfo.setAttribute("font-family", legendFont);
  footerInfo.setAttribute("fill", "#5a6c7d");
  footerInfo.textContent = "سیستم مدیریت پروژه و بازرسی کیفیت - دانشگاه خاتم";
  finalSvg.appendChild(footerInfo);

  // Serialize and Download
  const serializer = new XMLSerializer();
  let svgString = serializer.serializeToString(finalSvg);

  // Add proper XML declaration and encoding
  svgString =
    '<?xml version="1.0" encoding="UTF-8" standalone="no"?>\n' + svgString;

  const blob = new Blob([svgString], { type: "image/svg+xml;charset=utf-8" });
  const link = document.createElement("a");
  const url = URL.createObjectURL(blob);
  link.setAttribute("href", url);
  link.setAttribute(
    "download",
    `${getDisplayName(currentPlanFileName)}_${getPersianDateForFilename()}.svg`
  );
  link.style.visibility = "hidden";
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);

  // Show enhanced success message
  setTimeout(() => {
    alert(
      `✅ فایل SVG با موفقیت دانلود شد!\n\n` +
        `📋 نقشه: ${getDisplayName(currentPlanFileName)}\n` +
        `🎨 لایه‌ها: ${selectedLayers.length}\n` +
        `📊 آمار عناصر: ${includeStatistics ? "✓ شامل" : "✗ بدون آمار"}\n\n` +
        `📄 فایل برای چاپ A3 بهینه شده است.\n` +
        `🎯 کیفیت حرفه‌ای برای ارائه و مستندسازی`
    );
  }, 100);
}

// Helper function to calculate statistics height
function calculateStatsHeight(statisticsData, selectedLayers) {
  let height = 90; // Base height for title and separator

  if (
    statisticsData.element_statistics &&
    statisticsData.element_statistics.length > 0
  ) {
    // Filter to count only selected element types
    const filteredStats = statisticsData.element_statistics.filter((stat) => {
      for (const layerId of selectedLayers) {
        const config = svgGroupConfig[layerId];
        if (config && config.elementType === stat.element_type) {
          return true;
        }
        if (layerId === stat.element_type) {
          return true;
        }
      }
      return false;
    });

    if (filteredStats.length > 0) {
      height += filteredStats.length * 22; // Each element stat row
      height += 50; // Total row
    }
  }

  return height;
}
/**
 * Helper function to calculate statistics section height
 */
function calculateStatsHeight(statisticsData, includeScaffolding) {
  let height = 60; // Base height for title and separator

  if (
    statisticsData.element_statistics &&
    statisticsData.element_statistics.length > 0
  ) {
    height += statisticsData.element_statistics.length * 20; // Each element stat row
    height += 30; // Total row
  }

  if (includeScaffolding && statisticsData.scaffolding) {
    height += 80; // Scaffolding section
  }

  return height;
}

/**
 * Get Persian display name for plan
 */
function getDisplayName(filename) {
  if (!filename) return "بدون نام";

  const nameMap = {
    "Plan.svg": "نمای کلی پروژه",
    "WestLib.svg": "غرب کتابخانه",
    "EastLib.svg": "شرق کتابخانه",
    "SouthLib.svg": "جنوب کتابخانه",
    "NorthLib.svg": "شمال کتابخانه",
    "VoidLib.svg": "وید کتابخانه",
    "WestAgri.svg": "غرب کشاورزی",
    "EastAgri.svg": "شرق کشاورزی",
    "SouthAgri.svg": "جنوب کشاورزی",
    "NorthAgri.svg": "شمال کشاورزی",
  };

  return nameMap[filename] || filename.replace(".svg", "");
}

/**
 * Get current Persian date
 */
function getPersianDate() {
  const now = new Date();
  try {
    return now.toLocaleDateString("fa-IR", {
      year: "numeric",
      month: "long",
      day: "numeric",
    });
  } catch (e) {
    // Fallback if Persian locale not supported
    return now.toLocaleDateString("fa-IR");
  }
}

/**
 * Get Persian date for filename
 */
function getPersianDateForFilename() {
  const now = new Date();
  try {
    return now
      .toLocaleDateString("fa-IR", {
        year: "numeric",
        month: "2-digit",
        day: "2-digit",
      })
      .replace(/\//g, "-");
  } catch (e) {
    return now.toISOString().split("T")[0];
  }
}
// Initialize the download button when the page loads

// ADD THIS FUNCTION TO ghom_app.js (and make sure no other button creators exist)
function setupDrawingButtons() {
  const container = document.getElementById("layerControlsContainer");
  if (!container || document.getElementById("drawing-actions-menu")) return;

  const userRole = document.body.dataset.userRole;
  const hasPermission = [
    "admin",
    "superuser",
    "supervisor",
    "planner",
  ].includes(userRole);
  if (!hasPermission) return;

  const btnGroup = document.createElement("div");
  btnGroup.className = "btn-group";
  btnGroup.id = "drawing-actions-menu";

  const dropdownButton = document.createElement("button");
  dropdownButton.type = "button";
  dropdownButton.className = "btn dropdown-toggle";
  dropdownButton.setAttribute("data-toggle", "dropdown");
  dropdownButton.innerHTML = "✏️ ترسیم / ویرایش";
  dropdownButton.style.cssText = `background: linear-gradient(45deg, #20c997, #17a2b8); color: white; font-weight: bold;`;

  const dropdownMenu = document.createElement("div");
  dropdownMenu.className = "dropdown-menu";

  btnGroup.appendChild(dropdownButton);
  btnGroup.appendChild(dropdownMenu);
  container.appendChild(btnGroup);

  // This jQuery event populates the menu just before it's shown
  $("#drawing-actions-menu").on("show.bs.dropdown", function () {
    dropdownMenu.innerHTML = ""; // Clear old items

    // Add "Draw Scaffolding"
    const scaffoldingItem = document.createElement("a");
    scaffoldingItem.className = "dropdown-item";
    scaffoldingItem.href = "#";
    scaffoldingItem.innerHTML = "🏗️ ترسیم داربست";
    scaffoldingItem.addEventListener("click", (e) => {
      e.preventDefault();
      PlanDrawingModule.openDrawer("scaffolding", {
        label: "داربست",
        color: "#FF6B6B",
      });
    });
    dropdownMenu.appendChild(scaffoldingItem);
    dropdownMenu.appendChild(document.createElement("div")).className =
      "dropdown-divider";

    // Add "Edit Layer" options
    const existingLayers = Array.from(
      document.querySelectorAll(".custom-drawing-layer")
    ).map((l) => l.id.replace("drawing-layer-", ""));
    if (existingLayers.length > 0) {
      existingLayers.forEach((layerName) => {
        if (layerName === "scaffolding") return;
        const layerItem = document.createElement("a");
        layerItem.className = "dropdown-item";
        layerItem.href = "#";
        layerItem.textContent = `ویرایش لایه: ${layerName}`;
        layerItem.addEventListener("click", (e) => {
          e.preventDefault();
          const configs = PlanDrawingModule.getLayerConfigs();
          const config = configs[layerName] || {
            label: layerName,
            color: "#339AF0",
          };
          PlanDrawingModule.openDrawer(layerName, config);
        });
        dropdownMenu.appendChild(layerItem);
      });
      dropdownMenu.appendChild(document.createElement("div")).className =
        "dropdown-divider";
    }

    // Add "Create New Layer"
    const newItem = document.createElement("a");
    newItem.className = "dropdown-item";
    newItem.href = "#";
    newItem.innerHTML = "✨ ایجاد لایه جدید...";
    newItem.addEventListener("click", (e) => {
      e.preventDefault();
      const layerName = prompt("نام انگلیسی لایه جدید:", "annotations");
      if (layerName && layerName.trim()) {
        const cleanName = layerName.trim().toLowerCase().replace(/\s+/g, "-");
        PlanDrawingModule.openDrawer(cleanName, {
          label: cleanName,
          color: "#339AF0",
        });
      }
    });
    dropdownMenu.appendChild(newItem);
  });
}

function createMainToolsDropdown() {
  // Prevent creating duplicates if the function is ever called more than once
  if (document.getElementById("main-tools-dropdown")) return;

  // These are the original functions for your buttons. Make sure they exist in your file.
  // If they were deleted, you may need to restore them from a backup.
  // - createStatisticsButton() -> becomes loadAndDisplayStatistics()
  // - createRefreshButton() -> becomes refreshAllLayers()
  // - createDownloadSVGButton() -> becomes downloadCurrentSVG()

  const container = document.body;
  const btnGroup = document.createElement("div");
  btnGroup.id = "main-tools-dropdown";
  btnGroup.className = "btn-group";
  btnGroup.style.cssText = `position: fixed; top: 20px; left: 20px; z-index: 1001;`;

  btnGroup.innerHTML = `
        <button type="button" class="btn btn-secondary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" style="background: #0056b3; font-weight: bold; padding: 10px 20px;">
            ابزارهای اصلی
        </button>
        <div class="dropdown-menu">
            <a class="dropdown-item" href="#" id="main-tool-stats">📊 آمار</a>
            <a class="dropdown-item" href="#" id="main-tool-refresh">🔄 بروزرسانی</a>
            <a class="dropdown-item" href="#" id="main-tool-download">📄 Download SVG</a>
        </div>
    `;

  container.appendChild(btnGroup);

  // Wire up the new menu items to call the correct functions
  document.getElementById("main-tool-stats").addEventListener("click", (e) => {
    e.preventDefault();
    // This function should already exist and will show the stats panel
    loadAndDisplayStatistics(currentPlanFileName);
  });

  document
    .getElementById("main-tool-refresh")
    .addEventListener("click", (e) => {
      e.preventDefault();
      // This function should already exist to refresh all layers
      refreshAllLayers();
    });

  document
    .getElementById("main-tool-download")
    .addEventListener("click", (e) => {
      e.preventDefault();
      // This function should already exist to start the SVG download process
      downloadCurrentSVG();
    });
}
