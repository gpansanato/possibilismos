ALTER TABLE events
    ADD COLUMN review_status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending' AFTER base_score;

UPDATE events
SET review_status = CASE
    WHEN active = 1 THEN 'approved'
    ELSE 'rejected'
END;

UPDATE events
SET active = CASE
    WHEN review_status = 'approved' THEN 1
    ELSE 0
END;

CREATE INDEX idx_events_review_status ON events (review_status);
