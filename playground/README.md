# Playground

Plain Symfony application used to validate that `umanit/tiptap-bundle` installs and works on any project, with both
front-end pipelines:

| URL            | Pipeline                                                             |
|----------------|----------------------------------------------------------------------|
| `/assetmapper` | AssetMapper + importmap (`importmap.php`, `assets/controllers.json`) |
| `/encore`      | Webpack Encore + stimulus-bridge (`webpack.config.js`, `encore/`)    |

Each page renders a `TiptapType` field inside a regular form plus a standalone `<twig:Umanit:Tiptap:Editor>`.

```shell
make playground         # from the root of the repository; http://127.0.0.1:8765 (PORT=… to change it)
```

It installs the dependencies (the bundle is symlinked from `..`), builds the Encore entrypoint and starts the PHP
server. In CI, `make -C playground smoke` requests each page of the running server and expects a 200.

Both pages also load `assets/tiptap-custom.js`, which shows how an application extends the editor (see
`docs/customization.md`): a TipTap mark and a manager registered from the entrypoint, and the two matching tools listed
by `src/Toolbar/PlaygroundToolsBuilder.php` and added to the default toolbar by
`src/Toolbar/PlaygroundToolbarBuilder.php`.

## Installation notes (what a host application has to do)

- `assets/controllers.json`: declare the four controllers of `@umanit/tiptap-bundle` (Flex does it automatically).
- AssetMapper: `@tiptap/pm` has no root export, so require its sub-paths. At the time of writing jsDelivr serves
  `prosemirror-model@1.25.12` with an encoded range that AssetMapper cannot resolve, hence the explicit pin:

  ```shell
  php bin/console importmap:require @hotwired/stimulus @intevation/tiptap-extension-office-paste @tiptap/core \
      @tiptap/extension-image @tiptap/extension-placeholder @tiptap/extension-subscript \
      @tiptap/extension-superscript @tiptap/extension-table @tiptap/extension-text-align @tiptap/starter-kit \
      @tiptap/pm/model @tiptap/pm/state @tiptap/pm/view prosemirror-model@1.25.4
  ```

  and map `@symfony/stimulus-bundle` and `@umanit/tiptap-bundle` (see `importmap.php`).
- Encore: `"@umanit/tiptap-bundle": "file:vendor/umanit/tiptap-bundle/assets"` in `package.json`, **and** the
  `peerDependencies` of that package (`@tiptap/*`, `@intevation/tiptap-extension-office-paste`). Symfony Flex adds them
  to a real application; the playground has no Flex, and npm links a `file:` directory without installing what it
  depends on, so the build fails with "Module not found" otherwise.
- Dev checkout caveat: `npm install` here follows the `file:` symlink and can alter `../assets/node_modules`
  (the bundle's own Yarn install). If `make eslint` breaks afterwards, run `rm -rf assets/node_modules && yarn install`
  in `assets/`. A real installation (copy under `vendor/`) is not affected.
