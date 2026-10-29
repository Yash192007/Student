USE studenthub;

ALTER TABLE users
  MODIFY COLUMN role ENUM('student', 'faculty', 'admin') NOT NULL DEFAULT 'student';

CREATE TABLE IF NOT EXISTS faculty (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  department VARCHAR(120) NOT NULL,
  title VARCHAR(80) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_faculty_user (user_id),
  KEY idx_faculty_department (department),
  CONSTRAINT fk_faculty_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;
