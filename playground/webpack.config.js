const path = require('path');
const Encore = require('@symfony/webpack-encore');

Encore
  .setOutputPath('public/build/')
  .setPublicPath('/build')
  .addEntry('app', './encore/app.js')
  .enableStimulusBridge('./assets/controllers.json')
  .splitEntryChunks()
  .enableSingleRuntimeChunk()
  .cleanupOutputBeforeBuild()
  .enableSourceMaps(!Encore.isProduction())
  // The bundle is symlinked here, so its own `assets/node_modules` (dev checkout) would provide a second copy of
  // Stimulus: force a single one. Not needed with a real installation.
  .addAliases({
    '@hotwired/stimulus': path.resolve(__dirname, 'node_modules/@hotwired/stimulus'),
  });

const config = Encore.getWebpackConfig();

// Webpack follows the symlink to the real path of the bundle, which lies outside of this application: let it find the
// dependencies of the bundle in the `node_modules` of the playground. Not needed with a real installation.
config.resolve.modules = [...(config.resolve.modules ?? ['node_modules']), path.resolve(__dirname, 'node_modules')];

module.exports = config;
