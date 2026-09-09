# Logotipos de las marcas de gasolinera

Deja aquí el logotipo de cada marca y el mapa de `/precios` lo usará en lugar
de la insignia con las iniciales. Si no hay fichero, sigue saliendo la insignia:
no hace falta ponerlos todos ni ninguno.

## Cómo se nombran

El nombre del fichero es la **clave de la marca**, que sale de
`App\Support\FuelBrand`. Son éstas:

```
repsol      cepsa       moeve       campsa      petronor
galp        shell       bp          q8          gm-oil
petroprix   ballenoil   plenoil     petromax    meroil
carrefour   alcampo     eroski      bonarea     disa
avia        tamoil      esclatoil
```

Es decir: `repsol.png`, `cepsa.png`, `bp.png`… Se admite `.png`, `.svg`, `.jpg`
y `.webp`, para no tener que convertir nada antes de dejarlo aquí. Si hubiera
varios con el mismo nombre gana el `.png`, y luego el `.svg`.

## Cómo tienen que ser

- **Cuadrados**, o casi. El mapa los encaja en una insignia cuadrada de 30 px,
  así que un logotipo muy alargado se verá diminuto. Mejor el símbolo de la
  marca que el nombre completo.
- **Con fondo transparente**. La insignia pone un fondo blanco redondeado
  debajo, que es lo que mantiene el logotipo legible sobre cualquier parte del
  mapa.
- **Al menos 60 × 60 px** si son PNG. Se dibujan a doble resolución para que no
  salgan borrosos en un móvil.

## Por qué no vienen puestos

Los logotipos de Repsol, Cepsa, BP y compañía son de sus dueños. Meterlos en el
repositorio sería redistribuir material del que no se puede acreditar la
licencia, así que esa decisión se deja a quien tenga los ficheros y sepa de
dónde salen.

Usarlos para identificar la marca real de cada gasolinera —que es lo que hace
esta pantalla— es el uso normal y el que hace cualquier aplicación de mapas,
pero eso no convierte en libre el archivo del logotipo.
