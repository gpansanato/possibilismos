# MVP Historico Diario

MVP em PHP + MySQL para selecionar fatos historicos do dia, ranquear com base em temas atuais e publicar os aprovados.

## Estrutura

```text
app/       Nucleo PHP, banco, auth, ranking e fontes
admin/     Telas administrativas
cron/      Script diario protegido por token
public/    Pagina publica
sql/       Schema inicial
```

## Configuracao

1. Crie um banco MySQL na hospedagem.
2. Importe `sql/schema.sql`.
3. Copie `app/config.local.example.php` para `app/config.local.php`.
4. Edite `app/config.local.php` com dados do banco e um token de cron.
5. Importe `sql/seed.sql` se quiser dados de teste para 22 de maio.
6. Acesse `/admin/create-user.php` uma vez para criar o primeiro usuario.
7. Cadastre eventos em `/admin/events.php`.
8. Rode `/admin/run.php` ou configure o CronJob para chamar `cron/daily.php`.

## Migracoes

Em bancos ja publicados, aplique os arquivos em `sql/migrations/` antes de sincronizar codigo que depende de novas colunas.

Migracao atual:

```text
sql/migrations/2026_05_22_event_review_status.sql
```

Ela cria o estado editorial dos eventos:

```text
pending   Nao avaliado
approved  Aprovado
rejected  Reprovado
```

## CronJob

Configure uma execucao diaria para:

```text
https://SEU_DOMINIO/cron/daily.php?token=SEU_TOKEN
```

O token deve ser o mesmo configurado em `app/config.php`.

## Proximo incremento

- Integrar RSS real de noticias em `app/sources.php`.
- Melhorar ranking com API de IA externa.
- Adicionar aprovacao em lote e historico por data.

## Coleta Inicial

A rotina diaria importa eventos historicos da API publica Wikimedia "On this day" antes de ranquear.
Por padrao, o sistema tenta Wikipedia em portugues e depois ingles, usando eventos `selected` e, se necessario, `events`.

Fonte tecnica:

```text
https://api.wikimedia.org/feed/v1/wikipedia/{language}/onthisday/{type}/{MM}/{DD}
```
