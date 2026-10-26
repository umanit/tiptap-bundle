import { afterEach, describe, expect, it, vi } from 'vitest';
import { createFileManagerAdapter, registerFileManagerAdapter } from '../src/file-manager/registry.js';
import defaultAdapter, { DEFAULT_ADAPTER_NAME, MESSAGE_TYPE } from '../src/file-manager/default-adapter.js';
import '../src/index.js';

describe('file manager registry', () => {
  it('registers the default adapter on import', () => {
    expect(() => createFileManagerAdapter(DEFAULT_ADAPTER_NAME, '/files')).not.toThrow();
  });

  it('creates an adapter from a registered factory', () => {
    const adapter = vi.fn();
    const factory = vi.fn(() => adapter);
    registerFileManagerAdapter('custom', factory);

    expect(createFileManagerAdapter('custom', '/fm')).toBe(adapter);
    expect(factory).toHaveBeenCalledWith('/fm');
  });

  it('lists registered adapters when the name is unknown', () => {
    expect(() => createFileManagerAdapter('nope', '/fm')).toThrow(/Unknown file manager adapter "nope".*default/);
  });
});

describe('default file manager adapter', () => {
  afterEach(() => {
    vi.restoreAllMocks();
  });

  const open = () => {
    const popup = vi.spyOn(window, 'open').mockReturnValue(null);
    const onSelect = vi.fn();
    defaultAdapter('/files?conf=images')(onSelect);

    return { popup, onSelect };
  };

  const send = (data, origin = window.location.origin) => {
    window.dispatchEvent(new MessageEvent('message', { data, origin }));
  };

  it('opens the file manager in a popup', () => {
    const { popup } = open();

    expect(popup).toHaveBeenCalledOnce();
    expect(popup.mock.calls[0][0]).toBe(`${window.location.origin}/files?conf=images`);
  });

  it('calls back with the selected url, once', () => {
    const { onSelect } = open();

    send({ type: MESSAGE_TYPE, url: '/uploads/a.png' });
    send({ type: MESSAGE_TYPE, url: '/uploads/b.png' });

    expect(onSelect).toHaveBeenCalledOnce();
    expect(onSelect).toHaveBeenCalledWith('/uploads/a.png');
  });

  it('ignores messages from another origin', () => {
    const { onSelect } = open();

    send({ type: MESSAGE_TYPE, url: '/x.png' }, 'https://evil.example');

    expect(onSelect).not.toHaveBeenCalled();
  });

  it('ignores unrelated messages', () => {
    const { onSelect } = open();

    send({ type: 'other', url: '/x.png' });
    send('string');

    expect(onSelect).not.toHaveBeenCalled();
  });
});
