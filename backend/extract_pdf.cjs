const { PdfReader } = require("pdfreader");

const pdfPath = process.argv[2];

if (!pdfPath) {
    console.error("Please provide a PDF path.");
    process.exit(1);
}

// Row items are grouped by y-position (with tolerance) so each output line
// preserves column order left-to-right. This lets consumers reliably pick
// the rightmost column (e.g. VALOR) instead of parsing flattened text.
const Y_TOLERANCE = 0.15;

let rows = [];
let currentPage = null;
let currentY = null;
let currentRow = [];

function flushRow() {
    if (currentRow.length > 0) {
        currentRow.sort((a, b) => a.x - b.x);
        rows.push(currentRow.map((i) => i.text).join("|"));
        currentRow = [];
    }
}

new PdfReader().parseFileItems(pdfPath, (err, item) => {
    if (err) {
        console.error("Error:", err);
        process.exit(1);
    } else if (!item) {
        flushRow();
        console.log(JSON.stringify({ text: rows.join("\n") }));
    } else if (item.page) {
        if (item.page !== currentPage) {
            flushRow();
            currentPage = item.page;
            currentY = null;
        }
    } else if (item.text) {
        if (currentY === null || Math.abs(item.y - currentY) > Y_TOLERANCE) {
            flushRow();
            currentY = item.y;
        }
        currentRow.push({ x: item.x, text: item.text });
    }
});
