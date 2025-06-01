-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Tempo de geração: 01/06/2025 às 21:24
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
  PRIMARY KEY (`cliente_id`)
) ENGINE=MyISAM AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Despejando dados para a tabela `clientes`
--

INSERT INTO `clientes` (`cliente_id`, `nome`, `email`, `telefone`, `cpf`) VALUES
(1, 'Carlos Silva', 'carlos@email.com', '11999990001', '123.456.789-01'),
(2, 'Ana Souza', 'ana@email.com', '11999990002', '123.456.789-02'),
(3, 'Mariana Lima', 'mariana@email.com', '11999990003', '123.456.789-03'),
(4, 'Felipe Costa', 'felipe@email.com', '11999990004', '123.456.789-04'),
(5, 'Juliana Alves', 'juliana@email.com', '11999990005', '123.456.789-05'),
(6, 'Roberto Dias', 'roberto@email.com', '11999990006', '123.456.789-06'),
(7, 'Fernanda Castro', 'fernanda@email.com', '11999990007', '123.456.789-07'),
(8, 'Bruno Martins', 'bruno@email.com', '11999990008', '123.456.789-08'),
(9, 'Patrícia Gomes', 'patricia@email.com', '11999990009', '123.456.789-09'),
(10, 'Ricardo Moreira', 'ricardo@email.com', '11999990010', '123.456.789-10'),
(11, 'Amanda Barros', 'amanda@email.com', '11999990011', '123.456.789-11'),
(12, 'Thiago Ferreira', 'thiago@email.com', '11999990012', '123.456.789-12'),
(13, 'Isabela Mendes', 'isabela@email.com', '11999990013', '123.456.789-13'),
(14, 'Luciana Ramos', 'luciana@email.com', '11999990014', '123.456.789-14'),
(15, 'André Lopes', 'andre@email.com', '11999990015', '123.456.789-15'),
(22, 'Usuário 01', 'user@user.com', '(11) 99999-9999', '123.123.123-12');

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
) ENGINE=MyISAM AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Despejando dados para a tabela `estoque`
--

INSERT INTO `estoque` (`produto_id`, `nome`, `quantidade`, `valor`, `unidade_medida`, `limite_alerta`) VALUES
(1, 'Café Espresso', 100, 7.00, NULL, 0),
(2, 'Cappuccino', 80, 9.50, NULL, 0),
(3, 'Latte', 10, 20.00, NULL, 0),
(4, 'Mocha', 60, 10.00, NULL, 0),
(5, 'Chá Gelado', 50, 6.00, NULL, 0),
(6, 'Bolo de Chocolate', 30, 12.00, NULL, 0),
(7, 'Bolo de Cenoura', 25, 11.00, NULL, 0),
(8, 'Croissant', 40, 8.00, NULL, 0),
(9, 'Pão de Queijo', 100, 5.00, NULL, 0),
(10, 'Torrada com Manteiga', 50, 4.50, NULL, 0),
(16, 'Produto 01', 4, 30.00, '1', 10);

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
) ENGINE=MyISAM AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Despejando dados para a tabela `item_pedido`
--

INSERT INTO `item_pedido` (`item_pedido_id`, `produto_id`, `pedido_id`, `quantidade`, `produto_nome`) VALUES
(1, 10, 5, 4, 'Torrada com Manteiga'),
(2, 1, 4, 5, 'Café Espresso'),
(3, 2, 8, 5, 'Cappuccino'),
(4, 13, 9, 1, 'Produto01'),
(5, 13, 10, 20, 'Produto01'),
(6, 13, 11, 40, 'Produto01'),
(7, 13, 12, 5, 'Produto01'),
(8, 16, 13, 1, 'Produto 01');

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
) ENGINE=MyISAM AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Despejando dados para a tabela `licencas`
--

INSERT INTO `licencas` (`licenca_id`, `nome_licenca`, `cnpj`, `orgao_responsavel`, `data_validade`, `prazo_expiracao`, `caminho_documento`) VALUES
(1, 'Alvará de Funcionamento', '12.345.678/0001-90', 'Prefeitura', '2025-12-31', '90', NULL),
(2, 'Licença Ambiental', '23.456.789/0001-80', 'Ibama', '2025-11-15', '60', NULL),
(3, 'Registro Sanitário', '34.567.890/0001-70', 'Anvisa', '2025-10-10', '30', NULL),
(8, 'Licença 01', '00.000.000/0000-00', 'Órgão 01', '2025-07-30', '30', NULL);

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
  PRIMARY KEY (`pedido_id`)
) ENGINE=MyISAM AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Despejando dados para a tabela `pedidos`
--

INSERT INTO `pedidos` (`pedido_id`, `cliente_id`, `data_pedido`, `valor`, `mesa`) VALUES
(8, 20, '2025-06-01', 47.50, '01'),
(5, 1, '2025-06-01', 18.00, '01'),
(4, 11, '2025-06-01', 35.00, '03'),
(9, 11, '2025-06-01', 10.00, '01'),
(10, 4, '2025-06-01', 200.00, '03'),
(11, 1, '2025-06-01', 400.00, '01'),
(12, 7, '2025-06-01', 50.00, '02'),
(13, 22, '2025-06-01', 30.00, '04');

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
