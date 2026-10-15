import path from 'node:path';
import { fileURLToPath } from 'node:url';
import js from '@eslint/js';
import { FlatCompat } from '@eslint/eslintrc';

const compat = new FlatCompat({
  baseDirectory: path.dirname(fileURLToPath(import.meta.url)),
  recommendedConfig: js.configs.recommended,
  allConfig: js.configs.all,
});

export default [...compat.extends('@umanit'), {
  files: ['*.config.{js,mjs,cjs}'],
  rules: {
    'import/no-extraneous-dependencies': ['error', { devDependencies: true }],
  },
}, {
  languageOptions: {
    // The bundle is not transpiled: no Babel config is needed, only its parser.
    parserOptions: { requireConfigFile: false },
  },
  rules: {
    // `@tiptap/pm` only exposes sub-path exports which the import plugin cannot resolve.
    'import/no-unresolved': ['error', { ignore: ['@tiptap/pm'] }],
    // Relative imports are written with their extension (required by native ESM and AssetMapper).
    'import/extensions': ['error', 'ignorePackages'],
    // Shared constants live in their own module.
    'import/prefer-default-export': 'off',
    'no-new': 'off',
    'no-unused-vars': ['error', { argsIgnorePattern: '^_' }],
  },
}];
