CREATE TABLE `eventstore` (
                              `id` char(36) COLLATE utf8mb4_general_ci NOT NULL,
                              `aggregate_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
                              `aggregate_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
                              `event_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
                              `event_version` int unsigned NOT NULL,
                              `event_data` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
                              `created_at` datetime(6) NOT NULL,
                              PRIMARY KEY (`id`),
                              KEY `aggregate_id` (`aggregate_id`),
                              KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;