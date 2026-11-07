# PSGC lookup snapshot

These compact JSON files contain PSGC codes, names, and parent relationships used by the applicant location picker and server validation. They were sourced from the MIT-licensed `fish-and-bear/psgc` project (`psgc/data/core`, Q1 2026 data): https://github.com/fish-and-bear/psgc

The project republishes PSA PSGC data and is not affiliated with PSA. Refresh this snapshot when PSA publishes a newer PSGC masterlist. The source's province data includes pseudo-provinces so independent cities can be represented; the picker presents those cities directly from their region. The snapshot intentionally excludes coordinates, population, postal codes, and other unrelated metadata.


Barangays were imported from the same repository on 2026-11-07 (`psgc/data/core/barangays.json`): 42,010 unique codes, reduced to code, name and city parent. The source represents Manila through 14 district parents; those 897 barangays are mapped to the existing City of Manila code 1380600000. All resulting parent codes are checked against the bundled city snapshot. The form loads only one city at a time from its own Laravel endpoint.
