CREATE TABLE `commandstore` (
                              `id` char(36) COLLATE utf8mb4_general_ci NOT NULL,
                              `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
                              `data` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
                              `request_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
                              `correlation_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
                              `executor_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
                              `status` char(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
                              `created_at` datetime(6) NOT NULL,
                              PRIMARY KEY (`id`),
                              KEY `request_id` (`request_id`),
                              KEY `executor_id` (`executor_id`),
                              KEY `status` (`status`),
                              KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;