# API de gestión de proyectos

API REST construida con PHP y CodeIgniter 4. Permite iniciar/cerrar sesión, consultar categorías activas y administrar proyectos con sus tareas.

## Preparar el proyecto

1. Inicia Apache y MySQL desde XAMPP.
2. Importa `bd_gestor_proyecto.sql` en MySQL. El script crea la base `gestion_proyectos` y sus tablas de roles, usuarios, categorías, proyectos, tareas y tokens.
3. Revisa las credenciales de conexión en `app/Config/Database.php`.
4. La URL base configurada es `http://localhost/fs2026/gestion_de_proyectos/public/`. Todas las rutas de la API empiezan con `/api`.

## Flujo de datos

```text
Petición HTTP
  → ruta definida en app/Config/Routes.php
  → AuthFilter valida Bearer token en rutas protegidas
  → controlador valida entrada, permisos y respuesta
  → modelo ejecuta consultas con el Query Builder de CodeIgniter
  → respuesta JSON y código HTTP
```

Los comentarios `1.`, `2.`, etc. dentro de los controladores marcan el orden del flujo. Para aprenderlo, empieza por una ruta de lectura y sigue la petición desde `Routes.php` hasta el método del modelo.

## Rutas

Todas las rutas, excepto el login, requieren el encabezado:

```http
Authorization: Bearer <token>
```

| Método | Ruta | Función |
|---|---|---|
| `POST` | `/api/autenticacion/login` | Validar credenciales y obtener token |
| `POST` | `/api/autenticacion/logout` | Revocar el token actual |
| `GET` | `/api/categorias/activas` | Listar categorías que se pueden asignar a tareas |
| `GET` | `/api/proyectos` | Listar proyectos con paginación y filtros |
| `GET` | `/api/proyectos/{id}` | Consultar un proyecto |
| `POST` | `/api/proyectos` | Crear un proyecto |
| `PUT`, `PATCH` | `/api/proyectos/{id}` | Actualizar un proyecto |
| `DELETE` | `/api/proyectos/{id}` | Eliminar un proyecto |
| `GET` | `/api/proyectos/{id_proyecto}/tareas` | Listar tareas del proyecto con paginación y filtros |
| `GET` | `/api/proyectos/{id_proyecto}/tareas/{id_tarea}` | Consultar una tarea del proyecto |
| `POST` | `/api/proyectos/{id_proyecto}/tareas` | Crear una tarea en el proyecto |
| `PUT`, `PATCH` | `/api/proyectos/{id_proyecto}/tareas/{id_tarea}` | Actualizar una tarea del proyecto |
| `DELETE` | `/api/proyectos/{id_proyecto}/tareas/{id_tarea}` | Eliminar una tarea del proyecto |

Las rutas de tareas usan dos segmentos separados por `/`: por ejemplo, `/api/proyectos/12/tareas/34`.

## Roles y acceso

- `ADMINISTRACION` puede consultar y gestionar cualquier proyecto.
- `USUARIO` puede consultar y gestionar únicamente sus propios proyectos.
- Las tareas heredan el acceso del proyecto al que pertenecen.
- Cualquier usuario autenticado puede consultar categorías activas.
- Un usuario normal no puede asignar un proyecto a otra persona.
- Para respetar la relación de la base de datos, elimina las tareas antes de eliminar su proyecto; si quedan tareas, la API responde `409`.

## Búsqueda y paginación

En los listados de proyectos y tareas se pueden usar estos parámetros:

| Parámetro | Predeterminado | Límite / función |
|---|---:|---|
| `page` | `1` | Página, entero mayor que cero |
| `per_page` | `20` | Elementos por página, de 1 a 100 |
| `q` | vacío | Busca texto en el nombre/título o la descripción |
| `estado` | vacío | Filtra por estado |

Ejemplo: `/api/proyectos?page=1&per_page=10&q=tienda&estado=EN_PROCESO`.

## Formato de respuesta

Éxito:

```json
{
  "exito": true,
  "datos": {},
  "meta": {
    "pagina": 1,
    "por_pagina": 20,
    "total": 1
  }
}
```

`meta` se incluye en respuestas paginadas. Los errores usan `exito: false`, un `mensaje` y, cuando aplica, un objeto `errores`.

Para crear una tarea, envía un objeto JSON con título y una categoría activa:

```json
{
  "titulo": "Preparar pruebas",
  "descripcion": "Verificar el flujo de la API",
  "id_categoria": 1,
  "prioridad": "MEDIA",
  "estado": "PENDIENTE",
  "fecha_limite": "2099-12-31"
}
```

Al crear un proyecto, los campos requeridos son `nombre` y `fecha_inicio` (`YYYY-MM-DD`). El propietario se asigna al usuario autenticado; solo administración puede indicar `id_usuario`.
