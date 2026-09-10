-- CrismaQuest gameplay engine v1.
-- Canonical season: 2026-09-10 through 2027-02-09.
-- Safe/idempotent on MySQL/MariaDB shared hosting.

CREATE TABLE IF NOT EXISTS cq_game_config (
  config_key VARCHAR(80) NOT NULL,
  config_value VARCHAR(255) NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (config_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cq_game_levels (
  level_no TINYINT NOT NULL,
  name VARCHAR(80) NOT NULL,
  xp_min INT NOT NULL,
  PRIMARY KEY (level_no),
  UNIQUE KEY uq_cq_game_level_xp (xp_min)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cq_journey_steps (
  id INT NOT NULL AUTO_INCREMENT,
  step_no TINYINT NOT NULL,
  chapter_no TINYINT NOT NULL,
  title VARCHAR(180) NOT NULL,
  subtitle VARCHAR(255) NULL,
  opens_at DATE NOT NULL,
  closes_at DATE NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cq_step_no (step_no),
  KEY idx_cq_step_chapter (chapter_no, step_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cq_missions (
  id INT NOT NULL AUTO_INCREMENT,
  slug VARCHAR(120) NOT NULL,
  step_no TINYINT NULL,
  chapter_no TINYINT NOT NULL,
  mission_type VARCHAR(40) NOT NULL,
  title VARCHAR(180) NOT NULL,
  body TEXT NOT NULL,
  question TEXT NULL,
  options_json TEXT NULL,
  correct_answer VARCHAR(120) NULL,
  feedback TEXT NULL,
  xp_reward INT NOT NULL DEFAULT 0,
  lumen_reward INT NOT NULL DEFAULT 0,
  bonus_xp_correct INT NOT NULL DEFAULT 0,
  special_reward VARCHAR(120) NULL,
  available_from DATE NOT NULL,
  available_until DATE NOT NULL,
  repeatable TINYINT(1) NOT NULL DEFAULT 0,
  grants_streak TINYINT(1) NOT NULL DEFAULT 1,
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cq_mission_slug (slug),
  KEY idx_cq_mission_availability (active, available_from, available_until),
  KEY idx_cq_mission_step (step_no, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cq_mission_completions (
  id BIGINT NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  mission_id INT NOT NULL,
  answer_text TEXT NULL,
  was_correct TINYINT(1) NULL,
  xp_awarded INT NOT NULL DEFAULT 0,
  lumens_awarded INT NOT NULL DEFAULT 0,
  completed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cq_mission_completion (user_id, mission_id),
  KEY idx_cq_mission_completion_user_date (user_id, completed_at),
  CONSTRAINT fk_cq_mc_user FOREIGN KEY (user_id) REFERENCES ct_utenti(id_utente) ON DELETE CASCADE,
  CONSTRAINT fk_cq_mc_mission FOREIGN KEY (mission_id) REFERENCES cq_missions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cq_reward_events (
  id BIGINT NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  reward_key VARCHAR(190) NOT NULL,
  xp_delta INT NOT NULL DEFAULT 0,
  lumen_delta INT NOT NULL DEFAULT 0,
  description VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cq_reward_key (reward_key),
  KEY idx_cq_reward_user_date (user_id, created_at),
  CONSTRAINT fk_cq_reward_user FOREIGN KEY (user_id) REFERENCES ct_utenti(id_utente) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cq_chest_catalog (
  id INT NOT NULL AUTO_INCREMENT,
  slug VARCHAR(80) NOT NULL,
  name VARCHAR(120) NOT NULL,
  threshold_xp INT NOT NULL,
  reward_lumens INT NOT NULL DEFAULT 0,
  card_count TINYINT NOT NULL DEFAULT 0,
  guaranteed_new TINYINT(1) NOT NULL DEFAULT 0,
  cosmetic_slug VARCHAR(80) NULL,
  description VARCHAR(255) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cq_chest_slug (slug),
  UNIQUE KEY uq_cq_chest_threshold (threshold_xp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cq_user_chests (
  id BIGINT NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  chest_id INT NOT NULL,
  result_json TEXT NULL,
  claimed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cq_user_chest (user_id, chest_id),
  CONSTRAINT fk_cq_uc_user FOREIGN KEY (user_id) REFERENCES ct_utenti(id_utente) ON DELETE CASCADE,
  CONSTRAINT fk_cq_uc_chest FOREIGN KEY (chest_id) REFERENCES cq_chest_catalog(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cq_badge_catalog (
  id INT NOT NULL AUTO_INCREMENT,
  slug VARCHAR(80) NOT NULL,
  name VARCHAR(120) NOT NULL,
  description VARCHAR(255) NOT NULL,
  icon VARCHAR(50) NOT NULL DEFAULT 'fa-award',
  rule_code VARCHAR(80) NOT NULL,
  rule_value INT NOT NULL DEFAULT 1,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cq_badge_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cq_user_badges (
  id BIGINT NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  badge_id INT NOT NULL,
  earned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cq_user_badge (user_id, badge_id),
  CONSTRAINT fk_cq_ub_user FOREIGN KEY (user_id) REFERENCES ct_utenti(id_utente) ON DELETE CASCADE,
  CONSTRAINT fk_cq_ub_badge FOREIGN KEY (badge_id) REFERENCES cq_badge_catalog(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cq_daily_sparks (
  id INT NOT NULL AUTO_INCREMENT,
  slug VARCHAR(100) NOT NULL,
  title VARCHAR(160) NOT NULL,
  body VARCHAR(600) NOT NULL,
  xp_reward INT NOT NULL DEFAULT 5,
  lumen_reward INT NOT NULL DEFAULT 1,
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cq_spark_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cq_spark_completions (
  id BIGINT NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  spark_id INT NOT NULL,
  activity_date DATE NOT NULL,
  xp_awarded INT NOT NULL DEFAULT 0,
  lumens_awarded INT NOT NULL DEFAULT 0,
  completed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cq_spark_day (user_id, activity_date),
  KEY idx_cq_spark_user_week (user_id, activity_date),
  CONSTRAINT fk_cq_sc_user FOREIGN KEY (user_id) REFERENCES ct_utenti(id_utente) ON DELETE CASCADE,
  CONSTRAINT fk_cq_sc_spark FOREIGN KEY (spark_id) REFERENCES cq_daily_sparks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cq_intercessions (
  id BIGINT NOT NULL AUTO_INCREMENT,
  class_id INT NOT NULL,
  sender_user_id INT NOT NULL,
  recipient_user_id INT NOT NULL,
  week_key CHAR(8) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'available',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cq_intercession_sender_week (sender_user_id, week_key),
  KEY idx_cq_intercession_recipient (recipient_user_id, status, expires_at),
  CONSTRAINT fk_cq_int_class FOREIGN KEY (class_id) REFERENCES ct_classi(id_classe) ON DELETE CASCADE,
  CONSTRAINT fk_cq_int_sender FOREIGN KEY (sender_user_id) REFERENCES ct_utenti(id_utente) ON DELETE CASCADE,
  CONSTRAINT fk_cq_int_recipient FOREIGN KEY (recipient_user_id) REFERENCES ct_utenti(id_utente) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cq_streak_recoveries (
  id BIGINT NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  recovery_date DATE NOT NULL,
  recovery_type VARCHAR(30) NOT NULL,
  source_id BIGINT NULL,
  lumen_cost INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cq_streak_recovery_day (user_id, recovery_date),
  KEY idx_cq_streak_recovery_type_date (user_id, recovery_type, created_at),
  CONSTRAINT fk_cq_sr_user FOREIGN KEY (user_id) REFERENCES ct_utenti(id_utente) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cq_streak_pauses (
  id BIGINT NOT NULL AUTO_INCREMENT,
  scope_type VARCHAR(20) NOT NULL,
  scope_id INT NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  reason VARCHAR(255) NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_cq_pause_scope_date (scope_type, scope_id, start_date, end_date),
  CONSTRAINT fk_cq_pause_creator FOREIGN KEY (created_by) REFERENCES ct_utenti(id_utente) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
