const fs = require('node:fs');
const path = require('node:path');
const zlib = require('node:zlib');
const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const pdf = fs.readFileSync(path.join(__dirname, 'approved-loan.pdf'));
const source = pdf.toString('latin1');
const pieces = [];
for (const match of source.matchAll(/(\d+) 0 obj\s*<<([\s\S]*?)>>\s*stream\r?\n/g)) {
    const length = Number(match[2].match(/\/Length\s+(\d+)/)?.[1]);
    if (!length) continue;
    const start = match.index + match[0].length;
    let bytes = pdf.subarray(start, start + length);
    if (match[2].includes('/FlateDecode')) {
        try { bytes = zlib.inflateSync(bytes); } catch { continue; }
    }
    const text = bytes.toString('latin1');
    for (const [, graphics] of text.matchAll(/\[((?:\((?:\\.|[^\\)])*\)|[-\d. ])+)\]\s*TJ/gs)) {
        pieces.push([...graphics.matchAll(/\(((?:\\.|[^\\)])*)\)/gs)].map(([, literal]) =>
            Buffer.from(literal.replace(/\\([\\()])/g, '$1'), 'latin1').swap16().toString('utf16le')
        ).join(''));
    }
}
const normalize = text => text.replace(/\s+/g, ' ').trim();
const printed = normalize(pieces.join(' '));
const accepted = normalize(fs.readFileSync(path.join(__dirname, 'accepted-terms.txt'), 'utf8'));
assert.ok(printed.includes(accepted), 'The complete accepted text must appear in the downloaded PDF.');
for (const term of ['loan-terms-v3', '6 months', 'Finalized installment schedule']) {
    assert.ok(printed.includes(term), 'Missing PDF content: ' + term);
}
assert.match(source, /\/BaseFont\s*\/\S*Bold\b/);
console.log('Extracted the complete accepted terms, the agreement version, and the schedule heading from the downloaded PDF.');


