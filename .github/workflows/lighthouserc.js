module.exports = {
  ci: {
    assert: {
      preset: "lighthouse:recommended",
      assertions: {
        "first-contentful-paint": ["error", {"minScore": 0.6}]
      }
    },
    upload: {
      target: "temporary-public-storage",
    },
  },
};
