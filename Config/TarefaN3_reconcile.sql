-- Reconcile script: cria objetos que estavam no `TarefaN3.sql` mas faltavam no dump atual
-- Alvo: MySQL 8.0.44 (usa utf8mb4_0900_ai_ci conforme dump fornecido)
-- Atenção: faça backup antes de aplicar (EXPORT ou mysqldump do DB atual).

SET @OLD_FOREIGN_KEY_CHECKS = @@FOREIGN_KEY_CHECKS;
SET FOREIGN_KEY_CHECKS = 0;

-- 1) TB_ANALISES_PROD (faltava no dump)
CREATE TABLE IF NOT EXISTS `TB_ANALISES_PROD` (
  `Id` INT NOT NULL AUTO_INCREMENT,
  `Descricao` VARCHAR(100) NOT NULL,
  `idSituacao` INT DEFAULT NULL,
  `idParceiro` INT DEFAULT NULL,
  `idSistema` INT DEFAULT NULL,
  `idStatus` INT DEFAULT NULL,
  `idUsuario` INT DEFAULT NULL,
  `chkFicha` CHAR(1) DEFAULT NULL,
  `numeroFicha` INT DEFAULT NULL,
  `chkParado` CHAR(1) DEFAULT NULL,
  `criado_em` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `ult_edicao` DATETIME DEFAULT NULL COMMENT 'Data da ultima edição',
  PRIMARY KEY (`Id`),
  KEY `idx_analises_prod_parceiro` (`idParceiro`),
  KEY `idx_analises_prod_sistema` (`idSistema`),
  KEY `idx_analises_prod_status` (`idStatus`),
  KEY `idx_analises_prod_usuario` (`idUsuario`),
  CONSTRAINT `FK_ANALISES_PROD_PARCEIRO` FOREIGN KEY (`idParceiro`) REFERENCES `TB_PARCEIROS`(`Id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `FK_ANALISES_PROD_SISTEMA` FOREIGN KEY (`idSistema`) REFERENCES `TB_SISTEMA`(`Id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `FK_ANALISES_PROD_STATUS` FOREIGN KEY (`idStatus`) REFERENCES `TB_STATUS`(`Id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `FK_ANALISES_PROD_USUARIO` FOREIGN KEY (`idUsuario`) REFERENCES `TB_USUARIO`(`Id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 2) TB_INDICACAO (faltava no dump)
CREATE TABLE IF NOT EXISTS `TB_INDICACAO` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `plugin_id` INT NOT NULL,
    `data` DATE NOT NULL,
    `cnpj` VARCHAR(20) DEFAULT NULL,
    `serial` VARCHAR(50) DEFAULT NULL,
    `contato` VARCHAR(100) DEFAULT NULL,
    `fone` VARCHAR(50) DEFAULT NULL,
    `user_id` INT NOT NULL,
    `idConsultor` INT NOT NULL,
    `status` ENUM('Faturado','Pendente','Cancelado') NOT NULL DEFAULT 'Pendente',
    `vlr_total` DECIMAL(18,4) DEFAULT NULL,
    `n_venda` INT DEFAULT NULL,
    `revenda` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Indica se a indicação foi para revenda: 1=Sim, 0=Não',
    PRIMARY KEY (`id`),
    KEY `idx_indicacao_plugin` (`plugin_id`),
    KEY `idx_indicacao_user` (`user_id`),
    KEY `idx_indicacao_consultor` (`idConsultor`),
    CONSTRAINT `FK_INDICACAO_PLUGIN` FOREIGN KEY (`plugin_id`) REFERENCES `TB_PLUGIN`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `FK_INDICACAO_USER` FOREIGN KEY (`user_id`) REFERENCES `TB_USUARIO`(`Id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `FK_INDICACAO_CONSULTOR` FOREIGN KEY (`idConsultor`) REFERENCES `TB_USUARIO`(`Id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 3) TB_EMBEDDINGS (faltava no dump)
CREATE TABLE IF NOT EXISTS `TB_EMBEDDINGS` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `data_geracao` DATETIME NOT NULL,
  `tipo` ENUM('artigos','video') NOT NULL DEFAULT 'artigos',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

SET FOREIGN_KEY_CHECKS = @OLD_FOREIGN_KEY_CHECKS;

-- Fim do script de reconciliação.
-- Sugestão: revise o conteúdo antes de aplicar e faça um dump/backup do DB atual.
