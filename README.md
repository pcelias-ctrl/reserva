# Reserva On-line

Sistema de reservas para restaurantes, com reserva publica ou em area logada,
interacao por email, consentimento LGPD e painel administrativo para agenda,
aprovacao de reservas, perguntas dinamicas, ocasioes, ambientes e layout de mesas.

## Requisitos

- PHP 8.0+
- MySQL 5.7+ ou MariaDB equivalente
- Servidor web apontando para a pasta `public`

## Instalacao local

1. Crie o banco executando `sql/database.sql`.
2. Configure as variaveis de ambiente, se necessario:

```bash
DB_HOST=localhost
DB_NAME=reserva_online
DB_USER=root
DB_PASS=
DB_PORT=3306
APP_URL=http://localhost/reserva-online/public
MAIL_FROM=reservas@seudominio.com
MAIL_ADMIN_TO=admin@seudominio.com
```

3. Acesse:

- Cliente: `/public/index.php`
- Admin: `/admin/login.php`

Opcional com PHP embutido:

```bash
php -S localhost:8080 -t public
```

Opcional com Docker:

```bash
docker build -t reserva-online .
docker run --rm -p 8080:80 --env DB_HOST=host.docker.internal reserva-online
```

Login inicial do administrador:

- Email: `admin@reserva.local`
- Senha: `admin123`

## Deploy no Fly.io

Este projeto ja inclui configuracao para rodar no Fly com PHP 8.2/Apache e MySQL
em um app separado.

Apps sugeridos:

- Aplicacao: `reserva-online-pcelias`
- Banco MySQL: `reserva-online-mysql-pcelias`
- Regiao: `gru` Sao Paulo

Comandos principais:

```bash
fly apps create reserva-online-mysql-pcelias
fly volumes create mysqldata --app reserva-online-mysql-pcelias --region gru --size 10
fly secrets set --app reserva-online-mysql-pcelias MYSQL_PASSWORD=SENHA_DO_USUARIO MYSQL_ROOT_PASSWORD=SENHA_ROOT
fly deploy --config fly.mysql.toml

fly apps create reserva-online-pcelias
fly secrets set --app reserva-online-pcelias DB_PASS=SENHA_DO_USUARIO MAIL_ADMIN_TO=admin@seudominio.com
fly deploy --config fly.toml
fly ssh console --app reserva-online-pcelias -C "php /var/www/html/scripts/init-db.php"
```

Depois do deploy:

- Cliente: `https://reserva-online-pcelias.fly.dev`
- Admin: `https://reserva-online-pcelias.fly.dev/admin/login.php`

## Escopo da primeira versao

- Reserva publica sem login.
- Reserva com cliente logado.
- Multi-restaurante com dados cadastrais, logo por URL e WhatsApp.
- Upload de foto/logo do restaurante salvo no banco de dados.
- Cadastro/login simples de cliente.
- Ocasioes especiais cadastraveis no admin.
- Perguntas dinamicas cadastraveis no admin.
- Campos de aniversario, restricao alimentar, observacoes e LGPD.
- Envio de email via `mail()` quando disponivel.
- Cockpit administrativo com indicadores, agenda e acoes de aprovacao/confirmacao.
- Cadastro de ambientes.
- Cadastro visual de mesas por ambiente com posicao `x/y` salva em banco.
- Edicao de ambientes, dimensoes do layout e dados das mesas cadastradas.
- Link de WhatsApp com mensagem pronta para o restaurante selecionado ao final da reserva.

## Proximos incrementos sugeridos

- Bloqueio automatico por capacidade e disponibilidade real.
- SMTP autenticado.
- Integracao com WhatsApp Business API para envio automatico sem acao manual do cliente.
- Politicas detalhadas de cancelamento/no-show.
- Editor visual mais avancado para plantas de salao.
- Multi-restaurante/multiunidade.
