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
