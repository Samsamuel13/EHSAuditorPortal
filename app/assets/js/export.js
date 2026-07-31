// File: assets/js/export.js
document.addEventListener('DOMContentLoaded', function () {
    const monthInput = document.getElementById('month-input');
    const xlsxLink = document.getElementById('xlsx-link');
    const pdfLink = document.getElementById('pdf-link');

    function updateLinks() {
        const month = monthInput.value; // 'YYYY-MM'
        xlsxLink.href = `${window.EHS_BASE_URL}/api/export_xlsx.php?month=${month}`;
        pdfLink.href = `${window.EHS_BASE_URL}/api/export_pdf.php?month=${month}`;
    }

    monthInput.addEventListener('change', updateLinks);
    updateLinks();
});
