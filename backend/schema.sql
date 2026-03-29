-- Lagoon Padel — MySQL / MariaDB
CREATE DATABASE IF NOT EXISTS lagoon_padel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE lagoon_padel;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS terrains (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reservations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    terrain_id INT UNSIGNED NOT NULL,
    `date` DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    price INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_res_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_res_terrain FOREIGN KEY (terrain_id) REFERENCES terrains (id) ON DELETE CASCADE,
    UNIQUE KEY uq_terrain_slot (`date`, terrain_id, start_time)
) ENGINE=InnoDB;

INSERT INTO terrains (id, name) VALUES
    (1, 'Terrain 1'),
    (2, 'Terrain 2'),
    (3, 'Terrain 3')
ON DUPLICATE KEY UPDATE name = VALUES(name);
