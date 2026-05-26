CREATE INDEX idx_event_import_source_key ON event_imports (source, normalized_key);
CREATE INDEX idx_event_imports_run_date ON event_imports (run_date);
CREATE INDEX idx_event_imports_canonical_event ON event_imports (canonical_event_id);
CREATE INDEX idx_event_sources_source_id ON event_sources (source, source_event_id);
