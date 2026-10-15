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

module.exports = Encore.getWebpackConfig();
