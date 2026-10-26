import { beforeEach, describe, expect, it } from 'vitest';
import { renderDiffLists, renderDiffPreviews } from '../src/editor/diff-viewer.js';

beforeEach(() => {
  document.head.innerHTML = '<link rel="stylesheet" href="/app.css">';
  document.body.innerHTML = '<ul id="signals"></ul><div id="lines"></div>'
    + '<iframe id="before"></iframe><iframe id="after"></iframe>';
});

describe('renderDiffLists', () => {
  it('lists the lost signals and the lines, as text only', () => {
    renderDiffLists(document.getElementById('signals'), document.getElementById('lines'), {
      lost: [{ signal: 'span', before: 3, after: 1 }],
      lines: [
        { type: 'removed', line: '<b onclick="x()">old</b>' },
        { type: 'added', line: '<strong>new</strong>' },
        { type: 'gap', line: '…' },
      ],
    });

    expect(document.getElementById('signals').textContent).toBe('span (3 → 1)');

    const lines = [...document.querySelectorAll('#lines code')];

    expect(lines.map((line) => line.textContent))
      .toEqual(['- <b onclick="x()">old</b>', '+ <strong>new</strong>', '  …']);
    expect(lines[0].className).toBe('umanit-tiptap__diff-line umanit-tiptap__diff-line--removed');
    expect(document.querySelector('#lines b')).toBeNull();
  });
});

describe('renderDiffPreviews', () => {
  it('replaces the frames by fresh ones which show the contents with the stylesheets of the page', () => {
    const [before, after] = ['before', 'after'].map((id) => document.getElementById(id));

    renderDiffPreviews(before, after, '<p>old</p>', '<p>new</p>');

    const frames = [...document.querySelectorAll('iframe')];

    expect(frames).toHaveLength(2);
    expect(frames).not.toContain(before);
    expect(frames.map((frame) => frame.id)).toEqual(['before', 'after']);
    expect(frames[0].srcdoc).toContain('<p>old</p>');
    expect(frames[0].srcdoc).toContain('<link rel="stylesheet" href="/app.css">');
    expect(frames[1].srcdoc).toContain('<p>new</p>');
  });
});
