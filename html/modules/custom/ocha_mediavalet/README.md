# OCHA MediaValet

Provides integration with MediaValet for both images and videos.

## Videos

For embedding we support 2 differenct modes:

- local: Use a plain video html tag with a poster
- directlink: Use MV API to create embedding

## Drush commands

- `ocha_mediavalet:generate-embed-link uuid` generates embedding for a certain asset
- `ocha_mediavalet:generate-missing-embed-links` generates embedding for all videos

