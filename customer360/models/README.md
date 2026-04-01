Place optional model artifacts for the analysis flow in this directory.

Expected files:
- `scaler.pkl`
- `segment_map.json`
- `cluster_rfm_profile.csv`

The backend loads these on startup when present and falls back gracefully when
they are absent.
