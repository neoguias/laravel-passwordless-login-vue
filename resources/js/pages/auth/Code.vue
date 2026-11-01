<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    email: { type: String, required: true },
});

const form = useForm({ code: '' });
const resend = useForm({});

function submit() {
    form.post('/login/codigo');
}

function sendAnother() {
    resend.post('/login/codigo/reenviar', {
        onSuccess: () => form.reset('code'),
    });
}
</script>

<template>
    <Head title="Revisa tu correo" />

    <main class="card">
        <p v-if="$page.props.status" class="notice" role="status">{{ $page.props.status }}</p>

        <h1>Revisa tu correo</h1>
        <p class="lead">Te hemos enviado seis cifras a <strong>{{ email }}</strong>.</p>

        <form @submit.prevent="submit">
            <label for="code">Código</label>
            <input id="code" v-model="form.code" type="text" required autofocus inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" placeholder="000000" class="code">
            <p v-if="form.errors.code || resend.errors.code" class="error">{{ form.errors.code || resend.errors.code }}</p>

            <button type="submit" :disabled="form.processing">
                {{ form.processing ? 'Comprobando…' : 'Entrar' }}
            </button>
        </form>

        <div class="actions">
            <Link href="/login">Usar otro email</Link>
            <button type="button" class="link" :disabled="resend.processing" @click="sendAnother">Enviar otro código</button>
        </div>

        <p class="hint">Caduca en diez minutos y solo sirve una vez. Si no lo ves, mira en spam.</p>
    </main>
</template>
