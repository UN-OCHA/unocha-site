// Default config
const config = {
  browserContext: 'incognito',
  launch: {
    slowMo: 10,
    headless: 'new',
    ignoreHTTPSErrors: true,
  },
  testTimeout: 10000,
};

// Determine which mode we're running.
const DEBUG_MODE = process.argv.includes('--debug');

// When debugging we want to see the browser do its work.
if (DEBUG_MODE) {
  config.launch.headless = false;
  config.testTimeout = 10000;
  config.launch.slowMo = 250;
}

// Export
module.exports = config;

