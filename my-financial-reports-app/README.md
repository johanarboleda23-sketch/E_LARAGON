# My Financial Reports App

## Descripción del Proyecto

My Financial Reports App es una aplicación Laravel diseñada para generar y exportar reportes e informes financieros. Este proyecto replica las funcionalidades de reportes ofrecidas por el software de Siigo, permitiendo a los usuarios acceder a información financiera de manera eficiente y organizada.

## Estructura del Proyecto

La aplicación está organizada en varias carpetas y archivos, cada uno con una función específica:

- **app/**: Contiene la lógica de la aplicación, incluyendo modelos, controladores, servicios y solicitudes.
  - **Exports/**: Archivos responsables de la exportación de reportes en diferentes formatos (PDF y Excel).
  - **Http/**: Controladores y solicitudes que manejan las interacciones del usuario.
  - **Models/**: Modelos que representan las entidades de la base de datos.
  - **Services/**: Servicios que contienen la lógica de negocio para la generación de reportes.
  - **Support/**: Funciones de soporte para aplicar filtros a los reportes.

- **config/**: Archivos de configuración para la aplicación, incluyendo parámetros globales para los reportes.

- **database/**: Contiene migraciones, fábricas y sembradores para la base de datos.
  
- **public/**: Archivos estáticos y activos relacionados con la aplicación.

- **resources/**: Archivos de recursos como vistas, CSS y JavaScript.

- **routes/**: Definición de rutas web para la aplicación.

- **tests/**: Pruebas funcionales para asegurar el correcto funcionamiento de la aplicación.

## Instalación

1. Clona el repositorio en tu máquina local.
2. Navega a la carpeta del proyecto:
   ```sh
   cd my-financial-reports-app
   ```
3. Instala las dependencias de Composer:
   ```sh
   composer install
   ```
4. Configura el archivo `.env` basado en `.env.example`.
5. Ejecuta las migraciones para crear las tablas en la base de datos:
   ```sh
   php artisan migrate
   ```
6. (Opcional) Si deseas sembrar datos iniciales, ejecuta:
   ```sh
   php artisan db:seed
   ```

## Uso

- Accede a la aplicación a través de tu navegador en `http://localhost:8000`.
- Utiliza las funcionalidades de generación y exportación de reportes desde el panel de control.

## Contribuciones

Las contribuciones son bienvenidas. Si deseas contribuir, por favor abre un issue o un pull request.

## Licencia

Este proyecto está bajo la Licencia MIT.