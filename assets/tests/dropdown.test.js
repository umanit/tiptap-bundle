import { afterEach, describe, expect, it } from 'vitest';
import { Application } from '@hotwired/stimulus';
import DropdownController from '../src/controllers/dropdown_controller.js';

let application;

afterEach(() => {
  application?.stop();
});

describe('dropdown controller', () => {
  it('closes the other dropdowns when one opens', async () => {
    document.body.innerHTML = [1, 2].map(() => `
      <div data-controller="dropdown">
        <button type="button" data-action="dropdown#toggle"></button>
      </div>`).join('');
    application = Application.start();
    application.register('dropdown', DropdownController);
    await new Promise((resolve) => {
      setTimeout(resolve, 20);
    });

    const [first, second] = document.querySelectorAll('[data-controller]');

    first.querySelector('button').click();
    expect(first.hasAttribute('data-open')).toBe(true);

    second.querySelector('button').click();
    expect(second.hasAttribute('data-open')).toBe(true);
    expect(first.hasAttribute('data-open')).toBe(false);
  });

  it('tells on its button whether it is open, and gives it the focus back on Escape', async () => {
    document.body.innerHTML = `
      <div data-controller="dropdown">
        <button type="button" data-dropdown-target="button" data-action="dropdown#toggle"
          aria-expanded="false"></button>
        <ul><li><button type="button" id="entry"></button></li></ul>
      </div>`;
    application = Application.start();
    application.register('dropdown', DropdownController);
    await new Promise((resolve) => {
      setTimeout(resolve, 20);
    });

    const button = document.querySelector('[data-dropdown-target="button"]');

    button.click();
    expect(button.getAttribute('aria-expanded')).toBe('true');

    document.getElementById('entry').focus();
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));

    expect(button.getAttribute('aria-expanded')).toBe('false');
    expect(document.activeElement).toBe(button);
  });
});
