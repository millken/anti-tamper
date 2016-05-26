--
-- Database: `anti_tamper`
--

-- --------------------------------------------------------

--
-- Table structure for table `logs`
--

CREATE TABLE `logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `url` varchar(128) COLLATE utf8_bin NOT NULL,
  `url_md5` char(32) COLLATE utf8_bin NOT NULL,
  `url_body` longblob NOT NULL,
  `addtime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `url` (`url`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin ROW_FORMAT=COMPACT;

-- --------------------------------------------------------

--
-- Table structure for table `urls`
--

CREATE TABLE `urls` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `class` enum('SensitiveMonitor','keywordMonitor','TamperMonitor','TrojanMonitor') COLLATE utf8_bin DEFAULT NULL,
  `group` varchar(32) COLLATE utf8_bin NOT NULL,
  `url` varchar(128) COLLATE utf8_bin NOT NULL,
  `host` char(15) COLLATE utf8_bin DEFAULT NULL,
  `keyword` longtext COLLATE utf8_bin,
  `interval` smallint(3) NOT NULL DEFAULT '60',
  `status` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `group` (`group`) USING BTREE,
  KEY `class` (`class`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;



