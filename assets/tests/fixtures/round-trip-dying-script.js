// Speaks the protocol version, then stops without answering any content.
import { PROTOCOL_VERSION } from '../../src/audit/protocol.js';

if (process.argv.includes('--protocol-version')) {
  process.stdout.write(`${PROTOCOL_VERSION}\n`);
} else {
  process.exit(1);
}
