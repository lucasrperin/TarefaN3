CREATE SCHEMA TarefaN3;

USE TarefaN3;

CREATE TABLE TB_SITUACAO 
( 
 Id INT PRIMARY KEY auto_increment,  
 Descricao varchar(50) UNIQUE NOT NULL
); 

CREATE TABLE TB_ANALISTA 
( 
 Id INT PRIMARY KEY auto_increment,  
 Descricao varchar(50) UNIQUE NOT NULL
); 

CREATE TABLE TB_SISTEMA 
( 
 Descricao varchar(50) UNIQUE NOT NULL,  
 Id INT PRIMARY KEY auto_increment
); 

CREATE TABLE TB_STATUS 
( 
 Descricao varchar(50) UNIQUE NOT NULL,  
 Id INT PRIMARY KEY auto_increment
); 

CREATE TABLE TB_ANALISES 
( 
 Id INT PRIMARY KEY auto_increment,  
 Descricao varchar(50) UNIQUE NOT NULL,  
 idSituacao INT,  
 idAnalista INT,  
 idSistema INT,  
 idStatus INT,
 idUsuario int,
 Hora_ini DATETIME,  
 Hora_fim DATETIME,  
 Total_hora TIME  
); 

CREATE TABLE TB_USUARIO
( 
 Id INT PRIMARY KEY AUTO_INCREMENT,
 Nome varchar(50) NOT NULL,
 Email varchar(50) NOT NULL,
 Senha varchar(255) NOT NULL
); 

ALTER TABLE TB_ANALISES ADD FOREIGN KEY(idSituacao) REFERENCES TB_SITUACAO (Id);
ALTER TABLE TB_ANALISES ADD FOREIGN KEY(idAnalista) REFERENCES TB_ANALISTA (Id);
ALTER TABLE TB_ANALISES ADD FOREIGN KEY(idSistema) REFERENCES TB_SISTEMA (Id);
ALTER TABLE TB_ANALISES ADD FOREIGN KEY(idStatus) REFERENCES TB_STATUS (Id);
ALTER TABLE TB_ANALISES ADD FOREIGN KEY(idUsuario) REFERENCES TB_USUARIO (Id);

-- Inserindo Usuário N3
INSERT INTO TB_USUARIO (Nome, Email, Senha) VALUES ('Lucas P', 'lucas.perin@zucchetti.com', 1234);
INSERT INTO TB_USUARIO (Nome, Email, Senha) VALUES ('Guilherme F', 'guilherme.ferri@zucchetti.com', 1234);

-- Inserindo Analistas N3
INSERT INTO TB_ANALISTA (Descricao) VALUES ('Lucas P');
INSERT INTO TB_ANALISTA (Descricao) VALUES ('Guilherme F');

-- Inserindo Sistemas
INSERT INTO TB_SISTEMA (Descricao) VALUES ('Clipp 360');
INSERT INTO TB_SISTEMA (Descricao) VALUES ('Clipp PRO');
INSERT INTO TB_SISTEMA (Descricao) VALUES ('NFS-e');
INSERT INTO TB_SISTEMA (Descricao) VALUES ('DAV');
INSERT INTO TB_SISTEMA (Descricao) VALUES ('ZWeb');
INSERT INTO TB_SISTEMA (Descricao) VALUES ('SmallSoft');
INSERT INTO TB_SISTEMA (Descricao) VALUES ('Clipp Fácil');
INSERT INTO TB_SISTEMA (Descricao) VALUES ('ECF');
INSERT INTO TB_SISTEMA (Descricao) VALUES ('NFC-e');
INSERT INTO TB_SISTEMA (Descricao) VALUES ('Clipp Cheff');
INSERT INTO TB_SISTEMA (Descricao) VALUES ('AppsCloud');
INSERT INTO TB_SISTEMA (Descricao) VALUES ('Clipp Service');
INSERT INTO TB_SISTEMA (Descricao) VALUES ('Mercado Livre');
INSERT INTO TB_SISTEMA (Descricao) VALUES ('Minhas Notas');

-- Inserindo Status
INSERT INTO TB_STATUS (Descricao) VALUES ('Resolvido');
INSERT INTO TB_STATUS (Descricao) VALUES ('Desenvolvimento');
INSERT INTO TB_STATUS (Descricao) VALUES ('Aguardando');

-- Inserindo Tipos de Situação
INSERT INTO TB_SITUACAO (Descricao) VALUES ('Análises N3');
INSERT INTO TB_SITUACAO (Descricao) VALUES ('Auxilio Suporte/Vendas');
INSERT INTO TB_SITUACAO (Descricao) VALUES ('Ficha Criada');

