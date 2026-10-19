#!/usr/bin/env node
/**
 * Round-trips HTML contents through the editor schema and reports what it dropped.
 *
 * Reads JSON lines on stdin: `{"html": "<p>…</p>", …}`. Writes one JSON line per input line on stdout: the input
 * fields untouched (an `id`, a field name…) plus `after`, `textChanged` and `lost`, or `error` for a content the
 * editor cannot load. Blank lines are skipped. An input line which is not JSON aborts with exit code 1.
 *
 * Option `--legacy-styles=<file.json>` turns on the legacy import mode: the file maps inline `color`,
 * `backgroundColor` and `fontSize` values onto palette classes, which are then converted instead of dropped.
 *
 * Meant to audit existing contents before moving a field from another editor (CKEditor…) to this one.
 */
/* global globalThis */
import { readFileSync } from 'node:fs';
import { createInterface } from 'node:readline';
import { JSDOM } from 'jsdom';

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
process.exit(exitCode);
