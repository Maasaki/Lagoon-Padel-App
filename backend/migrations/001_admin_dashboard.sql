-- À exécuter une fois sur une base déjà créée avant l’ajout du tableau de bord admin.
-- Les nouvelles installations peuvent utiliser uniquement backend/schema.sql.

ALTER TABLE users
    ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER password;

CREATE TABLE IF NOT EXISTS terrain_day_blocks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    terrain_id INT UNSIGNED NOT NULL,
    block_date DATE NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_terrain_day_block (terrain_id, block_date),
    CONSTRAINT fk_tdb_terrain FOREIGN KEY (terrain_id) REFERENCES terrains (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS slot_blocks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    terrain_id INT UNSIGNED NOT NULL,
    block_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_slot_block (terrain_id, block_date, start_time),
    CONSTRAINT fk_sb_terrain FOREIGN KEY (terrain_id) REFERENCES terrains (id) ON DELETE CASCADE
) ENGINE=InnoDB;
