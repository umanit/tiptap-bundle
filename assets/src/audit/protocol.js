/**
 * Version of the JSON lines protocol spoken by `bin/roundtrip.js` and by `Audit\RoundTrip\RoundTripProtocol` in PHP.
 *
 * Both sides declare it, `--protocol-version` tells it to the PHP side which refuses a script that speaks another one.
 * Increment it, with its PHP twin, whenever the shape of a request or of a response changes.
 */
export const PROTOCOL_VERSION = 1;
