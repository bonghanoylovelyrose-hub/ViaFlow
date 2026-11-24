const fs = require('node:fs');
const net = require('node:net');
const path = require('node:path');
const root = process.argv[2];
const write = (name, data) => fs.writeFileSync(path.join(root, name), data);
const server = net.createServer((socket) => {
  let pending = '', recipient = '', body = [], insideData = false;
  socket.setEncoding('utf8');
  socket.write('220 localhost test SMTP\r\n');
  socket.on('data', (chunk) => {
    pending += chunk;
    for (let end; (end = pending.indexOf('\r\n')) !== -1; ) {
      const line = pending.slice(0, end);
      pending = pending.slice(end + 2);
      if (insideData) {
        if (line === '.') {
          fs.appendFileSync(path.join(root, 'recipients.txt'), recipient + '\n');
          write('received.eml', body.join('\r\n'));
          insideData = false;
          socket.write('250 mail accepted\r\n');
        } else {
          body.push(line.replace(/^\.\./, '.'));
        }
      } else if (/^(EHLO|HELO|MAIL FROM:|RSET)/i.test(line)) {
        socket.write('250 localhost\r\n');
      } else if (/^RCPT TO:/i.test(line)) {
        recipient = line;
        socket.write('250 recipient accepted\r\n');
      } else if (line === 'DATA') {
        body = [];
        insideData = true;
        socket.write('354 enter mail data\r\n');
      } else if (line === 'QUIT') {
        socket.end('221 bye\r\n');
      } else {
        socket.write('500 unsupported command\r\n');
      }
    }
  });
});
server.listen(0, '127.0.0.1', () => { write('port.txt', String(server.address().port)); });
