<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Captcha } from '@laracaptcha/vue';

defineProps({
    // Lo que devuelve Captcha::props() en el servidor: proveedor, clave pública y script.
    captcha: { type: Object, required: true },
});

const form = useForm({ email: '', captcha: '' });
const captchaWidget = ref(null);

function submit() {
    form.post('/login', {
        // Cloudflare acepta cada token una sola vez: si el servidor rechaza el envío, hace falta otro.
        onError: () => captchaWidget.value?.reset(),
    });
}
</script>

<template>
    <Head title="Entrar" />

    <main class="card">
        <h1>Entrar</h1>
        <p class="lead">Sin contraseña: te enviamos un código a tu email.</p>

        <form @submit.prevent="submit">
            <label for="email">Email</label>
            <input id="email" v-model="form.email" type="email" required autofocus autocomplete="username" maxlength="255">
            <p v-if="form.errors.email" class="error">{{ form.errors.email }}</p>

            <Captcha ref="captchaWidget" v-bind="captcha" v-model="form.captcha" language="es" />
            <p v-if="form.errors.captcha" class="error">{{ form.errors.captcha }}</p>

            <button type="submit" :disabled="form.processing">
                {{ form.processing ? 'Enviando…' : 'Enviarme el código' }}
            </button>
        </form>

        <p class="hint">¿Aún no tienes cuenta? Escribe tu email: el mismo formulario la crea.</p>
    </main>
</template>
