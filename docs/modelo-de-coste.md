# Modelo de coste

Implementación: [`app/Services/Costing/TripCostCalculator.php`](../app/Services/Costing/TripCostCalculator.php).
Constantes: [`config/trayectos.php`](../config/trayectos.php), sección `physics`.

## El problema

El consumo homologado de un coche (L/100 km) corresponde a terreno esencialmente llano. Cualquier
desnivel añade una energía que el motor debe entregar y de la que sólo se recupera una parte al
bajar. **Ese factor de recuperación es lo que separa de verdad a un híbrido de un motor de
combustión**: el térmico disipa la bajada en los frenos, el híbrido la devuelve a la batería.

## La fórmula

### 1. Masa real en carretera

```
m = tara + 75 kg × ocupantes + equipaje
```

Subir un puerto con cinco personas cuesta bastante más que subirlo solo, y el modelo tiene que
notarlo.

### 2. Energía gravitatoria neta

```
E = m · g · (D⁺ − r · D⁻ᵤₜᵢₗ)          [julios en la rueda]
```

- `D⁺`, `D⁻`: ascenso y descenso acumulados, en metros (los da OpenRouteService)
- `r`: factor de recuperación de la tecnología
- `D⁻ᵤₜᵢₗ`: la parte del descenso que la batería puede realmente absorber

### 3. El techo de regeneración

Un híbrido bajando un puerto de 1.200 m **no recupera 1.200 m de energía**: llena su batería en los
primeros cientos de metros y el resto lo disipa en los frenos.

```
D⁻ᵤₜᵢₗ = min(D⁻,  kWh_útiles × 3,6·10⁶ × 0,90 / (m · g))
```

Para un híbrido típico (1,3 kWh útiles, 1.900 kg cargado) el techo ronda los **226 m de desnivel**.
Es la razón por la que un híbrido no es tan mágico en montaña como en ciudad, y el modelo lo captura.

### 4. Factores por tecnología

| Tecnología | `r` (recuperación) | `η` (tanque → rueda) | Por qué |
|---|---|---|---|
| Gasolina | 0,05 | 0,25 | Sólo corte de inyección al decelerar |
| Diésel | 0,05 | 0,30 | Igual, con mejor rendimiento térmico |
| Híbrido (HEV) | 0,60 | 0,33 | Regenera, pero con una batería diminuta que satura enseguida |
| Enchufable (PHEV) | 0,65 | 0,32 | Batería grande: absorbe descensos largos |
| Eléctrico (BEV) | 0,70 | — (0,85 batería → rueda) | Regeneración eficiente; el resto son pérdidas y fricción |

Editables por vehículo desde la interfaz.

### 5. Reparto entre energía de red y combustible

- **BEV**: todo el trayecto es eléctrico.
- **PHEV**: `km_eléctricos = min(distancia, autonomía_EV × batería_inicial %)`; el resto, térmico.
- **HEV**: **cero kilómetros eléctricos**. Su batería es un amortiguador, no una fuente: toda su
  energía viene del depósito. Su ventaja ya está en el consumo homologado y en el factor `r`.

### 6. Consumo y coste

```
litros = max(litros_base × 0,40,  litros_base + E·(1−fracción_EV) / (η · LHV))
kWh    = max(kWh_base × 0,40,     kWh_base    + E·fracción_EV / 3,6·10⁶ / 0,85)

coste  = (litros × precio_litro + kWh × precio_kWh) × factor_calibración
```

El **suelo del 40 %** existe porque un coche bajando un puerto no consume cero: quedan accesorios,
climatización y tramos llanos. Sin él, un eléctrico en un descenso largo saldría a coste negativo.

Poderes caloríficos usados: gasolina 32,0 MJ/L · diésel 35,8 MJ/L · GLP 25,0 MJ/kg · GNC 38,0 MJ/kg.

## Comprobación

Madrid → Puerto de Navacerrada, 60 km, `D⁺` = 900 m, `D⁻` = 120 m, cuatro ocupantes:

| Coche | Base | Extra por desnivel | Total |
|---|---|---|---|
| Gasolina 1.500 kg, 6,5 L/100 | 3,90 L | +1,97 L | **5,87 L** (+50 %) |
| Híbrido 1.600 kg, 4,5 L/100 | 2,70 L | +1,33 L | **4,03 L** (+49 %) |
| Eléctrico 1.900 kg, 17 kWh/100 | 10,20 kWh | +4,90 kWh | **15,10 kWh** (+48 %) |

Un resultado contraintuitivo que conviene entender: **el recargo porcentual del híbrido no es menor**,
porque su consumo base es mucho más bajo y el mismo desnivel pesa proporcionalmente más. Lo que baja
es el gasto absoluto. Donde el híbrido gana de verdad es en el regreso, recuperando el 60 % de la
bajada frente al 5 % del gasolina. Está cubierto en
[`TripCostCalculatorTest`](../tests/Unit/TripCostCalculatorTest.php).

## Calibración

Cualquier modelo con eficiencias fijas se equivoca. En vez de discutirlo, el sistema compara los
repostajes reales con lo que predijo para los viajes de ese mismo periodo:

```
factor = Σ litros reales / Σ litros previstos        (mínimo 3 llenados completos)
```

Acotado a `[0,750 , 1,350]` para que un dato mal introducido no desmadre la contabilidad, y con una
ventana de 180 días.

## Inmutabilidad

Cada viaje guarda en `trips.cost_inputs` un **snapshot completo** de todo lo que entró en el cálculo:
consumos, factores, masa, precio aplicado, gasolinera de referencia, origen de la ruta y versión de
la fórmula. Recalibrar un coche o una subida del gasóleo **no cambia ni un céntimo** de los viajes ya
apuntados. Contabilidad que reescribe el pasado es contabilidad que nadie se cree.

## Limitaciones conocidas

- **Sin ORS, la distancia es una línea recta × 1,25** y el perfil de altitud atraviesa lo que haya en
  medio. Madrid → Segovia en línea recta cruza la sierra por arriba y acumula mucho más desnivel que
  la autopista con su túnel. La interfaz lo avisa; los kilómetros se pueden corregir a mano.
- **Los modelos digitales del terreno tienen error de varios metros.** Se filtra el ruido con un
  umbral de 3 m al acumular el perfil, pero sigue siendo una estimación.
- **No se modela nada de aerodinámica ni de estilo de conducción.** Ir a 140 km/h consume bastante
  más que a 100 y el modelo no lo distingue: eso lo absorbe el factor de calibración.
- **Peajes, aparcamiento y desgaste no entran.** Son costes reales de un viaje, pero no del
  combustible; si el grupo los quiere repartir, van como ajuste manual en el libro.
