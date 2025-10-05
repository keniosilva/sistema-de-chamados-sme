-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Tempo de geração: 26/09/2025 às 17:58
-- Versão do servidor: 8.0.33
-- Versão do PHP: 8.2.7

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `sistema_chamados`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `chamados`
--

CREATE TABLE `chamados` (
  `id` int NOT NULL,
  `id_unidade_escolar` int DEFAULT NULL,
  `id_usuario_abertura` int NOT NULL,
  `tipo_manutencao` enum('geral','informatica','casa_da_merenda','almoxarifado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `setor_destino` enum('manutencao_geral','informatica','casa_da_merenda','almoxarifado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('aberto','em_andamento','concluido','cancelado','aguardando_recebimento') COLLATE utf8mb4_unicode_ci DEFAULT 'aberto',
  `data_abertura` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `data_fechamento` timestamp NULL DEFAULT NULL,
  `id_tecnico_responsavel` int DEFAULT NULL,
  `observacoes_tecnico` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `confirmacao_recebimento_unidade` tinyint(1) DEFAULT '0',
  `confirmacao_entrega` tinyint(1) DEFAULT '0',
  `almoxarifado_confirmacao_entrega` tinyint(1) DEFAULT '0',
  `confirmacao_servico` tinyint(1) DEFAULT '0',
  `merenda_confirmacao_entrega` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `chamados`
--

INSERT INTO `chamados` (`id`, `id_unidade_escolar`, `id_usuario_abertura`, `tipo_manutencao`, `setor_destino`, `descricao`, `status`, `data_abertura`, `data_fechamento`, `id_tecnico_responsavel`, `observacoes_tecnico`, `confirmacao_recebimento_unidade`, `confirmacao_entrega`, `almoxarifado_confirmacao_entrega`, `confirmacao_servico`, `merenda_confirmacao_entrega`) VALUES
(1, 1, 4, 'geral', 'manutencao_geral', 'Torneira quebrada', 'em_andamento', '2025-09-18 00:00:12', NULL, NULL, 'Material será solicitado ao almoxarifado', 0, 0, 0, 0, 0),
(2, 1, 4, 'informatica', 'informatica', 'Computador não liga', 'concluido', '2025-09-18 00:00:33', '2025-09-25 13:43:07', 3, '--- 22/09/2025 18:01 - Administrador ---\r\nMaquina em manutenção\r\nRealizada limpeza na memoria.', 0, 0, 0, 0, 0),
(3, 1, 4, 'informatica', 'informatica', 'Impressora não imprime', 'concluido', '2025-09-18 00:24:42', '2025-09-19 00:18:41', 7, 'Realizado reset', 0, 0, 0, 0, 0),
(4, 1, 4, 'almoxarifado', 'almoxarifado', 'Solicito 10 resmas de papel oficio', 'concluido', '2025-09-19 18:42:22', '2025-09-19 22:24:56', NULL, 'Recebido.\r\nEntregue.', 0, 0, 0, 0, 0),
(5, 1, 4, 'casa_da_merenda', 'casa_da_merenda', 'Preciso de 10kg de feijao', 'concluido', '2025-09-19 19:21:24', '2025-09-25 03:54:06', NULL, 'Entregue', 0, 0, 0, 0, 0),
(6, 1, 4, 'almoxarifado', 'almoxarifado', 'Preciso de uma lampada de led', 'concluido', '2025-09-19 19:23:24', NULL, 8, 'Entregue', 0, 0, 0, 0, 0),
(7, 2, 5, 'geral', 'manutencao_geral', 'Ventilador com defeito', 'em_andamento', '2025-09-19 21:29:13', NULL, NULL, 'Tecnico irá a unidade para reparo.', 0, 0, 0, 0, 0),
(8, 2, 5, 'informatica', 'informatica', 'Solicito kit de tintas impressora Epson', 'em_andamento', '2025-09-19 21:29:47', NULL, NULL, '', 0, 0, 0, 0, 0),
(9, NULL, 10, 'informatica', 'informatica', 'sem internet', 'aberto', '2025-09-19 23:06:55', NULL, NULL, NULL, 0, 0, 0, 0, 0),
(10, NULL, 10, 'informatica', 'informatica', 'sem internet', 'aberto', '2025-09-19 23:09:41', NULL, NULL, NULL, 0, 0, 0, 0, 0),
(11, NULL, 10, 'informatica', 'informatica', 'sem internet', 'aberto', '2025-09-19 23:12:51', NULL, NULL, NULL, 0, 0, 0, 0, 0),
(12, NULL, 12, 'informatica', 'informatica', 'sem internet', 'em_andamento', '2025-09-19 23:21:52', NULL, 7, NULL, 0, 0, 0, 0, 0),
(13, NULL, 10, 'almoxarifado', 'almoxarifado', 'Necessito de uma caixa de grampos', 'aberto', '2025-09-20 23:39:38', NULL, NULL, NULL, 0, 0, 0, 0, 0),
(14, NULL, 10, 'almoxarifado', 'almoxarifado', 'Papel toalha', 'aberto', '2025-09-22 11:21:13', NULL, NULL, NULL, 0, 0, 0, 0, 0),
(15, NULL, 10, 'almoxarifado', 'almoxarifado', 'Papel toalha', 'aberto', '2025-09-22 11:21:51', NULL, NULL, NULL, 0, 0, 0, 0, 0),
(16, NULL, 10, 'almoxarifado', 'almoxarifado', 'Necessito de papel toalha', 'concluido', '2025-09-22 11:25:44', NULL, 8, 'Entregue', 0, 0, 0, 0, 0),
(17, NULL, 10, 'geral', 'manutencao_geral', 'Troca de tomada', 'aberto', '2025-09-22 11:26:06', NULL, NULL, NULL, 0, 0, 0, 0, 0),
(18, 1, 4, 'geral', 'manutencao_geral', 'Fechadura com defeito', 'aberto', '2025-09-24 00:50:18', NULL, NULL, NULL, 0, 0, 0, 0, 0),
(19, 1, 4, 'almoxarifado', 'almoxarifado', 'Preciso de 10 resmas de papel oficio', 'concluido', '2025-09-24 01:20:04', '2025-09-24 01:42:52', NULL, 'Entregue 5 resmas', 0, 0, 0, 0, 0),
(20, 1, 4, 'almoxarifado', 'almoxarifado', 'Preciso de 10 rolos de papel higienico', 'concluido', '2025-09-24 01:51:28', '2025-09-24 01:52:05', NULL, 'Entregue 5 rolos de papel higienico', 0, 0, 0, 0, 0),
(21, 1, 4, 'almoxarifado', 'almoxarifado', 'Preciso de 20 lapis grafite', 'concluido', '2025-09-24 01:57:37', '2025-09-24 01:58:15', NULL, 'entregue 10 lapis grafite', 0, 0, 1, 0, 0),
(22, 1, 4, 'almoxarifado', 'almoxarifado', 'Preciso de 01 cola branca', 'concluido', '2025-09-24 02:05:27', '2025-09-24 02:06:08', NULL, 'entregue 01 cola branca', 0, 0, 1, 0, 0),
(23, 1, 4, 'almoxarifado', 'almoxarifado', 'Preciso de uma cadeira', 'concluido', '2025-09-24 08:47:11', '2025-09-24 08:48:14', NULL, 'entregue duas cadeiras', 0, 0, 1, 0, 0),
(24, 1, 4, 'almoxarifado', 'almoxarifado', 'Preciso de duas mesas', 'em_andamento', '2025-09-24 08:47:36', NULL, NULL, NULL, 0, 0, 0, 0, 0),
(25, 1, 4, 'almoxarifado', 'almoxarifado', 'Preciso de cola de isopor', 'concluido', '2025-09-24 08:58:56', '2025-09-24 09:01:05', NULL, '', 0, 0, 1, 0, 0),
(26, 1, 4, 'informatica', 'informatica', 'Preciso de tintas epson', 'concluido', '2025-09-24 22:58:58', '2025-09-25 02:13:39', 3, 'Entregues', 0, 0, 0, 0, 0),
(27, 1, 4, 'almoxarifado', 'almoxarifado', 'Preciso de 10 resmas de oficio', 'concluido', '2025-09-24 23:14:19', '2025-09-25 03:00:27', NULL, 'Entregue 5 resmas de oficio', 0, 0, 1, 0, 0),
(28, 1, 4, 'informatica', 'informatica', 'Preciso de um monitor', 'concluido', '2025-09-25 00:09:36', '2025-09-25 03:14:38', 3, 'Entregue o monitor', 0, 0, 0, 0, 0),
(29, 1, 4, 'almoxarifado', 'almoxarifado', 'Preciso de cola branca', 'concluido', '2025-09-25 00:15:11', '2025-09-25 03:15:38', NULL, 'entregue cola branca', 0, 0, 0, 0, 0),
(30, 1, 4, 'almoxarifado', 'almoxarifado', '01 LITRO DE ALCOOL, 06 PANO DE CHÃO, 04 FLANELAS', 'concluido', '2025-09-25 10:44:00', '2025-09-26 03:49:14', NULL, 'Entregue', 0, 0, 0, 0, 0),
(31, 1, 4, 'almoxarifado', 'almoxarifado', 'preciso de toalhas', 'concluido', '2025-09-26 01:00:59', '2025-09-26 04:01:58', NULL, 'ok', 0, 0, 0, 0, 0),
(32, 1, 4, 'almoxarifado', 'almoxarifado', 'Vassouras', 'concluido', '2025-09-26 10:58:54', '2025-09-26 10:59:31', NULL, 'Entregue', 1, 0, 0, 0, 0),
(33, 1, 4, 'almoxarifado', 'almoxarifado', 'Rodos', 'concluido', '2025-09-26 11:01:14', '2025-09-26 11:33:28', NULL, 'Entregue', 1, 0, 0, 0, 0),
(34, 1, 4, 'almoxarifado', 'almoxarifado', '15 lápis grafite ', 'concluido', '2025-09-26 11:32:29', '2025-09-26 11:33:30', NULL, 'Entegue', 1, 0, 0, 0, 0),
(35, 1, 4, 'casa_da_merenda', 'casa_da_merenda', 'Frutas', 'aguardando_recebimento', '2025-09-26 11:34:37', NULL, NULL, 'Frutas entregue', 0, 0, 0, 0, 0),
(36, 1, 4, 'casa_da_merenda', 'casa_da_merenda', '10 abacaxis', 'aguardando_recebimento', '2025-09-26 17:37:34', NULL, NULL, 'entregue', 0, 0, 0, 0, 0),
(37, 1, 4, 'casa_da_merenda', 'casa_da_merenda', 'Preciso de 5 kg de peito de frango', 'aguardando_recebimento', '2025-09-26 17:52:58', NULL, NULL, 'Entregue', 0, 0, 0, 0, 0),
(38, 1, 4, 'almoxarifado', 'almoxarifado', 'PRECISO DE 10 RESMAS\r\n', 'aguardando_recebimento', '2025-09-26 17:54:33', NULL, NULL, 'ENTREGUE', 0, 0, 0, 0, 0);

-- --------------------------------------------------------

--
-- Estrutura para tabela `oficios`
--

CREATE TABLE `oficios` (
  `id` int NOT NULL,
  `id_chamado` int NOT NULL,
  `numero_oficio` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `data_oficio` date NOT NULL,
  `conteudo_oficio` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `caminho_arquivo` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hash_validacao` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `data_criacao` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `tipo_oficio` enum('regular','entrega') COLLATE utf8mb4_unicode_ci DEFAULT 'regular'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `oficios`
--

INSERT INTO `oficios` (`id`, `id_chamado`, `numero_oficio`, `data_oficio`, `conteudo_oficio`, `caminho_arquivo`, `hash_validacao`, `data_criacao`, `tipo_oficio`) VALUES
(1, 1, 'OF-001/2025', '2025-09-17', 'PREFEITURA MUNICIPAL DE BAYEUX\nSECRETARIA MUNICIPAL DE EDUCAÇÃO\n\nUNIDADE ESCOLAR: Assis Chateaubriand\nOFÍCIO Nº: OF-001/2025\nDATA: 18/09/2025\n\nSOLICITAÇÃO DE MANUTENÇÃO - GERAL\n\nDESCRIÇÃO:\nTorneira quebrada\n\nEste ofício é válido e foi gerado automaticamente pelo Sistema de Chamados da Secretaria Municipal de Educação de Bayeux.', NULL, 'c8b94a2dcedfacf699c28c9a2f555b3a496cee7052d62a4feca86f632d63ffdc', '2025-09-18 00:00:12', 'regular'),
(2, 2, 'OF-002/2025', '2025-09-17', 'PREFEITURA MUNICIPAL DE BAYEUX\nSECRETARIA MUNICIPAL DE EDUCAÇÃO\n\nUNIDADE ESCOLAR: Assis Chateaubriand\nOFÍCIO Nº: OF-002/2025\nDATA: 18/09/2025\n\nSOLICITAÇÃO DE MANUTENÇÃO - INFORMATICA\n\nDESCRIÇÃO:\nComputador não liga\n\nEste ofício é válido e foi gerado automaticamente pelo Sistema de Chamados da Secretaria Municipal de Educação de Bayeux.', NULL, '57ec8d7709c12bbec1cabd7f4c6dc2b179ba6dbb010cc2f1142655a13037a421', '2025-09-18 00:00:33', 'regular'),
(3, 3, 'OF-003/2025', '2025-09-17', 'PREFEITURA MUNICIPAL DE BAYEUX\nSECRETARIA MUNICIPAL DE EDUCAÇÃO\n\nUNIDADE ESCOLAR: Assis Chateaubriand\nOFÍCIO Nº: OF-003/2025\nDATA: 18/09/2025\n\nSOLICITAÇÃO DE MANUTENÇÃO - INFORMATICA\n\nDESCRIÇÃO:\nImpressora não imprime\n\nEste ofício é válido e foi gerado automaticamente pelo Sistema de Chamados da Secretaria Municipal de Educação de Bayeux.', NULL, '6676eac8fe95347c13f103bf3a9c26a24e', '2025-09-18 00:24:42', 'regular'),
(4, 4, 'OF-004/2025', '2025-09-19', 'PREFEITURA MUNICIPAL DE BAYEUX\nSECRETARIA MUNICIPAL DE EDUCAÇÃO\n\nUNIDADE ESCOLAR: Assis Chateaubriand\nOFÍCIO Nº: OF-004/2025\nDATA: 19/09/2025\n\nSOLICITAÇÃO DE MANUTENÇÃO - ALMOXARIFADO\n\nDESCRIÇÃO:\nSolicito 10 resmas de papel oficio\n\nEste ofício é válido e foi gerado automaticamente pelo Sistema de Chamados da Secretaria Municipal de Educação de Bayeux.', NULL, 'd1c5f4ff850c56c895082abd53e43b203ef891ca9add7e98975419b606cc1c86', '2025-09-19 18:42:22', 'regular'),
(5, 5, 'OF-005/2025', '2025-09-19', 'PREFEITURA MUNICIPAL DE BAYEUX\nSECRETARIA MUNICIPAL DE EDUCAÇÃO\n\nUNIDADE ESCOLAR: Assis Chateaubriand\nOFÍCIO Nº: OF-005/2025\nDATA: 19/09/2025\n\nSOLICITAÇÃO DE MANUTENÇÃO - CASA_DA_MERENDA\n\nDESCRIÇÃO:\nPreciso de 10kg de feijao\n\nEste ofício é válido e foi gerado automaticamente pelo Sistema de Chamados da Secretaria Municipal de Educação de Bayeux.', NULL, 'b047d6b1d4b4434e8d5a9d727cab873e122367f237c005bad2a1e5151f5e0040', '2025-09-19 19:21:24', 'regular'),
(6, 6, 'OF-006/2025', '2025-09-19', 'PREFEITURA MUNICIPAL DE BAYEUX\nSECRETARIA MUNICIPAL DE EDUCAÇÃO\n\nORIGEM: Assis Chateaubriand\nDESTINO: ALMOXARIFADO\nOFÍCIO Nº: OF-006/2025\nDATA: 19/09/2025\n\nSOLICITAÇÃO DE SERVIÇO - ALMOXARIFADO\n\nDESCRIÇÃO:\nPreciso de uma lampada de led\n\nEste ofício é válido e foi gerado automaticamente pelo Sistema de Chamados da Secretaria Municipal de Educação de Bayeux.', NULL, '2829523d584bf3dae4b05980db31daa1db7e80c40ed58914eafaeb4eb5ee5408', '2025-09-19 19:23:24', 'regular'),
(7, 7, 'OF-007/2025', '2025-09-19', 'PREFEITURA MUNICIPAL DE BAYEUX\nSECRETARIA MUNICIPAL DE EDUCAÇÃO\n\nORIGEM: Escola Airton Ciraulo\nDESTINO: MANUTENÇÃO GERAL\nOFÍCIO Nº: OF-007/2025\nDATA: 19/09/2025\n\nSOLICITAÇÃO DE SERVIÇO - MANUTENÇÃO GERAL\n\nDESCRIÇÃO:\nVentilador com defeito\n\nEste ofício é válido e foi gerado automaticamente pelo Sistema de Chamados da Secretaria Municipal de Educação de Bayeux.', NULL, '0365256d98ed5ff11c538fe643b7e2987d686cf1a565121a42c10b17a0916a02', '2025-09-19 21:29:13', 'regular'),
(8, 8, 'OF-008/2025', '2025-09-19', 'PREFEITURA MUNICIPAL DE BAYEUX\nSECRETARIA MUNICIPAL DE EDUCAÇÃO\n\nORIGEM: Escola Airton Ciraulo\nDESTINO: INFORMÁTICA\nOFÍCIO Nº: OF-008/2025\nDATA: 19/09/2025\n\nSOLICITAÇÃO DE SERVIÇO - INFORMÁTICA\n\nDESCRIÇÃO:\nSolicito kit de tintas impressora Epson\n\nEste ofício é válido e foi gerado automaticamente pelo Sistema de Chamados da Secretaria Municipal de Educação de Bayeux.', NULL, '0f446ab2af08f5733d651566feb9e251f5f3f3bd9c41b862927e30e21ec823e4', '2025-09-19 21:29:47', 'regular'),
(9, 9, 'OF-009/2025', '2025-09-19', 'PREFEITURA MUNICIPAL DE BAYEUX\nSECRETARIA MUNICIPAL DE EDUCAÇÃO\n\nORIGEM: Secretaria de Educação\nDESTINO: INFORMÁTICA\nOFÍCIO Nº: OF-009/2025\nDATA: 19/09/2025\n\nSOLICITAÇÃO DE SERVIÇO - INFORMÁTICA\n\nDESCRIÇÃO:\nsem internet\n\nEste ofício é válido e foi gerado automaticamente pelo Sistema de Chamados da Secretaria Municipal de Educação de Bayeux.', NULL, 'bccaa5ae55fb3e83d91f21043d8d5d7f2f1a31d14eaaa4a2ae2ef6deb544e90a', '2025-09-19 23:06:55', 'regular'),
(10, 10, 'OF-010/2025', '2025-09-19', 'PREFEITURA MUNICIPAL DE BAYEUX\nSECRETARIA MUNICIPAL DE EDUCAÇÃO\n\nORIGEM: Secretaria de Educação\nDESTINO: INFORMÁTICA\nOFÍCIO Nº: OF-010/2025\nDATA: 19/09/2025\n\nSOLICITAÇÃO DE SERVIÇO - INFORMÁTICA\n\nDESCRIÇÃO:\nsem internet\n\nEste ofício é válido e foi gerado automaticamente pelo Sistema de Chamados da Secretaria Municipal de Educação de Bayeux.', NULL, '1c7c45e84188a242a2dc8bd7e4225488977ff9c14a68f646f066e1daac148609', '2025-09-19 23:09:41', 'regular'),
(11, 12, 'OF-011/2025', '2025-09-19', 'PREFEITURA MUNICIPAL DE BAYEUX\nSECRETARIA MUNICIPAL DE EDUCAÇÃO\n\nORIGEM: Secretaria de Educação\nDESTINO: INFORMÁTICA\nOFÍCIO Nº: OF-011/2025\nDATA: 19/09/2025\n\nSOLICITAÇÃO DE SERVIÇO - INFORMÁTICA\n\nDESCRIÇÃO:\nsem internet\n\nEste ofício é válido e foi gerado automaticamente pelo Sistema de Chamados da Secretaria Municipal de Educação de Bayeux.', NULL, '279d35309d83884950b0ed639ab41cce89123999b8621f09fb940be0a92587fd', '2025-09-19 23:21:52', 'regular'),
(12, 13, 'OF-012/2025', '2025-09-20', 'PREFEITURA MUNICIPAL DE BAYEUX\nSECRETARIA MUNICIPAL DE EDUCAÇÃO\n\nORIGEM: Secretaria de Educação\nDESTINO: ALMOXARIFADO\nOFÍCIO Nº: OF-012/2025\nDATA: 20/09/2025\n\nSOLICITAÇÃO DE SERVIÇO - ALMOXARIFADO\n\nDESCRIÇÃO:\nNecessito de uma caixa de grampos\n\nEste ofício é válido e foi gerado automaticamente pelo Sistema de Chamados da Secretaria Municipal de Educação de Bayeux.', NULL, '63e53a071ad103de464e9c08a8fedcae0b75dc2a5298ab7d5216c6f75d7d52d9', '2025-09-20 23:39:38', 'regular'),
(13, 14, 'OF-013/2025', '2025-09-22', 'PREFEITURA MUNICIPAL DE BAYEUX\nSECRETARIA MUNICIPAL DE EDUCAÇÃO\n\nORIGEM: Secretaria de Educação\nDESTINO: ALMOXARIFADO\nOFÍCIO Nº: OF-013/2025\nDATA: 22/09/2025\n\nSOLICITAÇÃO DE SERVIÇO - ALMOXARIFADO\n\nDESCRIÇÃO:\nPapel toalha\n\nEste ofício é válido e foi gerado automaticamente pelo Sistema de Chamados da Secretaria Municipal de Educação de Bayeux.', NULL, 'a5e6f89ccf48207dd0800ae541742c7402444a7c9497622c2c3ac83fa6751821', '2025-09-22 11:21:13', 'regular'),
(14, 15, 'OF-014/2025', '2025-09-22', 'PREFEITURA MUNICIPAL DE BAYEUX\nSECRETARIA MUNICIPAL DE EDUCAÇÃO\n\nORIGEM: Secretaria de Educação\nDESTINO: ALMOXARIFADO\nOFÍCIO Nº: OF-014/2025\nDATA: 22/09/2025\n\nSOLICITAÇÃO DE SERVIÇO - ALMOXARIFADO\n\nDESCRIÇÃO:\nPapel toalha\n\nEste ofício é válido e foi gerado automaticamente pelo Sistema de Chamados da Secretaria Municipal de Educação de Bayeux.', NULL, '2f96f497631d6f9edb6f49b02b96aed5d542f2f0ac4fafbfd152f6ad7e4856c6', '2025-09-22 11:21:51', 'regular'),
(15, 16, 'OF-015/2025', '2025-09-22', 'PREFEITURA MUNICIPAL DE BAYEUX\nSECRETARIA MUNICIPAL DE EDUCAÇÃO\n\nORIGEM: Secretaria de Educação\nDESTINO: ALMOXARIFADO\nOFÍCIO Nº: OF-015/2025\nDATA: 22/09/2025\n\nSOLICITAÇÃO DE SERVIÇO - ALMOXARIFADO\n\nDESCRIÇÃO:\nNecessito de papel toalha\n\nEste ofício é válido e foi gerado automaticamente pelo Sistema de Chamados da Secretaria Municipal de Educação de Bayeux.', NULL, 'c3e88834955a61f5b730444138f0c3b24afd457f3e619f871a1bc30ff49d001d', '2025-09-22 11:25:44', 'regular'),
(16, 17, 'OF-016/2025', '2025-09-22', 'PREFEITURA MUNICIPAL DE BAYEUX\nSECRETARIA MUNICIPAL DE EDUCAÇÃO\n\nORIGEM: Secretaria de Educação\nDESTINO: MANUTENÇÃO GERAL\nOFÍCIO Nº: OF-016/2025\nDATA: 22/09/2025\n\nSOLICITAÇÃO DE SERVIÇO - MANUTENÇÃO GERAL\n\nDESCRIÇÃO:\nTroca de tomada\n\nEste ofício é válido e foi gerado automaticamente pelo Sistema de Chamados da Secretaria Municipal de Educação de Bayeux.', NULL, 'eeef86e9fcb94b62dff8e0d63b69c255ec6ee237ec661ec4202481e332d3eb75', '2025-09-22 11:26:06', 'regular'),
(17, 18, 'OF-017/2025', '2025-09-23', 'PREFEITURA MUNICIPAL DE BAYEUX\nSECRETARIA MUNICIPAL DE EDUCAÇÃO\n\nORIGEM: EMEF Assis Chateaubriand\nDESTINO: MANUTENÇÃO GERAL\nOFÍCIO Nº: OF-017/2025\nDATA: 24/09/2025\n\nSOLICITAÇÃO DE SERVIÇO - MANUTENÇÃO GERAL\n\nDESCRIÇÃO:\nFechadura com defeito\n\nEste ofício é válido e foi gerado automaticamente pelo Sistema de Chamados da Secretaria Municipal de Educação de Bayeux.', NULL, '6519ca5e7e193771369e72d6caa58c4fc7df9d249d2e3ac0a3b0995d663f03ad', '2025-09-24 00:50:18', 'regular'),
(18, 19, 'OF-018/2025', '2025-09-23', 'PREFEITURA MUNICIPAL DE BAYEUX\nSECRETARIA MUNICIPAL DE EDUCAÇÃO\n\nORIGEM: EMEF Assis Chateaubriand\nDESTINO: ALMOXARIFADO\nOFÍCIO Nº: OF-018/2025\nDATA: 24/09/2025\n\nSOLICITAÇÃO DE SERVIÇO - ALMOXARIFADO\n\nDESCRIÇÃO:\nPreciso de 10 resmas de papel oficio\n\nEste ofício é válido e foi gerado automaticamente pelo Sistema de Chamados da Secretaria Municipal de Educação de Bayeux.', NULL, '54245d0ec4c170d1b14f69c6fb6f3472073d98e85ac7d00fd833f9efa4195f39', '2025-09-24 01:20:04', 'regular'),
(19, 20, 'OF-019/2025', '2025-09-23', 'PREFEITURA MUNICIPAL DE BAYEUX\nSECRETARIA MUNICIPAL DE EDUCAÇÃO\n\nORIGEM: EMEF Assis Chateaubriand\nDESTINO: ALMOXARIFADO\nOFÍCIO Nº: OF-019/2025\nDATA: 24/09/2025\n\nSOLICITAÇÃO DE SERVIÇO - ALMOXARIFADO\n\nDESCRIÇÃO:\nPreciso de 10 rolos de papel higienico\n\nEste ofício é válido e foi gerado automaticamente pelo Sistema de Chamados da Secretaria Municipal de Educação de Bayeux.', NULL, 'f806ccdc78bb265de6bad1e8ef648be563384f4e2925cc73232c20d83e07c664', '2025-09-24 01:51:28', 'regular'),
(20, 21, 'OF-020/2025', '2025-09-23', 'PREFEITURA MUNICIPAL DE BAYEUX\nSECRETARIA MUNICIPAL DE EDUCAÇÃO\n\nORIGEM: EMEF Assis Chateaubriand\nDESTINO: ALMOXARIFADO\nOFÍCIO Nº: OF-020/2025\nDATA: 24/09/2025\n\nSOLICITAÇÃO DE SERVIÇO - ALMOXARIFADO\n\nDESCRIÇÃO:\nPreciso de 20 lapis grafite\n\nEste ofício é válido e foi gerado automaticamente pelo Sistema de Chamados da Secretaria Municipal de Educação de Bayeux.', NULL, '6ad743e28af59692cdbd14b829b5ad6a6ea357e30d456b282d2145405f07a13c', '2025-09-24 01:57:37', 'regular'),
(21, 22, 'OF-021/2025', '2025-09-23', 'PREFEITURA MUNICIPAL DE BAYEUX\nSECRETARIA MUNICIPAL DE EDUCAÇÃO\n\nORIGEM: EMEF Assis Chateaubriand\nDESTINO: ALMOXARIFADO\nOFÍCIO Nº: OF-021/2025\nDATA: 24/09/2025\n\nSOLICITAÇÃO DE SERVIÇO - ALMOXARIFADO\n\nDESCRIÇÃO:\nPreciso de 01 cola branca\n\nEste ofício é válido e foi gerado automaticamente pelo Sistema de Chamados da Secretaria Municipal de Educação de Bayeux.', NULL, '03d322b9c407b00b010ac44a9dc4c2a0f5a089d6eb84c97547a0d91226b9fb28', '2025-09-24 02:05:27', 'regular'),
(22, 23, 'OF-022/2025', '2025-09-24', 'PREFEITURA MUNICIPAL DE BAYEUX\nSECRETARIA MUNICIPAL DE EDUCAÇÃO\n\nORIGEM: EMEF Assis Chateaubriand\nDESTINO: ALMOXARIFADO\nOFÍCIO Nº: OF-022/2025\nDATA: 24/09/2025\n\nSOLICITAÇÃO DE SERVIÇO - ALMOXARIFADO\n\nDESCRIÇÃO:\nPreciso de uma cadeira\n\nEste ofício é válido e foi gerado automaticamente pelo Sistema de Chamados da Secretaria Municipal de Educação de Bayeux.', NULL, '98fd3ba51ae9392a09f7a8a7a7f31e9b1a7b86756539487c9e3c6a9a85a4a2fb', '2025-09-24 08:47:11', 'regular'),
(23, 24, 'OF-023/2025', '2025-09-24', 'PREFEITURA MUNICIPAL DE BAYEUX\nSECRETARIA MUNICIPAL DE EDUCAÇÃO\n\nORIGEM: EMEF Assis Chateaubriand\nDESTINO: ALMOXARIFADO\nOFÍCIO Nº: OF-023/2025\nDATA: 24/09/2025\n\nSOLICITAÇÃO DE SERVIÇO - ALMOXARIFADO\n\nDESCRIÇÃO:\nPreciso de duas mesas\n\nEste ofício é válido e foi gerado automaticamente pelo Sistema de Chamados da Secretaria Municipal de Educação de Bayeux.', NULL, '85bbf876fe82dd68499883837c9d11182c29fef81dbf4cbbfc4131bfd850f965', '2025-09-24 08:47:36', 'regular'),
(24, 25, 'OF-024/2025', '2025-09-24', 'PREFEITURA MUNICIPAL DE BAYEUX\nSECRETARIA MUNICIPAL DE EDUCAÇÃO\n\nORIGEM: EMEF Assis Chateaubriand\nDESTINO: ALMOXARIFADO\nOFÍCIO Nº: OF-024/2025\nDATA: 24/09/2025\n\nSOLICITAÇÃO DE SERVIÇO - ALMOXARIFADO\n\nDESCRIÇÃO:\nPreciso de cola de isopor\n\nEste ofício é válido e foi gerado automaticamente pelo Sistema de Chamados da Secretaria Municipal de Educação de Bayeux.', NULL, '5e2459b07c454a3bced52e774e99ca40843681fd06c766b9f09439d90d8c1796', '2025-09-24 08:58:56', 'regular'),
(25, 26, 'OF-025/2025', '2025-09-24', 'PREFEITURA MUNICIPAL DE BAYEUX\nSECRETARIA MUNICIPAL DE EDUCAÇÃO\n\nORIGEM: EMEF Assis Chateaubriand\nDESTINO: INFORMÁTICA\nOFÍCIO Nº: OF-025/2025\nDATA: 24/09/2025\n\nSOLICITAÇÃO DE SERVIÇO - INFORMÁTICA\n\nDESCRIÇÃO:\nPreciso de tintas epson\n\nEste ofício é válido e foi gerado automaticamente pelo Sistema de Chamados da Secretaria Municipal de Educação de Bayeux.', NULL, '00aea07808a7ce651eb6e07c8953e86985b5fd21d1d44cd62742c9c21e2dab82', '2025-09-24 22:58:58', 'regular'),
(26, 27, 'OF-026/2025', '2025-09-24', 'PREFEITURA MUNICIPAL DE BAYEUX\nSECRETARIA MUNICIPAL DE EDUCAÇÃO\n\nORIGEM: EMEF Assis Chateaubriand\nDESTINO: ALMOXARIFADO\nOFÍCIO Nº: OF-026/2025\nDATA: 24/09/2025\n\nSOLICITAÇÃO DE SERVIÇO - ALMOXARIFADO\n\nDESCRIÇÃO:\nPreciso de 10 resmas de oficio\n\nEste ofício é válido e foi gerado automaticamente pelo Sistema de Chamados da Secretaria Municipal de Educação de Bayeux.', NULL, 'b3c9ab7b432510fd0b9976699efb8fe5825ec7b4c2515632713400ddf3c422df', '2025-09-24 23:14:19', 'regular'),
(27, 28, 'OF-027/2025', '2025-09-24', 'PREFEITURA MUNICIPAL DE BAYEUX\nSECRETARIA MUNICIPAL DE EDUCAÇÃO\n\nORIGEM: EMEF Assis Chateaubriand\nDESTINO: INFORMÁTICA\nOFÍCIO Nº: OF-027/2025\nDATA: 25/09/2025\n\nSOLICITAÇÃO DE SERVIÇO - INFORMÁTICA\n\nDESCRIÇÃO:\nPreciso de um monitor\n\nEste ofício é válido e foi gerado automaticamente pelo Sistema de Chamados da Secretaria Municipal de Educação de Bayeux.', NULL, 'd95ca63107d6d7e6cb74c696f8ae5f5ba17a6e3a96a1542296332d4fa50a18a0', '2025-09-25 00:09:36', 'regular'),
(28, 29, 'OF-028/2025', '2025-09-24', 'PREFEITURA MUNICIPAL DE BAYEUX\nSECRETARIA MUNICIPAL DE EDUCAÇÃO\n\nORIGEM: EMEF Assis Chateaubriand\nDESTINO: ALMOXARIFADO\nOFÍCIO Nº: OF-028/2025\nDATA: 25/09/2025\n\nSOLICITAÇÃO DE SERVIÇO - ALMOXARIFADO\n\nDESCRIÇÃO:\nPreciso de cola branca\n\nEste ofício é válido e foi gerado automaticamente pelo Sistema de Chamados da Secretaria Municipal de Educação de Bayeux.', NULL, '080814dba1dda60d918ad9492950253c75829d1480e6d2c1ded8104eb39be3af', '2025-09-25 00:15:11', 'regular'),
(29, 30, 'OF-029/2025', '2025-09-25', 'PREFEITURA MUNICIPAL DE BAYEUX\nSECRETARIA MUNICIPAL DE EDUCAÇÃO\n\nORIGEM: EMEF Assis Chateaubriand\nDESTINO: ALMOXARIFADO\nOFÍCIO Nº: OF-029/2025\nDATA: 25/09/2025\n\nSOLICITAÇÃO DE SERVIÇO - ALMOXARIFADO\n\nDESCRIÇÃO:\n01 LITRO DE ALCOOL, 06 PANO DE CHÃO, 04 FLANELAS\n\nEste ofício é válido e foi gerado automaticamente pelo Sistema de Chamados da Secretaria Municipal de Educação de Bayeux.', NULL, '98fbbdde9c4cfab7d82992e70c20acaa2033417af931682af494e7da10e5c0ec', '2025-09-25 10:44:00', 'regular'),
(33, 31, 'OF-030/2025', '2025-09-25', 'PREFEITURA MUNICIPAL DE BAYEUX\nSECRETARIA MUNICIPAL DE EDUCAÇÃO\n\nORIGEM: EMEF Assis Chateaubriand\nDESTINO: ALMOXARIFADO\nOFÍCIO Nº: OF-030/2025\nDATA: 26/09/2025\n\nSOLICITAÇÃO DE SERVIÇO - ALMOXARIFADO\n\nDESCRIÇÃO:\npreciso de toalhas\n\nEste ofício é válido e foi gerado automaticamente pelo Sistema de Chamados da Secretaria Municipal de Educação de Bayeux.', NULL, '8e81884a8cd941299e5240432a6c3fa34d601af98c06f328c3ebd41574e359c7', '2025-09-26 01:00:59', 'regular'),
(35, 32, 'OF-031/2025', '2025-09-26', 'PREFEITURA MUNICIPAL DE BAYEUX\nSECRETARIA MUNICIPAL DE EDUCAÇÃO\n\nORIGEM: EMEF Assis Chateaubriand\nDESTINO: ALMOXARIFADO\nOFÍCIO Nº: OF-031/2025\nDATA: 26/09/2025\n\nSOLICITAÇÃO DE SERVIÇO - ALMOXARIFADO\n\nDESCRIÇÃO:\nVassouras\n\nEste ofício é válido e foi gerado automaticamente pelo Sistema de Chamados da Secretaria Municipal de Educação de Bayeux.', NULL, '11c823cee07f3bb73f14ebe43206ee1c13be52b36ee8b96ee61bd38d36d1edc8', '2025-09-26 10:58:54', 'regular'),
(36, 33, 'OF-032/2025', '2025-09-26', 'PREFEITURA MUNICIPAL DE BAYEUX\nSECRETARIA MUNICIPAL DE EDUCAÇÃO\n\nORIGEM: EMEF Assis Chateaubriand\nDESTINO: ALMOXARIFADO\nOFÍCIO Nº: OF-032/2025\nDATA: 26/09/2025\n\nSOLICITAÇÃO DE SERVIÇO - ALMOXARIFADO\n\nDESCRIÇÃO:\nRodos\n\nEste ofício é válido e foi gerado automaticamente pelo Sistema de Chamados da Secretaria Municipal de Educação de Bayeux.', NULL, '468b70b0dbbb8f0ff2ec3a1ae90f7f2ffb3eedc21468aca88a606cd8ca79f972', '2025-09-26 11:01:14', 'regular'),
(37, 34, 'OF-033/2025', '2025-09-26', 'PREFEITURA MUNICIPAL DE BAYEUX\nSECRETARIA MUNICIPAL DE EDUCAÇÃO\n\nORIGEM: EMEF Assis Chateaubriand\nDESTINO: ALMOXARIFADO\nOFÍCIO Nº: OF-033/2025\nDATA: 26/09/2025\n\nSOLICITAÇÃO DE SERVIÇO - ALMOXARIFADO\n\nDESCRIÇÃO:\n15 lápis grafite \n\nEste ofício é válido e foi gerado automaticamente pelo Sistema de Chamados da Secretaria Municipal de Educação de Bayeux.', NULL, '53e93cc6575bdc7d5d335e3b66829d1c8132b1dade7e9b14db8ac2576a1479a2', '2025-09-26 11:32:29', 'regular'),
(38, 35, 'OF-034/2025', '2025-09-26', 'PREFEITURA MUNICIPAL DE BAYEUX\nSECRETARIA MUNICIPAL DE EDUCAÇÃO\n\nORIGEM: EMEF Assis Chateaubriand\nDESTINO: CASA DA MERENDA\nOFÍCIO Nº: OF-034/2025\nDATA: 26/09/2025\n\nSOLICITAÇÃO DE SERVIÇO - CASA DA MERENDA\n\nDESCRIÇÃO:\nFrutas\n\nEste ofício é válido e foi gerado automaticamente pelo Sistema de Chamados da Secretaria Municipal de Educação de Bayeux.', NULL, 'b0bb303e46b51c6a5c68cc1c1603170f5f9e0bf8ed9f9920f77ef6fef0fed78b', '2025-09-26 11:34:37', 'regular'),
(39, 36, 'OF-035/2025', '2025-09-26', 'PREFEITURA MUNICIPAL DE BAYEUX\nSECRETARIA MUNICIPAL DE EDUCAÇÃO\n\nORIGEM: EMEF Assis Chateaubriand\nDESTINO: CASA DA MERENDA\nOFÍCIO Nº: OF-035/2025\nDATA: 26/09/2025\n\nSOLICITAÇÃO DE SERVIÇO - CASA DA MERENDA\n\nDESCRIÇÃO:\n10 abacaxis\n\nEste ofício é válido e foi gerado automaticamente pelo Sistema de Chamados da Secretaria Municipal de Educação de Bayeux.', NULL, '81355353e25c92abdfbf860be4dd01952ef1e17765695b4641b82fd74f14d046', '2025-09-26 17:37:34', 'regular'),
(40, 37, 'OF-036/2025', '2025-09-26', 'PREFEITURA MUNICIPAL DE BAYEUX\nSECRETARIA MUNICIPAL DE EDUCAÇÃO\n\nORIGEM: EMEF Assis Chateaubriand\nDESTINO: CASA DA MERENDA\nOFÍCIO Nº: OF-036/2025\nDATA: 26/09/2025\n\nSOLICITAÇÃO DE SERVIÇO - CASA DA MERENDA\n\nDESCRIÇÃO:\nPreciso de 5 kg de peito de frango\n\nEste ofício é válido e foi gerado automaticamente pelo Sistema de Chamados da Secretaria Municipal de Educação de Bayeux.', NULL, 'e62c044280f6b52cf0e7f3a2982064edaa1148f4d6408a803812b3d1c8e6c25c', '2025-09-26 17:52:58', 'regular'),
(41, 38, 'OF-037/2025', '2025-09-26', 'PREFEITURA MUNICIPAL DE BAYEUX\nSECRETARIA MUNICIPAL DE EDUCAÇÃO\n\nORIGEM: EMEF Assis Chateaubriand\nDESTINO: ALMOXARIFADO\nOFÍCIO Nº: OF-037/2025\nDATA: 26/09/2025\n\nSOLICITAÇÃO DE SERVIÇO - ALMOXARIFADO\n\nDESCRIÇÃO:\nPRECISO DE 10 RESMAS\r\n\n\nEste ofício é válido e foi gerado automaticamente pelo Sistema de Chamados da Secretaria Municipal de Educação de Bayeux.', NULL, 'fd75a88a2037546fb2ae1161a89a2282916d8d7bc1b3a8c0048a3c3b6225eafb', '2025-09-26 17:54:33', 'regular');

-- --------------------------------------------------------

--
-- Estrutura para tabela `unidades_escolares`
--

CREATE TABLE `unidades_escolares` (
  `id` int NOT NULL,
  `nome_unidade` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `endereco` varchar(300) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `telefone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_cadastro` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `unidades_escolares`
--

INSERT INTO `unidades_escolares` (`id`, `nome_unidade`, `endereco`, `telefone`, `email`, `data_cadastro`) VALUES
(1, 'EMEF Assis Chateaubriand', 'R. José Ulisses Teixeira, S/N - Centro', '(83) 3333-1111', 'assis@bayeux.pb.gov.br', '2025-09-17 23:05:47'),
(2, 'EMEF Airton Ciraulo', 'Av. Principal, 456 - Bairro Novo', '', 'airton@bayeux.pb.gov.br', '2025-09-17 23:05:47'),
(3, 'Escola Berenice Ribeiro', 'Av. Idalina Leite, S/N - São Bento', '', 'berenice@bayeux.pb.gov.br', '2025-09-17 23:05:47'),
(4, 'Escola Jaime Caetano', 'Avenida Liberdade, 290, Baralho', '', 'jaime@bayeux.pb.gov.br', '2025-09-18 02:17:46'),
(5, 'Cite', 'SME', '', 'cite@edubayeux.pb.gov.br', '2025-09-19 22:30:17'),
(6, 'SME', 'Avenida Santa Teresa, 77, Sesi', '', '', '2025-09-19 23:31:20');

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int NOT NULL,
  `nome` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `senha` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_usuario` enum('admin','unidade_escolar','secretaria','tecnico_geral','tecnico_informatica','almoxarifado','casa_da_merenda') COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_unidade_escolar` int DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT '1',
  `data_cadastro` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `nome_usuario` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `primeiro_login` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `nome`, `email`, `senha`, `tipo_usuario`, `id_unidade_escolar`, `ativo`, `data_cadastro`, `nome_usuario`, `primeiro_login`) VALUES
(1, 'Administrador', 'admin@bayeux.pb.gov.br', '$2y$10$gcS86NN1ZaSNxxI5JK2A0euxDoxzsUM4OI4QbnEFFQnvvy/pQet4O', 'admin', NULL, 1, '2025-09-17 23:05:47', 'admin', 0),
(2, 'Matias', 'matias@bayeux.pb.gov.br', '$2y$10$J8RSMF0BXtiqyzEOtFPm9OHITrCHDzRO1ucKkE6K6SNnQ6fhPZ/ai', 'tecnico_geral', NULL, 1, '2025-09-17 23:05:47', 'matias', 0),
(3, 'Kenio', 'kenio@bayeux.pb.gov.br', '$2y$10$Wn1Je.MVv0.KMuQr9zicxe4GuV6xgjsjN8NXwc.U5gPjS5QNb3SH.', 'tecnico_informatica', NULL, 1, '2025-09-17 23:05:47', 'kenio', 0),
(4, 'Betania', 'assis@bayeux.pb.gov.br', '$2y$10$BWIBgFXCr31WKGUPCk8YTe3nbOsSu3ukp6w2azh86KxyXPlGoufJ2', 'unidade_escolar', 1, 1, '2025-09-17 23:05:47', 'assis', 0),
(5, 'Diretora Maria', 'diretora.maria@bayeux.pb.gov.br', '$2y$10$HfNEkKa08KAAAvfCrOBDfuY8hBlKFfdeeLTg5Zi3d6KImSKOqQtDm', 'unidade_escolar', 2, 1, '2025-09-17 23:05:47', '', 1),
(6, 'Diretor Pedro', 'diretor.pedro@bayeux.pb.gov.br', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'unidade_escolar', 3, 1, '2025-09-17 23:05:47', '', 1),
(7, 'Marcos', 'marcos@bayeux.pb.gov.br', '$2y$10$zLs6pGidO9LKnhjhQRquBeJqu/koQGxOWZhN2KQkC0tH8Q5gq.BX.', 'tecnico_informatica', NULL, 1, '2025-09-19 00:17:45', 'marcos', 0),
(8, 'Katia', 'katia@bayeux.pb.gov.br', '$2y$10$lvDqMqMsYLyLL0EUGOK3lOXv.gsLlrWqzgHAeSuI5c5y0ZQwG3DDa', 'almoxarifado', NULL, 1, '2025-09-19 12:00:00', 'Katia', 0),
(9, 'Giva', 'giva@bayeux.pb.gov.br', '$2y$10$QZM.BYtn/q/ciYvReLDdBuftsacOyoEtc2EEQMNCDC/ID6jSqrluu', 'casa_da_merenda', NULL, 1, '2025-09-19 12:00:00', 'giva', 0),
(10, 'Aslana Sherla', 'aslana@bayeux.pb.gov.br', '$2y$10$USf3u7uuJHXF3bGwCHvncuzMGGXBAgFM1GB7U3vQNuy/8uK6GgsIS', 'secretaria', NULL, 1, '2025-09-19 12:00:00', 'aslana', 0),
(12, 'Joelma', 'joelma@bayeux.pb.gov.br', '$2y$10$sEA3ZoWGgJ0z0DGfsh0OTezhqsYxEFA.QW9yC/VAqj5ZFI2gWWn1i', 'secretaria', NULL, 1, '2025-09-19 22:38:16', 'joelma', 1),
(13, 'Joel', 'joel@bayeux.pb.gov.br', '$2y$10$rXoOfNiWh/cM50RJuO2e5eCIiNA0k9xRgPUdd2DcDMG1XA7Idk1GK', 'secretaria', NULL, 1, '2025-09-19 23:34:53', 'joel', 1);

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `chamados`
--
ALTER TABLE `chamados`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_chamado_unidade` (`id_unidade_escolar`),
  ADD KEY `fk_chamado_usuario` (`id_usuario_abertura`),
  ADD KEY `fk_chamado_tecnico` (`id_tecnico_responsavel`);

--
-- Índices de tabela `oficios`
--
ALTER TABLE `oficios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id_chamado` (`id_chamado`),
  ADD UNIQUE KEY `numero_oficio` (`numero_oficio`);

--
-- Índices de tabela `unidades_escolares`
--
ALTER TABLE `unidades_escolares`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nome_unidade` (`nome_unidade`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_usuario_unidade` (`id_unidade_escolar`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `chamados`
--
ALTER TABLE `chamados`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT de tabela `oficios`
--
ALTER TABLE `oficios`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT de tabela `unidades_escolares`
--
ALTER TABLE `unidades_escolares`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `chamados`
--
ALTER TABLE `chamados`
  ADD CONSTRAINT `fk_chamado_tecnico` FOREIGN KEY (`id_tecnico_responsavel`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_chamado_unidade` FOREIGN KEY (`id_unidade_escolar`) REFERENCES `unidades_escolares` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_chamado_usuario` FOREIGN KEY (`id_usuario_abertura`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `oficios`
--
ALTER TABLE `oficios`
  ADD CONSTRAINT `fk_oficio_chamado` FOREIGN KEY (`id_chamado`) REFERENCES `chamados` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `fk_usuario_unidade` FOREIGN KEY (`id_unidade_escolar`) REFERENCES `unidades_escolares` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
