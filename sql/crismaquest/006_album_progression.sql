-- CrismaQuest album progression and reward notifications.
-- Safe/idempotent extension: no existing gameplay tables are altered.

CREATE TABLE IF NOT EXISTS cq_album_activity (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  activity_date DATE NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cq_album_activity_user_date (user_id, activity_date),
  KEY idx_cq_album_activity_user_date (user_id, activity_date),
  CONSTRAINT fk_cq_album_activity_user
    FOREIGN KEY (user_id) REFERENCES ct_utenti(id_utente) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cq_user_notifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  event_key VARCHAR(190) NOT NULL,
  notification_type VARCHAR(40) NOT NULL,
  title VARCHAR(180) NOT NULL,
  message VARCHAR(500) NULL,
  payload_json LONGTEXT NULL,
  seen_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cq_user_notification_event (user_id, event_key),
  KEY idx_cq_user_notification_pending (user_id, seen_at, created_at),
  CONSTRAINT fk_cq_user_notification_user
    FOREIGN KEY (user_id) REFERENCES ct_utenti(id_utente) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
