ALTER TABLE event_imports
    ADD COLUMN source_variant VARCHAR(120) NOT NULL DEFAULT 'default' AFTER source;

CREATE INDEX idx_event_imports_source_variant ON event_imports (source, source_variant);

ALTER TABLE event_sources
    ADD COLUMN source_variant VARCHAR(120) NOT NULL DEFAULT 'default' AFTER source;

CREATE INDEX idx_event_sources_source_variant ON event_sources (source, source_variant);
