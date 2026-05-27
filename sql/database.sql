CREATE DATABASE IF NOT EXISTS reserva_online DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE reserva_online;

CREATE TABLE admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE customers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  phone VARCHAR(40) NOT NULL,
  password_hash VARCHAR(255) NULL,
  lgpd_marketing_consent TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE restaurants (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(180) NOT NULL,
  legal_name VARCHAR(180) NULL,
  document_number VARCHAR(40) NULL,
  email VARCHAR(160) NULL,
  phone VARCHAR(40) NULL,
  whatsapp VARCHAR(40) NOT NULL,
  logo_url VARCHAR(500) NULL,
  logo_mime VARCHAR(80) NULL,
  logo_data MEDIUMBLOB NULL,
  smtp_enabled TINYINT(1) NOT NULL DEFAULT 0,
  smtp_host VARCHAR(180) NULL,
  smtp_port INT NULL DEFAULT 587,
  smtp_username VARCHAR(180) NULL,
  smtp_password VARCHAR(255) NULL,
  smtp_encryption ENUM('tls','ssl','none') NOT NULL DEFAULT 'tls',
  smtp_from_email VARCHAR(180) NULL,
  smtp_from_name VARCHAR(180) NULL,
  smtp_admin_email VARCHAR(180) NULL,
  address TEXT NULL,
  reservation_message TEXT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE occasions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  asks_birthday TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE questionnaire_questions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  label VARCHAR(220) NOT NULL,
  field_type ENUM('text','textarea','select','checkbox') NOT NULL DEFAULT 'text',
  options_text TEXT NULL,
  is_required TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE environments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NOT NULL,
  name VARCHAR(120) NOT NULL,
  description TEXT NULL,
  width INT NOT NULL DEFAULT 960,
  height INT NOT NULL DEFAULT 520,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ,
  CONSTRAINT fk_environment_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE tables_map (
  id INT AUTO_INCREMENT PRIMARY KEY,
  environment_id INT NOT NULL,
  label VARCHAR(40) NOT NULL,
  shape ENUM('square','round') NOT NULL DEFAULT 'square',
  seats INT NOT NULL DEFAULT 2,
  position_x INT NOT NULL DEFAULT 40,
  position_y INT NOT NULL DEFAULT 40,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_table_environment FOREIGN KEY (environment_id) REFERENCES environments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE restaurant_hours (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NOT NULL,
  weekday TINYINT NOT NULL,
  period ENUM('lunch','dinner') NOT NULL,
  opens_at TIME NULL,
  closes_at TIME NULL,
  is_closed TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_restaurant_hours (restaurant_id, weekday, period),
  CONSTRAINT fk_hours_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE reservations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NOT NULL,
  customer_id INT NULL,
  occasion_id INT NULL,
  environment_id INT NULL,
  table_id INT NULL,
  customer_name VARCHAR(160) NOT NULL,
  customer_email VARCHAR(160) NOT NULL,
  customer_phone VARCHAR(40) NOT NULL,
  reservation_date DATE NOT NULL,
  reservation_time TIME NOT NULL,
  party_size INT NOT NULL,
  birthday_day TINYINT NULL,
  birthday_month TINYINT NULL,
  dietary_restrictions TEXT NULL,
  notes TEXT NULL,
  lgpd_terms_consent TINYINT(1) NOT NULL DEFAULT 0,
  lgpd_share_consent TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('pending','approved','confirmed','seated','completed','cancelled','no_show') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_reservation_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE,
  CONSTRAINT fk_reservation_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
  CONSTRAINT fk_reservation_occasion FOREIGN KEY (occasion_id) REFERENCES occasions(id) ON DELETE SET NULL,
  CONSTRAINT fk_reservation_environment FOREIGN KEY (environment_id) REFERENCES environments(id) ON DELETE SET NULL,
  CONSTRAINT fk_reservation_table FOREIGN KEY (table_id) REFERENCES tables_map(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE reservation_answers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  reservation_id INT NOT NULL,
  question_id INT NOT NULL,
  answer TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_answer_reservation FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
  CONSTRAINT fk_answer_question FOREIGN KEY (question_id) REFERENCES questionnaire_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO admins (name, email, password_hash) VALUES
('Administrador', 'admin@reserva.local', '$2y$10$sLnDifVi7C85VdlAZSAcBeRs3pLEciKHDMzrt2lsN4uQHJt.UZ2Fy'),
('Administrador', 'admin@admin.com', '$2y$10$sLnDifVi7C85VdlAZSAcBeRs3pLEciKHDMzrt2lsN4uQHJt.UZ2Fy');

INSERT INTO restaurants (name, legal_name, email, phone, whatsapp, logo_url, address, reservation_message) VALUES
('Restaurante Demo', 'Restaurante Demo Ltda', 'reservas@restaurantedemo.com', '(11) 99999-9999', '5511999999999', '', 'Rua das Reservas, 100', 'Nova reserva recebida pelo i-Reserva.');

INSERT INTO occasions (name, asks_birthday) VALUES
('Aniversário', 1),
('Reunião de negócios', 0),
('Celebração', 0),
('Jantar romantico', 0);

INSERT INTO questionnaire_questions (label, field_type, options_text, is_required, sort_order) VALUES
('Alguma restrição alimentar específica?', 'textarea', NULL, 0, 10),
('Prefere ambiente interno ou externo?', 'select', 'Interno\nExterno\nSem preferência', 0, 20);

INSERT INTO environments (restaurant_id, name, description, width, height) VALUES
(1, 'Salao principal', 'Ambiente interno principal', 960, 520),
(1, 'Varanda', 'Área externa coberta', 760, 420);

INSERT INTO tables_map (environment_id, label, shape, seats, position_x, position_y) VALUES
(1, 'M01', 'square', 2, 80, 80),
(1, 'M02', 'square', 4, 210, 90),
(1, 'M03', 'round', 6, 360, 140),
(2, 'V01', 'square', 4, 90, 90);

INSERT INTO restaurant_hours (restaurant_id, weekday, period, opens_at, closes_at, is_closed) VALUES
(1, 0, 'lunch', '12:00:00', '15:00:00', 0),
(1, 0, 'dinner', '19:00:00', '23:00:00', 0),
(1, 1, 'lunch', '12:00:00', '15:00:00', 0),
(1, 1, 'dinner', '19:00:00', '23:00:00', 0),
(1, 2, 'lunch', '12:00:00', '15:00:00', 0),
(1, 2, 'dinner', '19:00:00', '23:00:00', 0),
(1, 3, 'lunch', '12:00:00', '15:00:00', 0),
(1, 3, 'dinner', '19:00:00', '23:00:00', 0),
(1, 4, 'lunch', '12:00:00', '15:00:00', 0),
(1, 4, 'dinner', '19:00:00', '23:00:00', 0),
(1, 5, 'lunch', '12:00:00', '15:00:00', 0),
(1, 5, 'dinner', '19:00:00', '23:00:00', 0),
(1, 6, 'lunch', '12:00:00', '15:00:00', 0),
(1, 6, 'dinner', '19:00:00', '23:00:00', 0);
