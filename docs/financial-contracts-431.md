# Contratos financeiros — fase 4.3.1

Esta entrega prepara infraestrutura. Não ativa checkout, finalização, cancelamento,
estorno ou cobrança de OS, não altera permissões e não instala schema em banco persistente.

Base: 53d8d697f7518d7c01b39fe1eb5ba3255dd19b35.
Branch: feat/contratos-financeiros-431.

## Contratos

- CheckoutCommand: versão 1, UUID, caixa explícito, itens, total esperado e pagamentos confirmados.
- QuoteCommand: versão 1, UUID e itens; não aceita caixa, pagamento ou efeitos.
- FinalizeOrderCommand: versão 1, UUID, pedido, hash verificável do orçamento,
  caixa explícito e pagamentos. Itens vêm do pedido, não do navegador.
- OrderResult: fotografia comercial versionada, sem custo, com valores em strings.
- As listas semanticamente não ordenadas são normalizadas sem perder multiplicidade.
- Serviços sem produto estão reservados para a integração 4.3.5 e são rejeitados
  por este contrato de persistência de itens de produto.

## Valores e autorização

Money aceita somente strings decimais ou centavos inteiros, em PHP 64 bits.
O limite é 9.999.999.999 centavos; quantidade máxima de 2.147.483.647.
Multiplicação, agregação e descontos verificam limites antes de calcular.
Nenhuma conversão implícita de float é aceita.

CatalogProduct é uma fotografia confiável do repositório futuro, consultado sob bloqueio.
OrderAmountCalculator confere catálogo, atividade, estoque agregado, preços e soma exata.
Orçamentos não reservam estoque. Untracked é política do servidor, nunca um checkbox
livre enviado pelo cliente. A ausência da coluna/política significa tracked nos novos contratos.

DenyPriceExceptions é o padrão. Não presume que pos.sell autorize desconto.
Uma autorização futura deve ser validada no servidor; testes usam aprovador fictício.
Nenhum permissionamento real ou seeder foi modificado.

## Transação e identidade

coordinate() mantém assinatura, retorno, tentativas e suporte anterior a transações aninhadas.
orderTransaction() é o novo contrato que exige transação externa própria.
FinancialContext prova propriedade, conexão e duração do bloqueio; não pode ser reutilizado após o término.
O instante é UTC e permanece estável nas tentativas de uma operação nova.

Ordem: linha global, identidades, caixa (quando financeiro), sequência, pedido/itens,
produtos ordenados, gravações, comprovantes, vínculos e commit.
O preparador é código interno confiável, não uma extensão recebida por HTTP.
A integração futura deve bloquear e revalidar catálogo/pedido e chamar as políticas
antes de preparar os registros. Esta fase usa preparadores sintéticos exclusivamente nos testes.

operation_receipts identifica globalmente comandos novos. financial_operations
permanece autoritativa para o resultado financeiro. UUID, operação, autor e hash
do par são vinculados por FK composta. O JSON financeiro não é duplicado:
result fica NULL no registro global quando há comprovante financeiro.

Orçamentos armazenam seu resultado somente no registro global. Aberturas de caixa
preservam o resultado 4.1 derivado do ID do comprovante, inclusive quando seu JSON é NULL.
A recuperação histórica consulta financial_operations e não cria registros retroativos.

Os algoritmos de hash das operações de caixa e seus contratos públicos foram preservados.
Novos resultados de pedidos usam ordem canônica tanto na primeira resposta quanto no reenvio;
isso também evita depender da ordem das propriedades em JSON nativo MySQL.

## Schema e invariantes

Cinco migrations criam seis tabelas:
operation_receipts, order_settlements, order_cash_effects, order_stock_effects,
order_item_snapshots e document_sequences; acrescentam índices auxiliares e
products.stock_policy anulável.

As FKs são criadas somente nas tabelas novas. Nenhuma tabela legada é reconstruída.
FinancialSchema compila CHECK dentro do CREATE. SQLite verifica também tipo inteiro
nos campos de centavos/quantidade; MySQL usa suas colunas inteiras. JSON e DECIMAL
não são tratados como tipos idênticos entre os motores.

Há unicidade de UUID novo, comprovante financeiro pareado, liquidação por pedido,
lançamento por pagamento, efeito e fotografia. FKs compostas impedem troca de
pedido, caixa, produto e identidade entre os vínculos.
Todos os novos vínculos usam RESTRICT. Aritmética, tipo de efeito e correspondência
de valores são também conferidos pelo serviço antes do commit.

Uma tabela não consegue impor UNIQUE entre ela e outra tabela histórica.
A prevenção de colisão histórica exige o protocolo compartilhado sob bloqueio.
Ausência das estruturas necessárias produz falha, nunca escrita financeira sem coordenação.

Modelos financeiros são fachadas de leitura. Chaves compostas usam métodos explícitos
de consulta; não simulam suporte nativo do Eloquent a find/save compostos.

## Numeração e horários

Ano comercial America/Recife, calculado de instante UTC fixado no contexto.
Pedidos e orçamentos compartilham AAAA-NNNNNN; finalizar não aloca outro número.
O primeiro uso lê e valida os números existentes sob coordenação e inicializa
o contador vazio. MAX(id), renumeração e salto silencioso de conflito não são usados.
Formatos ambíguos e esgotamento interrompem a operação; rollback restaura a reserva.
Novos campos *_utc são DATETIME com valores UTC explícitos.
Horários históricos e configuração permanente da conexão não são reinterpretados.

## Testes e limites de entrega

Os testes usam exclusivamente SQLite :memory:, com asserts antes de migrar e
conexão MySQL desabilitada nos testes funcionais. Migrações 4.1/4.2 são aplicadas
separadamente para gerar schemas legados reais antes do upgrade.
São conferidos DDL, FKs, índices, CHECKs, integridade referencial, hashes originais,
rollback em cada gravação, limites, autorização, reenvio e virada de ano.

CashOpeningTest deixou de adulterar o tipo de um comprovante vinculado:
isso agora é bloqueado pela FK. O conflito entre operações é exercitado por HTTP
e a rejeição da adulteração é testada separadamente.

Nenhum teste SQLite comprova concorrência InnoDB ou precisão física de DECIMAL MySQL.
Uma homologação futura, com aprovação e isolamento, continua necessária.

## Ativação futura

Não publicar o código de caixa dependente desse schema antes de instalar e validar
as adições no ambiente autorizado. Não executar down automaticamente em falha de DDL.
Escritores antigos não podem coexistir com novos escritores sem conhecer a identidade
global; processos persistentes precisarão ser reiniciados numa ativação controlada.

PDV, pedidos e OS existentes continuam fora dos novos contratos nesta entrega.
Não há garantia financeira integral enquanto todos os escritores não forem adaptados.
Numeração continua legada nos controllers até a integração posterior.
Cancelamentos, estornos, crédito pendente e liquidação gratuita não foram implementados.

## Arquivos

Modificados:
- app/Services/FinancialCoordinator.php
- app/Models/Product.php
- tests/Feature/CashOpeningTest.php

Criados:
- app/Contracts/Orders/OrderCommand.php
- app/Contracts/Orders/PriceExceptionAuthorization.php
- app/Data/Orders/CatalogProduct.php
- app/Data/Orders/CheckoutCommand.php
- app/Data/Orders/FinalizeOrderCommand.php
- app/Data/Orders/OrderCommandData.php
- app/Data/Orders/OrderInput.php
- app/Data/Orders/OrderResult.php
- app/Data/Orders/OrderTotals.php
- app/Data/Orders/PreparedOrderOperation.php
- app/Data/Orders/QuoteCommand.php
- app/Models/DocumentSequence.php
- app/Models/OperationReceipt.php
- app/Models/OrderCashEffect.php
- app/Models/OrderItemSnapshot.php
- app/Models/OrderSettlement.php
- app/Models/OrderStockEffect.php
- app/Models/ReadOnlyFinancialRecord.php
- app/Services/DenyPriceExceptions.php
- app/Services/FinancialContext.php
- app/Services/OperationReceiptStore.php
- app/Services/OrderAmountCalculator.php
- app/Services/OrderLinkWriter.php
- app/Services/OrderNumberService.php
- app/Services/OrderOperationExecutor.php
- app/Services/OrderStockPolicy.php
- app/Services/QuoteVersion.php
- app/Support/CanonicalJson.php
- app/Support/FinancialSchema.php
- app/Support/Money.php
- database/migrations/2026_11_12_000101_create_operation_receipts_table.php
- database/migrations/2026_11_12_000102_create_order_settlements_table.php
- database/migrations/2026_11_12_000103_create_order_effect_links.php
- database/migrations/2026_11_12_000104_create_order_item_snapshots.php
- database/migrations/2026_11_12_000105_create_document_sequences_table.php
- tests/Feature/FinancialIntegrityTest.php
- tests/Feature/FinancialStructuresTest.php
- tests/Feature/HistoricalFinancialCompatibilityTest.php
- tests/Feature/OrderNumberServiceTest.php
- tests/Feature/OrderOperationInfrastructureTest.php
- tests/Support/FinancialTestCase.php
- tests/Unit/MoneyTest.php
- tests/Unit/OrderContractsTest.php
- docs/financial-contracts-431.md
