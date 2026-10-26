// Speaks another version of the protocol than the one of the bundle.
if (process.argv.includes('--protocol-version')) {
  process.stdout.write('0\n');
}
