# Login sin contraseña en Laravel (Inertia y Vue)

Una aplicación Laravel con Inertia y Vue en la que se entra sin contraseña: escribes tu email, te llega un código de seis cifras y con él entras. El mismo formulario crea la cuenta la primera vez y sirve para recuperar el acceso.

Antes de enviar ningún código, el formulario pasa por [Cloudflare Turnstile](https://www.cloudflare.com/products/turnstile/) con el componente de Vue de [Laracaptcha](https://github.com/edulazaro/laracaptcha). El servidor le pasa a la página lo que el componente necesita con `Captcha::props()`, y el token llega al formulario con `v-model`.

El tutorial completo, paso a paso, está en Neoguias: [Cómo crear un login sin contraseña con Laravel, Inertia y Vue](https://www.neoguias.com/login-sin-contrasena-laravel-vue/). La versión con Blade está en [neoguias/laravel-passwordless-login](https://github.com/neoguias/laravel-passwordless-login) y la de Livewire en [neoguias/laravel-passwordless-login-livewire](https://github.com/neoguias/laravel-passwordless-login-livewire).

## Cómo arrancarlo

```bash
composer install
npm install && npm run build
cp .env.example .env && php artisan key:generate
php artisan migrate
php artisan serve
```

Abre `http://localhost:8000` y escribe cualquier email. En local los correos no se envían: van al log (`MAIL_MAILER=log`), así que el código lo verás con `php artisan pail` o en `storage/logs/laravel.log`. Mientras desarrollas, `npm run dev` recarga los cambios de las páginas.

Las claves de Turnstile de `.env.example` son las de prueba que publica Cloudflare, que siempre aprueban. En producción pon las tuyas en `TURNSTILE_KEY` y `TURNSTILE_SECRET`, y un SMTP real en las variables `MAIL_*`.

Los tests cubren todo el flujo con `Mail::fake()` y `Captcha::fake()`, sin enviar correos ni llamar a Cloudflare:

```bash
php artisan test
```

Requiere PHP 8.3, Laravel 13, Inertia 3, Vue 3 y Laracaptcha 1.2.

## Dónde está cada cosa

- [`resources/js/pages/auth/Login.vue`](resources/js/pages/auth/Login.vue): el formulario del email, con el componente `Captcha` de Laracaptcha. Si el servidor rechaza el envío, pide un token nuevo, porque Cloudflare acepta cada uno una sola vez.
- [`resources/js/pages/auth/Code.vue`](resources/js/pages/auth/Code.vue): el formulario del código y el reenvío.
- [`resources/js/pages/Dashboard.vue`](resources/js/pages/Dashboard.vue): el panel y el cierre de sesión.
- [`app/Http/Controllers/Auth/LoginController.php`](app/Http/Controllers/Auth/LoginController.php): pide el código, lo verifica, crea la cuenta y limita los códigos que puede pedir una misma máquina.
- [`app/Auth/EmailCode.php`](app/Auth/EmailCode.php): genera el código, guarda su hash en la caché y lo comprueba. Caduca a los diez minutos, admite cinco intentos y limita los envíos por dirección.
- [`app/Mail/LoginCode.php`](app/Mail/LoginCode.php): el correo, con el código también en el asunto.
- [`app/Http/Middleware/HandleInertiaRequests.php`](app/Http/Middleware/HandleInertiaRequests.php): comparte con todas las páginas el usuario y los mensajes de estado.
- [`vite.config.js`](vite.config.js): el alias `@laracaptcha`, que apunta a los componentes que incluye el paquete.
- [`tests/Feature/PasswordlessLoginTest.php`](tests/Feature/PasswordlessLoginTest.php): los tests.

## Autor

Creado por [Edu Lázaro](https://edulazaro.com) para [Neoguias](https://www.neoguias.com).

## Licencia

Este ejemplo es software libre con [licencia MIT](LICENSE).
