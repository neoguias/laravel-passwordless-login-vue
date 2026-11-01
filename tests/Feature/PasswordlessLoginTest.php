<?php

namespace Tests\Feature;

use App\Mail\LoginCode;
use App\Models\User;
use EduLazaro\Laracaptcha\Facades\Captcha;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PasswordlessLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Captcha::fake();
    }

    public function test_the_login_page_gets_what_the_captcha_component_needs(): void
    {
        $this->get('/login')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('auth/Login')
            ->where('captcha.provider', 'fake')
            ->where('captcha.script', '')
            ->has('captcha.siteKey'));
    }

    public function test_the_code_page_shows_the_address_the_code_went_to(): void
    {
        $this->askForCode('ana@example.com');

        $this->get('/login/codigo')->assertInertia(fn (Assert $page) => $page
            ->component('auth/Code')
            ->where('email', 'ana@example.com'));
    }

    public function test_a_new_address_gets_a_code_and_the_right_code_creates_a_verified_account(): void
    {
        $this->askForCode('Nueva@Example.com ')->assertRedirect('/login/codigo');

        Mail::assertSent(LoginCode::class, fn (LoginCode $mail) => $mail->hasTo('nueva@example.com') && $mail->isNew);
        $this->assertDatabaseMissing('users', ['email' => 'nueva@example.com']);

        $this->post('/login/codigo', ['code' => $this->sentCode('nueva@example.com')])->assertRedirect('/panel');

        $user = User::where('email', 'nueva@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->password);
    }

    public function test_an_existing_account_signs_in_with_its_code(): void
    {
        $user = User::factory()->create(['email' => 'ana@example.com']);

        $this->askForCode('ana@example.com');
        Mail::assertSent(LoginCode::class, fn (LoginCode $mail) => ! $mail->isNew);

        $this->post('/login/codigo', ['code' => $this->sentCode('ana@example.com')])->assertRedirect('/panel');

        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, User::count());
    }

    public function test_no_code_is_sent_without_passing_the_captcha(): void
    {
        $this->from('/login')->post('/login', ['email' => 'nueva@example.com'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('captcha');

        Captcha::fake(success: false);
        $this->askForCode('nueva@example.com')->assertSessionHasErrors('captcha');

        Mail::assertNothingSent();
    }

    public function test_a_wrong_code_is_refused_and_the_fifth_destroys_it(): void
    {
        $this->askForCode('nueva@example.com');
        $code = $this->sentCode('nueva@example.com');
        $wrong = $code === '000000' ? '111111' : '000000';

        $this->post('/login/codigo', ['code' => $wrong])
            ->assertSessionHasErrors(['code' => 'Ese no es el código. Te quedan 4 intentos.']);

        for ($i = 0; $i < 4; $i++) {
            $this->post('/login/codigo', ['code' => $wrong]);
        }

        $this->post('/login/codigo', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'nueva@example.com']);
    }

    public function test_a_new_code_replaces_the_previous_one(): void
    {
        $this->askForCode('nueva@example.com');
        $first = $this->sentCode('nueva@example.com');

        $this->post('/login/codigo/reenviar')->assertRedirect('/login/codigo')->assertSessionHas('status');
        $second = $this->sentCode('nueva@example.com');

        if ($first !== $second) {
            $this->post('/login/codigo', ['code' => $first])->assertSessionHasErrors('code');
        }
        $this->post('/login/codigo', ['code' => $second])->assertRedirect('/panel');
    }

    public function test_one_address_gets_three_codes_every_ten_minutes(): void
    {
        $this->askForCode('nueva@example.com');
        $this->post('/login/codigo/reenviar');
        $this->post('/login/codigo/reenviar');

        $this->post('/login/codigo/reenviar')->assertSessionHasErrors('code');

        Mail::assertSentCount(3);
    }

    public function test_one_machine_gets_six_codes_an_hour_across_addresses(): void
    {
        for ($i = 1; $i <= 6; $i++) {
            $this->askForCode("persona{$i}@example.com")->assertRedirect('/login/codigo');
        }

        $this->from('/login')->post('/login', ['email' => 'persona7@example.com', 'captcha' => Str::random(20)])
            ->assertSessionHasErrors('email');

        Mail::assertSentCount(6);
    }

    public function test_the_code_screen_needs_an_address_first(): void
    {
        $this->get('/login/codigo')->assertRedirect('/login');
        $this->post('/login/codigo', ['code' => '123456'])->assertRedirect('/login');
    }

    public function test_the_panel_is_only_for_signed_in_users_and_logout_ends_the_session(): void
    {
        $this->get('/panel')->assertRedirect('/login');

        $user = User::factory()->create();
        $this->actingAs($user)->get('/panel')->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('auth.user.email', $user->email));

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    /**
     * @return TestResponse
     */
    private function askForCode(string $email)
    {
        return $this->from('/login')->post('/login', ['email' => $email, 'captcha' => Str::random(20)]);
    }

    /**
     * El último código enviado a una dirección.
     */
    private function sentCode(string $email): string
    {
        return Mail::sent(LoginCode::class, fn (LoginCode $mail) => $mail->hasTo($email))->last()->code;
    }
}
