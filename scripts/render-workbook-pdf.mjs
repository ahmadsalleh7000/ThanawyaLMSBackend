import playwright from '../../frontend/node_modules/playwright/index.js';
import { readFile } from 'node:fs/promises';

const { chromium } = playwright;

const [, , htmlPath, outputPath] = process.argv;

if (!htmlPath || !outputPath) {
  console.error('Usage: node render-workbook-pdf.mjs <html-path> <output-path>');
  process.exit(2);
}

const executablePath = process.env.WORKBOOK_CHROME_PATH || '/usr/bin/google-chrome';
const html = await readFile(htmlPath, 'utf8');
const browser = await chromium.launch({
  executablePath,
  args: ['--no-sandbox', '--disable-dev-shm-usage'],
});

try {
  const page = await browser.newPage({ locale: 'ar-EG' });
  await page.setContent(html, { waitUntil: 'networkidle' });
  await page.emulateMedia({ media: 'print' });
  await page.pdf({
    path: outputPath,
    format: 'A4',
    printBackground: true,
    preferCSSPageSize: true,
    displayHeaderFooter: true,
    headerTemplate: '<div></div>',
    footerTemplate:
      '<div style="width:100%;font-size:8px;color:#64748b;padding:0 14mm;display:flex;justify-content:space-between;font-family:Arial,sans-serif;"><span>Ahmed LMS</span><span><span class="pageNumber"></span> / <span class="totalPages"></span></span></div>',
    margin: { top: '14mm', right: '14mm', bottom: '16mm', left: '14mm' },
  });
} finally {
  await browser.close();
}
