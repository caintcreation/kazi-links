-- ===================================================
-- KaziLink Database Schema & Seed Data
-- ===================================================
--
-- TABLE MAP
-- users                User accounts and login details
-- admins               Separate administration accounts
-- wallets              One balance record per user
-- transactions         Complete wallet credit/debit ledger
-- referrals            Inviter/referred-user relationships
-- withdrawals          M-Pesa withdrawal requests
-- tasks                Project categories
-- levels               Levels belonging to each project
-- questions            Questions used by each level
-- user_task_progress   Per-user project progress summary
-- user_level_progress  Per-user completed level records
-- answers              Submitted level attempts and results
-- settings             Configurable business rules
-- password_resets      Password reset tokens
-- admin_audit_logs     Administrative activity history
--
-- The users table is the normal public login portal.
-- The admins table is the separate management portal.

-- Note: On InfinityFree / shared hosts, select your database in phpMyAdmin before importing.
-- CREATE DATABASE IF NOT EXISTS `kazilink` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE `kazilink`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `admin_audit_logs`;
DROP TABLE IF EXISTS `password_resets`;
DROP TABLE IF EXISTS `answers`;
DROP TABLE IF EXISTS `user_level_progress`;
DROP TABLE IF EXISTS `user_task_progress`;
DROP TABLE IF EXISTS `questions`;
DROP TABLE IF EXISTS `payment_intents`;
DROP TABLE IF EXISTS `levels`;
DROP TABLE IF EXISTS `tasks`;
DROP TABLE IF EXISTS `withdrawals`;
DROP TABLE IF EXISTS `referrals`;
DROP TABLE IF EXISTS `transactions`;
DROP TABLE IF EXISTS `wallets`;
DROP TABLE IF EXISTS `settings`;
DROP TABLE IF EXISTS `admins`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. USERS TABLE
CREATE TABLE `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NULL,
  `email` VARCHAR(191) NOT NULL UNIQUE,
  `phone` VARCHAR(30) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `referral_code` VARCHAR(20) NOT NULL UNIQUE,
  `email_verified` TINYINT(1) NOT NULL DEFAULT 1,
  `verification_token` VARCHAR(64) NULL,
  `verification_code` CHAR(6) NULL,
  `verification_expires_at` DATETIME NULL,
  `status` ENUM('active', 'suspended', 'pending') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_users_email` (`email`),
  INDEX `idx_users_phone` (`phone`),
  INDEX `idx_users_referral_code` (`referral_code`),
  INDEX `idx_users_verification_token` (`verification_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. ADMINS TABLE
CREATE TABLE `admins` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(60) NOT NULL UNIQUE,
  `email` VARCHAR(191) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('superadmin', 'admin', 'moderator') NOT NULL DEFAULT 'admin',
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `last_login` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. WALLETS TABLE (Ledger base)
CREATE TABLE `wallets` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL UNIQUE,
  `balance` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `pending_balance` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `total_earned` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `total_withdrawn` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. TRANSACTIONS TABLE
CREATE TABLE `transactions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `reference` VARCHAR(64) NOT NULL UNIQUE,
  `type` ENUM('welcome_bonus', 'task_reward', 'referral_reward', 'level_unlock', 'withdrawal', 'adjustment') NOT NULL,
  `amount` DECIMAL(12, 2) NOT NULL,
  `direction` ENUM('credit', 'debit') NOT NULL,
  `status` ENUM('completed', 'pending', 'cancelled', 'rejected') NOT NULL DEFAULT 'completed',
  `description` VARCHAR(255) NOT NULL,
  `metadata` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_tx_user_created` (`user_id`, `created_at`),
  INDEX `idx_tx_type` (`type`),
  INDEX `idx_tx_ref` (`reference`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. REFERRALS TABLE
CREATE TABLE `referrals` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `referrer_id` INT UNSIGNED NOT NULL,
  `referred_id` INT UNSIGNED NOT NULL UNIQUE,
  `reward_amount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `status` ENUM('pending', 'eligible', 'rewarded') NOT NULL DEFAULT 'pending',
  `rewarded_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`referrer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`referred_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_ref_referrer` (`referrer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. WITHDRAWALS TABLE
CREATE TABLE `withdrawals` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `reference` VARCHAR(64) NOT NULL UNIQUE,
  `amount` DECIMAL(12, 2) NOT NULL,
  `fee` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `net_amount` DECIMAL(12, 2) NOT NULL,
  `phone` VARCHAR(30) NOT NULL,
  `status` ENUM('pending', 'processing', 'paid', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending',
  `admin_note` TEXT NULL,
  `processed_by` INT UNSIGNED NULL,
  `processed_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`processed_by`) REFERENCES `admins`(`id`) ON DELETE SET NULL,
  INDEX `idx_withdrawals_user` (`user_id`),
  INDEX `idx_withdrawals_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. TASKS TABLE
CREATE TABLE `tasks` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT NOT NULL,
  `icon` VARCHAR(50) NOT NULL DEFAULT 'bi-clipboard-check',
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `order_index` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. LEVELS TABLE
CREATE TABLE `levels` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `task_id` INT UNSIGNED NOT NULL,
  `level_number` INT NOT NULL,
  `title` VARCHAR(100) NOT NULL,
  `price` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `reward` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `passing_score` INT NOT NULL DEFAULT 70,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`task_id`) REFERENCES `tasks`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `uk_task_level` (`task_id`, `level_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. PAYHERO LEVEL PAYMENT INTENTS
CREATE TABLE `payment_intents` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `level_id` INT UNSIGNED NOT NULL,
  `reference` VARCHAR(64) NOT NULL UNIQUE,
  `provider_reference` VARCHAR(128) NULL,
  `amount` DECIMAL(12, 2) NOT NULL,
  `phone` VARCHAR(30) NOT NULL,
  `status` ENUM('pending', 'paid', 'failed') NOT NULL DEFAULT 'pending',
  `callback_payload` LONGTEXT NULL,
  `paid_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`level_id`) REFERENCES `levels`(`id`) ON DELETE CASCADE,
  INDEX `idx_payment_user_level` (`user_id`, `level_id`),
  INDEX `idx_payment_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. QUESTIONS TABLE
CREATE TABLE `questions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `level_id` INT UNSIGNED NOT NULL,
  `question_text` TEXT NOT NULL,
  `option_a` VARCHAR(255) NOT NULL,
  `option_b` VARCHAR(255) NOT NULL,
  `option_c` VARCHAR(255) NOT NULL,
  `option_d` VARCHAR(255) NOT NULL,
  `correct_option` ENUM('A', 'B', 'C', 'D') NOT NULL,
  `explanation` TEXT NULL,
  `points` INT NOT NULL DEFAULT 10,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`level_id`) REFERENCES `levels`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. USER TASK PROGRESS TABLE
CREATE TABLE `user_task_progress` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `task_id` INT UNSIGNED NOT NULL,
  `completed_levels` INT NOT NULL DEFAULT 0,
  `last_active_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`task_id`) REFERENCES `tasks`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `uk_user_task` (`user_id`, `task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. USER LEVEL PROGRESS TABLE
CREATE TABLE `user_level_progress` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `level_id` INT UNSIGNED NOT NULL,
  `status` ENUM('locked', 'unlocked', 'completed') NOT NULL DEFAULT 'locked',
  `unlocked_at` DATETIME NULL,
  `completed_at` DATETIME NULL,
  `score` INT NOT NULL DEFAULT 0,
  `attempts` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`level_id`) REFERENCES `levels`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `uk_user_level` (`user_id`, `level_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. ANSWERS / SUBMISSIONS TABLE
CREATE TABLE `answers` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `level_id` INT UNSIGNED NOT NULL,
  `score` INT NOT NULL,
  `total_questions` INT NOT NULL,
  `passed` TINYINT(1) NOT NULL DEFAULT 0,
  `details` JSON NULL,
  `submitted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`level_id`) REFERENCES `levels`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. SETTINGS TABLE
CREATE TABLE `settings` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` TEXT NULL,
  `setting_group` VARCHAR(50) NOT NULL DEFAULT 'general',
  `description` VARCHAR(255) NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. ADMIN AUDIT LOGS TABLE
CREATE TABLE `admin_audit_logs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `admin_id` INT UNSIGNED NULL,
  `action` VARCHAR(100) NOT NULL,
  `target_type` VARCHAR(50) NULL,
  `target_id` INT UNSIGNED NULL,
  `details` TEXT NULL,
  `ip_address` VARCHAR(45) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`admin_id`) REFERENCES `admins`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. PASSWORD RESETS TABLE
CREATE TABLE `password_resets` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(191) NOT NULL,
  `token` VARCHAR(128) NOT NULL,
  `reset_code` CHAR(6) NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_pwd_token` (`token`),
  INDEX `idx_pwd_code` (`reset_code`),
  INDEX `idx_pwd_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ===================================================
-- SEED DATA
-- ===================================================

-- Administration account
INSERT INTO `admins` (`id`, `username`, `email`, `password_hash`, `role`, `status`) VALUES
(1, 'admin', 'kazilinks5@gmail.com', '$2y$10$pPMro1bGzfr9VPaKJxGjiukB6Prmdb34sqlexI0K.8UgkaT3jNc1G', 'superadmin', 'active');

-- Initial public user account
-- Login email: kazilinks5@gmail.com
-- Login password: 12345678
INSERT INTO `users` (`id`, `email`, `phone`, `password_hash`, `referral_code`, `email_verified`, `status`) VALUES
(1, 'kazilinks5@gmail.com', '0799999999', '$2y$10$pPMro1bGzfr9VPaKJxGjiukB6Prmdb34sqlexI0K.8UgkaT3jNc1G', 'KZADMIN5', 1, 'active');

INSERT INTO `wallets` (`user_id`, `balance`, `pending_balance`, `total_earned`, `total_withdrawn`) VALUES
(1, 0.00, 0.00, 0.00, 0.00);

-- System Settings
-- Business rules:
--   * New users receive KSh 100 without a referral code.
--   * New users receive KSh 300 with a valid referral code.
--   * The inviter receives KSh 100 immediately after the referred account is created.
--   * Withdrawals require KSh 2,500 minimum balance and 8 completed project levels.
INSERT INTO `settings` (`setting_key`, `setting_value`, `setting_group`, `description`) VALUES
('site_name', 'KaziLink Kenya', 'general', 'Platform Name'),
('site_tagline', 'Simple Online Microtasks & Flexible Rewards in Kenya', 'general', 'Hero and Header Tagline'),
('currency', 'KES', 'general', 'Platform Currency Code'),
('currency_symbol', 'KSh', 'general', 'Platform Currency Symbol'),
('support_email', 'kazilinks5@gmail.com', 'general', 'Customer Care Email'),
('support_phone', '0700000000', 'general', 'Customer Support Phone/WhatsApp'),
('welcome_reward', '100.00', 'financial', 'Instant registration welcome bonus without a referral code (KES)'),
('welcome_reward_referred', '300.00', 'financial', 'Welcome bonus for users who join with a valid referral code (KES)'),
('referral_reward', '100.00', 'financial', 'Bonus credited to referrer immediately when a referred user registers (KES)'),
('referral_eligibility_levels', '0', 'financial', 'Legacy setting; referral rewards are credited at registration'),
('minimum_withdrawal', '2500.00', 'financial', 'Minimum withdrawal threshold (KES)'),
('withdrawal_min_tasks', '8', 'financial', 'Minimum completed project levels required before withdrawal'),
('withdrawal_fee_percent', '2.50', 'financial', 'Withdrawal processing fee percentage (%)'),
('withdrawal_fee_fixed', '10.00', 'financial', 'Fixed withdrawal transaction fee (KES)'),
('referrals_enabled', '1', 'system', '1 = Enabled, 0 = Disabled'),
('withdrawals_enabled', '1', 'system', '1 = Enabled, 0 = Disabled'),
('registration_enabled', '1', 'system', '1 = Enabled, 0 = Disabled'),
('maintenance_mode', '0', 'system', '1 = Enabled, 0 = Disabled');

-- 5 Primary Tasks
INSERT INTO `tasks` (`id`, `name`, `slug`, `description`, `icon`, `status`, `order_index`) VALUES
(1, 'Braintest', 'braintest', 'Quick analytical questions, logical puzzles, and pattern recognition tasks designed for fast thinking.', 'bi-lightbulb', 'active', 1),
(2, 'House Review', 'house-review', 'Analyze real estate listings, inspect photo details, and evaluate rental property descriptions.', 'bi-house-check', 'active', 2),
(3, 'Surveys', 'surveys', 'Share insights on Kenyan consumer goods, retail services, and daily shopping trends.', 'bi-card-checklist', 'active', 3),
(4, 'Chat Moderator', 'chat-moderator', 'Review social chat exchanges, flag offensive language, and maintain community safety guidelines.', 'bi-chat-dots', 'active', 4),
(5, 'Online Tutor', 'online-tutor', 'Review secondary school homework snippets, grammar corrections, and general knowledge queries.', 'bi-mortarboard', 'active', 5);

-- 6 Levels for Task 1 (Braintest)
INSERT INTO `levels` (`id`, `task_id`, `level_number`, `title`, `price`, `reward`, `passing_score`, `status`) VALUES
(1, 1, 1, 'Quick Logic Level 1', 0.00, 100.00, 70, 'active'),
(2, 1, 2, 'Pattern Logic Level 2', 30.00, 100.00, 70, 'active'),
(3, 1, 3, 'Reasoning Logic Level 3', 30.00, 100.00, 70, 'active'),
(4, 1, 4, 'Advanced Analytical Level 4', 30.00, 100.00, 70, 'active'),
(5, 1, 5, 'Master Mind Level 5', 30.00, 100.00, 70, 'active'),
(6, 1, 6, 'Grand Cognitive Challenge Level 6', 30.00, 100.00, 70, 'active');

-- 6 Levels for Task 2 (House Review)
INSERT INTO `levels` (`id`, `task_id`, `level_number`, `title`, `price`, `reward`, `passing_score`, `status`) VALUES
(7, 2, 1, 'Studio Apartment Review', 0.00, 100.00, 70, 'active'),
(8, 2, 2, '1-Bedroom Listing Check', 30.00, 100.00, 70, 'active'),
(9, 2, 3, '2-Bedroom Quality Audit', 30.00, 100.00, 70, 'active'),
(10, 2, 4, 'Mansionette Evaluation', 30.00, 100.00, 70, 'active'),
(11, 2, 5, 'Commercial Space Verification', 30.00, 100.00, 70, 'active'),
(12, 2, 6, 'Luxury Penthouse Inspection', 30.00, 100.00, 70, 'active');

-- 6 Levels for Task 3 (Surveys)
INSERT INTO `levels` (`id`, `task_id`, `level_number`, `title`, `price`, `reward`, `passing_score`, `status`) VALUES
(13, 3, 1, 'Consumer FMCG Survey 1', 0.00, 100.00, 70, 'active'),
(14, 3, 2, 'Telecomm & Mobile Money Survey 2', 30.00, 100.00, 70, 'active'),
(15, 3, 3, 'Public Transport & Mobility Survey 3', 30.00, 100.00, 70, 'active'),
(16, 3, 4, 'Banking & Savings Habits Survey 4', 30.00, 100.00, 70, 'active'),
(17, 3, 5, 'Digital Media Consumption Survey 5', 30.00, 100.00, 70, 'active'),
(18, 3, 6, 'National Consumer Trends Survey 6', 30.00, 100.00, 70, 'active');

-- 6 Levels for Task 4 (Chat Moderator)
INSERT INTO `levels` (`id`, `task_id`, `level_number`, `title`, `price`, `reward`, `passing_score`, `status`) VALUES
(19, 4, 1, 'Spam Detection Level 1', 0.00, 100.00, 70, 'active'),
(20, 4, 2, 'Harassment Flagging Level 2', 30.00, 100.00, 70, 'active'),
(21, 4, 3, 'Impersonation Identification Level 3', 30.00, 100.00, 70, 'active'),
(22, 4, 4, 'Policy Violations Screening Level 4', 30.00, 100.00, 70, 'active'),
(23, 4, 5, 'Escalation Resolution Level 5', 30.00, 100.00, 70, 'active'),
(24, 4, 6, 'Lead Moderator Evaluation Level 6', 30.00, 100.00, 70, 'active');

-- 6 Levels for Task 5 (Online Tutor)
INSERT INTO `levels` (`id`, `task_id`, `level_number`, `title`, `price`, `reward`, `passing_score`, `status`) VALUES
(25, 5, 1, 'Basic English & Syntax Level 1', 0.00, 100.00, 70, 'active'),
(26, 5, 2, 'General Science Facts Level 2', 30.00, 100.00, 70, 'active'),
(27, 5, 3, 'Kenya Geography & History Level 3', 30.00, 100.00, 70, 'active'),
(28, 5, 4, 'Everyday Mathematics Level 4', 30.00, 100.00, 70, 'active'),
(29, 5, 5, 'Technology & Computing Level 5', 30.00, 100.00, 70, 'active'),
(30, 5, 6, 'Senior Academic Review Level 6', 30.00, 100.00, 70, 'active');


-- Questions for Level 1 (Braintest Level 1 - Free)
INSERT INTO `questions` (`level_id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_option`, `explanation`, `points`) VALUES
(1, 'What is the next number in the sequence: 2, 4, 8, 16, ...?', '24', '32', '30', '36', 'B', 'Each number is multiplied by 2 (doubled).', 10),
(1, 'Which of the following does NOT belong with the others?', 'Apple', 'Banana', 'Carrot', 'Orange', 'C', 'Carrot is a root vegetable, whereas the others are fruits.', 10),
(1, 'If 5 workers take 5 hours to make 5 widgets, how many hours does it take 1 worker to make 1 widget?', '1 hour', '5 hours', '25 hours', '0.5 hours', 'B', 'Each worker makes one widget in 5 hours.', 10),
(1, 'A train travels at 60 km/h. How far does it travel in 45 minutes?', '45 km', '40 km', '50 km', '30 km', 'A', '45 minutes is 0.75 hours. 60 * 0.75 = 45 km.', 10),
(1, 'Which word is the antonym of "Diligent"?', 'Careful', 'Hardworking', 'Lazy', 'Enthusiastic', 'C', 'Lazy is the opposite of diligent.', 10),
(1, 'Complete the analogy: Book is to Reading as Fork is to ...?', 'Writing', 'Eating', 'Drawing', 'Sleeping', 'B', 'A book is used for reading, and a fork is used for eating.', 10),
(1, 'If all roses are flowers and some flowers fade quickly, which statement is definitely true?', 'All roses fade quickly', 'Some roses fade quickly', 'Roses are flowers', 'No flowers fade quickly', 'C', 'It is directly given that all roses are flowers.', 10),
(1, 'What comes next in the alphabet pattern: A, C, E, G, ...?', 'H', 'I', 'J', 'K', 'B', 'The pattern skips one letter: A, (B), C, (D), E, (F), G, (H), I.', 10),
(1, 'A bat and ball cost KSh 110 in total. The bat costs KSh 100 more than the ball. How much does the ball cost?', 'KSh 10', 'KSh 5', 'KSh 15', 'KSh 20', 'B', 'If ball = 5, bat = 105. Total = 110. (x + x + 100 = 110 => 2x = 10 => x = 5).', 10),
(1, 'Which month of the year has 28 days?', 'February only', 'All 12 months', 'February and March', 'Only leap years', 'B', 'All 12 months have at least 28 days.', 10);

-- Questions for Level 2 (Braintest Level 2)
INSERT INTO `questions` (`level_id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_option`, `explanation`, `points`) VALUES
(2, 'Look at this series: 36, 34, 30, 28, 24, ... What number comes next?', '20', '22', '23', '26', 'B', 'This is an alternating subtraction series: -2, -4, -2, -4, -2: 24 - 2 = 22.', 10),
(2, 'Which shape has the most vertices?', 'Hexagon', 'Octagon', 'Heptagon', 'Pentagon', 'B', 'An octagon has 8 vertices, more than the others.', 10),
(2, 'Mary is 16 years old. She is 4 times older than her brother. How old will Mary be when she is twice as old as her brother?', '20', '24', '28', '32', 'B', 'Her brother is 4. The difference is 12 years. When brother is 12, Mary will be 24 (twice as old).', 10),
(2, 'Rearrange "RAPIS" to form the name of a famous European capital:', 'PRAGUE', 'PARIS', 'ROME', 'BERLIN', 'B', 'RAPIS anagrams to PARIS.', 10),
(2, 'Find the odd one out: 27, 64, 125, 144, 216', '27', '64', '144', '216', 'C', '144 is 12 squared, while 27 (3^3), 64 (4^3), 125 (5^3), and 216 (6^3) are cubes.', 10),
(2, 'If SOUTH is coded as 19-15-21-20-8, what is EAST?', '5-1-19-20', '5-2-18-20', '4-1-19-19', '5-1-20-21', 'A', 'Numbers correspond to their positions in the alphabet: E=5, A=1, S=19, T=20.', 10),
(2, 'How many degrees are in a right angle?', '45', '90', '180', '360', 'B', 'A right angle measures 90 degrees.', 10),
(2, 'A clock shows 3:15. What is the approximate angle between the hands?', '0 degrees', '7.5 degrees', '15 degrees', '30 degrees', 'B', 'At 3:15, minute hand is at 90 deg, hour hand has moved 1/4 of 30 deg = 7.5 deg.', 10),
(2, 'Which word means the same as "Ambiguous"?', 'Clear', 'Vague or unclear', 'Definite', 'Loud', 'B', 'Ambiguous means open to more than one interpretation; unclear.', 10),
(2, 'If you scramble the letters "CIFAIPC", you get the name of a(n):', 'Country', 'Animal', 'Ocean', 'City', 'C', 'PACIFIC is an ocean.', 10);

-- Questions for Level 7 (House Review Level 1 - Free)
INSERT INTO `questions` (`level_id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_option`, `explanation`, `points`) VALUES
(7, 'When evaluating an apartment listing, which factor is most essential for basic security?', 'Granite countertops', 'Reinforced perimeter wall and gate security', 'Swimming pool', 'Jacuzzi', 'B', 'Perimeter walls and guards provide essential safety.', 10),
(7, 'What does the abbreviation "bedsitter" typically describe in Kenyan rental housing?', 'A 3-bedroom maisonette', 'A single room combining living space and bedroom with private bath/kitchenette', 'A student hostel dormitory', 'An empty commercial warehouse', 'B', 'A bedsitter is a studio/single self-contained room unit.', 10),
(7, 'If a landlord lists water as "token/borehole available 24/7", what does it ensure for the tenant?', 'Frequent dry taps', 'Reliable continuous water backup', 'Free electricity', 'Rainwater collection only', 'B', 'Borehole backup indicates uninterrupted water availability.', 10),
(7, 'Which document verifies legal ownership before signing a long-term tenancy agreement?', 'Social media profile', 'Title deed or certified lease agreement', 'Business card', 'WhatsApp chat history', 'B', 'A certified lease or title deed proves legal authority.', 10),
(7, 'What is the standard refundable deposit rule common in Nairobi residential rentals?', 'One month rent deposit plus one month upfront rent', '5 months rent upfront', 'No deposit ever required', 'Vehicle logbook', 'A', 'Standard rental terms require 1 month rent deposit and first month rent.', 10),
(7, 'Which photo in a listing is considered a red flag for misleading advertising?', 'Clear daytime shot of the bathroom', 'Wide angle view of the living room', 'Stock render photos without genuine building shots', 'Detailed photo of kitchen sink', 'C', 'Stock 3D renders without actual premises photos often indicate unverified or fake units.', 10),
(7, 'In property listings, what does "master en-suite" mean?', 'Master bedroom shares bathroom with living room', 'Master bedroom has its own private bathroom attached', 'Master bedroom is on the rooftop', 'No bathroom inside the house', 'B', 'En-suite means the bathroom is directly connected inside the master bedroom.', 10),
(7, 'Why is proximity to matatu stages or bus stops important for tenants?', 'It reduces daily commuting costs and transit time', 'It makes the apartment sound quieter', 'It reduces electricity bills', 'It provides free internet', 'A', 'Proximity to transit directly improves daily mobility and saves fare.', 10),
(7, 'What should an inspector check first when examining a kitchen in a rental unit?', 'Cabinet finishes, water tap pressure, and functional drain', 'Curtain color', 'Refrigerator brand of neighbor', 'Ceiling paint hue', 'A', 'Plumbing drainage and structural storage are primary inspection points.', 10),
(7, 'What is an escrow or formal receipt important for during tenancy payment?', 'Valid legal proof of payment', 'Tax avoidance', 'Discounting grocery prices', 'Building faster wifi', 'A', 'Receipts and bank/M-Pesa records provide indisputable proof of payment.', 10);

-- Questions for Level 13 (Surveys Level 1 - Free)
INSERT INTO `questions` (`level_id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_option`, `explanation`, `points`) VALUES
(13, 'Which mobile payment service is most widely used across Kenya for daily transactions?', 'PayPal', 'M-Pesa', 'Venmo', 'Cash App', 'B', 'Safaricom M-Pesa is Kenya’s primary mobile money platform.', 10),
(13, 'What is the primary factor Kenyan consumers look for when purchasing cooking oil?', 'Brand prestige', 'Price and packaging volume (affordability)', 'Scent', 'Import origin', 'B', 'Affordability and size value are top drivers for everyday household goods.', 10),
(13, 'How do most Kenyans access the internet daily?', 'Home desktop computer', 'Smartphones via mobile data/bundles', 'Cyber cafes', 'Public libraries', 'B', 'Mobile smartphones provide over 90% of daily internet connectivity in Kenya.', 10),
(13, 'Which retail channel handles the highest volume of daily essential groceries in residential areas?', 'Large hypermarkets', 'Local duka / neighbourhood kiosks', 'Online grocery delivery apps', 'International duty free', 'B', 'Local dukas serve the vast majority of neighborhood daily shopping.', 10),
(13, 'What time of day is mobile data consumption typically highest for social entertainment?', '3:00 AM - 5:00 AM', '12:00 PM - 1:00 PM', '7:00 PM - 10:00 PM', '6:00 AM - 7:00 AM', 'C', 'Evening leisure hours see peak social media, streaming, and chatting.', 10),
(13, 'What is the main reason shoppers choose supermarket private label brands over premium brands?', 'Lower cost for comparable basic quality', 'More expensive packaging', 'Celebrity endorsements', 'Slower availability', 'A', 'Supermarket house brands offer budget-friendly savings.', 10),
(13, 'When participating in consumer research, what makes survey responses most valuable?', 'Answering randomly to finish fast', 'Providing honest, thoughtful personal experiences', 'Guessing what the brand wants to hear', 'Entering false demographic details', 'B', 'Authentic consumer opinions allow brands to improve actual products.', 10),
(13, 'Which feature is most valued by Kenyans when choosing a commercial bank account?', 'Low monthly maintenance fees and easy mobile app/M-Pesa integration', 'Expensive physical branch furniture', 'Marble floors in head office', 'Foreign branch locations only', 'A', 'Low fees and smooth mobile money linkages are critical.', 10),
(13, 'How do most consumers verify the quality of a new beauty or skincare product in Kenya?', 'Peer reviews, word of mouth, and trusted influencers', 'Billboard advertisements only', 'Random guessing', 'Mail flyers', 'A', 'Community recommendations and authentic user reviews drive trust.', 10),
(13, 'What payment method is most preferred for online cash-on-delivery in Kenya?', 'Personal paper cheques', 'M-Pesa on delivery', 'Foreign bank wire', 'Cryptocurrency', 'B', 'M-Pesa on delivery provides immediate security and convenience.', 10);

-- Questions for Level 19 (Chat Moderator Level 1 - Free)
INSERT INTO `questions` (`level_id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_option`, `explanation`, `points`) VALUES
(19, 'A user posts: "Click here to win 50,000 KES instantly by entering your M-Pesa PIN!" What action should a moderator take?', 'Approve the message', 'Pin it to the top', 'Remove message immediately and ban/warn account for phishing', 'Reply asking if it works', 'C', 'Phishing links requesting PINs are dangerous security violations.', 10),
(19, 'What is the appropriate response to hate speech targeting a specific community or ethnicity?', 'Ignore if no one complains', 'Immediate deletion and enforce strict platform suspension', 'Laugh at the post', 'Ask the user to explain', 'B', 'Zero tolerance applies to ethnic hate speech and discrimination.', 10),
(19, 'A user repeatedly posts identical commercial messages 20 times in 2 minutes. This is classified as:', 'Constructive conversation', 'Spamming / Flooding', 'Educational discussion', 'Normal engagement', 'B', 'Flooding multiple identical messages is classic disruptive spam.', 10),
(19, 'If two members disagree politely about football results, what should the moderator do?', 'Ban both members', 'Allow respectful debate without interference', 'Delete the thread', 'Mute everyone in the channel', 'B', 'Civil and respectful differences of opinion are healthy and allowed.', 10),
(19, 'A user shares an unverified photo of an individual accusing them of theft with their private phone number. This violates rules against:', 'Grammar standards', 'Doxxing and unauthorized sharing of personal private data', 'File format restrictions', 'Emoji limits', 'B', 'Doxxing violates privacy laws and platform safety policies.', 10),
(19, 'What should a moderator do if they are unsure whether a complex post violates local law or terms of service?', 'Delete it without logging', 'Escalate to Senior Moderator or Community Admin for review', 'Ignore forever', 'Post their own personal opinion', 'B', 'Escalation ensures consistent and fair rule enforcement.', 10),
(19, 'Which tone should a moderator maintain when issuing a warning to a rule-breaking user?', 'Aggressive and insulting', 'Objective, calm, polite, and professional', 'Sarcastic', 'Overly informal and apologetic', 'B', 'Professional objectivity defuses conflict and upholds authority.', 10),
(19, 'What should be done with messages containing explicit or abusive adult imagery in a general public chat?', 'Keep them for 24 hours', 'Immediate removal, log evidence, and report/ban offender', 'Mark as favorite', 'Forward to friends', 'B', 'Explicit materials in public general forums must be purged immediately.', 10),
(19, 'Why is keeping moderation logs and audit trails important?', 'To waste server space', 'To maintain accountability, consistency, and transparency', 'To spy on team members', 'There is no purpose', 'B', 'Audit logs ensure fair treatment and enable accountability.', 10),
(19, 'A user claims to be an official administrator asking members to DM their passwords. What is this?', 'Legitimate customer support', 'Social engineering / Impersonation scam', 'Helpful reminder', 'Marketing contest', 'B', 'Staff never ask for account passwords in private chat.', 10);

-- Questions for Level 25 (Online Tutor Level 1 - Free)
INSERT INTO `questions` (`level_id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_option`, `explanation`, `points`) VALUES
(25, 'Identify the correct grammatical sentence:', 'They has gone to the market yesterday.', 'They went to the market yesterday.', 'They goes to the market yesterday.', 'They going to the market yesterday.', 'B', '"They went" is the correct simple past tense form.', 10),
(25, 'What is the capital city of Kenya?', 'Mombasa', 'Kisumu', 'Nairobi', 'Nakuru', 'C', 'Nairobi is the capital and largest city of Kenya.', 10),
(25, 'In mathematics, what is the value of 15% of 200?', '20', '30', '25', '35', 'B', '0.15 * 200 = 30.', 10),
(25, 'Which gas do plants absorb during photosynthesis?', 'Oxygen', 'Carbon Dioxide', 'Nitrogen', 'Argon', 'B', 'Plants absorb carbon dioxide and release oxygen during photosynthesis.', 10),
(25, 'Which lake is the largest freshwater lake in Africa, shared by Kenya, Uganda, and Tanzania?', 'Lake Turkana', 'Lake Naivasha', 'Lake Victoria', 'Lake Baringo', 'C', 'Lake Victoria is Africa’s largest freshwater lake.', 10),
(25, 'What is the boiling point of pure water at standard sea-level atmospheric pressure?', '50 °C', '90 °C', '100 °C', '120 °C', 'C', 'Water boils at 100 degrees Celsius under standard atmospheric pressure.', 10),
(25, 'Choose the word that is correctly spelled:', 'Acommodate', 'Accommodate', 'Acomodate', 'Accomodate', 'B', 'Accommodate has two c\'s and two m\'s.', 10),
(25, 'If a rectangle has length 12 cm and width 5 cm, what is its perimeter?', '60 cm', '34 cm', '17 cm', '24 cm', 'B', 'Perimeter = 2 * (length + width) = 2 * (12 + 5) = 34 cm.', 10),
(25, 'Who was the first President of the Republic of Kenya?', 'Daniel arap Moi', 'Mwai Kibaki', 'Jomo Kenyatta', 'Uhuru Kenyatta', 'C', 'Mzee Jomo Kenyatta served as the first president of Kenya.', 10),
(25, 'In computer science, what does CPU stand for?', 'Central Performance Utility', 'Central Processing Unit', 'Core Programming Unit', 'Central Power Unit', 'B', 'CPU stands for Central Processing Unit.', 10);
