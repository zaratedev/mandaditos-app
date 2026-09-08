# Mandaditos app — Propuesta y Documento de Descubrimiento (MVP)

> Documento base del proyecto. **Fase 1 (MVP) completada.** En curso: **Fase 2** (notificaciones, reportes, mejoras de desglose).
> Fecha: 2026-09-08 · Autor: equipo técnico (Sandslash) · Estado: **Fase 1 completada — Fase 2 en progreso**
>
> **Convención dura del proyecto:** toda la **arquitectura y el código en inglés**
> (tablas, columnas, modelos, enums, rutas, variables). La **prosa y la UI** pueden
> estar en español.

---

## 0. Decisiones confirmadas (Fase 0)

| # | Decisión | Resolución |
|---|---|---|
| 1 | Login del cliente en el MVP | **No.** El cliente no entra al sistema; es herramienta interna. |
| 2 | Comisión | **Manual** — el admin la captura por pedido. |
| 3 | Quién captura el pedido | **Admin** (por ahora). |
| 4 | Quién registra el cobro | **Admin**. |
| 5 | Corte / liquidación | **Sí, en el MVP** (lo hace el admin). |
| 6 | Base de datos | **MySQL**. |
| 7 | Marca / nombre en la UI | **"Mandaditos app"**. |
| 8 | Desglose de productos | **Ambas**: lista en texto libre **y** líneas de producto con precio. |

> Nota sobre roles: aunque hoy solo el **admin** captura pedidos y registra cobros,
> el rol **courier** (repartidor) existe desde el MVP para asignarle pedidos y que
> vea/actualice el estado operativo de los suyos. Ampliar sus permisos (capturar
> pedidos / registrar cobros) es un cambio menor en fase posterior.

---

## 1. Objetivo del documento

Alinear **qué construimos, para quién, con qué alcance y por qué** antes de programar.
Describe el proceso actual, los dolores, la viabilidad, el alcance del MVP, el modelo
de datos (en inglés), los flujos y el stack (Laravel 12 + Inertia + Vue 3).

---

## 2. El proceso actual (tal como ocurre hoy)

```
1. Cliente escribe por WhatsApp la lista de cosas que necesita
2. Administrador responde "sí claro" (acepta el pedido)
3. Administrador asigna el pedido a uno de sus repartidores (courier)
4. Repartidor compra los productos
5. Repartidor / admin avisa: total de la compra + comisión del mandado
6. Cliente indica método de pago: transferencia o efectivo
7. Repartidor entrega en la dirección ya conocida del cliente
8. Cliente paga (transferencia o efectivo). Si es efectivo, el repartidor trae cambio
9. Pedido cerrado
```

**Características clave:** canal de entrada WhatsApp; 3 actores (cliente, admin, courier);
cobro contra entrega (transferencia/efectivo); los couriers manejan efectivo (cambio) →
hay control de caja / liquidación; el cliente frecuente ya tiene dirección conocida;
el total no se conoce hasta comprar.

---

## 3. Actores y roles

| Actor | Rol en el sistema | Acceso |
|---|---|---|
| **Administrador / dueño** | `admin` | Total: orders, clients, couriers, reportes, corte. Captura pedidos y registra cobros. |
| **Repartidor** | `courier` | Ve **sus** pedidos asignados y actualiza su estado operativo (desde el celular). |
| **Cliente** | — (sin login) | Dato administrado por el negocio. Ver fase futura. |

---

## 4. Dolores que resuelve el software

1. **Sin registro estructurado** de pedidos (todo en WhatsApp).
2. **Asignación informal** a couriers (no se sabe quién tiene qué).
3. **Control del dinero frágil** (efectivo + transferencias + comisiones + cambio → corte).
4. **Sin historial de clientes** (direcciones, frecuencia, consumo).
5. **Sin métricas** (pedidos/día, facturación, comisiones, rendimiento por courier).

**Valor central:** orden, trazabilidad y control del dinero sin cambiar su forma de
trabajar (siguen usando WhatsApp con el cliente; registran y controlan en el sistema).

---

## 5. Viabilidad (resumen)

**Viable.** Problema real y concreto; MVP pequeño y entregable; ventaja de confianza
(cliente frecuente = acceso directo a quien decide); stack maduro y barato.
**Riesgo #1: adopción** → el MVP debe ser *más fácil* que WhatsApp + papel (captura en
pocos clics). No competimos con WhatsApp como canal; el sistema es gestión interna.

---

## 6. Cómo ofrecérselo (estrategia)

Enmarcarlo como **ayuda, no venta**. Proponer **piloto gratuito** (~1 mes) a cambio de
retroalimentación; tú absorbes el hosting inicial (mínimo). Empezar por su dolor #1: el
**control del efectivo / corte**. Monetización (cuota mensual baja, pago único, o "caso
de éxito") se decide **cuando el uso demuestre valor**. Este primer cliente es tu caso de
validación para venderlo después a otros negocios de mandados.

---

## 7. Alcance del MVP

### 7.1. Dentro del MVP (v1)

| # | Funcionalidad |
|---|---|
| 1 | **Auth y roles** (`admin`, `courier`). |
| 2 | **Clients** — alta/edición con teléfono y una o varias direcciones. |
| 3 | **Couriers** — alta/edición (usuarios con rol `courier`). |
| 4 | **Orders** — captura rápida (admin): cliente + lista (texto) + **líneas de producto con precio** + dirección + notas. |
| 5 | **Asignación** de un courier al pedido. |
| 6 | **Ciclo de vida del pedido** con timestamps (sección 9). |
| 7 | **Montos**: items_subtotal + commission (manual) = total; método y estado de pago (registrado por admin). |
| 8 | **Panel del courier** (mobile-first): ve solo sus pedidos y actualiza estado. |
| 9 | **Dashboard del admin**: pedidos del día por estado, totales, quién tiene qué. |
| 10 | **Corte / liquidación** (admin): efectivo vs transferencia y comisiones por courier. |

### 7.2. Fuera del MVP (roadmap)

Integración WhatsApp Business API · portal/login de cliente · pagos en línea ·
notificaciones automáticas · catálogo de productos con tiendas · geolocalización/rutas ·
app nativa (el MVP es **web responsive**) · multi-negocio (multi-tenant) · reportes
avanzados y exportación contable.

---

## 8. Modelo de datos (en inglés)

```
users        (admin & couriers; role column)
clients      (name, phone, notes)
addresses    (belongs to a client)
orders       (the errand / mandado — central entity)
order_items  (shopping line items, optional unit price)
```

**`users`**

| Column | Type | Notes |
|---|---|---|
| id | pk | |
| name, email, password | — | Breeze default |
| role | enum(`admin`,`courier`) | default `courier` |
| timestamps | | |

**`clients`**

| Column | Type | Notes |
|---|---|---|
| id | pk | |
| name | string | |
| phone | string | |
| notes | text nullable | |
| timestamps | | |

**`addresses`**

| Column | Type | Notes |
|---|---|---|
| id | pk | |
| client_id | fk → clients | cascade |
| label | string nullable | ej. "Casa", "Trabajo" |
| street | string | |
| neighborhood | string nullable | |
| city | string nullable | |
| landmark | string nullable | referencias para llegar |
| notes | text nullable | |
| timestamps | | |

**`orders`** (entidad central)

| Column | Type | Notes |
|---|---|---|
| id | pk | |
| client_id | fk → clients | |
| address_id | fk → addresses | dirección de entrega |
| courier_id | fk → users, nullable | asignado por el admin |
| status | enum | `requested`,`confirmed`,`assigned`,`purchasing`,`purchased`,`on_the_way`,`delivered`,`cancelled` |
| shopping_list | text | lista original tal cual la mandó el cliente |
| items_subtotal | decimal(10,2) nullable | suma de productos (al comprar) |
| commission | decimal(10,2) nullable | ganancia del negocio (manual) |
| total | decimal(10,2) nullable | items_subtotal + commission |
| payment_method | enum(`transfer`,`cash`) nullable | |
| payment_status | enum(`pending`,`paid`) | default `pending` |
| notes | text nullable | |
| created_by | fk → users | quién capturó |
| confirmed_at | datetime nullable | |
| purchased_at | datetime nullable | |
| delivered_at | datetime nullable | |
| paid_at | datetime nullable | |
| timestamps | | |

**`order_items`** (desglose con precio — opción "ambas")

| Column | Type | Notes |
|---|---|---|
| id | pk | |
| order_id | fk → orders | cascade |
| name | string | descripción del producto |
| quantity | decimal(8,2) | default 1 |
| unit_price | decimal(10,2) nullable | se llena al comprar |
| line_total | decimal(10,2) nullable | quantity * unit_price |
| timestamps | | |

> **Diseño:** `shopping_list` guarda la lista tal cual la mandó el cliente (rápido de
> capturar). `order_items` permite el desglose con precios para control fino. El
> `items_subtotal` puede derivarse de la suma de `order_items.line_total` cuando se use
> el desglose, o capturarse directo cuando solo haya lista de texto. **Sin tabla
> `payments` en la v1**: el pago vive como campos en `orders` (un pago por pedido); se
> extraerá a `payments` solo si aparecen pagos parciales/múltiples. No sobre-diseñamos.
>
> **Enums de PHP 8.x** respaldan `status`, `payment_method`, `payment_status` y el
> `role` de usuario (`OrderStatus`, `PaymentMethod`, `PaymentStatus`, `UserRole`).

---

## 9. Ciclo de vida del pedido

```
requested    → el admin captura el pedido
confirmed    → el admin confirma
assigned     → el admin asigna un courier
purchasing   → el courier está comprando
purchased    → se registra items_subtotal + commission (ya se conoce el total)
on_the_way   → el courier va en camino
delivered    → entregado
cancelled    → cancelado (en cualquier punto)
```

- Cada transición guarda **quién** y **cuándo** (timestamps dedicados).
- `status` (avance operativo) y `payment_status` (dinero) son **independientes**:
  un pedido puede estar `delivered` con `payment_status = pending`.

---

## 10. Flujos principales del MVP

**A — Admin crea y asigna un pedido**
Nuevo pedido → elige/crea cliente → pega la lista (y/o captura líneas de producto) →
elige dirección → guarda → asigna courier. Aparece en el panel del courier.

**B — Courier ejecuta el mandado (celular)**
Ve sus pedidos → `purchasing` → compra → `purchased` → `on_the_way` → `delivered`.
(El cobro lo registra el **admin** en la v1.)

**C — Corte del día (admin)**
Por courier: total cobrado en efectivo, total en transferencia y comisiones →
cuánto efectivo neto debe entregar cada courier al cierre.

---

## 11. Stack y arquitectura técnica

| Capa | Tecnología | Motivo |
|---|---|---|
| Backend | **Laravel 12 (PHP 8.3+; local 8.4)** | Rápido para CRUD + reglas de negocio |
| Frontend | **Inertia + Vue 3** | SPA sin API separada; menos complejidad para MVP |
| Estilos | **Tailwind CSS** | Responsive (clave para el courier en la calle) |
| Auth | **Laravel Breeze (Inertia + Vue)** | Auth de fábrica; roles con columna `role` |
| Base de datos | **MySQL** | Confirmado |
| Autorización | **Policies / Gates** | El courier solo ve sus pedidos; admin ve todo |
| Validación | **Form Requests** | Consistente y limpia |
| UI | Controllers → Inertia responses | Sin API REST separada en el MVP |

**Principios:** aprovechar lo nativo de Laravel (Form Requests, Policies, Eloquent,
Enums de PHP); sin sobre-ingeniería (nada de repos/DTOs/eventos salvo necesidad real);
**mobile-first** en el panel del courier; migrations + seeders + factories desde el inicio.

---

## 12. Consideraciones especiales

- **Dinero:** `decimal(10,2)`, nunca `float`; operaciones con montos dentro de
  transacciones cuando aplique.
- **Efectivo/cambio:** el corte indica al admin el efectivo neto por courier; el
  "cambio" no se modela como entidad en la v1.
- **Comisión:** monto **manual** por pedido.
- **Seguridad:** roles separados, Policies (el courier no ve datos de otros), validación
  de todo input, sin exponer datos sensibles.

---

## 13. Roadmap por fases

| Fase | Contenido | Estado |
|---|---|---|
| **Fase 0** | Documento + acuerdo de alcance | ✅ **Completada** |
| **Fase 1 (MVP)** | Scaffolding, auth+roles, clients, couriers, orders + ciclo de vida, corte, editar/cancelar, español/MX | ✅ **Completada** |
| **Fase 2** | Notificaciones, reportes, mejoras de desglose | 🔄 **En progreso** |
| **Fase 3** | Integración WhatsApp, portal de cliente | Pendiente |
| **Fase 4** | Multi-negocio / SaaS | Pendiente |

---

## 14. Métricas de éxito del MVP

- El negocio **captura sus pedidos** en el sistema (adopción real).
- El admin sabe **al instante** cuántos pedidos hay hoy y su estado.
- El **corte del día cuadra** y ahorra tiempo vs. el método actual.
- Retroalimentación positiva tras 2–4 semanas de uso.

---

## 15. Plan de arranque de la Fase 1 (MVP)

Entorno verificado: PHP 8.4.23 · Composer 2.10.2 · Node 24 · npm 11 · Laravel Installer
5.31.1 · MySQL client instalado (**iniciar el servidor** con `brew services start mysql`).

**Pasos previstos:**
1. Crear proyecto Laravel 12 en la raíz e instalar **Breeze (Inertia + Vue + Tailwind)**.
2. Configurar `.env` para MySQL (DB `mandaditos`).
3. Enums de PHP: `UserRole`, `OrderStatus`, `PaymentMethod`, `PaymentStatus`.
4. Migrations: `role` en `users`, `clients`, `addresses`, `orders`, `order_items`.
5. Models + relaciones + casts (Enums) + factories + seeders con datos realistas.
6. Policies (courier ve solo lo suyo) y middleware de rol.
7. Primer flujo end-to-end: **crear + asignar pedido** (admin) y **panel del courier**.
8. Dashboard del admin + **corte del día**.

> **Pendiente menor para arrancar Fase 1:** confirmar credenciales locales de MySQL
> (usuario/contraseña) o si uso el default `root` sin contraseña; y nombre de la DB
> (propongo `mandaditos`).

---

## 16. Fase 2 — alcance y estado

**Fase 1 (MVP) — ✅ completada.** Scaffolding Laravel 13 + Inertia/Vue, auth y roles
(admin/courier), clientes y direcciones, pedidos con ciclo de vida y desglose de
productos, asignación, registro de compra/comisión/cobro, panel del courier, dashboard
con corte del día, editar/cancelar pedido, toasts, y app en español con zona horaria de
México. 58 pruebas en verde.

**Fase 2 — 🔄 en progreso.** Tres frentes:

1. **Notificaciones (en curso).** Avisos in-app (base de datos) para el equipo: al
   asignar un pedido se notifica al repartidor; al marcarse *entregado* se notifica a
   los administradores. Campana con contador en el encabezado y bandeja de
   notificaciones. (Canales externos como WhatsApp/email quedan para la Fase 3.)
2. **Reportes.** Panel de reportes para el admin con rango de fechas: ventas y
   comisiones, pedidos por día, desempeño por repartidor y por método de pago.
3. **Mejoras de desglose.** Mejor captura y visualización de los productos del pedido
   (precios por línea, subtotal automático) y desglose por producto en los reportes.
