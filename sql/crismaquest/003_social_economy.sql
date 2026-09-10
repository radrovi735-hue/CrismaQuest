-- CrismaQuest social economy: Correio da Jornada, presentes, trocas e extrato de Lúmens.

CREATE TABLE IF NOT EXISTS cq_lumen_ledger (
  id BIGINT NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  delta INT NOT NULL,
  balance_after INT NOT NULL,
  reason_type VARCHAR(50) NOT NULL,
  reason_ref VARCHAR(120) NULL,
  description VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_cq_lumen_user_date (user_id, created_at),
  CONSTRAINT fk_cq_lumen_user FOREIGN KEY (user_id) REFERENCES ct_utenti(id_utente) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cq_gift_catalog (
  id INT NOT NULL AUTO_INCREMENT,
  slug VARCHAR(80) NOT NULL,
  name VARCHAR(120) NOT NULL,
  category VARCHAR(40) NOT NULL,
  cost_lumens INT NOT NULL,
  icon VARCHAR(40) NOT NULL DEFAULT 'fa-gift',
  cosmetic_slot VARCHAR(40) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cq_gift_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cq_peer_notes (
  id BIGINT NOT NULL AUTO_INCREMENT,
  class_id INT NOT NULL,
  sender_user_id INT NOT NULL,
  recipient_user_id INT NOT NULL,
  message_key VARCHAR(50) NOT NULL,
  custom_text VARCHAR(120) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  read_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_cq_note_recipient (recipient_user_id, created_at),
  KEY idx_cq_note_sender (sender_user_id, created_at),
  CONSTRAINT fk_cq_note_class FOREIGN KEY (class_id) REFERENCES ct_classi(id_classe) ON DELETE CASCADE,
  CONSTRAINT fk_cq_note_sender FOREIGN KEY (sender_user_id) REFERENCES ct_utenti(id_utente) ON DELETE CASCADE,
  CONSTRAINT fk_cq_note_recipient FOREIGN KEY (recipient_user_id) REFERENCES ct_utenti(id_utente) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cq_gifts (
  id BIGINT NOT NULL AUTO_INCREMENT,
  class_id INT NOT NULL,
  sender_user_id INT NOT NULL,
  recipient_user_id INT NOT NULL,
  gift_catalog_id INT NULL,
  card_edition_id INT NULL,
  cost_lumens INT NOT NULL DEFAULT 0,
  note VARCHAR(120) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  opened_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_cq_gift_recipient (recipient_user_id, created_at),
  KEY idx_cq_gift_sender (sender_user_id, created_at),
  CONSTRAINT fk_cq_gift_class FOREIGN KEY (class_id) REFERENCES ct_classi(id_classe) ON DELETE CASCADE,
  CONSTRAINT fk_cq_gift_sender FOREIGN KEY (sender_user_id) REFERENCES ct_utenti(id_utente) ON DELETE CASCADE,
  CONSTRAINT fk_cq_gift_recipient FOREIGN KEY (recipient_user_id) REFERENCES ct_utenti(id_utente) ON DELETE CASCADE,
  CONSTRAINT fk_cq_gift_catalog FOREIGN KEY (gift_catalog_id) REFERENCES cq_gift_catalog(id) ON DELETE SET NULL,
  CONSTRAINT fk_cq_gift_card FOREIGN KEY (card_edition_id) REFERENCES cq_card_editions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cq_user_cosmetics (
  id BIGINT NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  gift_catalog_id INT NOT NULL,
  cosmetic_slot VARCHAR(40) NOT NULL,
  equipped TINYINT(1) NOT NULL DEFAULT 0,
  obtained_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cq_user_cosmetic (user_id, gift_catalog_id),
  KEY idx_cq_user_cosmetic_slot (user_id, cosmetic_slot, equipped),
  CONSTRAINT fk_cq_cosmetic_user FOREIGN KEY (user_id) REFERENCES ct_utenti(id_utente) ON DELETE CASCADE,
  CONSTRAINT fk_cq_cosmetic_gift FOREIGN KEY (gift_catalog_id) REFERENCES cq_gift_catalog(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cq_trade_offers (
  id BIGINT NOT NULL AUTO_INCREMENT,
  class_id INT NOT NULL,
  offerer_user_id INT NOT NULL,
  recipient_user_id INT NOT NULL,
  offered_card_edition_id INT NOT NULL,
  requested_card_edition_id INT NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  responded_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_cq_trade_recipient_status (recipient_user_id, status, created_at),
  KEY idx_cq_trade_offerer_status (offerer_user_id, status, created_at),
  CONSTRAINT fk_cq_trade_class FOREIGN KEY (class_id) REFERENCES ct_classi(id_classe) ON DELETE CASCADE,
  CONSTRAINT fk_cq_trade_offerer FOREIGN KEY (offerer_user_id) REFERENCES ct_utenti(id_utente) ON DELETE CASCADE,
  CONSTRAINT fk_cq_trade_recipient FOREIGN KEY (recipient_user_id) REFERENCES ct_utenti(id_utente) ON DELETE CASCADE,
  CONSTRAINT fk_cq_trade_offered FOREIGN KEY (offered_card_edition_id) REFERENCES cq_card_editions(id) ON DELETE CASCADE,
  CONSTRAINT fk_cq_trade_requested FOREIGN KEY (requested_card_edition_id) REFERENCES cq_card_editions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO cq_gift_catalog (slug, name, category, cost_lumens, icon, cosmetic_slot, active, sort_order) VALUES
('cartao-luz','Cartão de Luz','note',20,'fa-envelope-open-text',NULL,1,10),
('selo-paz','Selo Paz','sticker',30,'fa-dove',NULL,1,20),
('selo-chama','Selo Chama','sticker',30,'fa-fire',NULL,1,30),
('selo-palavra','Selo Palavra','sticker',30,'fa-book-bible',NULL,1,40),
('selo-comunidade','Selo Comunidade','sticker',30,'fa-people-group',NULL,1,50),
('envelope-dourado','Envelope Dourado','envelope',40,'fa-envelope',NULL,1,60),
('moldura-vinho','Moldura Vinho','cosmetic',100,'fa-square', 'profile_frame',1,70),
('moldura-album','Moldura do Álbum','cosmetic',120,'fa-images','album_frame',1,80),
('fundo-aurora','Fundo Aurora','cosmetic',150,'fa-sun','profile_background',1,90),
('chama-dourada','Chama Dourada','cosmetic',180,'fa-fire-flame-curved','flame_style',1,100),
('tema-caminho','Tema Caminho','cosmetic',200,'fa-route','journey_theme',1,110)
ON DUPLICATE KEY UPDATE name=VALUES(name), category=VALUES(category), cost_lumens=VALUES(cost_lumens), icon=VALUES(icon), cosmetic_slot=VALUES(cosmetic_slot), active=VALUES(active), sort_order=VALUES(sort_order);
