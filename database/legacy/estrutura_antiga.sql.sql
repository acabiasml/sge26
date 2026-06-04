-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Tempo de geração: 31/05/2026 às 04:37
-- Versão do servidor: 11.8.6-MariaDB-log
-- Versão do PHP: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `u810745753_beaba`
--
CREATE DATABASE IF NOT EXISTS `u810745753_beaba` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `u810745753_beaba`;

-- --------------------------------------------------------

--
-- Estrutura para tabela `areas`
--

CREATE TABLE IF NOT EXISTS `areas` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `avisos`
--

CREATE TABLE IF NOT EXISTS `avisos` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `dia` date DEFAULT NULL,
  `aviso` varchar(255) DEFAULT NULL,
  `escola` int(10) UNSIGNED DEFAULT NULL,
  `enviadopor` int(10) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `avisos_escola_foreign` (`escola`),
  KEY `avisos_enviadopor_foreign` (`enviadopor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `calendarios`
--

CREATE TABLE IF NOT EXISTS `calendarios` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) DEFAULT NULL,
  `ano` smallint(6) DEFAULT NULL,
  `escolas_id` int(10) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `calendarios_escolas_id_foreign` (`escolas_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `componentes`
--

CREATE TABLE IF NOT EXISTS `componentes` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) DEFAULT NULL,
  `horas` varchar(255) DEFAULT NULL,
  `area_id` int(10) UNSIGNED DEFAULT NULL,
  `cursos_id` int(10) UNSIGNED DEFAULT NULL,
  `professor` int(10) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `componentes_area_id_foreign` (`area_id`),
  KEY `componentes_cursos_id_foreign` (`cursos_id`),
  KEY `componentes_professor_foreign` (`professor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `cursos`
--

CREATE TABLE IF NOT EXISTS `cursos` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) DEFAULT NULL,
  `status` varchar(255) DEFAULT NULL,
  `modalidade` varchar(255) DEFAULT NULL,
  `calendarios_id` int(10) UNSIGNED DEFAULT NULL,
  `inicio` int(10) UNSIGNED DEFAULT NULL,
  `fim` int(10) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cursos_calendarios_id_foreign` (`calendarios_id`),
  KEY `cursos_inicio_foreign` (`inicio`),
  KEY `cursos_fim_foreign` (`fim`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `diarios`
--

CREATE TABLE IF NOT EXISTS `diarios` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `data` date DEFAULT NULL,
  `conteudo` varchar(255) DEFAULT NULL,
  `componentes_id` int(10) UNSIGNED DEFAULT NULL,
  `geminada` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `diarios_componentes_id_foreign` (`componentes_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `escolas`
--

CREATE TABLE IF NOT EXISTS `escolas` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) DEFAULT NULL,
  `fundacao` date DEFAULT NULL,
  `info` varchar(255) DEFAULT NULL,
  `razao` varchar(255) DEFAULT NULL,
  `cnpj` varchar(255) DEFAULT NULL,
  `telefone` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `site` varchar(255) DEFAULT NULL,
  `endereco` varchar(255) DEFAULT NULL,
  `bairro` varchar(255) DEFAULT NULL,
  `numero` varchar(255) DEFAULT NULL,
  `cidade` varchar(255) DEFAULT NULL,
  `estado` varchar(255) DEFAULT NULL,
  `cep` varchar(255) DEFAULT NULL,
  `diretor` int(10) UNSIGNED DEFAULT NULL,
  `coordenador` int(10) UNSIGNED DEFAULT NULL,
  `secretario` int(10) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `escolas_diretor_foreign` (`diretor`),
  KEY `escolas_coordenador_foreign` (`coordenador`),
  KEY `escolas_secretario_foreign` (`secretario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `frequencias`
--

CREATE TABLE IF NOT EXISTS `frequencias` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `presenca` varchar(45) DEFAULT NULL,
  `diarios_id` int(10) UNSIGNED DEFAULT NULL,
  `users_id` int(10) UNSIGNED DEFAULT NULL,
  `turmas_id` int(10) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `frequencias_diarios_id_foreign` (`diarios_id`),
  KEY `frequencias_users_id_foreign` (`users_id`),
  KEY `frequencias_turmas_id_foreign` (`turmas_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `horarios`
--

CREATE TABLE IF NOT EXISTS `horarios` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `aulas_semana` varchar(255) DEFAULT NULL,
  `dias_semana` varchar(255) DEFAULT NULL,
  `inicio` date DEFAULT NULL,
  `componentes_id` int(10) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `horarios_componentes_id_foreign` (`componentes_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `medias`
--

CREATE TABLE IF NOT EXISTS `medias` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nota` varchar(255) DEFAULT NULL,
  `componentes_id` int(10) UNSIGNED DEFAULT NULL,
  `users_id` int(10) UNSIGNED DEFAULT NULL,
  `periodos_id` int(10) UNSIGNED DEFAULT NULL,
  `nota1` decimal(5,2) DEFAULT NULL,
  `nota2` decimal(5,2) DEFAULT NULL,
  `substitutiva` decimal(5,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `medias_componentes_id_foreign` (`componentes_id`),
  KEY `medias_users_id_foreign` (`users_id`),
  KEY `medias_periodos_id_foreign` (`periodos_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `migrations`
--

CREATE TABLE IF NOT EXISTS `migrations` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `periodos`
--

CREATE TABLE IF NOT EXISTS `periodos` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) DEFAULT NULL,
  `inicio` date DEFAULT NULL,
  `fim` date DEFAULT NULL,
  `calendarios_id` int(10) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `periodos_calendarios_id_foreign` (`calendarios_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `personal_access_tokens`
--

CREATE TABLE IF NOT EXISTS `personal_access_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `turmas`
--

CREATE TABLE IF NOT EXISTS `turmas` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `datamatricula` date DEFAULT NULL,
  `datatransf` date DEFAULT NULL,
  `status` varchar(255) DEFAULT NULL,
  `tipo` varchar(255) DEFAULT NULL,
  `cursos_id` int(10) UNSIGNED DEFAULT NULL,
  `users_id` int(10) UNSIGNED DEFAULT NULL,
  `usermatricula` int(10) UNSIGNED DEFAULT NULL,
  `usertransf` int(10) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `turmas_cursos_id_foreign` (`cursos_id`),
  KEY `turmas_users_id_foreign` (`users_id`),
  KEY `turmas_usermatricula_foreign` (`usermatricula`),
  KEY `turmas_usertransf_foreign` (`usertransf`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `users`
--

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `codigo` varchar(255) DEFAULT NULL,
  `arquivado` varchar(255) DEFAULT NULL,
  `tipo` varchar(255) DEFAULT NULL,
  `inep` varchar(255) DEFAULT NULL,
  `nomesocial` varchar(255) DEFAULT NULL,
  `nascimento` date DEFAULT NULL,
  `sexo` varchar(255) DEFAULT NULL,
  `cor` varchar(255) DEFAULT NULL,
  `gemeo` varchar(255) DEFAULT NULL,
  `genitora` varchar(255) DEFAULT NULL,
  `genitor` varchar(255) DEFAULT NULL,
  `responsavel` varchar(255) DEFAULT NULL,
  `responcpf` varchar(255) DEFAULT NULL,
  `respontel1` varchar(255) DEFAULT NULL,
  `respontel2` varchar(255) DEFAULT NULL,
  `nacionalidade` varchar(255) DEFAULT NULL,
  `naturalidade` varchar(255) DEFAULT NULL,
  `naturaif` varchar(255) DEFAULT NULL,
  `identidade` varchar(255) DEFAULT NULL,
  `identemissor` varchar(255) DEFAULT NULL,
  `identuf` varchar(255) DEFAULT NULL,
  `identexpedicao` varchar(255) DEFAULT NULL,
  `cpf` varchar(255) DEFAULT NULL,
  `docextrangeiro` varchar(255) DEFAULT NULL,
  `certidao` varchar(255) DEFAULT NULL,
  `certifolha` varchar(255) DEFAULT NULL,
  `certilivro` varchar(255) DEFAULT NULL,
  `certiemissao` varchar(255) DEFAULT NULL,
  `titulo` varchar(255) DEFAULT NULL,
  `titulozona` varchar(255) DEFAULT NULL,
  `titulosessao` varchar(255) DEFAULT NULL,
  `titulouf` varchar(255) DEFAULT NULL,
  `docmilitar` varchar(255) DEFAULT NULL,
  `endereco` varchar(255) DEFAULT NULL,
  `endnumero` varchar(255) DEFAULT NULL,
  `endbairro` varchar(255) DEFAULT NULL,
  `endcidade` varchar(255) DEFAULT NULL,
  `endcomplemento` varchar(255) DEFAULT NULL,
  `endcep` varchar(255) DEFAULT NULL,
  `enduf` varchar(255) DEFAULT NULL,
  `telefone1` varchar(255) DEFAULT NULL,
  `telefone2` varchar(255) DEFAULT NULL,
  `cartaosus` varchar(255) DEFAULT NULL,
  `tiposangue` varchar(255) DEFAULT NULL,
  `nutricionais` varchar(255) DEFAULT NULL,
  `nis` varchar(255) DEFAULT NULL,
  `carteiratrab` varchar(255) DEFAULT NULL,
  `habilitacao` varchar(255) DEFAULT NULL,
  `habilcategoria` varchar(255) DEFAULT NULL,
  `habilvalidade` varchar(255) DEFAULT NULL,
  `banco` varchar(255) DEFAULT NULL,
  `agencia` varchar(255) DEFAULT NULL,
  `conta` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email_UNIQUE` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `avisos`
--
ALTER TABLE `avisos`
  ADD CONSTRAINT `avisos_enviadopor_foreign` FOREIGN KEY (`enviadopor`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `avisos_escola_foreign` FOREIGN KEY (`escola`) REFERENCES `escolas` (`id`);

--
-- Restrições para tabelas `calendarios`
--
ALTER TABLE `calendarios`
  ADD CONSTRAINT `calendarios_escolas_id_foreign` FOREIGN KEY (`escolas_id`) REFERENCES `escolas` (`id`);

--
-- Restrições para tabelas `componentes`
--
ALTER TABLE `componentes`
  ADD CONSTRAINT `componentes_area_id_foreign` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`),
  ADD CONSTRAINT `componentes_cursos_id_foreign` FOREIGN KEY (`cursos_id`) REFERENCES `cursos` (`id`),
  ADD CONSTRAINT `componentes_professor_foreign` FOREIGN KEY (`professor`) REFERENCES `users` (`id`);

--
-- Restrições para tabelas `cursos`
--
ALTER TABLE `cursos`
  ADD CONSTRAINT `cursos_calendarios_id_foreign` FOREIGN KEY (`calendarios_id`) REFERENCES `calendarios` (`id`),
  ADD CONSTRAINT `cursos_fim_foreign` FOREIGN KEY (`fim`) REFERENCES `periodos` (`id`),
  ADD CONSTRAINT `cursos_inicio_foreign` FOREIGN KEY (`inicio`) REFERENCES `periodos` (`id`);

--
-- Restrições para tabelas `diarios`
--
ALTER TABLE `diarios`
  ADD CONSTRAINT `diarios_componentes_id_foreign` FOREIGN KEY (`componentes_id`) REFERENCES `componentes` (`id`);

--
-- Restrições para tabelas `escolas`
--
ALTER TABLE `escolas`
  ADD CONSTRAINT `escolas_coordenador_foreign` FOREIGN KEY (`coordenador`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `escolas_diretor_foreign` FOREIGN KEY (`diretor`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `escolas_secretario_foreign` FOREIGN KEY (`secretario`) REFERENCES `users` (`id`);

--
-- Restrições para tabelas `frequencias`
--
ALTER TABLE `frequencias`
  ADD CONSTRAINT `FK_id_diarios` FOREIGN KEY (`diarios_id`) REFERENCES `diarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `FK_id_turmas` FOREIGN KEY (`turmas_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `FK_id_users` FOREIGN KEY (`users_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `frequencias_diarios_id_foreign` FOREIGN KEY (`diarios_id`) REFERENCES `diarios` (`id`),
  ADD CONSTRAINT `frequencias_turmas_id_foreign` FOREIGN KEY (`turmas_id`) REFERENCES `turmas` (`id`),
  ADD CONSTRAINT `frequencias_users_id_foreign` FOREIGN KEY (`users_id`) REFERENCES `users` (`id`);

--
-- Restrições para tabelas `horarios`
--
ALTER TABLE `horarios`
  ADD CONSTRAINT `horarios_componentes_id_foreign` FOREIGN KEY (`componentes_id`) REFERENCES `componentes` (`id`);

--
-- Restrições para tabelas `medias`
--
ALTER TABLE `medias`
  ADD CONSTRAINT `medias_componentes_id_foreign` FOREIGN KEY (`componentes_id`) REFERENCES `componentes` (`id`),
  ADD CONSTRAINT `medias_periodos_id_foreign` FOREIGN KEY (`periodos_id`) REFERENCES `periodos` (`id`),
  ADD CONSTRAINT `medias_users_id_foreign` FOREIGN KEY (`users_id`) REFERENCES `users` (`id`);

--
-- Restrições para tabelas `periodos`
--
ALTER TABLE `periodos`
  ADD CONSTRAINT `periodos_calendarios_id_foreign` FOREIGN KEY (`calendarios_id`) REFERENCES `calendarios` (`id`);

--
-- Restrições para tabelas `turmas`
--
ALTER TABLE `turmas`
  ADD CONSTRAINT `turmas_cursos_id_foreign` FOREIGN KEY (`cursos_id`) REFERENCES `cursos` (`id`),
  ADD CONSTRAINT `turmas_usermatricula_foreign` FOREIGN KEY (`usermatricula`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `turmas_users_id_foreign` FOREIGN KEY (`users_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `turmas_usertransf_foreign` FOREIGN KEY (`usertransf`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
