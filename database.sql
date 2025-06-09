-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Tempo de geração: 09/06/2025 às 15:19
-- Versão do servidor: 9.1.0
-- Versão do PHP: 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `cafealvorada`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `clientes`
--

DROP TABLE IF EXISTS `clientes`;
CREATE TABLE IF NOT EXISTS `clientes` (
  `cliente_id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `cpf` varchar(14) DEFAULT NULL,
  PRIMARY KEY (`cliente_id`),
  UNIQUE KEY `cpf` (`cpf`)
) ENGINE=MyISAM AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Despejando dados para a tabela `clientes`
--

INSERT INTO `clientes` (`cliente_id`, `nome`, `email`, `telefone`, `cpf`) VALUES
(30, 'Alexandre', 'alexandre@gmail.com', '(11) 94983-5776', '123.456.789-02'),
(12, 'Thiago Ferreira', 'thiago@email.com', '11999990012', '123.456.789-12');

-- --------------------------------------------------------

--
-- Estrutura para tabela `estoque`
--

DROP TABLE IF EXISTS `estoque`;
CREATE TABLE IF NOT EXISTS `estoque` (
  `produto_id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `quantidade` int DEFAULT '0',
  `valor` decimal(10,2) NOT NULL,
  `unidade_medida` varchar(20) DEFAULT NULL,
  `limite_alerta` int DEFAULT '0',
  PRIMARY KEY (`produto_id`)
) ENGINE=MyISAM AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Despejando dados para a tabela `estoque`
--

INSERT INTO `estoque` (`produto_id`, `nome`, `quantidade`, `valor`, `unidade_medida`, `limite_alerta`) VALUES
(3, 'Latte', 10, 20.00, 'UND', 0),
(4, 'Mocha', 60, 10.00, 'UND', 0),
(5, 'Chá Gelado', 47, 6.00, 'UND', 0),
(8, 'Croissant', 39, 8.00, 'UND', 0),
(9, 'Pão de Queijo', 95, 5.00, 'UND', 0),
(10, 'Torrada com Manteiga', 53, 4.50, 'UND', 0),
(21, 'Bolacha de chocolate', 30, 4.50, 'UND', 49);

-- --------------------------------------------------------

--
-- Estrutura para tabela `item_pedido`
--

DROP TABLE IF EXISTS `item_pedido`;
CREATE TABLE IF NOT EXISTS `item_pedido` (
  `item_pedido_id` int NOT NULL AUTO_INCREMENT,
  `produto_id` int NOT NULL,
  `pedido_id` int NOT NULL,
  `quantidade` int NOT NULL,
  `produto_nome` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`item_pedido_id`)
) ENGINE=MyISAM AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Despejando dados para a tabela `item_pedido`
--

INSERT INTO `item_pedido` (`item_pedido_id`, `produto_id`, `pedido_id`, `quantidade`, `produto_nome`) VALUES
(13, 2, 18, 1, 'Cappuccino'),
(12, 5, 17, 1, 'Chá Gelado'),
(3, 2, 8, 5, 'Cappuccino'),
(14, 9, 18, 5, 'Pão de Queijo'),
(15, 2, 19, 1, 'Cappuccino'),
(16, 8, 19, 1, 'Croissant'),
(9, 6, 14, 2, 'Bolo de Chocolate'),
(19, 5, 2, 1, 'Chá Gelado'),
(20, 10, 2, 1, 'Torrada com Manteiga'),
(23, 21, 3, 20, 'Bolacha de chocolate'),
(24, 5, 3, 1, 'Chá Gelado');

-- --------------------------------------------------------

--
-- Estrutura para tabela `licencas`
--

DROP TABLE IF EXISTS `licencas`;
CREATE TABLE IF NOT EXISTS `licencas` (
  `licenca_id` int NOT NULL AUTO_INCREMENT,
  `nome_licenca` varchar(100) NOT NULL,
  `cnpj` varchar(18) NOT NULL,
  `orgao_responsavel` varchar(100) NOT NULL,
  `data_validade` date NOT NULL,
  `prazo_expiracao` enum('30','60','90','140') NOT NULL,
  `caminho_documento` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`licenca_id`)
) ENGINE=MyISAM AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Despejando dados para a tabela `licencas`
--

INSERT INTO `licencas` (`licenca_id`, `nome_licenca`, `cnpj`, `orgao_responsavel`, `data_validade`, `prazo_expiracao`, `caminho_documento`) VALUES
(2, 'Licença Ambiental', '23.456.789/0001-80', 'Ibama', '2025-11-15', '60', NULL),
(3, 'Registro Sanitário', '34.567.890/0001-70', 'Anvisa', '2025-10-10', '30', NULL),
(13, 'Alvará de Funcionaomento', '12.345.678/0001-90', 'Prefeitura', '2025-05-01', '30', '../uploads/licencas/68421a93a0778_Alvara-Regin.jpg');

-- --------------------------------------------------------

--
-- Estrutura para tabela `pedidos`
--

DROP TABLE IF EXISTS `pedidos`;
CREATE TABLE IF NOT EXISTS `pedidos` (
  `pedido_id` int NOT NULL AUTO_INCREMENT,
  `cliente_id` int NOT NULL,
  `data_pedido` date NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `mesa` varchar(50) DEFAULT NULL,
  `status` enum('aberto','concluido') DEFAULT 'aberto',
  PRIMARY KEY (`pedido_id`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Despejando dados para a tabela `pedidos`
--

INSERT INTO `pedidos` (`pedido_id`, `cliente_id`, `data_pedido`, `valor`, `mesa`, `status`) VALUES
(2, 12, '2025-06-05', 10.50, '01', 'aberto'),
(3, 30, '2025-06-05', 96.00, '02', 'concluido');

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE IF NOT EXISTS `usuarios` (
  `usuario_id` int NOT NULL AUTO_INCREMENT,
  `nome_usuario` varchar(50) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `tipo_usuario` enum('admin','usuario','gerente') DEFAULT 'usuario',
  PRIMARY KEY (`usuario_id`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`usuario_id`, `nome_usuario`, `senha`, `tipo_usuario`) VALUES
(1, 'ruan', 'admin123', 'admin'),
(2, 'yasmin', 'senha123', 'usuario'),
(3, 'gabriel', 'senha123', 'usuario'),
(4, 'alexandre', 'senha123', 'gerente'),
(5, 'carlos', 'senha123', 'usuario');
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
