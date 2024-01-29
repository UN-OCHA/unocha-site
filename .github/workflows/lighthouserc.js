module.exports = {
  ci: {
    collect: {
      startServerCommand: 'docker compose -f tests/docker-compose.yml exec -T drupal drush rs 127.0.0.1:8080'
    },
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
