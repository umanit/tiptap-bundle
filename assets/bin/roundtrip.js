#!/usr/bin/env node
/**
 * Round-trips HTML contents through the editor schema and reports what it dropped.
 *
 * Reads JSON lines on stdin: `{"html": "<p>…</p>", …}`. Writes one JSON line per input line on stdout: the input
 * fields untouched (an `id`, a field name…) plus `after`, `textChanged`, `lost` and `inlineStyles` (the distinct
 * colors, background colors and font sizes the content sets inline), or `error` for a content the editor cannot
 * load. Blank lines are skipped. An input line which is not JSON aborts with exit code 1.
 *
 * Option `--protocol-version` writes the version of this protocol (see `src/audit/protocol.js`) and exits: the PHP side
 * refuses a script which does not speak its own.
 *
 * Option `--legacy-styles=<file.json>` turns on the legacy import mode: the file maps inline `color`,
 * `backgroundColor` and `fontSize` values onto palette classes, which are then converted instead of dropped.
 *
 * Meant to audit existing contents before moving a field from another editor (CKEditor…) to this one.
 */
/* global globalThis */
import { readFileSync } from 'node:fs';
import { createInterface } from 'node:readline';
import { PROTOCOL_VERSION } from '../src/audit/protocol.js';

if (process.argv.includes('--protocol-version')) {
  process.stdout.write(`${PROTOCOL_VERSION}\n`, () => process.exit(0));
  await new Promise(() => {
  });
}

const { JSDOM } = await import('jsdom');
const dom = new JSDOM('<!doctype html><body></body>', { pretendToBeVisual: true });

// The editor reads the DOM from globals.
Object.getOwnPropertyNames(dom.window)
  .filter((name) => !(name in globalThis))
  .forEach((name) => {
    try {
      globalThis[name] = dom.window[name];
    } catch {
      // Some window properties are getters which throw outside a browser: they are not needed.
    }
  });
globalThis.window = dom.window;
globalThis.document = dom.window.document;
Object.defineProperty(globalThis, 'navigator', { value: dom.window.navigator, configurable: true });

const { default: RoundTripper } = await import('../src/audit/round-trip.js');

const legacyOption = process.argv.slice(2).find((arg) => arg.startsWith('--legacy-styles='));
let legacyStyles = null;

if (legacyOption) {
  try {
    legacyStyles = JSON.parse(readFileSync(legacyOption.slice('--legacy-styles='.length), 'utf8'));
  } catch (error) {
    process.stderr.write(`Cannot read the legacy styles: ${error.message}\n`);
    process.exit(1);
  }
}

const roundTripper = new RoundTripper(dom.window, legacyStyles);
let exitCode = 0;

for await (const line of createInterface({ input: process.stdin, crlfDelay: Infinity })) {
  if (line.trim() !== '') {
    try {
      const record = JSON.parse(line);

      try {
        process.stdout.write(`${JSON.stringify({ ...record, ...roundTripper.run(record.html ?? '') })}\n`);
      } catch (error) {
        process.stdout.write(`${JSON.stringify({ ...record, error: error.message })}\n`);
      }
    } catch (error) {
      process.stderr.write(`Invalid JSON line: ${error.message}\n`);
      exitCode = 1;
      break;
    }
  }
}

roundTripper.destroy();

// `process.exit()` drops what a full pipe still has queued: it waits for stdout to be flushed.
process.stdout.write('', () => process.exit(exitCode));
