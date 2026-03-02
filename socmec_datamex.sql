-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 02, 2026 at 12:16 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `socmec_datamex`
--

-- --------------------------------------------------------

--
-- Table structure for table `accounts`
--

CREATE TABLE `accounts` (
  `profileId` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `displayName` varchar(255) NOT NULL,
  `userType` varchar(255) NOT NULL,
  `bio` text NOT NULL,
  `dateCreated` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `accounts`
--

INSERT INTO `accounts` (`profileId`, `email`, `password`, `displayName`, `userType`, `bio`, `dateCreated`) VALUES
(1, 'notthebestgamer64@gmail.com', '$2y$10$t1t5fDFu4TPC8AcBB7xiReba/y7vRlY/EBGD3kmeIUSdsW1S8m.RK', 'Gabriel', '', 'This user is lazy to put anything.', '2026-01-17 04:57:54');

-- --------------------------------------------------------

--
-- Table structure for table `admin_logs`
--

CREATE TABLE `admin_logs` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `action` varchar(100) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_logs`
--

INSERT INTO `admin_logs` (`id`, `admin_id`, `action`, `details`, `created_at`) VALUES
(1, 1, 'Warn User', 'Warned user #2: wala lang', '2026-01-27 05:00:07'),
(2, 1, 'Warn User', 'Warned user #2: wala lang', '2026-01-27 05:14:19'),
(3, 1, 'Warn User', 'Warned user #2: wala lang', '2026-01-27 06:02:32'),
(4, 1, 'Warn User', 'Warned user #2: tanginamo', '2026-01-28 00:05:11'),
(5, 1, 'Ban User', 'Banned user #2 for 1 minutes: minura moko', '2026-01-28 00:17:11'),
(6, 1, 'Delete Post', 'Deleted post #15 by user #2: admin ulol', '2026-01-28 00:20:01'),
(7, 1, 'Ban User', 'Banned user #2 for 1 minutes: nagmura ka', '2026-01-28 00:28:43'),
(8, 1, 'Warn User', 'Warned user #2: nag ban eh', '2026-01-28 01:19:21'),
(9, 1, 'Ban User', 'Banned user #6 for 1 minutes: tangina nagmura ka', '2026-01-28 02:59:11'),
(10, 1, 'Ban User', 'Banned user #2 for 1 minutes: nagmura amputa', '2026-01-28 03:00:08'),
(11, 1, 'Warn User', 'Warned user #2: nagmura ka kupal', '2026-02-02 02:39:53'),
(12, 9, 'Ban User', 'Banned user #2 for 1 hours: hey', '2026-02-21 15:47:19'),
(13, 9, 'Delete Post', 'Deleted post #3 by user #2: tanginaaaaaa', '2026-02-21 15:47:26'),
(14, 9, 'Lock User', 'Locked user #8 for 10 minutes. Reason: Wow', '2026-02-21 21:37:59'),
(15, 9, 'Warn User', 'Warned user #8: This bad', '2026-02-21 21:38:41'),
(16, 9, 'Ban User', 'Banned user #12 for 10 minutes: ewwa', '2026-02-21 22:10:09'),
(17, 9, 'Delete User', 'Deleted user #14 (1234)', '2026-02-21 22:10:27'),
(18, 15, 'Change User Role', 'Changed user #6 (123123123) role from faculty to student', '2026-02-28 22:29:11'),
(19, 15, 'Delete User', 'Deleted user #12 (123124)', '2026-03-01 09:03:53'),
(20, 15, 'Delete User', 'Deleted user #17 (13214)', '2026-03-01 09:37:39');

-- --------------------------------------------------------

--
-- Table structure for table `comments`
--

CREATE TABLE `comments` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `content` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `comments`
--

INSERT INTO `comments` (`id`, `post_id`, `user_id`, `content`, `created_at`) VALUES
(1, 2, 4, 'goodlooking', '2026-01-21 14:42:50'),
(2, 2, 4, 'pogi', '2026-01-21 14:42:55'),
(6, 58, 13, 'Damn Son', '2026-02-26 17:48:33'),
(7, 74, 8, 'Cool', '2026-02-28 01:19:13'),
(8, 58, 9, 'How any comments', '2026-02-28 02:39:50'),
(9, 58, 9, 'awsedawd', '2026-02-28 02:39:53'),
(10, 58, 9, 'For real for real', '2026-02-28 02:40:01'),
(11, 58, 9, 'craxy', '2026-02-28 02:40:08'),
(12, 68, 18, 'O_O', '2026-03-02 06:18:49'),
(13, 79, 8, 'as', '2026-03-02 11:13:01');

-- --------------------------------------------------------

--
-- Table structure for table `email_verification`
--

CREATE TABLE `email_verification` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `code` varchar(10) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `email_verification`
--

INSERT INTO `email_verification` (`id`, `email`, `code`, `expires_at`, `created_at`) VALUES
(9, 'nobaradelacruz@gmail.com', '870715', '2026-02-22 05:53:07', '2026-02-21 21:43:07');

-- --------------------------------------------------------

--
-- Table structure for table `follows`
--

CREATE TABLE `follows` (
  `follower_id` int(11) NOT NULL,
  `followed_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `follows`
--

INSERT INTO `follows` (`follower_id`, `followed_id`, `created_at`) VALUES
(2, 1, '2026-01-21 14:07:14'),
(2, 3, '2026-01-21 14:53:07'),
(2, 4, '2026-01-21 14:46:07'),
(3, 1, '2026-01-21 14:34:00'),
(4, 2, '2026-01-21 14:57:41'),
(4, 3, '2026-01-21 14:43:22'),
(8, 2, '2026-02-21 16:39:22'),
(8, 18, '2026-03-02 08:37:58'),
(13, 8, '2026-02-26 17:44:48'),
(13, 9, '2026-02-21 21:56:07');

-- --------------------------------------------------------

--
-- Table structure for table `likes`
--

CREATE TABLE `likes` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `likes`
--

INSERT INTO `likes` (`id`, `post_id`, `user_id`, `created_at`) VALUES
(2, 3, 2, '2026-01-21 13:28:59'),
(3, 3, 1, '2026-01-21 14:04:04'),
(4, 2, 1, '2026-01-21 14:04:06'),
(5, 5, 1, '2026-01-21 14:29:51'),
(7, 5, 4, '2026-01-21 14:51:02'),
(8, 10, 2, '2026-01-21 14:52:54'),
(9, 14, 2, '2026-01-27 07:35:24'),
(10, 14, 4, '2026-02-01 15:43:56'),
(12, 5, 9, '2026-02-21 15:47:06'),
(16, 58, 13, '2026-02-26 17:48:20'),
(17, 56, 13, '2026-02-26 17:48:22'),
(18, 55, 13, '2026-02-26 17:48:23'),
(20, 74, 8, '2026-02-28 01:19:05'),
(21, 53, 18, '2026-03-02 06:18:38');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `content` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notes`
--

CREATE TABLE `notes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `content` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notes`
--

INSERT INTO `notes` (`id`, `user_id`, `title`, `content`, `created_at`, `updated_at`) VALUES
(6, 8, '', 'wsarae', '2026-02-21 15:43:06', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `note_likes`
--

CREATE TABLE `note_likes` (
  `id` int(11) NOT NULL,
  `note_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `from_user_id` int(11) DEFAULT NULL,
  `post_id` int(11) DEFAULT NULL,
  `type` enum('like','comment','share','admin_post','admin_comment','admin_share','warning','follow','post_deleted','story','story_like') NOT NULL,
  `message` varchar(255) NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `post_content_snapshot` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `from_user_id`, `post_id`, `type`, `message`, `is_read`, `created_at`, `post_content_snapshot`) VALUES
(1, 2, 1, 3, 'like', 'Administrator liked your post', 1, '2026-01-21 14:04:04', NULL),
(2, 2, 1, 2, 'like', 'Administrator liked your post', 1, '2026-01-21 14:04:06', NULL),
(3, 2, 1, 5, 'like', 'Datamex Admin liked your post', 1, '2026-01-21 14:29:51', NULL),
(4, 2, 4, 2, 'comment', 'zedrix angeles commented on your post', 1, '2026-01-21 14:42:50', NULL),
(5, 2, 4, 2, 'comment', 'zedrix angeles commented on your post', 1, '2026-01-21 14:42:55', NULL),
(6, 2, 4, 5, 'like', 'Aira Mae Sueno liked your post', 1, '2026-01-21 14:51:02', NULL),
(7, 4, 2, 10, 'like', 'Ardrew Castillo Angeles liked your post', 0, '2026-01-21 14:52:54', NULL),
(8, 1, 2, 12, '', 'Ardrew Castillo Angeles posted: ardrew angeles po', 0, '2026-01-27 04:56:55', NULL),
(9, 2, 1, NULL, 'warning', 'You received a warning from Datamex Admin. Reason: wala lang', 1, '2026-01-27 05:14:19', NULL),
(11, 2, 1, NULL, 'warning', 'You received a warning from Datamex Admin. Reason: wala lang', 1, '2026-01-27 06:02:32', NULL),
(12, 6, 2, 14, 'like', 'Ardrew Castillo Angeles liked your post', 1, '2026-01-27 07:35:24', NULL),
(13, 2, 1, NULL, 'warning', 'You received a warning from Datamex Admin. Reason: tanginamo', 1, '2026-01-28 00:05:11', NULL),
(14, 1, 2, 15, 'admin_post', 'Ardrew Castillo Angeles posted: admin ulol', 1, '2026-01-28 00:16:02', NULL),
(15, 1, 2, 16, 'admin_post', 'Ardrew Castillo Angeles posted: lol', 1, '2026-01-28 00:28:04', NULL),
(17, 2, 1, NULL, 'warning', 'You received a warning from Datamex Admin. Reason: nag ban eh', 1, '2026-01-28 01:19:21', NULL),
(19, 4, 2, NULL, 'story', 'Ardrew Castillo Angeles posted a story.', 0, '2026-02-01 15:32:03', NULL),
(20, 2, 4, NULL, 'story_like', 'Aira Mae Sueno liked your story.', 1, '2026-02-01 15:43:47', NULL),
(21, 2, 4, NULL, 'story_like', 'Aira Mae Sueno liked your story.', 1, '2026-02-01 15:43:51', NULL),
(22, 6, 4, 14, 'like', 'Aira Mae Sueno liked your post', 0, '2026-02-01 15:43:56', NULL),
(23, 1, 2, 19, 'admin_post', 'Ardrew Castillo Angeles posted: puta kayo', 0, '2026-02-02 02:39:19', NULL),
(24, 2, 1, NULL, 'warning', 'You received a warning from Datamex Admin. Reason: nagmura ka kupal', 1, '2026-02-02 02:39:53', NULL),
(56, 1, 8, 50, 'admin_post', 'Mikaelle Angelo A. Gabriel posted: Annyeong', 0, '2026-02-21 15:40:51', NULL),
(57, 1, 8, 51, 'admin_post', 'Mikaelle Angelo A. Gabriel posted: ', 0, '2026-02-21 15:40:59', NULL),
(58, 2, 9, 5, 'like', 'Gabriel liked your post', 0, '2026-02-21 15:47:06', NULL),
(59, 2, 9, 3, 'post_deleted', 'An admin removed your post.', 0, '2026-02-21 15:47:26', 'tanginaaaaaa'),
(60, 2, 8, NULL, 'follow', 'Mikaelle Angelo A. Gabriel started following you.', 0, '2026-02-21 16:35:54', NULL),
(61, 2, 8, NULL, 'follow', 'Mikaelle Angelo A. Gabriel started following you.', 0, '2026-02-21 16:39:22', NULL),
(62, 1, 8, 55, 'admin_post', 'Mikaelle Angelo A. Gabriel posted: s', 0, '2026-02-21 16:45:26', NULL),
(63, 9, 8, 55, 'admin_post', 'Mikaelle Angelo A. Gabriel posted: s', 0, '2026-02-21 16:45:26', NULL),
(64, 1, 8, 56, 'admin_post', 'Mikaelle Angelo A. Gabriel posted: Hey Loook at me!', 0, '2026-02-21 17:01:49', NULL),
(65, 9, 8, 56, 'admin_post', 'Mikaelle Angelo A. Gabriel posted: Hey Loook at me!', 0, '2026-02-21 17:01:49', NULL),
(66, 1, 8, 57, 'admin_post', 'Mikaelle Angelo A. Gabriel posted: You shouldn\'t be seeing this', 0, '2026-02-21 17:02:09', NULL),
(67, 9, 8, 57, 'admin_post', 'Mikaelle Angelo A. Gabriel posted: You shouldn\'t be seeing this', 0, '2026-02-21 17:02:09', NULL),
(68, 1, 8, 58, 'admin_post', 'Mikaelle Angelo A. Gabriel posted: Only my followers can see this', 0, '2026-02-21 17:02:42', NULL),
(69, 9, 8, 58, 'admin_post', 'Mikaelle Angelo A. Gabriel posted: Only my followers can see this', 1, '2026-02-21 17:02:42', NULL),
(72, 2, NULL, NULL, 'follow', 'Ben started following you.', 0, '2026-02-21 17:22:07', NULL),
(73, 2, NULL, NULL, 'follow', 'Ben started following you.', 0, '2026-02-21 17:30:29', NULL),
(74, 8, NULL, NULL, 'story_like', 'Ben liked your story.', 0, '2026-02-21 17:31:20', NULL),
(75, 8, NULL, NULL, 'story_like', 'Ben liked your story.', 1, '2026-02-21 17:31:22', NULL),
(76, 8, NULL, NULL, 'follow', 'Ben started following you.', 0, '2026-02-21 17:36:41', NULL),
(77, 9, NULL, NULL, 'follow', 'Ben started following you.', 0, '2026-02-21 17:41:22', NULL),
(88, 8, NULL, NULL, 'story_like', 'Ben liked your story.', 1, '2026-02-21 18:18:07', NULL),
(89, 8, NULL, NULL, 'follow', 'Ben started following you.', 1, '2026-02-21 18:24:10', NULL),
(90, 8, NULL, NULL, 'follow', 'Ben started following you.', 1, '2026-02-21 18:24:12', NULL),
(91, 8, NULL, NULL, 'follow', 'Ben started following you.', 0, '2026-02-21 18:24:15', NULL),
(92, 8, NULL, NULL, 'follow', 'Ben started following you.', 0, '2026-02-21 18:24:31', NULL),
(93, 8, NULL, NULL, 'follow', 'Ben started following you.', 0, '2026-02-21 18:26:53', NULL),
(95, 8, 9, NULL, 'warning', 'Your account has been locked due to sensitive and not reliable content. Reason: Wow', 0, '2026-02-21 21:37:59', NULL),
(96, 8, 9, NULL, 'warning', 'You received a warning from Gabriel. Reason: This bad', 0, '2026-02-21 21:38:41', NULL),
(97, 9, 13, NULL, 'follow', 'Nobara started following you.', 0, '2026-02-21 21:56:07', NULL),
(98, 2, NULL, NULL, 'follow', 'Mikaelle Angelo Gab started following you.', 0, '2026-02-21 22:00:20', NULL),
(112, 8, NULL, NULL, 'story_like', 'Mikaelle Angelo Gab liked your story.', 0, '2026-02-21 22:07:36', NULL),
(113, 8, NULL, NULL, 'story_like', 'Mikaelle Angelo Gab liked your story.', 0, '2026-02-21 22:07:37', NULL),
(114, 8, NULL, 58, 'like', 'Mikaelle Angelo Gab liked your post', 0, '2026-02-21 22:07:46', NULL),
(115, 8, NULL, NULL, 'follow', 'Mikaelle Angelo Gab started following you.', 1, '2026-02-21 22:07:56', NULL),
(117, 13, 9, NULL, 'story', 'Gabriel posted a story.', 0, '2026-02-21 22:11:20', NULL),
(118, 8, 13, NULL, 'follow', 'Nobara started following you.', 0, '2026-02-26 17:44:48', NULL),
(119, 8, 13, 58, 'like', 'Nobara liked your post', 0, '2026-02-26 17:48:20', NULL),
(120, 8, 13, 56, 'like', 'Nobara liked your post', 0, '2026-02-26 17:48:22', NULL),
(121, 8, 13, 55, 'like', 'Nobara liked your post', 0, '2026-02-26 17:48:23', NULL),
(122, 8, 13, 58, 'comment', 'Nobara commented on your post', 0, '2026-02-26 17:48:33', NULL),
(132, 13, 8, NULL, 'story', 'Mikaelle Angelo A. Gabriel posted a story.', 0, '2026-02-27 23:34:29', NULL),
(133, 1, 8, 74, 'admin_post', 'Mikaelle Angelo A. Gabriel posted: Test Cloudinary', 0, '2026-02-28 01:18:55', NULL),
(134, 9, 8, 74, 'admin_post', 'Mikaelle Angelo A. Gabriel posted: Test Cloudinary', 0, '2026-02-28 01:18:55', NULL),
(135, 1, 8, 74, 'admin_comment', 'Mikaelle Angelo A. Gabriel commented: Cool', 0, '2026-02-28 01:19:13', NULL),
(136, 9, 8, 74, 'admin_comment', 'Mikaelle Angelo A. Gabriel commented: Cool', 0, '2026-02-28 01:19:13', NULL),
(137, 8, 9, 58, 'comment', 'Gabriel commented on your post', 0, '2026-02-28 02:39:50', NULL),
(138, 8, 9, 58, 'comment', 'Gabriel commented on your post', 0, '2026-02-28 02:39:53', NULL),
(139, 8, 9, 58, 'comment', 'Gabriel commented on your post', 0, '2026-02-28 02:40:01', NULL),
(140, 8, 9, 58, 'comment', 'Gabriel commented on your post', 0, '2026-02-28 02:40:08', NULL),
(144, 8, 18, NULL, 'follow', 'Ben started following you.', 1, '2026-03-01 11:58:07', NULL),
(145, 1, 18, 78, 'admin_post', 'Ben posted: ssaD', 0, '2026-03-01 15:20:19', NULL),
(146, 9, 18, 78, 'admin_post', 'Ben posted: ssaD', 0, '2026-03-01 15:20:19', NULL),
(147, 15, 18, 78, 'admin_post', 'Ben posted: ssaD', 0, '2026-03-01 15:20:19', NULL),
(148, 1, 18, 79, 'admin_post', 'Ben posted: ', 0, '2026-03-02 06:17:20', NULL),
(149, 9, 18, 79, 'admin_post', 'Ben posted: ', 0, '2026-03-02 06:17:20', NULL),
(150, 15, 18, 79, 'admin_post', 'Ben posted: ', 0, '2026-03-02 06:17:20', NULL),
(151, 9, 18, 53, 'like', 'Ben liked your post', 0, '2026-03-02 06:18:38', NULL),
(152, 9, 18, 68, 'comment', 'Ben commented on your post', 0, '2026-03-02 06:18:49', NULL),
(153, 1, 18, 68, 'admin_comment', 'Ben commented: O_O', 0, '2026-03-02 06:18:49', NULL),
(154, 9, 18, 68, 'admin_comment', 'Ben commented: O_O', 0, '2026-03-02 06:18:49', NULL),
(155, 15, 18, 68, 'admin_comment', 'Ben commented: O_O', 0, '2026-03-02 06:18:49', NULL),
(156, 1, 8, 80, 'admin_post', 'Mikaelle Angelo A. Gabriel posted: ', 0, '2026-03-02 08:37:33', NULL),
(157, 9, 8, 80, 'admin_post', 'Mikaelle Angelo A. Gabriel posted: ', 0, '2026-03-02 08:37:33', NULL),
(158, 15, 8, 80, 'admin_post', 'Mikaelle Angelo A. Gabriel posted: ', 0, '2026-03-02 08:37:33', NULL),
(159, 18, 8, NULL, 'follow', 'Mikaelle Angelo A. Gabriel started following you.', 0, '2026-03-02 08:37:58', NULL),
(160, 13, 8, NULL, 'story', 'Mikaelle Angelo A. Gabriel posted a story.', 0, '2026-03-02 08:58:47', NULL),
(161, 1, 8, 81, 'admin_post', 'Mikaelle Angelo A. Gabriel posted: ', 0, '2026-03-02 09:06:05', NULL),
(162, 9, 8, 81, 'admin_post', 'Mikaelle Angelo A. Gabriel posted: ', 0, '2026-03-02 09:06:05', NULL),
(163, 15, 8, 81, 'admin_post', 'Mikaelle Angelo A. Gabriel posted: ', 0, '2026-03-02 09:06:05', NULL),
(164, 2, 8, 2, 'share', 'Mikaelle Angelo A. Gabriel shared your post', 0, '2026-03-02 10:27:33', NULL),
(165, 1, 8, 2, 'admin_share', 'Mikaelle Angelo A. Gabriel shared a post', 0, '2026-03-02 10:27:33', NULL),
(166, 9, 8, 2, 'admin_share', 'Mikaelle Angelo A. Gabriel shared a post', 0, '2026-03-02 10:27:33', NULL),
(167, 15, 8, 2, 'admin_share', 'Mikaelle Angelo A. Gabriel shared a post', 0, '2026-03-02 10:27:33', NULL),
(168, 18, 8, 79, 'share', 'Mikaelle Angelo A. Gabriel shared your post', 0, '2026-03-02 11:00:14', NULL),
(169, 1, 8, 79, 'admin_share', 'Mikaelle Angelo A. Gabriel shared a post', 0, '2026-03-02 11:00:14', NULL),
(170, 9, 8, 79, 'admin_share', 'Mikaelle Angelo A. Gabriel shared a post', 0, '2026-03-02 11:00:14', NULL),
(171, 15, 8, 79, 'admin_share', 'Mikaelle Angelo A. Gabriel shared a post', 0, '2026-03-02 11:00:14', NULL),
(172, 1, 8, 83, 'admin_share', 'Mikaelle Angelo A. Gabriel shared a post', 0, '2026-03-02 11:00:34', NULL),
(173, 9, 8, 83, 'admin_share', 'Mikaelle Angelo A. Gabriel shared a post', 0, '2026-03-02 11:00:34', NULL),
(174, 15, 8, 83, 'admin_share', 'Mikaelle Angelo A. Gabriel shared a post', 0, '2026-03-02 11:00:34', NULL),
(175, 18, 8, 79, 'comment', 'Mikaelle Angelo A. Gabriel commented on your post', 0, '2026-03-02 11:13:01', NULL),
(176, 1, 8, 79, 'admin_comment', 'Mikaelle Angelo A. Gabriel commented: as', 0, '2026-03-02 11:13:01', NULL),
(177, 9, 8, 79, 'admin_comment', 'Mikaelle Angelo A. Gabriel commented: as', 0, '2026-03-02 11:13:01', NULL),
(178, 15, 8, 79, 'admin_comment', 'Mikaelle Angelo A. Gabriel commented: as', 0, '2026-03-02 11:13:01', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `user_id`, `token`, `expires_at`, `created_at`) VALUES
(8, 8, '74495171094181f7ba06d5302cba44ac', '2026-03-02 14:43:58', '2026-03-02 06:33:58');

-- --------------------------------------------------------

--
-- Table structure for table `posts`
--

CREATE TABLE `posts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `content` text DEFAULT NULL,
  `media_type` varchar(20) DEFAULT NULL,
  `privacy` enum('only_me','followers','friends_of_friends','public','private') DEFAULT 'public',
  `reference_post` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `media_urls` text DEFAULT NULL,
  `post_type` enum('post','announcement') DEFAULT 'post',
  `announcement_status` enum('pending','approved') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `posts`
--

INSERT INTO `posts` (`id`, `user_id`, `content`, `media_type`, `privacy`, `reference_post`, `created_at`, `updated_at`, `deleted_at`, `media_urls`, `post_type`, `announcement_status`) VALUES
(2, 2, '', 'image', 'public', NULL, '2026-01-21 13:28:42', NULL, NULL, '[\"https://res.cloudinary.com/dmkoc4lis/image/upload/v1772129042/post_6970d9e1cdb72_mku51y.jpg\"]', 'post', NULL),
(3, 2, 'tanginaaaaaa', 'text', 'public', NULL, '2026-01-21 13:28:50', NULL, '2026-02-21 15:47:26', NULL, 'post', NULL),
(5, 2, '', 'image', 'public', NULL, '2026-01-21 13:34:34', NULL, NULL, '[\"https://res.cloudinary.com/dmkoc4lis/image/upload/v1772129042/post_6970d5ea744c8_n5ijbw.jpg\",\"https://res.cloudinary.com/dmkoc4lis/image/upload/v1772129042/post_6970d5ea74cbf_ikqrco.jpg\"]', 'post', NULL),
(10, 4, 'pogi', 'text', 'public', NULL, '2026-01-21 14:43:04', NULL, NULL, NULL, 'post', NULL),
(11, 4, 'datamex', 'text', 'public', NULL, '2026-01-21 14:57:52', NULL, NULL, NULL, 'post', NULL),
(12, 2, 'ardrew angeles po', 'text', 'public', NULL, '2026-01-27 04:56:55', NULL, NULL, NULL, 'post', NULL),
(14, 6, 'ardrew', 'text', 'public', NULL, '2026-01-27 06:21:48', NULL, NULL, NULL, 'announcement', 'approved'),
(15, 2, 'admin ulol', 'text', 'only_me', NULL, '2026-01-28 00:16:02', NULL, '2026-01-28 00:20:01', NULL, 'post', NULL),
(16, 2, 'lol', 'text', 'public', NULL, '2026-01-28 00:28:04', NULL, NULL, NULL, 'post', NULL),
(19, 2, 'puta kayo', 'text', 'public', NULL, '2026-02-02 02:39:19', NULL, NULL, NULL, 'post', NULL),
(50, 8, 'Annyeong', 'text', 'public', NULL, '2026-02-21 15:40:51', NULL, NULL, NULL, 'post', NULL),
(51, 8, '', 'image', 'public', NULL, '2026-02-21 15:40:59', NULL, NULL, '[\"https://res.cloudinary.com/dmkoc4lis/image/upload/v1772129051/post_6999d20bc77e6_qlg6o8.jpg\"]', 'post', NULL),
(52, 9, 'Hey Im walking here', 'text', 'public', NULL, '2026-02-21 15:48:18', NULL, NULL, NULL, 'post', NULL),
(53, 9, 'Hey', 'text', 'public', NULL, '2026-02-21 15:50:54', NULL, NULL, NULL, 'announcement', 'approved'),
(54, 9, 'dsfsdf', 'text', 'public', NULL, '2026-02-21 16:14:10', NULL, NULL, NULL, 'post', NULL),
(55, 8, 's', 'text', 'public', NULL, '2026-02-21 16:45:26', NULL, NULL, NULL, 'post', NULL),
(56, 8, 'Hey Loook at me!', 'text', 'public', NULL, '2026-02-21 17:01:49', NULL, NULL, NULL, 'post', NULL),
(57, 8, 'You shouldn\'t be seeing this', 'text', 'only_me', NULL, '2026-02-21 17:02:09', NULL, NULL, NULL, 'post', NULL),
(58, 8, 'Only my followers can see this', 'text', 'followers', NULL, '2026-02-21 17:02:42', NULL, NULL, NULL, 'post', NULL),
(68, 9, 'Senn', 'image', 'public', NULL, '2026-02-21 22:09:30', NULL, NULL, '[\"https://res.cloudinary.com/dmkoc4lis/image/upload/v1772129041/post_699a2d1a60b83_q77vi6.png\"]', 'announcement', 'approved'),
(69, 9, 'Heyyyyy', 'text', 'public', NULL, '2026-02-26 17:01:19', NULL, NULL, NULL, 'announcement', 'approved'),
(74, 8, 'Test Cloudinary Edit as well', 'image', 'public', NULL, '2026-02-28 01:18:55', '2026-03-02 08:09:41', NULL, '[{\"url\":\"https:\\/\\/res.cloudinary.com\\/dmkoc4lis\\/image\\/upload\\/v1772241535\\/uploads\\/posts\\/abguv3rjjajovbbdl1jj.jpg\",\"public_id\":\"uploads\\/posts\\/abguv3rjjajovbbdl1jj\",\"resource_type\":\"image\"},{\"url\":\"https:\\/\\/res.cloudinary.com\\/dmkoc4lis\\/image\\/upload\\/v1772438955\\/uploads\\/posts\\/gm0ns1evoezluchfht39.png\",\"public_id\":\"uploads\\/posts\\/gm0ns1evoezluchfht39\",\"resource_type\":\"image\"},{\"url\":\"https:\\/\\/res.cloudinary.com\\/dmkoc4lis\\/image\\/upload\\/v1772438956\\/uploads\\/posts\\/sjcg6safgp0xxetamtsf.png\",\"public_id\":\"uploads\\/posts\\/sjcg6safgp0xxetamtsf\",\"resource_type\":\"image\"},{\"url\":\"https:\\/\\/res.cloudinary.com\\/dmkoc4lis\\/image\\/upload\\/v1772438957\\/uploads\\/posts\\/x1n3ifstbspnsyaflrag.png\",\"public_id\":\"uploads\\/posts\\/x1n3ifstbspnsyaflrag\",\"resource_type\":\"image\"},{\"url\":\"https:\\/\\/res.cloudinary.com\\/dmkoc4lis\\/image\\/upload\\/v1772438963\\/uploads\\/posts\\/itealftes8vmbrh41nji.jpg\",\"public_id\":\"uploads\\/posts\\/itealftes8vmbrh41nji\",\"resource_type\":\"image\"},{\"url\":\"https:\\/\\/res.cloudinary.com\\/dmkoc4lis\\/image\\/upload\\/v1772438965\\/uploads\\/posts\\/ssfhnjvyitc0zeh4odqe.jpg\",\"public_id\":\"uploads\\/posts\\/ssfhnjvyitc0zeh4odqe\",\"resource_type\":\"image\"},{\"url\":\"https:\\/\\/res.cloudinary.com\\/dmkoc4lis\\/image\\/upload\\/v1772438966\\/uploads\\/posts\\/mnkhbxapsshwmj2cgz91.jpg\",\"public_id\":\"uploads\\/posts\\/mnkhbxapsshwmj2cgz91\",\"resource_type\":\"image\"},{\"url\":\"https:\\/\\/res.cloudinary.com\\/dmkoc4lis\\/image\\/upload\\/v1772438971\\/uploads\\/posts\\/ynd84gcohaeoab7t1by8.png\",\"public_id\":\"uploads\\/posts\\/ynd84gcohaeoab7t1by8\",\"resource_type\":\"image\"},{\"url\":\"https:\\/\\/res.cloudinary.com\\/dmkoc4lis\\/image\\/upload\\/v1772438972\\/uploads\\/posts\\/ltmolo42mifoutmhu9cl.png\",\"public_id\":\"uploads\\/posts\\/ltmolo42mifoutmhu9cl\",\"resource_type\":\"image\"},{\"url\":\"https:\\/\\/res.cloudinary.com\\/dmkoc4lis\\/image\\/upload\\/v1772438974\\/uploads\\/posts\\/q4yzlxfbsbepwe025ucq.jpg\",\"public_id\":\"uploads\\/posts\\/q4yzlxfbsbepwe025ucq\",\"resource_type\":\"image\"},{\"url\":\"https:\\/\\/res.cloudinary.com\\/dmkoc4lis\\/image\\/upload\\/v1772438974\\/uploads\\/posts\\/a2urshxvnrrwwpqu8gcd.png\",\"public_id\":\"uploads\\/posts\\/a2urshxvnrrwwpqu8gcd\",\"resource_type\":\"image\"},{\"url\":\"https:\\/\\/res.cloudinary.com\\/dmkoc4lis\\/image\\/upload\\/v1772438975\\/uploads\\/posts\\/kf2qsybj7kma8fxuuksu.png\",\"public_id\":\"uploads\\/posts\\/kf2qsybj7kma8fxuuksu\",\"resource_type\":\"image\"},{\"url\":\"https:\\/\\/res.cloudinary.com\\/dmkoc4lis\\/image\\/upload\\/v1772438980\\/uploads\\/posts\\/kcxfq10y8ji6eoammm5s.png\",\"public_id\":\"uploads\\/posts\\/kcxfq10y8ji6eoammm5s\",\"resource_type\":\"image\"}]', 'post', NULL),
(75, 9, '', 'video', 'public', NULL, '2026-02-28 15:08:32', NULL, NULL, '[{\"url\":\"https:\\/\\/res.cloudinary.com\\/dmkoc4lis\\/video\\/upload\\/v1772291311\\/uploads\\/posts\\/pdrnjesirp1tydsk5bzk.mp4\",\"public_id\":\"uploads\\/posts\\/pdrnjesirp1tydsk5bzk\",\"resource_type\":\"video\"}]', 'post', NULL),
(76, 13, 'Undead', 'text', 'public', NULL, '2026-02-28 21:29:41', NULL, NULL, NULL, 'post', NULL),
(78, 18, 'ssaD', 'text', 'public', NULL, '2026-03-01 15:20:19', NULL, NULL, NULL, 'post', NULL),
(79, 18, '', 'image', 'public', NULL, '2026-03-02 06:17:20', NULL, NULL, '[{\"url\":\"https:\\/\\/res.cloudinary.com\\/dmkoc4lis\\/image\\/upload\\/v1772432239\\/uploads\\/posts\\/zsdnhwootixn1oguf9gz.jpg\",\"public_id\":\"uploads\\/posts\\/zsdnhwootixn1oguf9gz\",\"resource_type\":\"image\"}]', 'post', NULL),
(80, 8, '', 'image', 'public', NULL, '2026-03-02 08:37:33', NULL, NULL, '[{\"url\":\"https:\\/\\/res.cloudinary.com\\/dmkoc4lis\\/image\\/upload\\/v1772440651\\/uploads\\/posts\\/ytopra6lyjzalpkyrdcu.png\",\"public_id\":\"uploads\\/posts\\/ytopra6lyjzalpkyrdcu\",\"resource_type\":\"image\"},{\"url\":\"https:\\/\\/res.cloudinary.com\\/dmkoc4lis\\/image\\/upload\\/v1772440652\\/uploads\\/posts\\/cui2xppdgeu4yla4uilm.jpg\",\"public_id\":\"uploads\\/posts\\/cui2xppdgeu4yla4uilm\",\"resource_type\":\"image\"},{\"url\":\"https:\\/\\/res.cloudinary.com\\/dmkoc4lis\\/image\\/upload\\/v1772440653\\/uploads\\/posts\\/irkcpxqpjfca967toshr.jpg\",\"public_id\":\"uploads\\/posts\\/irkcpxqpjfca967toshr\",\"resource_type\":\"image\"}]', 'post', NULL),
(81, 8, '', 'video', 'public', NULL, '2026-03-02 09:06:05', '2026-03-02 09:28:16', NULL, '[{\"url\":\"https:\\/\\/res.cloudinary.com\\/dmkoc4lis\\/video\\/upload\\/v1772442356\\/uploads\\/posts\\/soqp9dm8gxfztueycb4n.mp4\",\"public_id\":\"uploads\\/posts\\/soqp9dm8gxfztueycb4n\",\"resource_type\":\"video\"},{\"url\":\"https:\\/\\/res.cloudinary.com\\/dmkoc4lis\\/video\\/upload\\/v1772442364\\/uploads\\/posts\\/m7itymplmo2huyqbgzfw.mp4\",\"public_id\":\"uploads\\/posts\\/m7itymplmo2huyqbgzfw\",\"resource_type\":\"video\"},{\"url\":\"https:\\/\\/res.cloudinary.com\\/dmkoc4lis\\/video\\/upload\\/v1772443276\\/uploads\\/posts\\/mw6kzzxfmjnvqdvysdnw.mp4\",\"public_id\":\"uploads\\/posts\\/mw6kzzxfmjnvqdvysdnw\",\"resource_type\":\"video\"},{\"url\":\"https:\\/\\/res.cloudinary.com\\/dmkoc4lis\\/image\\/upload\\/v1772443278\\/uploads\\/posts\\/xmltqmb5zjfdv5fnrln2.jpg\",\"public_id\":\"uploads\\/posts\\/xmltqmb5zjfdv5fnrln2\",\"resource_type\":\"image\"},{\"url\":\"https:\\/\\/res.cloudinary.com\\/dmkoc4lis\\/image\\/upload\\/v1772443658\\/uploads\\/posts\\/nooyrr8v6xlr9tnguybh.png\",\"public_id\":\"uploads\\/posts\\/nooyrr8v6xlr9tnguybh\",\"resource_type\":\"image\"},{\"url\":\"https:\\/\\/res.cloudinary.com\\/dmkoc4lis\\/video\\/upload\\/v1772443692\\/uploads\\/posts\\/p28vecfl2ct9i9oury3k.mp4\",\"public_id\":\"uploads\\/posts\\/p28vecfl2ct9i9oury3k\",\"resource_type\":\"video\"}]', 'post', NULL),
(82, 8, '', 'image', 'public', 2, '2026-03-02 10:27:33', NULL, NULL, '[\"https://res.cloudinary.com/dmkoc4lis/image/upload/v1772129042/post_6970d9e1cdb72_mku51y.jpg\"]', 'post', NULL),
(83, 8, '', 'text', 'followers', 79, '2026-03-02 11:00:14', NULL, NULL, NULL, 'post', NULL),
(84, 8, '', 'text', 'public', 83, '2026-03-02 11:00:34', NULL, NULL, NULL, 'post', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `reporter_id` int(11) NOT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('pending','resolved','dismissed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `resolved_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stories`
--

CREATE TABLE `stories` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `media_type` enum('image','video') NOT NULL,
  `media_url` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stories`
--

INSERT INTO `stories` (`id`, `user_id`, `media_type`, `media_url`, `created_at`) VALUES
(1, 2, 'image', 'https://res.cloudinary.com/dmkoc4lis/image/upload/v1772134273/story_697f69077fc1d_yhdfjw.jpg', '2026-02-01 14:53:59'),
(2, 2, 'image', 'https://res.cloudinary.com/dmkoc4lis/image/upload/v1772134273/story_697f71f3de859_umngsm.jpg', '2026-02-01 15:32:03'),
(3, 8, 'image', 'https://res.cloudinary.com/dmkoc4lis/image/upload/v1772134277/story_6999d2225b40d_alkitm.png', '2026-02-21 15:41:22'),
(4, 8, 'image', 'https://res.cloudinary.com/dmkoc4lis/image/upload/v1772134275/story_6999d232ad6a5_w6mgwg.jpg', '2026-02-21 15:41:38'),
(5, 9, 'image', 'https://res.cloudinary.com/dmkoc4lis/image/upload/v1772134274/story_699a2d88709cd_qvbi4x.png', '2026-02-21 22:11:20'),
(6, 8, 'image', '{\"url\":\"https:\\/\\/res.cloudinary.com\\/dmkoc4lis\\/image\\/upload\\/v1772235268\\/uploads\\/stories\\/kz0awm8oul0xt4oovdkw.png\",\"public_id\":\"uploads\\/stories\\/kz0awm8oul0xt4oovdkw\",\"resource_type\":\"image\"}', '2026-02-27 23:34:29'),
(7, 8, 'image', '{\"url\":\"https:\\/\\/res.cloudinary.com\\/dmkoc4lis\\/image\\/upload\\/v1772441926\\/uploads\\/stories\\/rma8ngvg8mgmp0slts7m.png\",\"public_id\":\"uploads\\/stories\\/rma8ngvg8mgmp0slts7m\",\"resource_type\":\"image\"}', '2026-03-02 08:58:47');

-- --------------------------------------------------------

--
-- Table structure for table `story_likes`
--

CREATE TABLE `story_likes` (
  `id` int(11) NOT NULL,
  `story_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `story_likes`
--

INSERT INTO `story_likes` (`id`, `story_id`, `user_id`, `created_at`) VALUES
(1, 1, 6, '2026-02-01 15:39:21'),
(2, 2, 6, '2026-02-01 15:39:24'),
(4, 1, 4, '2026-02-01 15:43:47'),
(5, 2, 4, '2026-02-01 15:43:51'),
(11, 4, 8, '2026-02-21 18:28:50'),
(14, 5, 9, '2026-02-21 22:11:24'),
(15, 6, 8, '2026-02-27 23:34:36');

-- --------------------------------------------------------

--
-- Table structure for table `story_views`
--

CREATE TABLE `story_views` (
  `id` int(11) NOT NULL,
  `story_id` int(11) NOT NULL,
  `viewer_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `story_views`
--

INSERT INTO `story_views` (`id`, `story_id`, `viewer_id`, `created_at`) VALUES
(1, 1, 2, '2026-02-01 15:31:05'),
(3, 2, 2, '2026-02-01 15:32:07'),
(10, 1, 6, '2026-02-01 15:39:20'),
(11, 2, 6, '2026-02-01 15:39:23'),
(15, 1, 4, '2026-02-01 15:43:46'),
(16, 2, 4, '2026-02-01 15:43:50'),
(34, 3, 8, '2026-02-21 15:41:24'),
(37, 4, 8, '2026-02-21 15:41:42'),
(60, 5, 9, '2026-02-21 22:11:22'),
(61, 6, 8, '2026-02-27 23:34:32'),
(63, 7, 8, '2026-03-02 08:58:55');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `avatar_id` varchar(255) DEFAULT NULL,
  `status` enum('active','suspended') DEFAULT 'active',
  `warnings` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `failed_login_attempts` int(11) DEFAULT 0,
  `lock_level` tinyint(1) DEFAULT 0,
  `lock_until` datetime DEFAULT NULL,
  `cover_photo` varchar(255) DEFAULT NULL,
  `account_type` enum('student','faculty','admin') DEFAULT 'student',
  `warning_reasons` text DEFAULT NULL,
  `banned_until` datetime DEFAULT NULL,
  `ban_reason` text DEFAULT NULL,
  `new_user_guide_dismissed` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `name`, `bio`, `avatar`, `avatar_id`, `status`, `warnings`, `created_at`, `failed_login_attempts`, `lock_level`, `lock_until`, `cover_photo`, `account_type`, `warning_reasons`, `banned_until`, `ban_reason`, `new_user_guide_dismissed`) VALUES
(1, 'admin', 'admin@localhost', '$2y$10$cjH8BQCmB5rNlKAtaQQuhO.4oWoKjGgZsLx1wAqTgzaX9m0kDOS8u', 'Datamex Admin', '', 'https://res.cloudinary.com/dmkoc4lis/image/upload/v1772134168/avatar_6970e29df232e_oyccuf.jpg', '{\"public_id\":\"avatar_6970e29df232e_oyccuf\":\"image\"}', 'active', 0, '2026-01-21 12:55:42', 0, 0, NULL, 'uploads/covers/cover_6970e2bad0fb1.jpg', 'admin', NULL, NULL, NULL, 0),
(2, '12345678', 'riveroardrewangeles@gmail.com', '$2y$10$RFFNXsLPvyvQcve28Qw0C.F0PpEIVf2/3OEtriZ1BXeAzurzRY7CK', 'Ardrew Castillo Angeles', '', 'https://res.cloudinary.com/dmkoc4lis/image/upload/v1772134167/avatar_6970d5f70dbde_jpganz.jpg', '', '', 6, '2026-01-21 13:02:43', 0, 0, NULL, 'uploads/covers/cover_6970d9fa75c60.jpg', 'student', '[{\"reason\":\"wala lang\",\"admin_id\":1,\"timestamp\":\"2026-01-27 06:00:06\"},{\"reason\":\"wala lang\",\"admin_id\":1,\"timestamp\":\"2026-01-27 06:14:19\"},{\"reason\":\"wala lang\",\"admin_id\":1,\"timestamp\":\"2026-01-27 07:02:32\"},{\"reason\":\"tanginamo\",\"admin_id\":1,\"timestamp\":\"2026-01-28 01:05:11\"},{\"reason\":\"nag ban eh\",\"admin_id\":1,\"timestamp\":\"2026-01-28 02:19:21\"},{\"reason\":\"nagmura ka kupal\",\"admin_id\":1,\"timestamp\":\"2026-02-02 03:39:53\"}]', '2026-02-21 17:47:19', 'hey', 0),
(3, '123456', 'felixardoangeles1963@gmail.com', '$2y$10$am4W3Y9evP5EsRkQMpo9n.wJRDK5O5ZH6nWSJfAWPk/M1v/DVRUyW', 'felixardoangeles', '', 'assets/images/default-avatar.png', '', 'active', 0, '2026-01-21 14:33:20', 0, 0, NULL, NULL, 'student', NULL, NULL, NULL, 0),
(4, '1233', 'zedrix@gmail.com', '$2y$10$Rgnr/x5Rp47EhzVxWmdWw.JQgCCqDJ59Iv/EPtcNVZcYgQVvQQaQS', 'Aira Mae Sueno', '', 'https://res.cloudinary.com/dmkoc4lis/image/upload/v1772134168/avatar_6970e7a6f2f1c_dspyri.jpg', '', 'active', 0, '2026-01-21 14:40:05', 0, 0, NULL, NULL, 'student', NULL, NULL, NULL, 0),
(5, '1234567', 'peviwix223@ncien.com', '$2y$10$JTupDh5u2LJ0WpYxlbBsiukbmozBNaYGMCgSAdmM0GuL5kClcLRWe', 'lalamo sobra', '', 'assets/images/default-avatar.png', '', 'active', 0, '2026-01-27 04:36:43', 0, 0, NULL, NULL, 'student', NULL, NULL, NULL, 0),
(6, '123123123', 'rizalinaangeles@gmail.com', '$2y$10$AH07e0vZhUZayFcxM4GoBeniblWVVPrNpTsdtzFAt7K.ljx5OCeAG', 'FACULTY', '', 'https://res.cloudinary.com/dmkoc4lis/image/upload/v1772134170/avatar_6979549578d86_mgofyh.jpg', '', 'active', 0, '2026-01-27 06:20:46', 0, 0, NULL, NULL, 'student', NULL, NULL, NULL, 0),
(7, '123123123123', 'ryananthonyterrado15@gmail.com', '$2y$10$rraRTtGFloqgtuF1UrZ2Oe6nkL2.YpaW3XobwJ8tybtQwKHVmr1f2', 'ryan terrado', '', 'assets/images/default-avatar.png', '', 'active', 0, '2026-01-27 07:59:43', 0, 0, NULL, NULL, 'student', NULL, NULL, NULL, 0),
(8, '18870', 'mikaellegabriel68@gmail.com', '$2y$10$pth9MQ1n9qvCe7auCcFGw.0URpX71SEenW4bw2cSNBrxTOxsZXT.K', 'Mikaelle Angelo A. Gabriel', 'I want Chen', 'https://res.cloudinary.com/dmkoc4lis/image/upload/v1772243937/uploads/avatars/sdvro9dviwvqzxr8wc2o.png', '{\"public_id\":\"uploads\\/avatars\\/sdvro9dviwvqzxr8wc2o\",\"resource_type\":\"image\"}', 'active', 1, '2026-02-21 08:22:21', 0, 0, NULL, 'uploads/covers/cover_69a5512576d4c.PNG', 'student', '[{\"reason\":\"This bad\",\"admin_id\":9,\"timestamp\":\"2026-02-21 22:38:41\"}]', NULL, NULL, 0),
(9, '54241145', 'apogiko722@gmail.com', '$2y$10$m30Z4VL32Srd7TFe7muZG.zoMWMfGjLF967G1BARnuDR11eWZS7U2', 'Gabriel', '', 'https://res.cloudinary.com/dmkoc4lis/image/upload/v1772246311/uploads/avatars/hmmpp9yrmkgvpose7pxf.jpg', '{\"public_id\":\"uploads\\/avatars\\/hmmpp9yrmkgvpose7pxf\",\"resource_type\":\"image\"}', 'active', 0, '2026-02-21 15:46:22', 0, 0, NULL, NULL, 'admin', NULL, NULL, NULL, 0),
(13, '1234354', 'nobaradelacrus@gmail.com', '$2y$10$85aAWqIxaWyUfu5CXI/z.OPB8H9tXs50Sk0Os7M76hxqLlod06al2', 'Nobara', '', 'assets/images/default-avatar.png', '', 'active', 0, '2026-02-21 21:54:54', 0, 0, NULL, NULL, 'faculty', NULL, NULL, NULL, 0),
(15, '00000', 'datamexcollegeofsaintadeline0@gmail.com', '$2y$10$D75yOKtNwCaw3ht8/aCZiOvJzlqkZQZQI2OEOcGtcYhVdyn5rAIvq', 'ADMIN', '', 'https://res.cloudinary.com/dmkoc4lis/image/upload/v1772318475/uploads/avatars/ufekyfwmcho6nm7io3l7.png', '{\"public_id\":\"uploads\\/avatars\\/ufekyfwmcho6nm7io3l7\",\"resource_type\":\"image\"}', 'active', 0, '2026-02-28 22:26:57', 0, 0, NULL, NULL, 'admin', NULL, NULL, NULL, 0),
(18, '0875', 'benpogi747@gmail.com', '$2y$10$Q/.V1kkDHmpCMGbBfXp59.4XD/Rr7PBTkqlZJP.a07.7mwhqezd0e', 'Ben', 'Undying', 'assets/images/default-avatar.png', NULL, 'active', 0, '2026-03-01 09:38:56', 0, 0, NULL, NULL, 'student', NULL, NULL, NULL, 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accounts`
--
ALTER TABLE `accounts`
  ADD PRIMARY KEY (`profileId`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `admin_logs`
--
ALTER TABLE `admin_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `post_id` (`post_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `email_verification`
--
ALTER TABLE `email_verification`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indexes for table `follows`
--
ALTER TABLE `follows`
  ADD PRIMARY KEY (`follower_id`,`followed_id`),
  ADD KEY `followed_id` (`followed_id`);

--
-- Indexes for table `likes`
--
ALTER TABLE `likes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_like` (`post_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`);

--
-- Indexes for table `notes`
--
ALTER TABLE `notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `note_likes`
--
ALTER TABLE `note_likes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_note_like` (`note_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `from_user_id` (`from_user_id`),
  ADD KEY `post_id` (`post_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_token` (`token`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `posts`
--
ALTER TABLE `posts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `reference_post` (`reference_post`);

--
-- Indexes for table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `post_id` (`post_id`),
  ADD KEY `reporter_id` (`reporter_id`);

--
-- Indexes for table `stories`
--
ALTER TABLE `stories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_stories_created` (`created_at`);

--
-- Indexes for table `story_likes`
--
ALTER TABLE `story_likes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_story_like` (`story_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `story_views`
--
ALTER TABLE `story_views`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_view` (`story_id`,`viewer_id`),
  ADD KEY `viewer_id` (`viewer_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accounts`
--
ALTER TABLE `accounts`
  MODIFY `profileId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `admin_logs`
--
ALTER TABLE `admin_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `comments`
--
ALTER TABLE `comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `email_verification`
--
ALTER TABLE `email_verification`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `likes`
--
ALTER TABLE `likes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notes`
--
ALTER TABLE `notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `note_likes`
--
ALTER TABLE `note_likes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=179;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `posts`
--
ALTER TABLE `posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=85;

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stories`
--
ALTER TABLE `stories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `story_likes`
--
ALTER TABLE `story_likes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `story_views`
--
ALTER TABLE `story_views`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_logs`
--
ALTER TABLE `admin_logs`
  ADD CONSTRAINT `admin_logs_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `comments`
--
ALTER TABLE `comments`
  ADD CONSTRAINT `comments_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `follows`
--
ALTER TABLE `follows`
  ADD CONSTRAINT `follows_ibfk_1` FOREIGN KEY (`follower_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `follows_ibfk_2` FOREIGN KEY (`followed_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `likes`
--
ALTER TABLE `likes`
  ADD CONSTRAINT `likes_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `likes_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notes`
--
ALTER TABLE `notes`
  ADD CONSTRAINT `notes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `note_likes`
--
ALTER TABLE `note_likes`
  ADD CONSTRAINT `note_likes_ibfk_1` FOREIGN KEY (`note_id`) REFERENCES `notes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `note_likes_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`from_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `notifications_ibfk_3` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `posts`
--
ALTER TABLE `posts`
  ADD CONSTRAINT `posts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `posts_ibfk_2` FOREIGN KEY (`reference_post`) REFERENCES `posts` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `reports`
--
ALTER TABLE `reports`
  ADD CONSTRAINT `reports_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reports_ibfk_2` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `stories`
--
ALTER TABLE `stories`
  ADD CONSTRAINT `stories_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `story_likes`
--
ALTER TABLE `story_likes`
  ADD CONSTRAINT `story_likes_ibfk_1` FOREIGN KEY (`story_id`) REFERENCES `stories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `story_likes_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `story_views`
--
ALTER TABLE `story_views`
  ADD CONSTRAINT `story_views_ibfk_1` FOREIGN KEY (`story_id`) REFERENCES `stories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `story_views_ibfk_2` FOREIGN KEY (`viewer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
