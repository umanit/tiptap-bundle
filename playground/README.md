# Playground

Plain Symfony application used to validate that `umanit/tiptap-bundle` installs and works on
any project, with both front-end pipelines:

| URL            | Pipeline                                                             |
|----------------|----------------------------------------------------------------------|
| `/assetmapper` | AssetMapper + importmap (`importmap.php`, `assets/controllers.json`) |
| `/encore`      | Webpack Encore + stimulus-bridge (`webpack.config.js`, `encore/`)    |

Each page renders a `TiptapType` field inside a regular form plus a standalone `<twig:Umanit:Tiptap:Editor>`.

```shell
cd playground
composer install        # the bundle is symlinked from ..
php bin/console importmap:install
npm install && npm run build
php -S 127.0.0.1:8000 -t public
```

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
- Encore: `"@umanit/tiptap-bundle": "file:vendor/umanit/tiptap-bundle/assets"` in `package.json`.
- Dev checkout caveat: `npm install` here follows the `file:` symlink and can alter `../assets/node_modules`
  (the bundle's own Yarn install). If `make eslint` breaks afterwards, run `rm -rf assets/node_modules && yarn install`
  in `assets/`. A real installation (copy under `vendor/`) is not affected.
