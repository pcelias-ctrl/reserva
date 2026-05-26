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

## Escopo da primeira versao

- Reserva publica sem login.
- Reserva com cliente logado.
- Cadastro/login simples de cliente.
- Ocasioes especiais cadastraveis no admin.
- Perguntas dinamicas cadastraveis no admin.
- Campos de aniversario, restricao alimentar, observacoes e LGPD.
- Envio de email via `mail()` quando disponivel.
- Cockpit administrativo com indicadores, agenda e acoes de aprovacao/confirmacao.
- Cadastro de ambientes.
- Cadastro visual de mesas por ambiente com posicao `x/y` salva em banco.

## Proximos incrementos sugeridos

- Bloqueio automatico por capacidade e disponibilidade real.
- SMTP autenticado.
- Politicas detalhadas de cancelamento/no-show.
- Editor visual mais avancado para plantas de salao.
- Multi-restaurante/multiunidade.
