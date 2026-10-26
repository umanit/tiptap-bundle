import { spawnSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { describe, expect, it } from 'vitest';
import { PROTOCOL_VERSION } from '../src/audit/protocol.js';

// The cases are replayed by `tests/Unit/Audit/RoundTrip/RoundTripProtocolTest.php` on the PHP side
const shared = JSON.parse(readFileSync(
  path.resolve(import.meta.dirname, '../../tests/Fixtures/RoundTrip/protocol-cases.json'),
  'utf8',
));
const bin = path.resolve(import.meta.dirname, '../bin/roundtrip.js');
const run = (args, input = '') => spawnSync(process.execPath, [bin, ...args], { input, encoding: 'utf8' });

describe('protocol of bin/roundtrip.js', () => {
  it('is the version the shared cases are written for', () => {
    expect(shared.version).toBe(PROTOCOL_VERSION);
  });

  it('tells its version and exits without waiting for contents', () => {
    const { status, stdout } = run(['--protocol-version']);

    expect(status).toBe(0);
    expect(stdout.trim()).toBe(String(PROTOCOL_VERSION));
  });

  it('answers the shared cases as the PHP side expects them', () => {
    const cases = shared.cases.filter((c) => c.node);
    const { status, stdout } = run([], `${cases.map((c) => JSON.stringify(c.request)).join('\n')}\n`);

    expect(status).toBe(0);
    expect(stdout.trim().split('\n').map((line) => JSON.parse(line))).toEqual(cases.map((c) => c.response));
  });
});
