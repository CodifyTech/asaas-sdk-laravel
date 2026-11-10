# Changelog

Todas as mudanças relevantes deste projeto são registradas aqui.

O formato segue o [Keep a Changelog 1.1.0](https://keepachangelog.com/pt-BR/1.1.0/) e o projeto adota o [Versionamento Semântico](https://semver.org/lang/pt-BR/).

## [Unreleased]

## [1.0.2] - 2026-11-10

### Changed

- O `User-Agent` padrão passa a ser `codifytech-asaas-sdk-laravel/1.0.2`.

## [1.0.1] - 2026-11-10

### Changed

- Suporte ao Guzzle 8: `guzzlehttp/guzzle` passa de `^7.8` para `^7.8.2|^8.0`. O Laravel 12 continua usando a 7.x.

### Removed

- Suporte a Laravel 11. O SDK passa a exigir Laravel 12 ou 13 (`illuminate/*` `^12.0|^13.0`, `orchestra/testbench` `^10.0|^11.0` em dev).

## [1.0.0] - 2026-11-10

Primeira versão pública.

### Added

- 34 serviços cobrindo os 213 endpoints oficiais da API Asaas v3, acessíveis por `asaas()` ou por `Asaas\Laravel\AsaasSdk`.
- Models de resposta tipados, listas paginadas (`items`, `totalCount`, `hasMore`), `GenericResource`, `GenericList` e resultados de remoção.
- Validação de entrada antes de cada chamada HTTP: campos obrigatórios e enums do corpo, query estrita (chaves desconhecidas lançam `ValidationException`).
- Exceções tipadas: `ErrorResponseDtoException` para HTTP 400 e `ApiError` para os demais status não 2xx.
- Upload multipart com `SplFileInfo`, `UploadedFile` ou stream resource.
- `AsaasSdk::fromConfig()` para instâncias independentes (multi-conta e subcontas).
- Retry com backoff exponencial e jitter, configurável por `retry.*`, respeitando `Retry-After` até `max_delay`. `POST` não é repetido por padrão.
- Endurecimento de segurança:
  - `base_url` exige `https://`; `http://` só é aceito para hosts de loopback.
  - `ASAAS_ENV` aceita apenas `production` (ou `prod`) e `sandbox`; outros valores lançam `InvalidArgumentException`.
  - Parâmetros de rota vazios, `.` ou `..` lançam `InvalidArgumentException` antes da requisição.
  - API key marcada com `#[\SensitiveParameter]` e oculta (`***`) em saídas de debug dos objetos de configuração.
  - Os headers de autenticação e `User-Agent` não podem ser sobrescritos por requisição, e headers com quebra de linha são rejeitados.
  - Redirects não são seguidos, para a API key não vazar para outro host.
  - Mensagens de erro limitadas a 500 caracteres, para páginas HTML não irem para os logs.
  - Nomes de arquivo de upload sanitizados (sem diretórios nem caracteres de controle).
  - Models convertem os campos de forma tolerante: um tipo inesperado vira `null`, e o valor original continua em `$data`.
- `declare(strict_types=1);` em todos os arquivos PHP.
- Skill para agentes de IA, carregada automaticamente pelo Laravel Boost, e playbook de uso com IA.
- Gerador de código a partir de `tools/endpoint-map.json` e da especificação oficial.
- Suporte a PHP 8.4 e 8.5 com Laravel 11, 12 e 13.

[Unreleased]: https://github.com/CodifyTech/asaas-sdk-laravel/compare/v1.0.2...HEAD
[1.0.2]: https://github.com/CodifyTech/asaas-sdk-laravel/compare/v1.0.1...v1.0.2
[1.0.1]: https://github.com/CodifyTech/asaas-sdk-laravel/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/CodifyTech/asaas-sdk-laravel/releases/tag/v1.0.0
