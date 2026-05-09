-- Student Collaboration Platform (XAMPP / MySQL)
-- Create DB + tables expected by this project.

CREATE DATABASE IF NOT EXISTS `student_collab_platform`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `student_collab_platform`;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(40) DEFAULT NULL,
  `profile_pic` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_users_email` (`email`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `projects` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(180) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_projects_created_by` (`created_by`),
  CONSTRAINT `fk_projects_created_by` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `project_members` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `role` ENUM('owner','member') NOT NULL DEFAULT 'member',
  `joined_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_project_members` (`project_id`, `user_id`),
  KEY `idx_project_members_user` (`user_id`),
  CONSTRAINT `fk_project_members_project` FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_project_members_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `project_requests` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` INT UNSIGNED NOT NULL,
  `from_user_id` INT UNSIGNED NOT NULL,
  `status` ENUM('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_project_requests` (`project_id`, `from_user_id`),
  KEY `idx_project_requests_project` (`project_id`),
  KEY `idx_project_requests_from_user` (`from_user_id`),
  CONSTRAINT `fk_project_requests_project` FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_project_requests_from_user` FOREIGN KEY (`from_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `tasks` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` INT UNSIGNED DEFAULT NULL,
  `title` VARCHAR(180) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `assigned_to` INT UNSIGNED DEFAULT NULL,
  `status` ENUM('todo','in-progress','done') NOT NULL DEFAULT 'todo',
  `taken_by` INT UNSIGNED DEFAULT NULL,
  `taken_at` TIMESTAMP NULL DEFAULT NULL,
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tasks_project` (`project_id`),
  KEY `idx_tasks_assigned_to` (`assigned_to`),
  KEY `idx_tasks_taken_by` (`taken_by`),
  KEY `idx_tasks_created_by` (`created_by`),
  CONSTRAINT `fk_tasks_project` FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tasks_assigned_to` FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tasks_taken_by` FOREIGN KEY (`taken_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tasks_created_by` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `files` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` INT UNSIGNED DEFAULT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `file_name` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(500) NOT NULL,
  `uploaded_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_files_project` (`project_id`),
  KEY `idx_files_user` (`user_id`),
  CONSTRAINT `fk_files_project` FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_files_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `messages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sender_id` INT UNSIGNED NOT NULL,
  `receiver_id` INT UNSIGNED NOT NULL,
  `message` TEXT NOT NULL,
  `seen` TINYINT(1) NOT NULL DEFAULT 0,
  `deleted_by_sender` TINYINT(1) NOT NULL DEFAULT 0,
  `deleted_by_receiver` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_messages_receiver_seen` (`receiver_id`, `seen`),
  KEY `idx_messages_pair` (`sender_id`, `receiver_id`, `id`),
  CONSTRAINT `fk_messages_sender` FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_messages_receiver` FOREIGN KEY (`receiver_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Ensure project owner is also a member (run after you add projects)
-- INSERT INTO project_members (project_id, user_id, role) VALUES (..., ..., 'owner');

