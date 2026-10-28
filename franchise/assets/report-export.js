/**
 * Shared report export helper — franchise Reports hub.
 * Exports an on-screen <table id="..."> to a real .xlsx file using SheetJS
 * (loaded per-page from the cdnjs XLSX build). Elements marked class="no-export"
 * inside the table (action columns, buttons, hidden detail rows) are stripped
 * from the clone before conversion, so the workbook only contains report data.
 */
function frExportTableToExcel(tableId, filename, sheetName) {
    var original = document.getElementById(tableId);
    if (!original || typeof XLSX === 'undefined') return;

    var clone = original.cloneNode(true);
    clone.querySelectorAll('.no-export').forEach(function (el) { el.remove(); });

    var wb = XLSX.utils.book_new();
    var ws = XLSX.utils.table_to_sheet(clone, { raw: false });
    XLSX.utils.book_append_sheet(wb, ws, sheetName || 'Report');
    XLSX.writeFile(wb, filename);
}
