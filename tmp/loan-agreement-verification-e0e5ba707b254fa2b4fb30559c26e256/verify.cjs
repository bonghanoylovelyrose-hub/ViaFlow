const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const zlib = require('node:zlib');
const assert = require('node:assert/strict');
const read = (file, encoding) => fs.readFileSync(path.join(process.argv[2], file), encoding);
const recipients = read('recipients.txt', 'utf8').trim().split('\n');
assert.deepEqual(recipients, ['RCPT TO:<agreement-consumer@example.test>']);
const eml = read('received.eml', 'utf8');
const body = eml.match(/Content-Type: application\/pdf[^\r]*[\s\S]*?Content-Transfer-Encoding: base64[\s\S]*?\r\n\r\n([\r\nA-Za-z0-9+/=]+)\r\n--/i);
assert.ok(body, 'MIME message includes an application/pdf base64 attachment');
const pdf = Buffer.from(body[1], 'base64');
assert.deepEqual(pdf, read('viewed.pdf'));
console.log('Accepted by local SMTP for the registered test consumer, exactly once.');
console.log('SMTP PDF attachment matches the My Loans response. SHA-256 ' + crypto.createHash('sha256').update(pdf).digest('hex'));
const source = pdf.toString('latin1');
const streams = [];
for (const match of source.matchAll(/(\d+) 0 obj\s*<<([\s\S]*?)>>\s*stream\r?\n/g)) {
  const length = Number(match[2].match(/\/Length\s+(\d+)/)?.[1]);
  if (!length) continue;
  const start = match.index + match[0].length;
  let data = pdf.subarray(start, start + length);
  try {
    if (match[2].includes('/FlateDecode')) data = zlib.inflateSync(data);
  } catch { continue; }
  const text = data.toString('latin1');
  if (text.includes(' TJ') || text.includes('begincmap')) streams.push({ id: match[1], text });
}
fs.writeFileSync(path.join(process.argv[2], 'streams.json'), JSON.stringify(streams));
console.log('PDF page text and font streams found: ' + streams.map(({ id, text }) => id + '/' + text.length).join(', '));