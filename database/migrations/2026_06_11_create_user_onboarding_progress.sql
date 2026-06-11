CREATE TABLE IF NOT EXISTS user_onboarding_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    tour_key VARCHAR(80) NOT NULL,
    tour_version VARCHAR(30) NOT NULL,
    dashboard_profile VARCHAR(30) NOT NULL,
    status ENUM('pending', 'completed', 'skipped', 'remind_later') NOT NULL DEFAULT 'pending',
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    skipped_at DATETIME NULL,
    reminded_at DATETIME NULL,
    last_seen_step INT DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_tour_version (user_id, tour_key, tour_version),
    INDEX idx_user_onboarding_user (user_id),
    INDEX idx_user_onboarding_status (status),
    CONSTRAINT fk_user_onboarding_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
) DEFAULT CHARSET=utf8mb4;
