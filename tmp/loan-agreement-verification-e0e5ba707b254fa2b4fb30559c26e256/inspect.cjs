const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const streams = JSON.parse(fs.readFileSync(path.join(process.argv[2], 'streams.json')));
const chunks = [];
for (const stream of streams) {
  for (const { 1: graphics } of stream.text.matchAll(/\[((?:\((?:\\.|[^\\)])*\)|[-\d. ])+)\]\s*TJ/gs)) {
    const text = [...graphics.matchAll(/\(((?:\\.|[^\\)])*)\)/gs)].map(match => {
      const bytes = Buffer.from(match[1].replace(/\\([\\()])/g, '$1'), 'latin1');
      return bytes.swap16().toString('utf16le');
    }).join('');
    chunks.push(text);
  }
}
const printed = chunks.join(' ').replace(/\s+/g, ' ');
for (const required of [
  'Agreement Test Consumer', 'AGREEMENT-1', 'Loan #1', 'loan-terms-v2',
  'Nov 19, 2026 9:30 AM', 'Nov 20, 2026 10:00 AM', '₱500.00', '0%', '3 months', '₱166.67',
  'Finalized installment schedule', '1 Dec 19, 2026 ₱166.67', '2 Jan 19, 2027 ₱166.67', '3 Feb 19, 2027 ₱166.66',
]) assert.ok(printed.includes(required), 'Generated PDF is missing ' + required);
const consent = execFileSync('C:/xampp/php/php.exe', [
  '-r', "require 'app/Support/LoanTerms.php'; echo \\App\\Support\\LoanTerms::text(3);",
], { encoding: 'utf8' }).replace(/\s+/g, ' ');
assert.ok(printed.includes(consent), 'Generated PDF should contain the exact accepted wording');
console.log('Extracted and verified every agreement paragraph, approved loan field, and schedule row in the received PDF.');
