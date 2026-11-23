CREATE DATABASE IF NOT EXISTS `registrals` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS `registrals_audit` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON `registrals`.* TO 'registrals_user'@'%';
GRANT ALL PRIVILEGES ON `registrals_audit`.* TO 'registrals_user'@'%';
FLUSH PRIVILEGES;
