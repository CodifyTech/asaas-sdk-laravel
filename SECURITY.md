# Política de segurança

## Versões suportadas

| Versão | Recebe correções de segurança |
|--------|-------------------------------|
| 1.x | sim |

Correções saem sempre na versão mais recente da série 1.x. Atualize para ela antes de reportar.

## Como reportar uma vulnerabilidade

Reporte em privado pelo GitHub Security Advisories:

https://github.com/CodifyTech/asaas-sdk-laravel/security/advisories/new

**Não abra issue pública, pull request ou discussão** sobre a falha antes da correção.

Inclua no relato:

- versão do SDK, do PHP e do Laravel;
- descrição do problema e do impacto;
- passos para reproduzir ou uma prova de conceito mínima;
- sugestão de correção, se tiver.

Nunca envie API keys reais, dados de cartão ou dados de clientes. Use chaves de sandbox e dados fictícios.

## Prazos

- Confirmação do recebimento em até 5 dias úteis.
- Após a análise, informamos se a falha foi aceita, o plano de correção e a previsão de release.
- A divulgação pública acontece depois da correção publicada, em comum acordo com quem reportou.

## Escopo

Dentro do escopo: o código deste pacote (`src/`, `config/`, `resources/`), por exemplo vazamento da API key, envio de credenciais sem TLS, path traversal em parâmetros de rota ou falhas de validação que permitam requisições indevidas.

Fora do escopo:

- falhas na API ou na plataforma da Asaas: reporte diretamente à Asaas;
- falhas no Laravel, no Guzzle ou em outras dependências: reporte ao projeto correspondente;
- problemas causados por configuração ou uso inseguro na sua aplicação, como registrar a API key em log ou habilitar retry de `POST`.
