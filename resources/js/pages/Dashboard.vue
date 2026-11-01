<script setup>
import { Head, router, usePage } from '@inertiajs/vue3';

const page = usePage();
</script>

<template>
    <Head title="Panel" />

    <main class="card">
        <h1>Hola, {{ page.props.auth.user.name }}</h1>
        <p class="lead">Has entrado como <strong>{{ page.props.auth.user.email }}</strong>, sin contraseña.</p>

        <button type="button" @click="router.post('/logout')">Cerrar sesión</button>
    </main>
</template>
