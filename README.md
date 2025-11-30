<p align="center">
  <img src="public/img/logo.png" alt="Alvorada" width="200"/>
</p>

<h1 align="center">Café Alvorada</h1>

UNIVERSIDADE NOVE DE JULHO - UNINOVE

<br><br><br>

NOME(S) DO(S) AUTOR(ES)
GABRIEL LEME DANTAS
ALEXANDRE HENRIQUE DORFLER SCHOPF

<br><br><br>

**CAFÉ ALVORADA: SISTEMA ERP PARA CAFETERIA**

<br><br><br>

SÃO PAULO - SP
2025

**CAFÉ ALVORADA: SISTEMA ERP PARA CAFETERIA**

> Projeto semestral.
>
> **Área de Concentração:** Análise e Desenvolvimento de Sistemas.
> **Orientador(a):** [Adriano Michelotti Schroeder]

<br><br>

SÃO PAULO - SP
2025

---

## SUMÁRIO

1. INTRODUÇÃO
2. COLABORADORES
3. ARQUITETURA DO SISTEMA
4. MODELO DE DADOS
5. REQUISITOS FUNCIONAIS
6. REQUISITOS NÃO FUNCIONAIS
7. REGRAS DE NEGÓCIO
8. CASOS DE USO
9. MANUAL DE INSTALAÇÃO
10. MANUAL DO USUÁRIO
11. PLANO DE TESTES
12. CONSIDERAÇÕES FINAIS
REFERÊNCIAS

---

# 1. INTRODUÇÃO

## 1.1 Contextualização
O *Café Alvorada* é um sistema do tipo ERP (*Enterprise Resource Planning*) desenvolvido para atender às necessidades operacionais de cafeterias. O projeto nasceu da necessidade de centralizar informações de clientes, pedidos, estoque e licenças, eliminando processos manuais e inconsistências de dados.

## 1.2 Problema
Empresas do segmento de alimentação sofrem frequentemente com:
* Falta de controle de estoque eficiente;
* Informações dispersas sobre clientes e pedidos;
* Riscos operacionais e legais ao operar com licenças vencidas;
* Falta de padronização e segurança nos processos internos.

## 1.3 Objetivos
O **Objetivo Geral** é criar um sistema totalmente *web*, simples e intuitivo, capaz de integrar e gerenciar as principais operações de uma cafeteria.

Os **Objetivos Específicos** incluem:
* Registrar pedidos e atualizar o estoque automaticamente;
* Gerenciar informações de clientes e histórico de compras;
* Controlar licenças e emitir alertas de vencimento;
* Garantir segurança, integridade e confiabilidade dos dados.

## 1.4 Escopo
O sistema **contempla**: Cadastro e controle de clientes; Emissão e gerenciamento de pedidos; Controle detalhado de estoque; Gestão de licenças e documentos obrigatórios.

O sistema **não contempla**: Pagamentos online; Integração com sistema fiscal (NFC-e); Aplicativo *mobile*; Gestão financeira avançada (contas a pagar/receber, fluxo de caixa).

---

# 2. COLABORADORES

A equipe de desenvolvimento é composta pelos seguintes membros:

| Nome | RA | E-mail |
| :--- | :--- | :--- |
| Gabriel Leme Dantas | 2224101466 | biel-leme@uni9.edu.br |
| Alexandre Henrique Dorfler Schopf | 2224101576 | schopf.alexandre@uni9.edu.br |

---

# 3. ARQUITETURA DO SISTEMA

## 3.1 Tecnologias Utilizadas
* **Back-end:** PHP.
* **Banco de Dados:** MySQL (via extensão MySQLi).
* **Front-end:** HTML5, CSS3, JavaScript.
* **Design:** Fontes *web* Instrument Sans e Inter.

## 3.2 Arquitetura Geral
O sistema segue um modelo em camadas para garantir a organização e manutenibilidade:

1.  **Camada de Apresentação (`views/` e `public/`):** Arquivos responsáveis por renderizar a interface.
2.  **Camada de Lógica de Negócio:** Estrutura presente nos arquivos de cada módulo.
3.  **Camada de Persistência (`config/database.php`):** Conexão com o MySQL e execução de queries seguras.
4.  **Camada de Autenticação (`auth/`):** Gerencia sessões, login e logout.

**Figura 1 – Diagrama da Arquitetura do Sistema**


    graph TD
    User((Usuário))
    subgraph "Camada de Apresentação (Views/Public)"
        HTML[Interface HTML/CSS]
        JS[Interatividade JS]
    end
    
    subgraph "Camada de Aplicação (Backend)"
        Auth["Autenticação (Auth)"]
        Logic["Lógica de Negócio (PHP)"]
    end
    
    subgraph "Camada de Dados (Persistência)"
        Config["Conexão (Database.php)"]
        DB[(Banco de Dados MySQL)]
    end

    User -->|Acessa| HTML
    HTML -->|Envia Requisição| Logic
    Logic -->|Verifica Sessão| Auth
    Logic -->|Query SQL| Config
    Config -->|Persistência| DB
    DB -->|Retorna Dados| Config
    Config -->|Dados| Logic
    Logic -->|Renderiza| HTML

---

# 4. MODELO DE DADOS

O sistema utiliza um banco de dados relacional MySQL composto por seis tabelas principais:

* **`usuarios`**: Gerencia o acesso ao sistema (`usuario_id`, `nome_usuario`, `senha`, `tipo_usuario`).
* **`clientes`**: Armazena dados dos consumidores (`cliente_id`, `nome`, `email`, `telefone`, `cpf`).
* **`estoque`**: Controla os produtos (`produto_id`, `nome`, `quantidade`, `valor`, `limite_alerta`).
* **`pedidos`**: Cabeçalho dos pedidos (`pedido_id`, `cliente_id`, `data`, `valor`, `status`).
* **`item_pedido`**: Relaciona produtos aos pedidos (`item_pedido_id`, `pedido_id`, `produto_id`, `quantidade`).
* **`licencas`**: Monitora a validade de documentos (`licenca_id`, `nome`, `validade`, `caminho_documento`).

**Figura 2 – Diagrama de Entidade-Relacionamento**


    erDiagram
    USUARIOS {
        int usuario_id PK
        string nome_usuario
        string senha
        enum tipo_usuario
    }
    CLIENTES ||--o{ PEDIDOS : "realiza (1:N)"
    CLIENTES {
        int cliente_id PK
        string nome
        string cpf UK
        string email
        string telefone
    }
    
    PEDIDOS ||--|{ ITEM_PEDIDO : "contém (1:N)"
    PEDIDOS {
        int pedido_id PK
        int cliente_id FK
        date data_pedido
        float valor
        string mesa
        enum status
    }

    ESTOQUE ||--o{ ITEM_PEDIDO : "fornece (1:N)"
    ESTOQUE {
        int produto_id PK
        string nome
        int quantidade
        float valor
        string unidade_medida
        int limite_alerta
    }

    ITEM_PEDIDO {
        int item_pedido_id PK
        int produto_id FK
        int pedido_id FK
        int quantidade
        string produto_nome
    }

    LICENCAS {
        int licenca_id PK
        string nome_licenca
        string cnpj
        string orgao_responsavel
        date data_validade
        enum prazo_expiracao
        string caminho_documento
    }

---

# 5. REQUISITOS FUNCIONAIS

## 5.1 Módulo Clientes
* **RF-C-001:** Cadastrar novos clientes.
* **RF-C-002:** Verificar duplicidade de CPF automaticamente.
* **RF-C-003:** Exibir histórico de compras do cliente.
* **RF-C-004:** Impedir exclusão de clientes com pedidos em aberto.

## 5.2 Módulo Pedidos
* **RF-P-001:** Criar novos pedidos associados a clientes e mesas.
* **RF-P-002:** Atualizar o estoque automaticamente na criação do pedido.
* **RF-P-003:** Reverter o estoque automaticamente ao excluir um pedido.
* **RF-P-004:** Alterar status do pedido (Aberto/Concluído).
* **RF-P-005:** Filtrar pedidos por mesa, cliente ou data.

## 5.3 Módulo Estoque
* **RF-E-001:** Realizar CRUD (Criar, Ler, Atualizar, Deletar) de produtos.
* **RF-E-002:** Registrar entradas de estoque.
* **RF-E-003:** Realizar exclusão múltipla de produtos via *checkbox*.

## 5.4 Módulo Licenças
* **RF-L-001:** Cadastrar licenças com data de validade.
* **RF-L-002:** Realizar *upload* de documentos comprovatórios.
* **RF-L-003:** Exibir *status* de vencimento (Vencida, Próximo Vencimento, Ativa).

---

# 6. REQUISITOS NÃO FUNCIONAIS

## 6.1 Segurança
* **RNF-S-001:** Todas as páginas internas devem ser protegidas por autenticação de sessão.
* **RNF-S-002:** O sistema deve utilizar *Prepared Statements* para prevenir *SQL Injection*.
* **RNF-S-003:** O sistema deve validar a existência física de arquivos antes da exclusão.

## 6.2 Usabilidade
* **RNF-U-001:** O sistema deve possuir interface limpa com navegação lateral fixa.
* **RNF-A-001:** O sistema deve exibir alertas visuais para produtos com estoque baixo.

---

# 7. REGRAS DE NEGÓCIO

1. O **CPF** deve ser único em toda a base de clientes.
2. Um cliente que possui um **pedido com status "Aberto"** não pode ser excluído do sistema.
3. Ao **criar** um pedido, a quantidade dos itens deve ser subtraída do estoque imediatamente.
4. Ao **excluir** um pedido, a quantidade dos itens deve ser devolvida (somada) ao estoque.
5. As **licenças** são classificadas automaticamente:
    * *Vencida*: Data de validade anterior à data atual.
    * *Próximo Vencimento*: Dentro do prazo de alerta configurado.
    * *Ativa*: Fora do prazo de alerta.
6. Produtos com quantidade igual ou inferior ao `limite_alerta` devem exibir um aviso de destaque no painel de estoque.

---

# 8. CASOS DE USO (RESUMIDOS)

* **UC01 – Gerenciar Clientes:** O Administrador acessa o módulo, cadastra um cliente e o sistema valida o CPF.
* **UC02 – Gerar Pedido:** O Atendente seleciona um cliente e uma mesa, adiciona itens ao carrinho e salva. O sistema baixa o estoque.
* **UC03 – Controlar Estoque:** O Gerente cadastra um produto novo ou lança uma entrada de mercadoria.
* **UC04 – Gerenciar Licenças:** O Administrador cadastra uma licença, faz o *upload* do PDF e o sistema monitora a data de validade.

---

# 9. MANUAL DE INSTALAÇÃO

1. Instalar um servidor local (XAMPP, WAMP ou equivalente).
2. Clonar o repositório do projeto na pasta pública do servidor (`htdocs` ou `www`).
3. Importar o arquivo `database.sql` utilizando o phpMyAdmin ou terminal MySQL.
4. Configurar o arquivo `config/database.php` com as credenciais do banco de dados local.
5. Garantir permissões de escrita na pasta `/uploads/licencas`.
6. Acessar o sistema pelo navegador: `http://localhost/Cafe_Alvorada/public/`.

---

# 10. MANUAL DO USUÁRIO

* **Login:** Acesse a tela inicial e informe seu usuário e senha.
* **Clientes:** Use o menu lateral para acessar "Clientes". Clique em "Novo Cliente" para cadastrar ou nos ícones de lápis/lixeira para editar/excluir.
* **Pedidos:** Em "Pedidos", clique em "Novo Pedido". Selecione o cliente, adicione produtos e salve. Para finalizar, clique em "Concluir".
* **Estoque:** Acompanhe a lista de produtos. Itens em amarelo indicam estoque baixo. Use os botões de ação para dar entrada ou saída manual.
* **Licenças:** Cadastre as licenças do estabelecimento. O sistema avisará com etiquetas coloridas sobre a proximidade do vencimento.

---

# 11. PLANO DE TESTES

Abaixo, os principais casos de teste executados para validação do sistema:

| ID | Caso de Teste | Ação Executada | Resultado Esperado |
| :--- | :--- | :--- | :--- |
| CT-01 | Cadastro de Cliente Duplicado | Tentar cadastrar cliente com CPF já existente. | Sistema exibe erro e bloqueia cadastro. |
| CT-02 | Baixa de Estoque | Criar um pedido com 5 unidades de um produto. | Estoque do produto reduz em 5 unidades. |
| CT-03 | Reversão de Estoque | Excluir o pedido criado no CT-02. | Estoque do produto aumenta em 5 unidades. |
| CT-04 | Alerta de Licença | Cadastrar licença com data de ontem. | Status exibe "Vencida" em vermelho. |
| CT-05 | Segurança de Acesso | Tentar acessar `dashboard.php` sem logar. | Redirecionamento para `login.php`. |

---

# 12. CONSIDERAÇÕES FINAIS

O sistema *Café Alvorada* atende às necessidades essenciais de gestão de uma cafeteria, integrando pedidos, estoque, clientes e licenças de forma eficiente. A arquitetura simples e modular facilita a manutenção e permite futuras expansões, como a inclusão de módulos financeiros, integração fiscal e desenvolvimento de aplicativo móvel.

