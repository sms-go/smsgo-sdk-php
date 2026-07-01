# Publicando o `orynlabs/smsgo` (Packagist)

Guia de release do SDK PHP. Registry: **Packagist** · pacote `orynlabs/smsgo`. O Composer resolve a versão a partir das **tags git** — o [`composer.json`](composer.json) **não** tem (e não deve ter) campo `version`.

## Pré-requisitos (uma vez)

1. Conta em [packagist.org](https://packagist.org).
2. **Submeter o pacote** uma vez: _Submit_ → cole a URL `https://github.com/SMSFy/smsgo-sdk-php`. O Packagist lê o `composer.json` e registra o nome `orynlabs/smsgo`.
3. **Auto-update** (para novas tags publicarem sozinhas): instale o **Packagist GitHub App** no repo (recomendado), ou configure o webhook do Packagist em _Settings → Webhooks_ com seu API token do Packagist. Guia: https://packagist.org/about#how-to-update-packages

## Passo a passo do release

1. `master` verde no CI (`composer validate --strict`, PHPStan, PHPUnit).
2. Atualize o [`CHANGELOG.md`](CHANGELOG.md). (A versão vem da tag — **não** edite `composer.json`.)
3. Commit + push na `master`.
4. **Tag semver:**
   ```bash
   git tag v0.3.0 && git push origin v0.3.0
   ```
5. O Packagist detecta a tag via webhook/App e publica `0.3.0`. Sem auto-update, clique **Update** na página do pacote.

## Verificação pós-publicação

```bash
mkdir /tmp/t && cd /tmp/t && composer init --no-interaction --name=test/t
composer require orynlabs/smsgo:^0.3
php -r "require 'vendor/autoload.php'; var_dump(class_exists(Orynlabs\SMSGo\Client::class));"
```
Página: https://packagist.org/packages/orynlabs/smsgo

## Notas

- **Não** adicione `version` ao `composer.json` — deixe o Packagist derivar das tags (evita divergência).
- Tags são **imutáveis** na prática (o Packagist cacheia). Para corrigir, suba `v0.3.1`.
- Restrinja o pacote a PHP ≥ 8.1 (já no `require`). Mantenha a tag alinhada às versões dos outros SDKs. Ver o guia central [`api/docs/sdks-publicacao.md`](../api/docs/sdks-publicacao.md).
