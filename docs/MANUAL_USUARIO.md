# MANUAL DE USO — JV3000 C.A.

Sistema de **Gestión de Inventario, Compras y Ventas**.

Este manual explica, en orden, qué debe hacer un **Administrador**, un **Operador de Carga** y un **Operador de Ventas** para que el sistema funcione correctamente de punta a punta.

---

## 1. Roles del sistema

| Rol | Acceso | Trabaja en |
|---|---|---|
| **Administrador** | Todo el sistema | Usuarios, Historial, Productos, Categorías, Compras, Ventas, Clientes, Estadísticas |
| **Operador de Carga** | Compras (incluye recepción y solicitudes), Productos (consulta) | Compras, Proveedores, Recepción, Solicitudes |
| **Operador de Ventas** | Ventas, Estadísticas, Productos (consulta) | Ventas y Salidas |

> Regla general: **Inventario** y **Productos** se pueden consultar con los 3 roles, pero solo el **Administrador** puede crearlos o modificarlos.

---

## 2. Instalación y primer arranque

1. Colocar el proyecto en `C:\xampp\htdocs\JV3000_db` con MySQL encendido.
2. Abrir en el navegador `http://localhost/JV3000_db/dashboard/index.php`.
   - Si la base de datos no existe, el sistema muestra la **pantalla de instalación**: pulsa **INSTALAR SISTEMA** (instalación limpia). Ese botón crea el esquema completo y los datos de sistema.
3. **Primer administrador**:
   - **Instalación limpia (sin usuarios):** el mismo sistema crea automáticamente la primera cuenta que se registre como **Administrador** (aprobada y activa).
   - **Base de datos ya rellenada (dump de sistema):** usuario `Administrador` / contraseña `Admin123*`.
4. **Cambiar la contraseña** del administrador en `Mi Perfil` la primera vez que entres.

---

## 3. Crear una cuenta de colaborador

**Lo hace el propio colaborador** desde la pantalla de registro, o el administrador gestiona la cuenta después.

1. Abrir `login/login.php` y pulsar **REGISTRARSE**.
2. Completar:
   - **Usuario:** de 4 a 20 caracteres (letras, números y guion bajo). Se guarda normalizado: `juan_perez` se muestra como `Juan_Perez`. No puede repetirse (ni con distintas mayúsculas).
   - **Correo:** válido y único.
   - **Contraseña:** mínimo 8 caracteres con mayúscula, minúscula, número y símbolo. No puede ser igual a la de otro usuario.
   - **Pregunta y respuesta de seguridad:** obligatorias (sirven para recuperar la contraseña).
3. Enviar. La cuenta queda en estado **PENDIENTE** hasta que el administrador la apruebe.
4. **Aviso al colaborador:** el correo que uses es el vehículo para recuperar acceso; asegúrate de que sea real y verificarlo.

> El primer usuario registrado en una instalación vacía es Administrador automáticamente y NO queda pendiente.

---

## 4. Aprobación de cuentas (solo Administrador)

`Control → Usuarios`

1. En la tabla, las cuentas nuevas aparecen resaltadas en **PENDIENTE**.
2. En la fila del colaborador, pulsa **APROBAR** (botón ✔ azul) **después de elegir un rol** en el selector:
   - **Operador de Carga** (solo compras/recepción) o **Operador de Ventas** (solo ventas).
3. Confirmar. El colaborador queda **ACTIVO** y ya puede iniciar sesión.

Acciones disponibles por fila (consejo de uso):

| Estado | Acciones |
|---|---|
| PENDIENTE | Aprobar (asignar rol) |
| ACTIVO | Editar ✏ (usuario, correo, rol) · Suspender 🔒 |
| INACTIVO (suspendido) | Editar ✏ · Reactivar ✔ |

- **Editar** abre una ventana con usuario, correo y rol. No cambies el rol de tu propia cuenta (está protegido).
- **Suspender** deja al colaborador fuera del sistema sin borrar su historial.
- Nunca borres cuentas: para quitar acceso usa **SUSPENDER**.

---

## 5. Iniciar sesión y recuperar acceso

**Iniciar sesión** (`login/login.php`):
1. Escribe tu usuario (funciona en mayúsculas o minúsculas) y tu contraseña.
2. Tres intentos fallidos bloquean el acceso durante 30 segundos.
3. Si tu cuenta está **pendiente**, el sistema te avisa; si está **suspendida**, debes pedir a un administrador que te reactive.

**Recuperar contraseña** (`login/recuperar.php`):
1. Escribe tu correo o usuario.
2. Responde tu **pregunta de seguridad** (3 intentos) o usa tu **PIN de 6 dígitos** (si el administrador lo configuró).
3. Escribe una **nueva contraseña fuerte**.
4. Listo, inicia sesión con la nueva contraseña.

---

## 6. El Panel de Inicio

Al entrar ves tu **saludo** (según la hora de Venezuela), la fecha/hora, tarjetas rápidas y una **campana de alertas** según tu rol:

- **Operador de Ventas:** productos vencidos, próximos a vencer (≤7 días) y prontos a vencer (8–30 días).
- **Operador de Carga / Administrador:** además de lo anterior, **stock bajo** (stock actual ≤ stock mínimo).

Estas alertas son el punto de partida del ciclo: si algo se vence o falta, toca pedirlo.

La **Guía de Uso** (este documento) está siempre disponible en el menú lateral, en el ítem **Guía de Uso** (ícono de libro).

---

## 7. El ciclo correcto de la mercancía (orden de uso)

El sistema está ordenado en **fases**; si sigues este orden, los números cuadran solos:

```
Fase 1  Control  →  cuentas del personal (Usuarios)
Fase 2  Inventario → productos, categorías y stock (configuración base)
Fase 3  Compras  →  pedir → comprar → recibir
Fase 4  Salidas  →  vender (nota de entrega)
Fase 5  Análisis →  estadísticas + imprimir reporte
```

---

### Fase 2 · Inventario: Productos y Categorías (Administrador configura)

**Categorías** (dentro de `Inventario`):
1. Pulsa el botón **CATEGORÍAS** del encabezado del módulo.
2. En la ventana, pulsa **NUEVA CATEGORÍA** (o el botón del centro si aún no hay ninguna).
3. Nombre único (se guarda en mayúsculas), código `CAT-XXX` (se genera solo), clasificación **ABC** y tipo de manejo (normal, inflamable, líquido, peligroso, voluminoso, aerosol).

**Productos** (solo el Administrador crea o edita):
1. Entra a `Inventario → Productos · Categorías` y pulsa crear/editar.
2. Datos clave: SKU/código, nombre, categoría, **stock mínimo** y **máximo** (si no defines máximo, hereda el de su categoría), **precio de venta**, **fecha de vencimiento obligatoria** y estado.
3. Activa o desactiva productos con el interruptor. **Nunca borres** un producto con historial; desactívalo.

> Técnica **FEFO**: las ventas consumen primero el lote que **vence primero**. Por eso la fecha de vencimiento es obligatoria en producto, compra y recepción.

---

### Fase 3 · Compras (Operador de Carga / Administrador)

> En el módulo **Compras** viven también las Solicitudes de Reposición y la Recepción de Mercancía. No necesitas salir de Compras: las pendientes aparecen como tarjetas arriba de la tabla de facturas.

#### Paso 1 — Solicitudes de Reposición
- Las solicitudes **nacen desde Ventas**: si al vender faltó producto, el sistema ofrece generar automáticamente una solicitud.
- En la tarjeta **"Solicitudes de Reposición Pendientes"** (parte superior de Compras) verás las pendientes con solicitante, motivo, productos y unidades.
- Para atender una: pulsa **ATENDER** → te lleva a una nueva compra con los ítems ya cargados. También puedes **cancelarla** con el botón de la X.
- Estados: `Pendiente` → `Atendida` (al crear la compra) o `Cancelada`.

#### Paso 2 — Compras
`Compras → botón NUEVA COMPRA` (4 pasos):

1. **Proveedor:** escoge un proveedor activo con RIF válido. *(Los proveedores se gestionan aquí mismo, con datos de empresa, RIF y contacto.)*
2. **Factura:** número (6–8 dígitos, **único por proveedor**) y Nº control de la papelería fiscal.
3. **Pago:** elige la forma. Si pagas en efectivo **USD/EUR**, el sistema trae la **tasa BCV automática** (puedes ajustarla) y calcula el equivalente en bolívares. Si el pago cubre el total → la compra queda **Pagada**; si no → **Pendiente** (podrás marcarla como pagada después).
4. **Productos:** busca cada producto, indica **cantidad**, **costo** y **fecha de vencimiento** (obligatoria por línea). El sistema suma Subtotal + **IVA** (configurado en el sistema) = Total.

> Importante: al guardar la compra **NO sube el stock**. Eso ocurre en la Recepción. La compra queda con estado de recepción **Pendiente**.

#### Paso 3 — Recepción
En la tarjeta **"Compras Pendientes de Recepción"** (dentro de Compras) pulsa el botón **RECIBIR** de la compra a recibir:

1. Verifica factura y proveedor (solo lectura). Nº de Guía/Recibo opcional.
2. Indica cuánto **vas a recibir** por línea (máximo lo pendiente por recibir).
3. Registra: el sistema **crea los lotes**, recalcula el **costo promedio**, sube el stock y deja la compra **Completa** (o **Parcial** si solo recibiste parte). Las recepciones recientes quedan en la tarjeta **"Últimas Recepciones"** (últimas 5).

> Regla de oro: **una compra solo aumenta el inventario cuando se recibe.** Si haces compras y nunca recibes, el stock no reflejará la mercancía.

---

### Fase 4 · Salidas (Operador de Ventas / Administrador)

`Salidas → Ventas · Salidas` → **NUEVA VENTA**.

**Tipos de movimiento**:

| Tipo | Cuándo usarlo | Cliente / Datos |
|---|---|---|
| **Venta** | Venta normal | Nombre + RIF/Cédula (formato validado: V/E + 8 o 9 dígitos, o J/G/P/C) |
| **Regalía** | Entrega gratis | Motivo (Promoción, Cortesía, Garantía, Producto dañado, Muestra) + cliente. Precio forzado a 0, IVA exento. |
| **Merma / Ajuste** | Producto vencido, dañado, robo hormiga, error de inventario… | Causa + motivo opcional. Consume los lotes vencidos. |

**Pasos para registrar una venta:**
1. Escoge el **tipo de movimiento**.
2. **Cliente:** búscalo (se autocompleta). Si no existe, el Administrador lo crea en el gestor de clientes del mismo módulo.
3. **Productos:** búscalos, indica cantidad. El sistema **descuenta FEFO** (primero lo que vence más pronto). Si el stock no alcanza, ofrece crear la **solicitud de compra**.
4. Pulsa **📄 VISTA PREVIA NOTA** → revisa la **nota imprimible** (cálculos, IVA, observaciones).
5. En la nota pulsa **CONFIRMAR Y REGISTRAR**. Solo ahí la venta queda registrada y el stock se descuenta.
6. Verás la nota final: entrégala al cliente, imprímela y guárdala como respaldo.

**Acciones sobre ventas ya registradas:** desde la tabla, **Ver Nota** (reimprimir), **Editar** (devuelve y reaplica stock) y **Anular** (solo Administrador; devuelve todo al inventario).

---

### Fase 5 · Análisis

- **Estadísticas** (`Análisis → Estadísticas`, admin + ventas): elige el período (día, semana, quincena, mes, trimestre, semestre o un rango personalizado) para ver gráficas con comparativa ▲/▼ vs. el período anterior.
- **Imprimir Reporte** (`Análisis → Imprimir Reporte`, los 3 roles): genera un reporte imprimible de **solo productos activos** con SKU, categoría, proveedor, stock, capacidad, precio costo/venta y el **valor total del inventario a costo y a venta**.
- **Historial** (`Control → Historial`, solo admin): bitácora de auditoría con filtros (usuario, acción, fechas, detalle). Aquí se ve **todo** lo que alguien hizo en el sistema.

---

## 8. Mi Perfil

`Mi Perfil` (todos los roles):
1. Cambia tu **usuario** (normalizado, 4–20 caracteres), **correo** y **pregunta de seguridad**.
2. Cambia tu **contraseña** con la misma regla de seguridad (mín 8, mayúscula, minúscula, número, símbolo; no repetida entre usuarios).

---

## 9. Reglas de oro para que todo funcione

1. **No borres**: para quitar una cuenta usa *Suspender*; para quitar un producto usa *desactivar*. Así el historial y las estadísticas se conservan.
2. **Toda venta pasa por la NOTA**: primero *Vista Previa*, luego *Confirmar y Registrar*. La nota confirma el descuento de stock.
3. **Toda mercancía entra por RECEPCIÓN**: un producto comprado y no recibido no existe en el inventario.
4. **Los vencimientos son sagrados**: la fecha de vencimiento es obligatoria al crear producto, comprar y recibir; el sistema consume *primero lo que vence antes* (FEFO) y alerta lo vencido/próximo.
5. **RIF/Cédula con formato válido**: el sistema valida los documentos fiscales; corrige antes de guardar.
6. **Contraseñas y seguridad**: contraseñas fuertes, pregunta de seguridad configurada; si un colaborador deja el equipo, suspéndelo y niega el acceso.
7. **El IVA y la tasa BCV**: el IVA lo defines en la configuración y el total lo calcula el sistema; al pagar en divisas usa la *tasa del día* que el sistema trae (editable).
8. **Revisa el Historial**: si algo no cuadra, el Historial dice quién, qué y cuándo.

---

## 10. Resumen rápido "qué hago hoy"

- **¿Soy administrador y llega un colaborador nuevo?** → `Control → Usuarios` → APROBAR con su rol.
- **¿Falta producto en el almacén?** → `Compras` → Solicitudes Pendientes → ATENDER → COMPRAR → RECIBIR.
- **¿Llegó un cliente y quiere mercancía?** → `Salidas → Ventas` → NUEVA VENTA → productos → VISTA PREVIA → CONFIRMAR.
- **¿Vence mercancía o hay merma?** → NUEVA VENTA tipo **Merma/Ajuste** con la causa.
- **¿Quiero saber si el negocio va bien?** → `Análisis → Estadísticas` y `Análisis → Imprimir Reporte`.
- **¿Alguien hizo algo raro?** → `Control → Historial`.