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
8. Rode a execucao completa em `/admin/collections.php` ou configure o CronJob para chamar `cron/daily.php`.

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

## Noticias e Priorizacao

A rotina diaria tambem tenta coletar noticias por RSS configurado em `app/config.php`.
No MVP, as fontes padrao usam Google News Brasil, Mundo e Tecnologia.

Se a coleta RSS falhar, o sistema usa temas seed para manter o ranking funcionando.

O score de priorizacao combina:

```text
relevancia historica       peso principal, a partir de base_score
conexao com noticias       termos dos eventos comparados a noticias do dia
conexao com tendencias     termos dos eventos comparados a tendencias do dia
aniversario significativo  10, 25, 50, 100 anos e multiplos relevantes
categoria em pauta         categoria do evento presente nos topicos atuais
diversidade editorial      pequeno ajuste para evitar repeticao de categoria
```

No admin, o processo pode ser operado em etapas:

```text
/admin/collections.php     Tela unica de coletas operacionais
/admin/contexts.php        Lista noticias e tendencias persistidas
/admin/apply-score.php     Aplica o score de prioridade
/admin/priority.php        Lista fatos ranqueados e parametros do score
```

A coleta de tendencias usa conectores para GDELT Project, Wikimedia Pageviews API, Agencia Brasil RSS e Hacker News API. O conector Media Cloud esta preparado, mas fica desativado por padrao ate informar endpoint e chave em `app/config.local.php`. Se as fontes externas falharem ou retornarem vazio, o MVP deriva tendencias a partir dos termos mais frequentes nas noticias coletadas do dia, salvando esses itens como `trend:derived-news`.

Noticias e tendencias coletadas tambem sao persistidas em `collected_contexts`, com chave unica por data, tipo, fonte e titulo normalizado. A tabela `current_topics` fica como area operacional para o score; `collected_contexts` e a base higienizada/auditavel.
