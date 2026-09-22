-- =========================================================
-- VISZ Closet — Plataforma de Aluguel de Roupas de Marca
-- Schema MySQL (InnoDB / utf8mb4)
-- =========================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  phone VARCHAR(30) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('cliente','admin') NOT NULL DEFAULT 'cliente',
  birth_date DATE NULL,
  address VARCHAR(255) NULL,
  city VARCHAR(100) NULL,
  state VARCHAR(2) NULL,
  zip VARCHAR(15) NULL,
  status ENUM('ativo','bloqueado') NOT NULL DEFAULT 'ativo',
  reset_token VARCHAR(64) NULL,
  reset_token_expires DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE measurements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL UNIQUE,
  height INT NULL,
  weight INT NULL,
  bust INT NULL,
  waist INT NULL,
  hip INT NULL,
  shoulders INT NULL,
  leg_length INT NULL,
  usual_size VARCHAR(10) NULL,
  shoe_size INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_meas_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE brands (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(120) NOT NULL UNIQUE,
  logo VARCHAR(255) NULL,
  description TEXT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(120) NOT NULL UNIQUE,
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  slug VARCHAR(180) NOT NULL UNIQUE,
  brand_id INT NOT NULL,
  category_id INT NOT NULL,
  description TEXT NULL,
  material VARCHAR(150) NULL,
  color VARCHAR(50) NULL,
  style ENUM('casual','social','festa','fashion','esportivo','elegante','streetwear') DEFAULT 'casual',
  rental_price DECIMAL(10,2) NOT NULL,
  deposit DECIMAL(10,2) NOT NULL DEFAULT 0,
  status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
  featured TINYINT(1) NOT NULL DEFAULT 0,
  deleted_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_prod_brand FOREIGN KEY (brand_id) REFERENCES brands(id),
  CONSTRAINT fk_prod_cat FOREIGN KEY (category_id) REFERENCES categories(id),
  INDEX idx_products_search (name, color, style),
  INDEX idx_products_status (status, deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE product_images (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  image_url VARCHAR(500) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_img_prod FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE product_sizes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  size VARCHAR(10) NOT NULL,
  bust_min INT NULL, bust_max INT NULL,
  waist_min INT NULL, waist_max INT NULL,
  hip_min INT NULL, hip_max INT NULL,
  shoulder_min INT NULL, shoulder_max INT NULL,
  stock INT NOT NULL DEFAULT 1,
  CONSTRAINT fk_size_prod FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  INDEX idx_sizes_product (product_id, size)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE favorites (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  product_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_fav (user_id, product_id),
  CONSTRAINT fk_fav_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_fav_prod FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_code VARCHAR(20) NOT NULL UNIQUE,
  user_id INT NOT NULL,
  delivery_recipient VARCHAR(150) NULL,
  delivery_phone VARCHAR(30) NULL,
  delivery_zip VARCHAR(15) NULL,
  delivery_address VARCHAR(255) NULL,
  delivery_number VARCHAR(20) NULL,
  delivery_complement VARCHAR(100) NULL,
  delivery_district VARCHAR(100) NULL,
  delivery_city VARCHAR(100) NULL,
  delivery_state VARCHAR(2) NULL,
  delivery_notes TEXT NULL,
  delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  subtotal DECIMAL(10,2) NOT NULL,
  deposit DECIMAL(10,2) NOT NULL DEFAULT 0,
  total DECIMAL(10,2) NOT NULL,
  status ENUM(
    'aguardando_confirmacao','confirmado','preparando','disponivel_retirada',
    'em_aluguel','devolucao_pendente','finalizado','cancelado'
  ) NOT NULL DEFAULT 'aguardando_confirmacao',
  notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_order_user FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_orders_status (status),
  INDEX idx_orders_code (order_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  product_id INT NOT NULL,
  size VARCHAR(10) NOT NULL,
  rental_price DECIMAL(10,2) NOT NULL,
  CONSTRAINT fk_item_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_item_prod FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE availability (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  order_id INT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  status ENUM('reservado','confirmado','em_aluguel','devolvido','manutencao','indisponivel') NOT NULL DEFAULT 'reservado',
  CONSTRAINT fk_avail_prod FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_avail_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
  INDEX idx_avail_product_dates (product_id, start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE settings (
  id INT PRIMARY KEY DEFAULT 1,
  size_tolerance_cm INT NOT NULL DEFAULT 3,
  instagram_url VARCHAR(255) NOT NULL DEFAULT 'https://instagram.com/viszcloset',
  instagram_handle VARCHAR(100) NOT NULL DEFAULT '@viszcloset',
  whatsapp_number VARCHAR(30) NOT NULL DEFAULT '5581999999999',
  site_name VARCHAR(100) NOT NULL DEFAULT 'VISZ Closet',
  delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
  delivery_free_above DECIMAL(10,2) NOT NULL DEFAULT 0,
  delivery_info VARCHAR(255) NOT NULL DEFAULT 'Entregamos em toda a região metropolitana do Recife.'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE admin_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  admin_id INT NOT NULL,
  action VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_log_admin FOREIGN KEY (admin_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- =========================================================
-- Dados iniciais
-- =========================================================

INSERT INTO settings (id, size_tolerance_cm, instagram_url, instagram_handle, whatsapp_number, site_name, delivery_fee, delivery_free_above, delivery_info)
VALUES (1, 3, 'https://instagram.com/viszcloset', '@viszcloset', '5581999999999', 'VISZ Closet', 20.00, 300.00, 'Entregamos em toda a região metropolitana do Recife.');

-- Admin de demonstração (rode sql/gerar_hashes.php com PHP para definir a senha real)
INSERT INTO users (id, name, last_name, email, phone, password_hash, role, status, created_at, updated_at)
VALUES (1, 'Administrador', 'VISZ', 'admin@viszcloset.com.br', '81999990000', '$2y$10$TEMPORARIO.RODE.O.SCRIPT.gerar_hashes.php', 'admin', 'ativo', NOW(), NOW());
