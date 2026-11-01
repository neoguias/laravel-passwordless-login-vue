<?php

namespace App\Http\Controllers\Auth;

use App\Auth\EmailCode;
use App\Http\Controllers\Controller;
use App\Models\User;
use EduLazaro\Laracaptcha\Facades\Captcha;
use EduLazaro\Laracaptcha\Rules\Captcha as CaptchaRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Una sola puerta: un email y después seis cifras enviadas a ese email. Entrar y registrarse
 * son la misma pantalla, y el código sirve también para recuperar el acceso, así que no hay
 * contraseña que olvidar.
 *
 * La cuenta se crea cuando una dirección nueva acierta su código, no cuando se escribe, para
 * que nadie pueda llenar la tabla de usuarios con direcciones de otras personas.
 */
class LoginController extends Controller
{
    /**
     * Códigos que puede haber enviado una misma máquina, sumando todas las direcciones. El
     * límite por dirección de EmailCode no ve a un bot que escribe una dirección distinta en
     * cada intento.
     *
     * @var array<int, int> Ventana en segundos => códigos permitidos en ella.
     */
    private const CODES_PER_ORIGIN = [3600 => 6, 86400 => 20];

    /**
     * La página recibe lo que el componente de Laracaptcha necesita para dibujar el widget:
     * el proveedor, la clave pública y el script.
     */
    public function create(): Response
    {
        return Inertia::render('auth/Login', ['captcha' => Captcha::props()]);
    }

    /**
     * Cada código es un email desde nuestro dominio hacia la dirección que hayan escrito,
     * así que la comprobación de Turnstile va antes de enviar ninguno.
     */
    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'captcha' => ['required', CaptchaRule::make()],
        ], [
            'email.required' => 'Escribe tu email.',
            'email.email' => 'Ese email no es válido.',
            'email.max' => 'Ese email es demasiado largo.',
            'captcha.required' => 'Completa la verificación de seguridad.',
        ]);

        $email = Str::lower(trim($data['email']));

        if ($error = $this->sendCode($request, $email)) {
            return back()->withInput($request->only('email'))->withErrors(['email' => $error]);
        }

        $request->session()->put('login_email', $email);

        return redirect()->route('login.code');
    }

    public function code(Request $request): Response|RedirectResponse
    {
        $email = $request->session()->get('login_email');

        return $email ? Inertia::render('auth/Code', ['email' => $email]) : redirect()->route('login');
    }

    /**
     * Otro código a la dirección guardada en la sesión. Esta vez sin captcha: esa dirección
     * lo pasó hace un momento, y EmailCode ya limita los códigos por dirección.
     */
    public function resend(Request $request): RedirectResponse
    {
        $email = $request->session()->get('login_email');

        if (! $email) {
            return redirect()->route('login');
        }

        if ($error = $this->sendCode($request, $email)) {
            return redirect()->route('login.code')->withErrors(['code' => $error]);
        }

        return redirect()->route('login.code')->with('status', 'Te hemos enviado un código nuevo. El anterior ya no sirve.');
    }

    public function verify(Request $request): RedirectResponse
    {
        $email = $request->session()->get('login_email');

        if (! $email) {
            return redirect()->route('login');
        }

        $request->validate(
            ['code' => ['required', 'digits:6']],
            ['code.required' => 'Escribe el código.', 'code.digits' => 'El código tiene 6 cifras.'],
        );

        $code = EmailCode::for($email);

        $error = match ($code->check($request->input('code'))) {
            EmailCode::OK => null,
            EmailCode::WRONG => 'Ese no es el código. Te quedan '.$code->triesLeft().' intentos.',
            EmailCode::BURNT => 'Demasiados intentos con ese código. Pide uno nuevo.',
            EmailCode::GONE => 'El código ha caducado. Pide uno nuevo.',
            EmailCode::LOCKED => 'Demasiados códigos incorrectos hoy para este email. Vuelve a intentarlo mañana.',
        };

        if ($error !== null) {
            return back()->withErrors(['code' => $error]);
        }

        // Llegar a la bandeja de entrada es lo que demuestra la dirección: ninguna cuenta nace sin verificar.
        $user = User::firstOrCreate(['email' => $email], ['name' => Str::before($email, '@')]);

        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        Auth::login($user, remember: true);

        $request->session()->forget('login_email');
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * @return string|null Por qué no se ha enviado, o null si se ha enviado.
     */
    private function sendCode(Request $request, string $email): ?string
    {
        $code = EmailCode::for($email);

        if ($code->tooManyToday()) {
            return 'Has pedido demasiados códigos hoy para este email. Vuelve a intentarlo mañana.';
        }

        if ($code->tooMany()) {
            return 'Ya te hemos enviado varios códigos. Espera '.max(1, (int) ceil($code->availableIn() / 60)).' minutos o usa el último que te llegó.';
        }

        $origin = sha1((string) $request->ip());

        foreach (self::CODES_PER_ORIGIN as $window => $allowed) {
            if (RateLimiter::tooManyAttempts("login:origin:{$window}:{$origin}", $allowed)) {
                return 'Se han enviado demasiados códigos desde aquí. Vuelve a intentarlo más tarde.';
            }
        }

        foreach (array_keys(self::CODES_PER_ORIGIN) as $window) {
            RateLimiter::hit("login:origin:{$window}:{$origin}", $window);
        }

        $code->send(isNew: ! User::where('email', $email)->exists());

        return null;
    }
}
