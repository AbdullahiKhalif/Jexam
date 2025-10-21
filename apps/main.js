document.addEventListener("DOMContentLoaded", function () {
  // --- Element Selection ---
  const dropZone = document.getElementById("dropZone");
  const fileInput = document.getElementById("excelFile");
  const browseBtn = document.getElementById("browseBtn");
  const generateBtn = document.getElementById("generateBtn");
  const fileStatus = document.getElementById("fileStatus");
  const filePreview = document.getElementById("filePreview");
  const resultsSection = document.getElementById("resultsSection");
  const hallTablesContainer = document.getElementById("hallTables");
  const actionButtonsContainer = document.getElementById(
    "actionButtonsContainer"
  );
  const loadingIndicator = document.getElementById("loadingIndicator");
  const errorDetails = document.getElementById("errorDetails");

  // New form inputs
  const campusSelect = document.getElementById("campusSelect");
  const shiftSelect = document.getElementById("shiftSelect");
  const daySelect = document.getElementById("daySelect");
  const dateInput = document.getElementById("dateInput");
  const timeInput = document.getElementById("timeInput");
  const formInputs = [
    campusSelect,
    shiftSelect,
    daySelect,
    dateInput,
    timeInput,
  ];

  // --- State Variables ---
  let uploadedFile = null;
  let generatedHallsData = null;
  let examDetails = {};
  let headerImageBase64 = "";

  // --- Functions ---

  // Function to check if all required fields are filled
  const validateForm = () => {
    const isFormComplete = formInputs.every(
      (input) => input.value.trim() !== ""
    );
    const isFileUploaded = uploadedFile !== null;
    generateBtn.disabled = !(isFormComplete && isFileUploaded);
  };

  // Pre-fetch and convert header image to Base64 for embedding
  const preloadHeaderImage = () => {
    // This now reads from your local 'images' folder.
    // Ensure you have a folder named 'images' next to your index.html,
    // and inside it, an image named 'header.jpg'.
    const imageUrl = "./images/header.jpg";
    fetch(imageUrl)
      .then((res) => {
        if (!res.ok) {
          throw new Error("Header image not found at " + imageUrl);
        }
        return res.blob();
      })
      .then((blob) => {
        const reader = new FileReader();
        reader.onloadend = () => {
          headerImageBase64 = reader.result;
        };
        reader.readAsDataURL(blob);
      })
      .catch((err) => {
        console.error("Error loading header image:", err);
        alert(
          "Could not load the header image. Please make sure the file exists at './images/header.jpg'"
        );
      });
  };

  // --- Event Listeners ---
  browseBtn.addEventListener("click", () => fileInput.click());

  fileInput.addEventListener("change", function () {
    if (this.files.length > 0) {
      uploadedFile = this.files[0];
      fileStatus.textContent = `Selected: ${uploadedFile.name}`;
      fileStatus.className = "status-badge badge-success";
      validateForm();
    }
  });

  // Add listeners to all form inputs to validate on change
  formInputs.forEach((input) => input.addEventListener("change", validateForm));

  // Drag and Drop
  dropZone.addEventListener("dragover", (e) => {
    e.preventDefault();
    dropZone.classList.add("dragover");
  });
  dropZone.addEventListener("dragleave", () =>
    dropZone.classList.remove("dragover")
  );
  dropZone.addEventListener("drop", (e) => {
    e.preventDefault();
    dropZone.classList.remove("dragover");
    if (e.dataTransfer.files.length > 0) {
      const file = e.dataTransfer.files[0];
      if (file.name.endsWith(".xlsx") || file.name.endsWith(".xls")) {
        uploadedFile = file;
        fileInput.files = e.dataTransfer.files;
        fileStatus.textContent = `Selected: ${uploadedFile.name}`;
        fileStatus.className = "status-badge badge-success";
        validateForm();
      } else {
        fileStatus.textContent = "Please select an Excel file (.xlsx, .xls)";
        fileStatus.className = "status-badge badge-error";
      }
    }
  });

  generateBtn.addEventListener("click", () => {
    if (!uploadedFile) {
      showError("Please select a file first");
      return;
    }
    if (!headerImageBase64) {
      alert(
        "Header image has not loaded yet. Please wait a moment or check the file path."
      );
      return;
    }

    // Store exam details from the form
    examDetails = {
      campus: campusSelect.value,
      shift: shiftSelect.value,
      day: daySelect.value,
      date: new Date(dateInput.value).toLocaleDateString("en-GB"), // Format as DD/MM/YYYY
      time: timeInput.value,
    };

    loadingIndicator.style.display = "block";
    errorDetails.style.display = "none";
    resultsSection.style.display = "none";

    const reader = new FileReader();
    reader.onload = function (e) {
      try {
        const data = new Uint8Array(e.target.result);
        const workbook = XLSX.read(data, { type: "array" });
        processWorkbook(workbook);
        loadingIndicator.style.display = "none";
      } catch (error) {
        console.error("Error processing file:", error);
        showError(`Error: ${error.message}`);
        loadingIndicator.style.display = "none";
      }
    };
    reader.readAsArrayBuffer(uploadedFile);
  });

  function showError(message) {
    fileStatus.textContent = "Error processing file";
    fileStatus.className = "status-badge badge-error";
    errorDetails.innerHTML = `
        <h4>Error Details:</h4>
        <p>${message}</p>
        <p><strong>Important:</strong> Please ensure your Excel file format is now:</p>
        <pre>Class | # of Std. | Subject | [Hall Allocations...]</pre>`;
    errorDetails.style.display = "block";
  }

  function processWorkbook(workbook) {
    const firstSheetName = workbook.SheetNames[0];
    const worksheet = workbook.Sheets[firstSheetName];
    const jsonData = XLSX.utils.sheet_to_json(worksheet, { header: 1 });

    let headerRowIndex = -1;
    for (let i = 0; i < Math.min(10, jsonData.length); i++) {
      const row = jsonData[i];
      if (row && row.length > 0 && String(row[0]).trim() === "Class") {
        headerRowIndex = i;
        break;
      }
    }

    if (headerRowIndex === -1) {
      throw new Error(
        'Could not find a header row starting with "Class". Please check the file format.'
      );
    }

    const headerRow = jsonData[headerRowIndex];
    const capacityRow = jsonData[headerRowIndex + 1];

    if (!capacityRow) {
      throw new Error("Could not find capacity row below the header row.");
    }

    // **DYNAMICALLY FIND HALL START**
    // Find the first column named "Hall..."
    const hallsStartIndex = headerRow.findIndex((header) =>
      String(header).trim().startsWith("Hall")
    );

    if (hallsStartIndex === -1) {
      throw new Error("Could not find any 'Hall' columns in the header row.");
    }

    // Determine if there is a subject column based on where halls start
    const hasSubjectColumn = hallsStartIndex === 3;

    const hallNames = headerRow.slice(hallsStartIndex);
    const hallCapacities = capacityRow.slice(hallsStartIndex);

    const halls = {};
    for (let i = 0; i < hallNames.length; i++) {
      const hallName = hallNames[i];
      const capacity = hallCapacities[i];
      if (hallName && String(hallName).trim() && capacity && !isNaN(capacity)) {
        halls[hallName] = {
          name: hallName,
          capacity: Number(capacity),
          classes: [],
        };
      }
    }

    for (let i = headerRowIndex + 2; i < jsonData.length; i++) {
      const row = jsonData[i];
      if (!row || row.length < 2) continue;

      const className = row[0];
      if (!className || String(className).trim() === "") break;

      // Read subject only if the column exists
      const subject = hasSubjectColumn ? row[2] || "" : "";

      const hallAllocations = row.slice(hallsStartIndex);
      for (let j = 0; j < hallNames.length; j++) {
        const hallName = hallNames[j];
        const studentCount = hallAllocations[j];
        if (
          halls[hallName] &&
          studentCount &&
          !isNaN(studentCount) &&
          Number(studentCount) > 0
        ) {
          halls[hallName].classes.push({
            className: className,
            students: Number(studentCount),
            subject: subject,
          });
        }
      }
    }

    generatedHallsData = halls;
    generateHallTables(halls);

    resultsSection.style.display = "block";
    resultsSection.scrollIntoView({ behavior: "smooth" });
  }

  function generateHallTables(halls) {
    hallTablesContainer.innerHTML = "";
    const hallKeys = Object.keys(halls).sort((a, b) => {
      const numA = parseInt(a.replace(/\D/g, ""), 10);
      const numB = parseInt(b.replace(/\D/g, ""), 10);
      return !isNaN(numA) && !isNaN(numB) ? numA - numB : a.localeCompare(b);
    });

    for (const hallName of hallKeys) {
      const hall = halls[hallName];
      if (hall.classes.length === 0) continue;

      const totalStudentsInHall = hall.classes.reduce(
        (sum, cls) => sum + cls.students,
        0
      );

      const card = document.createElement("div");
      card.className = "col-lg-9 col-sm-12 col-md-12";
      // Updated card layout to better match the user's image
      card.innerHTML = `
        <div class="allocation-card">
            <img src="${headerImageBase64}" alt="Header" class="allocation-header-image" style="height:10vh">
            <div class="allocation-details" style="font-family: 'Times New Roman', Times, serif;">
                <div class="d-flex justify-content-between px-md-5 px-3 pt-2" style="font-size: 1.2rem; font-weight: bold;">
                    <span>${examDetails.shift}</span>
                    <span>${examDetails.time}</span>
                </div>
                <div class="d-flex justify-content-between px-md-5 px-3" style="font-family: 'Times New Roman', Times, serif; font-size: 1.2rem; font-weight: bold;">
                    <span>${examDetails.campus}</span>
                    <span>Hall # <span class="hall-number-box">${hall.name.replace(
                      /\D/g,
                      ""
                    )}</span></span>
                </div>
            </div>
            <div class="allocation-table-container">
                <table class="allocation-table">
                    <thead>
    <!-- First row: Day (covers Class & # Of Students), Date (covers Subject) -->
    <tr class="day-date-row" style="text-align: center; font-family: 'Times New Roman', Times, serif; font-size: 21px;">
        <th colspan="2" style="font-weight:bold; width: 50%; text-align: center;">Day: ${
          examDetails.day
        }</th>
        <th style="font-weight:bold; width: 50%; text-align: center;">Date: ${
          examDetails.date
        }</th>
    </tr>

    <!-- Second row: individual columns -->
    <tr>
        <th style="width: 25%; text-align: center; font-family: 'Times New Roman', Times, serif; font-size: 21px;">Class</th>
        <th style="width: 25%; text-align: center; font-family: 'Times New Roman', Times, serif;  font-size: 21px;"># Of Students</th>
        <th style="width: 50%; text-align: center; font-family: 'Times New Roman', Times, serif;  font-size: 21px;">Subject</th>
    </tr>
</thead>

                    <tbody>
                        ${hall.classes
                          .map(
                            (cls) => `
                            <tr style="font-family: 'Times New Roman', Times, serif; font-weight: bold; font-size: 19px;">
                                <td>${cls.className}</td>
                                <td style="text-align: center; font-size: 19px;">${cls.students}</td>
                                <td>${cls.subject}</td>
                            </tr>`
                          )
                          .join("")}
                        <tr style="font-family: 'Times New Roman', Times, serif; font-weight: bold; font-size: 19px;">
                            <td style="text-align: right;"><strong>Total</strong></td>
                            <td style="text-align: center;">${totalStudentsInHall}</td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
      `;
      hallTablesContainer.appendChild(card);
    }
    displayActionButtons();
  }

  function displayActionButtons() {
    actionButtonsContainer.innerHTML = "";

    const printBtn = document.createElement("button");
    printBtn.className = "btn btn-secondary btn-lg";
    printBtn.innerHTML = '<i class="fas fa-print me-2"></i>Print';
    printBtn.addEventListener("click", () => window.print());
    actionButtonsContainer.appendChild(printBtn);

    const excelBtn = document.createElement("button");
    excelBtn.className = "btn btn-success btn-lg";
    excelBtn.innerHTML =
      '<i class="fas fa-file-excel me-2"></i>Export to Excel';
    excelBtn.addEventListener("click", () => exportToExcel(generatedHallsData));
    actionButtonsContainer.appendChild(excelBtn);

    const wordBtn = document.createElement("button");
    wordBtn.className = "btn btn-primary btn-lg";
    wordBtn.innerHTML = '<i class="fas fa-file-word me-2"></i>Export to Word';
    wordBtn.addEventListener("click", () => exportToWord());
    actionButtonsContainer.appendChild(wordBtn);
  }

  function exportToExcel(hallsData) {
    const wb = XLSX.utils.book_new();
    const sortedHallNames = Object.keys(hallsData).sort((a, b) => {
      const numA = parseInt(a.replace(/\D/g, ""), 10);
      const numB = parseInt(b.replace(/\D/g, ""), 10);
      return !isNaN(numA) && !isNaN(numB) ? numA - numB : a.localeCompare(b);
    });

    for (const hallName of sortedHallNames) {
      const hall = hallsData[hallName];
      if (hall.classes.length > 0) {
        const sheetData = [["Class", "# of Students", "Subject"]];
        let total = 0;
        hall.classes.forEach((cls) => {
          sheetData.push([cls.className, cls.students, cls.subject]);
          total += cls.students;
        });
        sheetData.push(["Total", total, ""]);

        const ws = XLSX.utils.aoa_to_sheet(sheetData);
        const safeSheetName = hall.name
          .replace(/[\\/*?[\]:]/g, "")
          .substring(0, 31);
        XLSX.utils.book_append_sheet(wb, ws, safeSheetName);
      }
    }
    XLSX.writeFile(wb, "Exam_Hall_Allocation.xlsx");
  }

function exportToWord() {
  if (!generatedHallsData || Object.keys(generatedHallsData).length === 0) {
    alert("No data available to export.");
    return;
  }
  if (!headerImageBase64) {
    alert("Header image is still loading. Please wait a moment and try again.");
    return;
  }

  // Clone the rendered cards and force inline inches on header image(s)
  const clone = hallTablesContainer.cloneNode(true);
  clone.querySelectorAll('img.allocation-header-image').forEach(img => {
    img.removeAttribute('width');
    img.removeAttribute('height');
    // CRUCIAL: inline inches so Word respects the physical size
    img.style.width = '8.26in';
    img.style.height = '0.92in';
    img.style.display = 'block';
    img.style.margin = '0 auto';
    img.style.objectFit = 'contain';
  });
  const cardsHTML = clone.innerHTML;

  const wordCSS = `
    <style>
      @page Section1 {
        size: 210mm 148mm; /* A5 landscape */
        mso-page-orientation: landscape;
        margin: 0;
      }
      div.Section1 { page: Section1; }

      html, body { margin:0; padding:0; }
      * { box-sizing: border-box; }

      .allocation-card{
        margin:0 !important;
        padding:0 !important;
        border:none;
        page-break-after: always;    /* Word */
        page-break-inside: avoid;
      }
      .allocation-card:last-of-type{
        page-break-after: auto;
      }

      .allocation-details,
      .allocation-table-container{
        margin:0 !important;
        padding:0 !important;
      }

      .allocation-table{ width:100%; border-collapse:collapse; margin:0 !important; }
      .allocation-table th, .allocation-table td{
        border:2px solid #000;
        padding:5px;
        text-align:left;
        font-family:"Times New Roman", Times, serif;
      }
      .allocation-table th{ font-weight:700; }

      .hall-number-box{
        border:2px solid #000; border-radius:10px; padding:0 6px; font-weight:700; display:inline-block;
      }
    </style>
  `;

  const fullHtml = `
    <html xmlns:w="urn:schemas-microsoft-com:office:word">
      <head>
        <meta charset="UTF-8">
        ${wordCSS}
        <!--[if gte mso 9]>
        <xml>
          <w:WordDocument>
            <w:View>Print</w:View>
            <w:Zoom>100</w:Zoom>
          </w:WordDocument>
        </xml>
        <![endif]-->
      </head>
      <body>
        <div class="Section1">
          ${cardsHTML}
        </div>
      </body>
    </html>
  `;

  const blob = new Blob(["\ufeff", fullHtml], { type: "application/msword" });
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = "Exam_Hall_Allocation.doc";
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
}


  // --- Initial Setup ---
  preloadHeaderImage();
});
