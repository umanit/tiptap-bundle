/**
 * Column widths of a table imported from another editor: CKEditor writes the width on every cell
 * (`<td style="width:274px">`, `<td width="120">`) where the schema keeps it on the columns (`<col width>`).
 */

// A width of 1px to 5px is a stray one (a cell collapsed by the editor), not a column of that size.
const MAX_STRAY_WIDTH = 5;

const cache = new WeakMap();

const colspanOf = (cell) => parseInt(cell.getAttribute('colspan'), 10) || 1;

const firstColumnOf = (cell) => Array.from(cell.parentElement.children)
  .slice(0, Array.from(cell.parentElement.children).indexOf(cell))
  .reduce((count, sibling) => count + colspanOf(sibling), 0);

/**
 * @param {Element} cell
 * @returns {number|null} The width in pixels set on a cell, as a style or as an attribute; null for any other unit
 *   (`50%`, `10em`…), as for a missing or empty one
 */
export const cellPixelWidth = (cell) => {
  const width = (cell.style?.width || cell.getAttribute('width') || '').trim().toLowerCase();
  const match = width.match(/^(\d*\.?\d+)(px)?$/);

  return match ? Math.round(parseFloat(match[1])) : null;
};

/**
 * @param {Element} table
 * @param {number} index Index of a column, spans counted
 * @returns {number} The width of a `<col>`, 0 when it has none or not in pixels
 */
export const colgroupWidth = (table, index) => {
  const width = table.querySelectorAll('colgroup > col')[index]?.getAttribute('width') ?? '';

  return /^\d+(px)?$/.test(width) ? parseInt(width, 10) : 0;
};

/**
 * The width of each column, read on the cells: the most frequent one, the largest one when several are. Stray
 * widths are ignored, and so are the cells which span columns (their width is the sum of several columns).
 *
 * @param {Element} table
 * @returns {number[]} 0 for a column without any readable width
 */
export const widthsFromCells = (table) => {
  if (cache.has(table)) {
    return cache.get(table);
  }

  const votes = [];

  Array.from(table.rows ?? []).forEach((row) => {
    Array.from(row.cells).forEach((cell) => {
      const width = cellPixelWidth(cell);

      if (colspanOf(cell) === 1 && width !== null && width > MAX_STRAY_WIDTH) {
        const column = firstColumnOf(cell);
        votes[column] = votes[column] ?? new Map();
        votes[column].set(width, (votes[column].get(width) ?? 0) + 1);
      }
    });
  });

  const widths = Array.from(votes, (counts) => (counts
    ? [...counts].sort(([widthA, countA], [widthB, countB]) => countB - countA || widthB - widthA)[0][0]
    : 0));

  cache.set(table, widths);

  return widths;
};

/**
 * @param {Element} cell A `td` or `th`
 * @returns {boolean} Whether its width ends up in the width of a column
 */
export const isWidthConverted = (cell) => {
  const table = cell.closest('table');
  const width = cellPixelWidth(cell);

  if (!table || width === null || colspanOf(cell) !== 1) {
    return false;
  }

  const column = firstColumnOf(cell);

  return colgroupWidth(table, column) === 0 && widthsFromCells(table)[column] === width;
};
