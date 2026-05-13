const { PdfReader } = require("pdfreader");

const pdfPath = process.argv[2];

if (!pdfPath) {
    console.error("Please provide a PDF path.");
    process.exit(1);
}

let text = "";
new PdfReader().parseFileItems(pdfPath, (err, item) => {
  if (err) {
    console.error("Error:", err);
    process.exit(1);
  } else if (!item) {
    // End of file
    console.log(JSON.stringify({ text }));
  } else if (item.text) {
    text += item.text + " ";
  }
});
