-- ============================================
-- 账号收支 数据库表结构（MySQL 5.7+）
-- ============================================

-- 管理员表（后台管理，与普通用户独立）
CREATE TABLE IF NOT EXISTS admins (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(50) NOT NULL COMMENT '管理员用户名',
  password_hash VARCHAR(255) NOT NULL COMMENT '密码哈希',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (id),
  UNIQUE KEY uk_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 用户表
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(50) NOT NULL COMMENT '用户名',
  password_hash VARCHAR(255) NOT NULL COMMENT '密码哈希',
  reg_ip VARCHAR(45) NOT NULL DEFAULT '' COMMENT '注册IP',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '注册时间',
  PRIMARY KEY (id),
  UNIQUE KEY uk_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 注册IP限制表（每个IP每天最多注册 DAILY_REG_LIMIT 个账号）
CREATE TABLE IF NOT EXISTS reg_log (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip VARCHAR(45) NOT NULL COMMENT '注册IP',
  reg_date DATE NOT NULL COMMENT '注册日期',
  cnt INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '当日已注册数量',
  PRIMARY KEY (id),
  UNIQUE KEY uk_ip_date (ip, reg_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 收支账号分类（用户自定义）
CREATE TABLE IF NOT EXISTS categories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL COMMENT '所属用户',
  name VARCHAR(50) NOT NULL COMMENT '分类名称',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (id),
  UNIQUE KEY uk_user_name (user_id, name),
  KEY idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 收支账号表（用户自定义的账号，如微信钱包、支付宝、银行卡）
CREATE TABLE IF NOT EXISTS accounts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL COMMENT '所属用户',
  name VARCHAR(50) NOT NULL COMMENT '账号名称',
  remark VARCHAR(100) NOT NULL DEFAULT '' COMMENT '备注（v2.0 起废弃，仅保留字段）',
  category_id INT UNSIGNED NULL DEFAULT NULL COMMENT '分类ID（categories.id，NULL=未分类）',
  tags VARCHAR(100) NOT NULL DEFAULT '' COMMENT '标签集合（CSV，可选值见 account_tags()）',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (id),
  KEY idx_user (user_id),
  KEY idx_category (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 收支流水表
CREATE TABLE IF NOT EXISTS transactions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL COMMENT '所属用户',
  account_id INT UNSIGNED NOT NULL COMMENT '所属账号',
  type TINYINT UNSIGNED NOT NULL COMMENT '1=收入 2=支出',
  amount DECIMAL(12,2) NOT NULL COMMENT '金额',
  note VARCHAR(100) NOT NULL DEFAULT '' COMMENT '备注',
  occurred_at DATE NOT NULL COMMENT '发生日期',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '记录时间',
  PRIMARY KEY (id),
  KEY idx_user_date (user_id, occurred_at),
  KEY idx_account (account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
