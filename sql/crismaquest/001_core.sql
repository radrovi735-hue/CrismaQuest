-- CrismaQuest core extensions
-- Safe to run after the ChronoQuest base schema.
-- MySQL 8.x / utf8mb4

CREATE TABLE IF NOT EXISTS cq_streaks (
  user_id INT NOT NULL,
  current_streak INT NOT NULL DEFAULT 0,
  longest_streak INT NOT NULL DEFAULT 0,
  last_qualified_activity_date DATE NULL,
  streak_freezes_available INT NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  CONSTRAINT fk_cq_streak_user FOREIGN KEY (user_id) REFERENCES ct_utenti(id_utente) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cq_streak_events (
  id BIGINT NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  activity_date DATE NOT NULL,
  source_type VARCHAR(50) NOT NULL,
  source_id VARCHAR(100) NOT NULL,
  idempotency_key CHAR(64) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cq_streak_event_idempotency (idempotency_key),
  KEY idx_cq_streak_user_date (user_id, activity_date),
  CONSTRAINT fk_cq_streak_event_user FOREIGN KEY (user_id) REFERENCES ct_utenti(id_utente) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cq_saint_cards (
  id INT NOT NULL AUTO_INCREMENT,
  card_number INT NOT NULL,
  slug VARCHAR(120) NOT NULL,
  name VARCHAR(180) NOT NULL,
  category VARCHAR(100) NOT NULL,
  short_bio TEXT NOT NULL,
  feast_date VARCHAR(20) NULL,
  short_teaching VARCHAR(500) NULL,
  image_path VARCHAR(255) NULL,
  image_license VARCHAR(255) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cq_saint_card_number (card_number),
  UNIQUE KEY uq_cq_saint_card_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cq_card_editions (
  id INT NOT NULL AUTO_INCREMENT,
  card_id INT NOT NULL,
  edition_type VARCHAR(40) NOT NULL DEFAULT 'normal',
  visual_asset VARCHAR(255) NULL,
  chance_weight INT NOT NULL DEFAULT 100,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cq_card_edition (card_id, edition_type),
  CONSTRAINT fk_cq_card_edition_card FOREIGN KEY (card_id) REFERENCES cq_saint_cards(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cq_user_cards (
  id BIGINT NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  card_edition_id INT NOT NULL,
  quantity INT NOT NULL DEFAULT 1,
  first_obtained_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cq_user_card (user_id, card_edition_id),
  CONSTRAINT fk_cq_user_card_user FOREIGN KEY (user_id) REFERENCES ct_utenti(id_utente) ON DELETE CASCADE,
  CONSTRAINT fk_cq_user_card_edition FOREIGN KEY (card_edition_id) REFERENCES cq_card_editions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cq_meetings (
  id INT NOT NULL AUTO_INCREMENT,
  class_id INT NOT NULL,
  title VARCHAR(180) NOT NULL,
  meeting_at DATETIME NOT NULL,
  theme VARCHAR(255) NULL,
  chapter_key VARCHAR(80) NULL,
  presence_xp INT NOT NULL DEFAULT 50,
  status VARCHAR(30) NOT NULL DEFAULT 'scheduled',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_cq_meeting_class_date (class_id, meeting_at),
  CONSTRAINT fk_cq_meeting_class FOREIGN KEY (class_id) REFERENCES ct_classi(id_classe) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cq_attendance (
  id BIGINT NOT NULL AUTO_INCREMENT,
  meeting_id INT NOT NULL,
  user_id INT NOT NULL,
  status ENUM('presente','ausente','justificada','atrasado') NOT NULL DEFAULT 'ausente',
  marked_by INT NULL,
  note VARCHAR(500) NULL,
  xp_granted INT NOT NULL DEFAULT 0,
  marked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cq_attendance_meeting_user (meeting_id, user_id),
  CONSTRAINT fk_cq_attendance_meeting FOREIGN KEY (meeting_id) REFERENCES cq_meetings(id) ON DELETE CASCADE,
  CONSTRAINT fk_cq_attendance_user FOREIGN KEY (user_id) REFERENCES ct_utenti(id_utente) ON DELETE CASCADE,
  CONSTRAINT fk_cq_attendance_marker FOREIGN KEY (marked_by) REFERENCES ct_utenti(id_utente) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
