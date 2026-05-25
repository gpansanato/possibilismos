ALTER TABLE events
    ADD COLUMN wikidata_entities_json TEXT NULL AFTER canonical_title,
    ADD COLUMN wikidata_location_json TEXT NULL AFTER wikidata_entities_json,
    ADD COLUMN wikidata_relations_json TEXT NULL AFTER wikidata_location_json;
