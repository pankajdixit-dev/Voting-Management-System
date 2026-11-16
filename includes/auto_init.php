<?php
/**
 * Auto-Initializes Aiven MySQL Database on demand
 * Ensures all 5 tables and default records exist automatically
 */

function auto_init_database($conn, $force = false) {
    if (!$conn || $conn->connect_error) {
        return false;
    }

    if (!$force) {
        // Quick check: if admin table exists and has rows, no need to recreate
        $chk = @$conn->query("SELECT id FROM `admin` LIMIT 1");
        if ($chk && $chk instanceof mysqli_result && $chk->num_rows > 0) {
            return true;
        }
    }

    @$conn->query("SET FOREIGN_KEY_CHECKS = 0;");

    // 1. Create table `admin`
    @$conn->query("CREATE TABLE IF NOT EXISTS `admin` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `username` varchar(50) NOT NULL,
        `password` varchar(255) NOT NULL,
        `firstname` varchar(50) NOT NULL DEFAULT 'Admin',
        `lastname` varchar(50) NOT NULL DEFAULT 'User',
        `photo` varchar(150) NOT NULL DEFAULT 'avatar.png',
        `created_on` date NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `idx_username` (`username`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 2. Create table `positions`
    @$conn->query("CREATE TABLE IF NOT EXISTS `positions` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `description` varchar(50) NOT NULL,
        `max_vote` int(11) NOT NULL,
        `priority` int(11) NOT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 3. Create table `candidates`
    @$conn->query("CREATE TABLE IF NOT EXISTS `candidates` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `position_id` int(11) NOT NULL,
        `firstname` varchar(30) NOT NULL,
        `lastname` varchar(30) NOT NULL,
        `photo` varchar(150) NOT NULL DEFAULT 'profile.jpg',
        `platform` text NOT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 4. Create table `voters`
    @$conn->query("CREATE TABLE IF NOT EXISTS `voters` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `voters_id` varchar(15) NOT NULL,
        `password` varchar(255) NOT NULL,
        `firstname` varchar(30) NOT NULL,
        `lastname` varchar(30) NOT NULL,
        `photo` varchar(150) NOT NULL DEFAULT 'profile.jpg',
        PRIMARY KEY (`id`),
        UNIQUE KEY `idx_voters_id` (`voters_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 5. Create table `votes`
    @$conn->query("CREATE TABLE IF NOT EXISTS `votes` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `voters_id` int(11) NOT NULL,
        `candidate_id` int(11) NOT NULL,
        `position_id` int(11) NOT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Ensure Default Admin User exists with password 'admin'
    $adminHash = password_hash('admin', PASSWORD_DEFAULT);
    $chkAdmin = @$conn->query("SELECT id FROM `admin` WHERE username = 'admin' LIMIT 1");
    if (!$chkAdmin || !($chkAdmin instanceof mysqli_result) || $chkAdmin->num_rows == 0) {
        @$conn->query("INSERT INTO `admin` (`id`, `username`, `password`, `firstname`, `lastname`, `photo`, `created_on`) 
                       VALUES (1, 'admin', '$adminHash', 'Source', 'Code', 'avatar.png', CURDATE())");
    } else {
        @$conn->query("UPDATE `admin` SET `password` = '$adminHash' WHERE `username` = 'admin'");
    }

    // Populate Default Positions if empty
    $chkPos = @$conn->query("SELECT id FROM `positions` LIMIT 1");
    if (!$chkPos || !($chkPos instanceof mysqli_result) || $chkPos->num_rows == 0) {
        @$conn->query("INSERT IGNORE INTO `positions` (`id`, `description`, `max_vote`, `priority`) VALUES
            (8, 'Sample', 5, 2),
            (9, 'Best Villian - MCU Actors', 5, 1);");
    }

    // Populate Default Candidates if empty
    $chkCan = @$conn->query("SELECT id FROM `candidates` LIMIT 1");
    if (!$chkCan || !($chkCan instanceof mysqli_result) || $chkCan->num_rows == 0) {
        @$conn->query("INSERT IGNORE INTO `candidates` (`id`, `position_id`, `firstname`, `lastname`, `photo`, `platform`) VALUES
            (18, 9, 'Josh ', 'Brolin', 'thanossmile.jpg', 'Marvel Cinematic Universe'),
            (19, 9, 'Tom', 'Hiddleston', 'AvengerIW4 (2).jpg', 'Marvel Cinematic Universe'),
            (20, 9, 'James', 'Spader', 'ultron.jpg', 'Marvel Cinematic Universe'),
            (21, 9, 'Cate', 'Blanchett', 'hela.jpg', 'Marvel Cinematic Universe'),
            (22, 9, 'Michael B', 'Jordan', 'killmongr.jpg', 'Marvel Cinematic Universe'),
            (23, 9, 'Hugo', 'Weaving', 'reddd.jpg', 'Marvel Cinematic Universe'),
            (24, 9, 'Tom', 'Vaughan', 'ebony.jpg', 'Marvel Cinematic Universe'),
            (25, 8, 'Mark', 'Doe', 'mask.jpg', 'Sample ...............'),
            (26, 8, 'Bruno', 'Jr', 'Opi51c74ead38850.png', 'Sample ..');");
    }

    // Populate Default Sample Voters if empty
    $chkVoters = @$conn->query("SELECT id FROM `voters` LIMIT 1");
    if (!$chkVoters || !($chkVoters instanceof mysqli_result) || $chkVoters->num_rows == 0) {
        $voterPass = password_hash('password', PASSWORD_DEFAULT);
        @$conn->query("INSERT IGNORE INTO `voters` (`id`, `voters_id`, `password`, `firstname`, `lastname`, `photo`) VALUES
            (5, 'pSMQYCjWk5dnmaD', '$voterPass', 'Harry', 'Den', 'dealer-logo.jpg'),
            (6, 'Is92CPnGcvOy4ue', '$voterPass', 'Tony', 'Jr', 'favicon.png'),
            (7, 'gwvIniKqT5xsWJc', '$voterPass', 'Danny', 'Hill', 'TROLLFACE-DEAL-WITH-IT.png'),
            (8, 'IpqGblR7m6tQFhz', '$voterPass', 'Tom', 'Lee', 'e360bc98dbb4441f73d253f90723d9a4.jpg');");
    }

    @$conn->query("SET FOREIGN_KEY_CHECKS = 1;");
    return true;
}
?>