# Contabilidad por partida doble

Implementación: [`app/Services/Ledger/LedgerService.php`](../app/Services/Ledger/LedgerService.php).

## Por qué no hay una columna `saldo`

Un campo `balance` por usuario acaba desajustado siempre: un doble submit, un borrado, un recálculo,
y ya nadie se fía del número. Aquí el saldo **no se almacena en ningún sitio**: es la suma de las
líneas del libro, y punto.

```sql
CREATE VIEW member_balances AS
SELECT gm.id, gm.group_id, gm.user_id, COALESCE(SUM(jl.amount_cents), 0) AS balance_cents
FROM group_members gm
LEFT JOIN journal_lines jl ON jl.group_member_id = gm.id
GROUP BY gm.id, gm.group_id, gm.user_id;
```

## La invariante

**Las líneas de un asiento suman exactamente cero.** Se comprueba en dos sitios:

1. En `LedgerService::createEntry()`, que lanza `UnbalancedEntryException` antes de escribir nada.
2. En PostgreSQL, con un trigger `DEFERRABLE INITIALLY DEFERRED` que valida al `COMMIT`. Así ningún
   script, ningún `tinker` y ninguna migración futura pueden descuadrar el libro, pasen o no por la
   aplicación.

En SQLite (local y tests) sólo actúa la primera; la vista de saldos existe en ambos motores.

## Cómo se asienta un viaje

Trayecto de 24,00 €, conduce Ana, van Bea, Carlos y Diego, y el grupo tiene activado que el conductor
paga su parte:

| Miembro | Línea | Concepto |
|---|---|---|
| Ana | **+18,00 €** | Adelanta el coste y recupera las tres partes ajenas |
| Bea | −6,00 € | Su parte |
| Carlos | −6,00 € | Su parte |
| Diego | −6,00 € | Su parte |
| | **0,00 €** ✓ | |

La parte de la conductora **no genera línea**: se netea contra su crédito. Si Ana viaja sola, no hay
asiento en absoluto.

## El céntimo sobrante

25,00 € entre tres son 8,33 + 8,33 + 8,34. Ese céntimo se reparte con el método del **mayor resto**,
y los empates se rompen con un orden **rotado por el id del viaje**. Dos exigencias que parecen
menores y no lo son:

- la suma de las partes tiene que dar el total exacto, o el asiento no cuadra;
- el sobrante no puede caer siempre en la misma persona, o esa persona paga de más para siempre.

Implementación: [`MoneySplitter`](../app/Services/Ledger/MoneySplitter.php).

## Nada se borra

`journal_lines` es *append-only*, con `ON DELETE RESTRICT`. Anular un viaje crea un asiento de tipo
`reversal` con las líneas invertidas y `reverses_id` apuntando al original. Los dos quedan visibles
en el libro. El histórico es auditable y los saldos vuelven solos a su sitio.

## Idempotencia

Cada asiento automático lleva un `external_ref` único por grupo (`trip:123`, `reversal:45`). Un doble
submit desde una PWA con mala cobertura —el caso normal, no el excepcional— devuelve el asiento
existente en lugar de duplicarlo.

## Dinero

**Todo en enteros de céntimos.** Ni un `float` en ninguna operación monetaria, en ningún punto del
sistema. Los precios de carburante van en milésimas de euro (`1,459 €/L` → `1459`), que es como los
publica el Ministerio.

## Plan de liquidación

`BalanceService::settlementPlan()` empareja acreedores y deudores por importe descendente. Es un
algoritmo voraz: no garantiza el óptimo teórico —el problema es NP-duro— pero con grupos reales de
5 a 15 personas da el mismo resultado que la solución exacta y se calcula al instante.

## Comprobación de integridad

`BalanceService::isConsistent()` verifica que la suma de todos los saldos de un grupo sea cero. La
pantalla del libro mayor lo muestra en cada carga: si algún día no cuadra, es que algo ha escrito
saltándose el servicio, y se ve al momento.
