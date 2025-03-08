CREATE SCHEMA TarefaN3;

USE TarefaN3;

CREATE TABLE TB_SITUACAO 
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
 Descricao varchar(100) NOT NULL,  
 idSituacao INT,  
 idAtendente INT,  
 idSistema INT,  
 idStatus INT,
 idUsuario int,
 Hora_ini DATETIME,  
 Hora_fim DATETIME,  
 Total_hora TIME,
 chkFicha char(1),
 numeroFicha INT DEFAULT NULL,
 chkMultiplica char(1),
 numeroMulti int DEFAULT NULL,
 chkParado char(1),
 Nota tinyint
); 

CREATE TABLE TB_USUARIO
( 
 Id INT PRIMARY KEY AUTO_INCREMENT,
 Nome varchar(50) NOT NULL,
 Email varchar(50) NOT NULL,
 Senha varchar(255) NOT NULL,
 Cargo varchar(255) NOT NULL
); 

-- Inserindo Atendentes
INSERT INTO TB_USUARIO (Nome, Email, Senha, Cargo) VALUES ('Lucas Perin', 'lucas.perin@zucchetti.com', 1234, 'Admin');
INSERT INTO TB_USUARIO (Nome, Email, Senha, Cargo) VALUES ('Guilherme Ferri', 'guilherme.ferri@zucchetti.com', 1234, 'Admin');
INSERT INTO TB_USUARIO (Nome, Email, Senha, Cargo) VALUES ('Gabriel Battistella', 'gabriel.battistella@zucchetti.com', 1234, 'Admin');
INSERT INTO TB_USUARIO (Nome, Email, Senha, Cargo) VALUES ('Bernardo Rachadel', 'bernardo.rachadel@zucchetti.com', 1234, 'Admin');
INSERT INTO TB_USUARIO (Nome, Email, Senha, Cargo) VALUES ('Douglas da Silva', 'douglas.silva@zucchetti.com', 1234, 'Viewer');
INSERT INTO TB_USUARIO (Nome, Email, Senha, Cargo) VALUES ('Vagner Wilske', 'vagner.wilske@zucchetti.com', 1234, 'Viewer');
INSERT INTO TB_USUARIO (Nome, Email, Senha, Cargo) VALUES ('Germano Santos', 'germano.santos@zucchetti.com', 1234, 'Viewer');
INSERT INTO TB_USUARIO (Nome, Email, Senha, Cargo) VALUES ('Leandro Haefliger', 'leandro.haefliger@zucchetti.com', 1234, 'User');
INSERT INTO TB_USUARIO (Nome, Email, Senha, Cargo) VALUES ('Wesley Zamarchi', 'wesley.zamarchi@zucchetti.com', 1234, 'User');
INSERT INTO TB_USUARIO (Nome, Email, Senha, Cargo) VALUES ('Lucas Rossato', 'lucas.rossato@zucchetti.com', 1234, 'User');
INSERT INTO TB_USUARIO (Nome, Email, Senha, Cargo) VALUES ('Giselle', 'giselle.goetz@zucchetti.com', 1234, 'User');
INSERT INTO TB_USUARIO (Nome, Email, Senha, Cargo) VALUES ('Caio Oliveira', 'caio.oliveira@zucchetti.com', 1234, 'User');
INSERT INTO TB_USUARIO (Nome, Email, Senha, Cargo) VALUES ('Eduardo Forcellini', 'eduardo.forcellini@zucchetti.com', 1234, 'User');
INSERT INTO TB_USUARIO (Nome, Email, Senha, Cargo) VALUES ('Antonio Zampeze', 'antonio.zampeze@zucchetti.com', 1234, 'User');
INSERT INTO TB_USUARIO (Nome, Email, Senha, Cargo) VALUES ('Gabriel Deggerone', 'gabriel.deggerone@zucchetti.com', 1234, 'User');
INSERT INTO TB_USUARIO (Nome, Email, Senha, Cargo) VALUES ('Douglas Santana', 'douglas.santana@zucchetti.com', 1234, 'Conversor');

ALTER TABLE TB_ANALISES ADD FOREIGN KEY(idSituacao) REFERENCES TB_SITUACAO (Id);
ALTER TABLE TB_ANALISES ADD FOREIGN KEY(idSistema) REFERENCES TB_SISTEMA (Id);
ALTER TABLE TB_ANALISES ADD FOREIGN KEY(idStatus) REFERENCES TB_STATUS (Id);
ALTER TABLE TB_ANALISES ADD FOREIGN KEY(idUsuario) REFERENCES TB_USUARIO (Id);

-- Inserindo Sistemas
INSERT INTO TB_SISTEMA (Descricao) VALUES ('Clipp 360');
INSERT INTO TB_SISTEMA (Descricao) VALUES ('Clipp PRO');
INSERT INTO TB_SISTEMA (Descricao) VALUES ('NFS-e');
INSERT INTO TB_SISTEMA (Descricao) VALUES ('DAV');
INSERT INTO TB_SISTEMA (Descricao) VALUES ('ZWeb');
INSERT INTO TB_SISTEMA (Descricao) VALUES ('SmallSoft');
INSERT INTO TB_SISTEMA (Descricao) VALUES ('Clipp Facil');
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
INSERT INTO TB_SITUACAO (Descricao) VALUES ('Analise N3');
INSERT INTO TB_SITUACAO (Descricao) VALUES ('Auxilio Suporte/Vendas');
INSERT INTO TB_SITUACAO (Descricao) VALUES ('Ficha Criada');


--Criação do controle de Conversão
CREATE TABLE TB_SISTEMA_CONVER (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL UNIQUE
);

INSERT INTO TB_SISTEMA_CONVER (nome) VALUES
    ('Conc. p/ ClippPro'), ('Clipp p/ ClippPro'), ('Zeta p/ ClippPro'), ('Conc. p/ ClippFacil'), ('Clipp p/ ClippFacil'),
    ('Zeta p/ Clipp360'), ('Small p/ Clipp360'), ('Conc. p/ Clipp360'), ('Clipp p/ Clipp360'), ('Conc. p/ ClippMEI'),
    ('Clipp p/ ClippMEI'), ('Clipp p/ ZetaWeb'), ('Conc p/ ZetaWeb'), ('Small p/ ZetaWeb'), ('Gdoor p/ ZetaWeb'),
    ('Gdoor p/ ClippPro'), ('AC p/ Clipp360'), ('ClippMei p/ ClippPro'), ('AC p/ ClippPRO');

CREATE TABLE TB_STATUS_CONVER (
    id INT AUTO_INCREMENT PRIMARY KEY,
    descricao VARCHAR(255) NOT NULL UNIQUE
);

INSERT INTO TB_STATUS_CONVER (descricao) VALUES
    ('Concluido'), ('Aguardando cliente'), ('Analise'), ('Em fila'), ('Cancelada'),
    ('Dar prioridade'), ('Aguardando Conversor'), ('Ficha'), ('Fin - Aguar. Login');

CREATE TABLE TB_ANALISTA_CONVER (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL UNIQUE
);

INSERT INTO TB_ANALISTA_CONVER (nome) VALUES
    ('Douglas'), ('Ian'), ('Gabriel');

CREATE TABLE TB_CONVERSOES (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email_cliente VARCHAR(255) NOT NULL,
    contato VARCHAR(255) NOT NULL,
    serial VARCHAR(255) NULL,
    retrabalho ENUM('Sim', 'Nao') NOT NULL,
    sistema_id INT NOT NULL,
    status_id INT NOT NULL,
    data_recebido DATETIME NOT NULL,
    prazo_entrega DATETIME NOT NULL,
    data_inicio DATETIME NOT NULL,
    data_conclusao DATETIME NULL,
    analista_id INT NOT NULL,
    observacao TEXT NULL,
    tempo_total TIME GENERATED ALWAYS AS (TIMEDIFF(data_conclusao, data_recebido)) VIRTUAL,
    FOREIGN KEY (sistema_id) REFERENCES TB_SISTEMA_CONVER(id) ON DELETE CASCADE,
    FOREIGN KEY (status_id) REFERENCES TB_STATUS_CONVER(id) ON DELETE CASCADE,
    FOREIGN KEY (analista_id) REFERENCES TB_ANALISTA_CONVER(id) ON DELETE CASCADE
);

-- Índices para otimizar buscas
CREATE INDEX idx_status ON TB_CONVERSOES(status_id);
CREATE INDEX idx_data_recebido ON TB_CONVERSOES(data_recebido);
CREATE INDEX idx_data_inicio ON TB_CONVERSOES(data_inicio);
