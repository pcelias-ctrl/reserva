# Manual de uso do i-Reserva

Este manual orienta a configuracao inicial e o uso diario do i-Reserva. A ordem abaixo foi pensada para colocar o sistema em operacao com seguranca, evitando cadastrar reservas antes de preparar restaurantes, horarios, ambientes e mesas.

## 1. Acesso administrativo

Entre na area administrativa pela URL:

`https://reserva-online-pcelias.fly.dev/admin/login.php`

Use o e-mail e a senha administrativa cadastrados. Depois do primeiro acesso definitivo, altere a senha em **Manutencao > Alterar senha administrativa**.

## 2. Ordem recomendada de cadastro

Antes de divulgar o link publico, cadastre nesta ordem:

1. **Restaurantes**
2. **SMTP/e-mail do restaurante**
3. **Horarios de funcionamento**
4. **Dias especiais**
5. **Ambientes**
6. **Mesas e layout fixo**
7. **Ocasioes**
8. **Questionario**
9. **Teste de reserva publica**
10. **Teste de e-mails e WhatsApp**

## 3. Restaurantes

Menu: **Restaurantes**

Cadastre cada restaurante que podera receber reservas.

Campos principais:

- **Nome comercial**: nome exibido ao cliente.
- **Razao social**: opcional, para identificacao interna.
- **CNPJ/Documento**: opcional.
- **E-mail**: contato do restaurante.
- **Telefone**: contato geral.
- **WhatsApp do restaurante**: usado para montar a mensagem de nova reserva.
- **URL do logo** ou **Upload da foto/logo**: imagem exibida na tela publica.
- **Endereco**: exibido para o cliente.
- **Mensagem padrao da reserva**: texto de apoio do restaurante.
- **Status**: deixe como `Ativo` para aparecer ao publico.

Depois de salvar, confira se o logo aparece corretamente na lista de restaurantes e na tela publica.

## 4. SMTP e e-mail do restaurante

Menu: **Restaurantes**

Dentro do cadastro do restaurante, configure a caixa **SMTP do restaurante**.

Campos principais:

- **Usar SMTP proprio para este restaurante**: marque para ativar envio por conta propria.
- **Servidor SMTP**: exemplo `smtp.gmail.com`.
- **Porta**: geralmente `587` para TLS ou `465` para SSL.
- **Usuario**: e-mail usado para autenticar.
- **Senha**: senha SMTP ou senha de app.
- **Seguranca**: TLS, SSL ou nenhuma.
- **E-mail de destino administrativo**: recebe novas reservas.
- **Remetente**: e-mail que aparece como remetente.
- **Nome do remetente**: nome exibido no e-mail.

Use o botao **Enviar e-mail de teste SMTP** antes de divulgar o sistema.

Fluxos de e-mail:

- Nova reserva: cliente recebe comprovante, se informou e-mail.
- Nova reserva: restaurante/admin recebe os dados.
- Atualizacao de status: cliente recebe aviso quando a reserva muda de status.
- Feedback: quando a reserva for concluida ou marcada como nao compareceu, pode ser enviado e-mail de feedback.

Observacao importante: as mensagens informam que a reserva possui tolerancia maxima de 15 minutos apos o horario marcado.

## 5. Horarios de funcionamento

Menu: **Configuracoes > Horarios**

Selecione o restaurante e configure os horarios por dia da semana.

Cada dia possui:

- **Almoco**
- **Jantar**

Para cada periodo, informe:

- horario de abertura
- horario de fechamento
- se o periodo esta fechado

Esses horarios sao usados na tela publica para gerar opcoes a cada 30 minutos. Exemplo: jantar das 19:00 as 23:00 mostra 19:00, 19:30, 20:00 etc.

## 6. Dias especiais

Menu: **Configuracoes > Dias especiais**

Use para datas em que o restaurante tera horarios diferentes, como Dia dos Namorados, Natal, Reveillon, eventos fechados ou menus especiais.

Campos:

- **Nome do dia especial**: exemplo `Dia dos Namorados`.
- **Data**: dia exato do evento.
- **Periodo**: almoco ou jantar.
- **Horarios disponiveis**: um horario por linha, ou separados por virgula.
- **Status**: ativo ou inativo.

Exemplo para Dia dos Namorados:

```text
19:00
21:30
```

Quando existe um dia especial ativo para a data escolhida, a tela publica mostra somente os horarios cadastrados nele. Os horarios semanais normais ficam temporariamente substituidos naquela data.

## 7. Ambientes

Menu: **Configuracoes > Layout de mesas**

Cadastre os ambientes de cada restaurante, por exemplo:

- Salao principal
- Varanda
- Area externa
- Mezanino
- Espaco reservado

Campos:

- **Restaurante**
- **Nome**
- **Descricao**
- **Largura**
- **Altura**
- **Status**

A largura e altura definem o tamanho do mapa visual usado no layout de mesas e na ocupacao.

## 8. Mesas e layout fixo

Menu: **Configuracoes > Layout de mesas**

Depois de criar o ambiente, cadastre as mesas fixas.

Campos:

- **Identificacao**: exemplo `M01`, `M02`, `V01`.
- **Formato**: quadrada ou redonda.
- **Lugares**: quantidade de assentos.

Depois de cadastrar, arraste as mesas no editor visual do salao. A posicao e salva automaticamente.

Tipos visuais:

- mesa de 2 lugares: quadrada
- mesa de 4 lugares: retangular
- mesa redonda: exibida como redonda
- cadeiras aparecem ao redor da mesa conforme a quantidade de lugares

Esse e o layout original. A tela **Ocupacao** permite alterar o layout apenas para um dia especifico sem mexer nesse cadastro.

## 9. Ocasioes

Menu: **Configuracoes > Ocasioes**

Cadastre motivos comuns de reserva, como:

- Aniversario
- Reuniao de negocios
- Celebracao
- Jantar romantico
- Confraternizacao

Se a ocasiao for aniversario, marque a opcao para pedir dia e mes do aniversario.

## 10. Questionario

Menu: **Configuracoes > Questionario**

Use para perguntas personalizadas que o restaurante quer fazer ao cliente.

Tipos de pergunta:

- texto curto
- texto longo
- selecao
- sim/nao

Tambem e possivel:

- definir ordem
- marcar como obrigatoria
- ativar ou inativar
- editar
- excluir

Exemplos:

- Preferencia de local?
- Tem alguma restricao alimentar importante?
- Deseja cadeira infantil?
- Prefere ambiente interno ou externo?

## 11. Tela publica de reserva

Menu publico: **Reservar**

Fluxo do cliente:

1. Escolhe o restaurante pelo card com logo e informacoes.
2. Seleciona data.
3. Seleciona horario disponivel.
4. Informa numero de pessoas.
5. Escolhe ambiente preferido, se desejar.
6. Preenche nome e telefone.
7. Informa e-mail, se quiser receber acompanhamento por e-mail.
8. Preenche preferencias, ocasiao e questionario.
9. Aceita os consentimentos LGPD.
10. Envia a reserva.

Regras importantes:

- e-mail e opcional.
- telefone/WhatsApp e obrigatorio.
- em dias especiais, aparecem somente os horarios especiais cadastrados.
- a tela informa tolerancia maxima de 15 minutos.
- ao finalizar, o sistema prepara a mensagem de WhatsApp para o restaurante.

## 12. Area do cliente

Menu publico: **Entrar** ou **Minhas reservas**

Quando o cliente cria senha e informa e-mail, ele pode acompanhar reservas depois.

Na area logada, o cliente pode:

- ver suas reservas
- acompanhar status
- enviar feedback quando a reserva estiver concluida ou marcada como nao compareceu

## 13. Painel administrativo

Menu: **Painel**

E a visao operacional das reservas.

Recursos:

- visao semanal, quinzenal ou mensal
- filtro por status
- agenda com reservas por dia
- identificacao visual por reserva
- clique para abrir detalhes
- acao pos-reserva para agradecimento e feedback

Use essa tela para acompanhar o movimento geral.

## 14. Reservas

Menu: **Reservas**

Use para gerenciar cada reserva individualmente.

Status disponiveis:

- **Aguardando aprovacao**
- **Confirmada (aprovada)**
- **Cancelada (nao aprovada)**
- **Concluida (mandar feedback)**
- **Nao compareceu (colher feedback)**

Boas praticas:

- aprove/confirme reservas validas.
- cancele reservas que nao serao atendidas.
- marque como concluida depois do atendimento.
- marque como nao compareceu quando o cliente nao vier.
- envie feedback quando fizer sentido.

## 15. Ocupacao

Menu: **Ocupacao**

Essa e a tela para montar o salao do dia e alocar reservas em mesas.

Fluxo recomendado:

1. Escolha o restaurante.
2. Escolha o ambiente.
3. Clique no dia desejado na agenda.
4. Veja as reservas do dia.
5. Arraste mesas no mapa, se precisar ajustar.
6. Marque as mesas em cada reserva.
7. Clique em **Salvar mesas**.
8. Use **Imprimir layout** se quiser levar o mapa para a operacao.

Recursos importantes:

- juntar mesas para grupos grandes.
- separar mesa do dia, por exemplo uma mesa de 4 virar duas mesas de 2.
- adicionar mesa extra para datas especiais.
- remover mesa fixa apenas naquele dia.
- restaurar mesa removida.
- limpar layout do dia e voltar ao original.

Exemplo de quebra:

- mesa original M04 tem 4 lugares.
- voce cria uma mesa do dia de 2 lugares com origem na M04.
- naquele dia a M04 passa a aparecer com 2 lugares.
- a nova mesa tambem aparece com 2 lugares.
- o cadastro original continua como M04 de 4 lugares.

Exemplo de data especial:

- no Dia dos Namorados, remova mesas grandes do dia.
- adicione mesas extras de 2 lugares.
- arraste tudo para o layout especial.
- salve as reservas nas mesas.
- imprima o layout do dia.

## 16. Limpar layout do dia

Menu: **Ocupacao**

Use quando o layout de uma data ficou errado ou precisa ser refeito.

O botao **Limpar layout do dia** remove somente daquele ambiente e data:

- mesas extras do dia
- mesas ocultadas do dia
- posicoes alteradas
- ajustes de lugares
- alocacoes de reserva em mesa

Depois disso, o ambiente volta ao layout fixo original cadastrado em **Configuracoes > Layout de mesas**.

## 17. Manutencao

Menu: **Manutencao**

Use com cuidado.

Recursos:

- excluir reservas de teste
- alterar senha administrativa

Para limpar reservas de teste:

1. marque as reservas desejadas.
2. clique em **Excluir selecionadas**.
3. confirme a exclusao.

Essa acao nao pode ser desfeita.

## 18. Checklist antes da inauguracao

Antes de divulgar o link:

- restaurantes cadastrados e ativos
- logos aparecendo na tela publica
- WhatsApp do restaurante correto
- SMTP testado com sucesso
- horarios semanais revisados
- dias especiais cadastrados, se houver
- ambientes cadastrados
- mesas posicionadas no layout fixo
- ocasioes cadastradas
- questionario revisado
- uma reserva de teste enviada pela tela publica
- e-mail de cliente recebido
- e-mail administrativo recebido
- WhatsApp gerado corretamente
- reserva aprovada e e-mail de status testado
- ocupacao testada com alocacao de mesa
- layout especial testado, se houver evento
- senha administrativa alterada
- reservas de teste excluidas

## 19. Link para divulgar

Use a URL publica:

`https://reserva-online-pcelias.fly.dev/`

Esse e o link que pode ser usado no botao de reservas do Google Business Profile, Instagram, site do restaurante ou WhatsApp.

