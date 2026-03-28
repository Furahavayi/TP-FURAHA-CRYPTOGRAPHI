-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : sam. 28 mars 2026 à 04:28
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `chat_furaha`
--

-- --------------------------------------------------------

--
-- Structure de la table `algorithmes`
--

CREATE TABLE `algorithmes` (
  `id` int(11) NOT NULL,
  `libelle` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `algorithmes`
--

INSERT INTO `algorithmes` (`id`, `libelle`, `created_at`) VALUES
(1, 'AES-256-CBC', '2026-03-28 02:17:36'),
(2, 'ChaCha20-Poly1305', '2026-03-28 02:17:36');

-- --------------------------------------------------------

--
-- Structure de la table `message`
--

CREATE TABLE `message` (
  `id` int(11) NOT NULL,
  `contenu_chiffre` text NOT NULL,
  `date_envoi` timestamp NOT NULL DEFAULT current_timestamp(),
  `id_user` int(11) NOT NULL,
  `id_algorithme` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `message`
--

INSERT INTO `message` (`id`, `contenu_chiffre`, `date_envoi`, `id_user`, `id_algorithme`) VALUES
(1, 'WmiKaepg8/lvMe+jIr8bwuk3VKMvs/Naqv8bya6tsZa5DYl2iK4vp4kKOckS4FB2Uh6ltyFeA8oTXmUPfKTO1w==', '2026-03-28 02:56:04', 2, 1),
(2, 'Y8Jc1KDRX9v9dvtfpo6WBg+jmg/Y1IiisuBYJrt7OmtO57Ark/mhX6DcV1npKR2Cg678M8/YjAeajIB7n3ryqA==', '2026-03-28 03:02:45', 2, 2),
(3, '4aLCpHlewEOZ/kFLx1R9RxoDuitjrHZ2nvhDbX26GpJ9sKCZbQJMePYAO2vReYpG', '2026-03-28 03:12:43', 2, 1);

-- --------------------------------------------------------

--
-- Structure de la table `preferences_chiffrement`
--

CREATE TABLE `preferences_chiffrement` (
  `id` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `algorithme_prefere` varchar(50) DEFAULT 'AES-256-CBC'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `utilisateur`
--

CREATE TABLE `utilisateur` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `est_bloque` tinyint(1) DEFAULT 0,
  `est_admin` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `utilisateur`
--

INSERT INTO `utilisateur` (`id`, `username`, `password`, `est_bloque`, `est_admin`, `created_at`) VALUES
(1, 'admin', '$2y$10$YourHashHere', 0, 1, '2026-03-28 02:17:38'),
(2, 'FURAHA', '$2y$10$cbkuwKhUmgKTuIOLhv4nkuxSGsyCSzQ73XSgYhr5130GX6WRo8FK2', 0, 0, '2026-03-28 02:30:53');

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `algorithmes`
--
ALTER TABLE `algorithmes`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `message`
--
ALTER TABLE `message`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_user` (`id_user`),
  ADD KEY `id_algorithme` (`id_algorithme`);

--
-- Index pour la table `preferences_chiffrement`
--
ALTER TABLE `preferences_chiffrement`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_user` (`id_user`);

--
-- Index pour la table `utilisateur`
--
ALTER TABLE `utilisateur`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `algorithmes`
--
ALTER TABLE `algorithmes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `message`
--
ALTER TABLE `message`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `preferences_chiffrement`
--
ALTER TABLE `preferences_chiffrement`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `utilisateur`
--
ALTER TABLE `utilisateur`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `message`
--
ALTER TABLE `message`
  ADD CONSTRAINT `message_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `utilisateur` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `message_ibfk_2` FOREIGN KEY (`id_algorithme`) REFERENCES `algorithmes` (`id`);

--
-- Contraintes pour la table `preferences_chiffrement`
--
ALTER TABLE `preferences_chiffrement`
  ADD CONSTRAINT `preferences_chiffrement_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `utilisateur` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
